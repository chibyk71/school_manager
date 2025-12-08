<?php

namespace App\Services;

use App\Events\UserCreated;
use App\Events\UserDeleted;
use App\Events\UserPasswordResetByAdmin;
use App\Events\UserProfileUpdated;
use App\Models\Profile;
use App\Models\User;
use App\Notifications\UserWelcomeNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class UserService
{
    /**
     * Get paginated users with smart filtering, sorting and search.
     */
    public function paginate(Request $request, array $with = ['profiles', 'roles', 'schools']): LengthAwarePaginator
    {
        return User::query()
            ->with($with)
            ->tableQuery($request, $this->extraTableFields())
            ->paginate($request->input('per_page', 25))
            ->through(function ($user) {
                return $this->transformUser($user);
            });
    }

    /**
     * Get all users (for selects, exports, etc.)
     */
    public function all(array $with = ['profiles']): Collection
    {
        return User::with($with)->get()->map->transformUser();
    }

    /**
     * Create a new user + primary profile in a single transaction.
     *
     * @param  array  $data  Must contain: email, password, first_name, last_name, profile_type
     * @param  bool   $sendWelcome  Send welcome email (default: true)
     * @return User
     *
     * @throws Throwable
     */
    public function create(array $data, bool $sendWelcome = true): User
    {
        // Role conflict validation: For new users, ensure email uniqueness and valid profile_type
        if (User::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages(['email' => 'A user with this email already exists.']);
        }

        if (!in_array($data['profile_type'], ['student', 'staff', 'guardian'])) {
            throw new InvalidArgumentException('Invalid profile_type provided.');
        }

        return DB::transaction(function () use ($data, $sendWelcome) {
            // 1. Create core User
            $user = User::create([
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'enrollment_id' => $data['enrollment_id'] ?? null,
                'must_change_password' => $data['must_change_password'] ?? false,
                'is_active' => $data['is_active'] ?? true,
            ]);

            // 2. Create primary Profile (polymorphic)
            $profile = Profile::create([
                'user_id' => $user->id,
                'profilable_type' => $this->resolveProfilableType($data['profile_type']),
                'profilable_id' => null, // will be filled after profilable created
                'school_id' => GetSchoolModel()->id,
                'profile_type' => $data['profile_type'],
                'title' => $data['title'] ?? null,
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'gender' => $data['gender'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'is_primary' => true,
            ]);

            // 3. Create actual profilable record (Student/Staff/Guardian)
            $profilable = $this->createProfilableRecord($data, $profile);

            // Link back
            $profile->update([
                'profilable_id' => $profilable->id,
            ]);

            // 4. Assign roles (Laratrust)
            if (!empty($data['role_ids'])) {
                $user->syncRoles($data['role_ids']);
            }

            // 5. Fire events & notifications
            event(new UserCreated($user));

            if ($sendWelcome) {
                $user->notify(new UserWelcomeNotification($data['password']));
            }

            activity()
                ->causedBy(auth()->user())
                ->performedOn($user)
                ->log("User created: {$user->full_name} ({$user->email})");

            return $user->load('profiles.profilable', 'roles');
        });
    }

    /**
     * Update user + primary profile.
     *
     * @param User $user
     * @param array $data
     * @return User
     *
     * @throws ValidationException
     */
    public function update(User $user, array $data): User
    {
        // Role conflict validation: If changing/adding profile_type, validate
        if (isset($data['profile_type']) && $data['profile_type'] !== $user->type) {
            if (!$user->canAddRole($data['profile_type'])) {
                throw ValidationException::withMessages(['profile_type' => 'This role conflicts with existing roles.']);
            }

            // If adding new profile type, create secondary profile
            if (!in_array($data['profile_type'], $user->profiles->pluck('profile_type')->toArray())) {
                $this->addSecondaryProfile($user, $data['profile_type'], $data);
                unset($data['profile_type']); // Remove to avoid primary update
            }
        }

        return DB::transaction(function () use ($user, $data) {
            $oldEmail = $user->email;

            $user->update([
                'email' => $data['email'] ?? $user->email,
                'is_active' => $data['is_active'] ?? $user->is_active,
                'must_change_password' => $data['must_change_password'] ?? $user->must_change_password,
            ]);

            // Update primary profile
            $profile = $user->primaryProfile;
            if ($profile) {
                $profile->update(array_only($data, [
                    'title',
                    'first_name',
                    'middle_name',
                    'last_name',
                    'gender',
                    'date_of_birth',
                    'phone',
                    'address'
                ]));
            }

            // Sync roles
            if (isset($data['role_ids'])) {
                $user->syncRoles($data['role_ids']);
            }

            // Fire event
            event(new UserProfileUpdated($user, $oldEmail));

            activity()
                ->causedBy(auth()->user())
                ->performedOn($user)
                ->withProperties(['old_email' => $oldEmail])
                ->log("User updated: {$user->full_name}");

            return $user->fresh(['profiles.profilable', 'roles']);
        });
    }

    /**
     * Add a secondary profile for multi-role support.
     *
     * @param User $user
     * @param string $profileType
     * @param array $data
     * @return Profile
     */
    private function addSecondaryProfile(User $user, string $profileType, array $data): Profile
    {
        $profile = Profile::create([
            'user_id' => $user->id,
            'profilable_type' => $this->resolveProfilableType($profileType),
            'profilable_id' => null,
            'school_id' => GetSchoolModel()->id,
            'profile_type' => $profileType,
            'title' => $data['title'] ?? null,
            'first_name' => $data['first_name'] ?? $user->primaryProfile->first_name,
            'middle_name' => $data['middle_name'] ?? $user->primaryProfile->middle_name,
            'last_name' => $data['last_name'] ?? $user->primaryProfile->last_name,
            'gender' => $data['gender'] ?? $user->primaryProfile->gender,
            'date_of_birth' => $data['date_of_birth'] ?? $user->primaryProfile->date_of_birth,
            'phone' => $data['phone'] ?? $user->primaryProfile->phone,
            'address' => $data['address'] ?? $user->primaryProfile->address,
            'is_primary' => false,
        ]);

        $profilable = $this->createProfilableRecord($data, $profile);
        $profile->update(['profilable_id' => $profilable->id]);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->log("Secondary profile added: {$profileType} for {$user->full_name}");

        return $profile;
    }

    /**
     * Bulk create users from array of data.
     *
     * @param array $usersData Array of user data arrays
     * @param bool $sendWelcome Send welcome emails
     * @return Collection
     *
     * @throws Throwable
     */
    public function bulkCreate(array $usersData, bool $sendWelcome = false): Collection
    {
        // Check setting
        $settings = getMergedSettings('user_management', GetSchoolModel());
        if (!$settings['allow_bulk_user_creation']) {
            throw new InvalidArgumentException('Bulk user creation is disabled in settings.');
        }

        if (empty($usersData)) {
            throw new InvalidArgumentException('No user data provided for bulk creation.');
        }

        return DB::transaction(function () use ($usersData, $sendWelcome) {
            $users = collect();

            foreach ($usersData as $data) {
                // Validate each entry minimally
                if (!isset($data['email'], $data['profile_type'])) {
                    continue; // Skip invalid
                }

                try {
                    $user = $this->create($data, $sendWelcome);
                    $users->push($user);
                } catch (Throwable $e) {
                    // Log error but continue for others
                    \Log::error("Bulk create failed for email {$data['email']}: " . $e->getMessage());
                }
            }

            activity()
                ->causedBy(auth()->user())
                ->log("Bulk created {$users->count()} users");

            return $users;
        });
    }

    /**
     * Soft delete user (only if no profiles exist – safety first)
     */
    public function delete(User $user): bool
    {
        if ($user->profiles()->exists()) {
            throw new InvalidArgumentException('Cannot delete user with associated profiles (Student/Staff/Guardian).');
        }

        $deleted = $user->delete();

        if ($deleted) {
            event(new UserDeleted($user));
            activity()->performedOn($user)->log('User permanently deleted');
        }

        return $deleted;
    }

    /**
     * Force delete (admin only, use carefully)
     */
    public function forceDelete(User $user): bool
    {
        $deleted = $user->forceDelete();
        if ($deleted) {
            activity()->performedOn($user)->log('User force deleted');
        }
        return $deleted;
    }

    /**
     * Bulk activate / deactivate
     */
    public function bulkUpdateStatus(array $userIds, bool $active): int
    {
        $affected = User::whereIn('id', $userIds)
            ->where('is_active', '!=', $active)
            ->update(['is_active' => $active]);

        if ($affected > 0) {
            activity()
                ->causedBy(auth()->user())
                ->log("Bulk user status changed to " . ($active ? 'active' : 'inactive') . " – {$affected} users");
        }

        return $affected;
    }

    /**
     * Admin resets user password and forces change on next login
     *
     * @param User $user
     * @param string|null $newPassword
     * @return void
     *
     * @throws RuntimeException If rate limit exceeded
     */
    public function resetPassword(User $user, ?string $newPassword = null): void
    {
        $key = 'reset-password:' . $user->id;
        $maxAttempts = 3;
        $decayMinutes = 1; // 1 minute lockout

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            throw new RuntimeException("Too many password reset attempts. Please try again in {$seconds} seconds.");
        }

        $password = $newPassword ?? Str::random(12);

        $user->update([
            'password' => Hash::make($password),
            'must_change_password' => true,
        ]);

        RateLimiter::hit($key, $decayMinutes * 60); // Increment attempts

        event(new UserPasswordResetByAdmin($user, $password));

        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->log('Password reset by admin');

        // send email/SMS with temporary password
        $user->notify(new \App\Notifications\AdminResetPasswordNotification($password));
    }

    /**
     * Change logged-in user password (with old password check)
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Current password is incorrect.']);
        }

        $user->update([
            'password' => Hash::make($newPassword),
            'must_change_password' => false,
        ]);

        event(new PasswordReset($user));

        return true;
    }

    // ====================================================================
    // Private Helpers
    // ====================================================================

    private function resolveProfilableType(string $type): string
    {
        return match ($type) {
            'student' => \App\Models\Academic\Student::class,
            'staff' => \App\Models\Employee\Staff::class,
            'guardian' => \App\Models\Guardian::class,
            default => throw new InvalidArgumentException("Invalid profile_type: {$type}"),
        };
    }

    private function createProfilableRecord(array $data, Profile $profile)
    {
        $class = $this->resolveProfilableType($data['profile_type']);

        return $class::create([
            'school_id' => GetSchoolModel()->id,
            // Add any type-specific fields here (e.g., staff_id_number, admission_date, etc.)
            // You can pass them in $data['profilable'] from controller
            ...(data_get($data, 'profilable', [])),
        ]);
    }

    private function extraTableFields(): array
    {
        return [
            'full_name' => ['header' => 'Name', 'sortable' => true],
            'profile_type' => ['header' => 'Type', 'filterType' => 'select'],
            'roles' => ['header' => 'Roles'],
            'is_active' => ['header' => 'Status', 'filterType' => 'boolean'],
        ];
    }

    private function transformUser(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'full_name' => $user->full_name,
            'profile_type' => $user->primaryProfile?->profile_type,
            'is_active' => $user->is_active,
            'enrollment_id' => $user->enrollment_id,
            'roles' => $user->roles->pluck('display_name')->implode(', '),
            'avatar' => $user->primaryProfile?->photo_url ?? asset('images/avatar.png'),
            'created_at' => $user->created_at?->format('d M Y'),
        ];
    }

    public function mergeUsers(User $fromUser, User $intoUser, ?Profile $keepPrimaryProfile = null): void
    {
        DB::transaction(function () use ($fromUser, $intoUser, $keepPrimaryProfile) {
            // Reassign all profiles
            $fromUser->profiles()->update(['user_id' => $intoUser->id]);

            // Optionally delete the old user
            if ($fromUser->profiles()->count() === 0) {
                $fromUser->delete();
            }

            // Ensure primary profile is set
            if ($keepPrimaryProfile) {
                $keepPrimaryProfile->markAsPrimary();
            }

            activity()
                ->causedBy(auth()->user())
                ->log("Merged user {$fromUser->email} into {$intoUser->email}");
        });
    }
}
