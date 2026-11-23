<?php

namespace App\Services\UserManagement;

use App\Enums\Auth\AccountLifecycle;
use App\Events\TenantAdminCreated;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * One-time system admin bootstrap during tenant setup.
 *
 * Creates Person first, then Authentication Account via UserAccountService.
 * Role assignment remains outside account provisioning.
 * Returns the actual credential assigned during provisioning.
 */
class TenantAdminBootstrapService
{
    public function __construct(
        protected UserAccountService $userAccountService
    ) {}

    /**
     * Create the initial system admin Person + Authentication Account.
     *
     * @return array{0: User, 1: string} User and plain password actually assigned
     */
    public function create(string $email, array $profileData = []): array
    {
        $email = strtolower(trim($email));

        if (Profile::query()->whereRaw('LOWER(email) = ?', [$email])->whereNotNull('user_id')->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email is already associated with an authentication account.',
            ]);
        }

        $profileData = array_merge([
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'gender' => 'other',
        ], $profileData);

        return DB::transaction(function () use ($email, $profileData) {
            $person = Profile::create([
                'first_name' => $profileData['first_name'],
                'last_name' => $profileData['last_name'],
                'middle_name' => $profileData['middle_name'] ?? null,
                'gender' => $profileData['gender'] ?? 'other',
                'phone' => $profileData['phone'] ?? null,
                'email' => $email,
            ]);

            $plainPassword = Str::random(16);
            $username = $profileData['username'] ?? ('admin_'.Str::lower(Str::random(6)));

            $user = $this->userAccountService->createForPerson(
                $person,
                [
                    'username' => $username,
                    'password' => $plainPassword,
                ],
                [
                    'lifecycle' => AccountLifecycle::Active,
                    'force_password_change' => true,
                    'auto_generate_username' => false,
                    'send_notification' => false,
                ]
            );

            // Role assignment is independent of account provisioning.
            if (method_exists($user, 'assignRole')) {
                $user->assignRole('super-admin');
            }

            event(new TenantAdminCreated($user));

            return [$user->load('profile'), $plainPassword];
        });
    }

    /**
     * Artisan/command entry: returns the same credential that was assigned.
     *
     * @return array{0: User, 1: string}
     */
    public function runFromCommand(string $email, array $profileData = []): array
    {
        return $this->create($email, $profileData);
    }
}
