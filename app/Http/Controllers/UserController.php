<?php

namespace App\Http\Controllers;

use App\Events\UserPasswordResetByAdmin;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\UserPasswordChanged;
use App\Support\ColumnDefinitionHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * API Controller for User Management
 *
 * Handles CRUD, bulk actions, password reset, and status toggle for users.
 * All operations are scoped to the active school and require proper permissions.
 *
 * @group Users
 * @authenticated
 */
class UserController extends Controller
{
    /**
     * Display a paginated, filtered, and sorted list of users.
     *
     * Supports:
     * - Global search
     * - Column filters (via Purity)
     * - Sorting (multi-column)
     * - Pagination
     * - Eager loading via `with` param
     *
     * @param \Illuminate\Http\Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     *
     * @throws \Exception If query fails
     */
    public function index(Request $request)
    {
        // Gate::authorize('viewAny', User::class);
        try {
            // 1. Define what relations to eager load
            $with = $request->filled('with')
                ? explode(',', $request->input('with'))
                : ['profiles', 'roles:name,id', 'schools:name,id'];

            // 2. Define column overrides & virtual fields (shared between table & response)
            $extraFields = [
                'full_name' => ['header' => 'Full Name', 'sortable' => true],
                'is_active' => ['header' => 'Status', 'filterType' => 'boolean'],
                'schools' => ['header' => 'School'],
                'roles' => ['header' => 'Roles'],
                'type' => ['header' => 'Type'],
            ];

            // 3. Run the smart paginated query
            $paginator = User::tableQuery(
                $request,
                extraFields: $extraFields,
                customModifiers: [fn($q) => $q->with($with)]
            );

            // 4. Transform collection to include accessors & relation data
            $paginator->setCollection(
                $paginator->getCollection()->transform(fn($user) => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'is_active' => $user->is_active,
                    'type' => $user->type ?? '**',
                    'created_at' => $user->created_at?->format('Y-m-d'),
                    'enrollment_id' => $user->enrollment_id ?? '***',
                    'schools' => $user->schools->pluck('name')->implode(', ') ?? null,
                    'roles' => $user->roles->pluck('name')->implode(', '),
                ])
            );

            // 5. Generate columns using the SAME extraFields config
            $columns = ColumnDefinitionHelper::fromModel(new User(), $extraFields);

            // 6. API response
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'data' => $paginator->items(),
                    'totalRecords' => $paginator->total(),
                    'page' => $paginator->currentPage(),
                    'pageSize' => $paginator->perPage(),
                ]);
            }

            // 7. Inertia response
            return Inertia::render('UserManagement/User', [
                'users' => $paginator,
                'columns' => $columns,
                'global_filter_fields' => (new User())->getGlobalFilterColumns(),
                'can' => [
                    'create' => auth()->user()->hasPermission('users.create'),
                    'edit' => auth()->user()->hasPermission('users.edit'),
                    'delete' => auth()->user()->hasPermission('users.delete'),
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error('User index failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to load users.'], 500)
                : back()->with('error', 'Failed to load users.');
        }
    }

    /**
     * Update only the email address of a user.
     *
     * - Only admins can perform this action
     * - Email must be unique
     * - Activity is logged
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\User $user
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, User $user): JsonResponse|RedirectResponse
    {
        Gate::authorize('edit-users');
        $this->authorizeUserAccess($user);

        $request->validate([
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        $oldEmail = $user->email;

        $user->update([
            'email' => $request->email,
            'email_verified_at' => null, // Force re-verification
        ]);

        activity()
            ->performedOn($user)
            ->withProperties(['old_email' => $oldEmail, 'new_email' => $request->email])
            ->log('Email updated');

        $message = 'Email updated successfully.';

        return $request->wantsJson()
            ? response()->json(['message' => $message, 'user' => $user->fresh()])
            : redirect()->back()->with('success', $message);
    }

    /**
     * --------------------------------------------------------------------
     *  UPDATE EMAIL (single or bulk)
     * --------------------------------------------------------------------
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function updateEmail(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('edit-users');

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:users,id',
            'email' => 'required|email|unique:users,email',
        ]);

        $school = GetSchoolModel();
        $users = User::whereIn('id', $request->ids)
            ->whereHas('schools', fn($q) => $q->where('school_id', $school->id))
            ->get();

        $updated = 0;
        foreach ($users as $user) {
            $old = $user->email;
            $user->update([
                'email' => $request->email,
                'email_verified_at' => null,
            ]);

            activity()
                ->performedOn($user)
                ->withProperties(['old_email' => $old, 'new_email' => $request->email])
                ->log('Email updated (bulk)');

            $updated++;
        }

        $msg = "Email updated for {$updated} user(s).";

        return $request->wantsJson()
            ? response()->json(['message' => $msg, 'updated' => $updated])
            : redirect()->back()->with('success', $msg);
    }

    /**
     * --------------------------------------------------------------------
     *  SET NEW PASSWORD (single or bulk) – admin only
     * --------------------------------------------------------------------
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function setPassword(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('reset-user-password');

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:users,id',
            // optional – admin may type a password, otherwise a random one is generated
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $school = GetSchoolModel();

        $users = User::whereIn('id', $request->ids)
            ->whereHas('schools', fn($q) => $q->where('school_id', $school->id))
            ->with('primaryProfile')
            ->get();

        $changed = 0;
        $generated = [];

        foreach ($users as $user) {
            // ----------------------------------------------------------------
            // 1. Determine the new password
            // ----------------------------------------------------------------
            $plainPassword = $request->filled('password')
                ? $request->password
                : Str::random(12);               // strong random if none supplied

            // ----------------------------------------------------------------
            // 2. Update the user record (hashed)
            // ----------------------------------------------------------------
            $user->forceFill([
                'password' => Hash::make($plainPassword),
                'must_change_password' => true,
            ])->save();

            // ----------------------------------------------------------------
            // 3. Send the plain password via secure mail
            // ----------------------------------------------------------------
            $user->notify(new UserPasswordChanged(
                $user,
                $plainPassword,
                $request->user() // admin who performed the change
            ));

            // ----------------------------------------------------------------
            // 4. Log & fire event
            // ----------------------------------------------------------------
            $generated[] = $plainPassword;

            activity()
                ->performedOn($user)
                ->causedBy($request->user())
                ->withProperties([
                    'admin_id' => $request->user()->id,
                    'generated' => $request->missing('password'),
                ])
                ->log('Password set by admin');

            event(new UserPasswordResetByAdmin($user, $request->user()));

            $changed++;
        }

        $msg = "Password set for {$changed} user(s).";

        // ----------------------------------------------------------------
        // 5. Response
        // ----------------------------------------------------------------
        if ($request->wantsJson()) {
            return response()->json([
                'message' => $msg,
                'changed' => $changed,
                // Only expose generated passwords in JSON when admin did NOT supply one
                'generated' => $request->missing('password') ? $generated : null,
            ]);
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * --------------------------------------------------------------------
     *  TOGGLE STATUS (single or bulk)
     * --------------------------------------------------------------------
     */
    public function toggleStatus(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('toggle-user-status');

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:users,id',
            'active' => 'required|boolean',          // true = activate, false = deactivate
        ]);

        $school = GetSchoolModel();
        $users = User::whereIn('id', $request->ids)
            ->whereHas('schools', fn($q) => $q->where('school_id', $school->id))
            ->get();

        $changed = 0;
        foreach ($users as $user) {
            if ($user->is_active !== $request->active) {
                $user->update(['is_active' => $request->active]);
                $changed++;

                activity()
                    ->performedOn($user)
                    ->withProperties(['is_active' => $request->active])
                    ->log($request->active ? 'User activated (bulk)' : 'User deactivated (bulk)');
            }
        }

        $msg = $request->active
            ? "Activated {$changed} user(s)."
            : "Deactivated {$changed} user(s).";

        return $request->wantsJson()
            ? response()->json(['message' => $msg, 'changed' => $changed])
            : redirect()->back()->with('success', $msg);
    }

    /**
     * --------------------------------------------------------------------
     *  DELETE USER (single or bulk) – soft-delete only
     * --------------------------------------------------------------------
     * Users can be deleted **only if they have no associated profiles**
     * (student/staff/guardian records).  This prevents orphan data.
     */
    public function destroy(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete-users');

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:users,id',
        ]);

        $school = GetSchoolModel();
        $users = User::whereIn('id', $request->ids)
            ->whereHas('schools', fn($q) => $q->where('school_id', $school->id))
            ->withCount(['profiles'])
            ->get();

        $deleted = 0;
        foreach ($users as $user) {
            if ($user->profiles_count > 0) {
                continue; // skip – has profiles
            }

            $user->delete();
            $deleted++;

            activity()
                ->performedOn($user)
                ->log('User soft-deleted (bulk)');
        }

        $msg = "Deleted {$deleted} user(s). Users with profiles were skipped.";

        return $request->wantsJson()
            ? response()->json(['message' => $msg, 'deleted' => $deleted])
            : redirect()->back()->with('success', $msg);
    }

    // =================================================================
    // PRIVATE HELPERS
    // =================================================================

    /**
     * Ensure the acting user has access to the target user (same school).
     *
     * @param \App\Models\User $user
     * @return void
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    private function authorizeUserAccess(User $user): void
    {
        $school = GetSchoolModel();
        if (!$school || !$user->schools()->where('school_id', $school->id)->exists()) {
            abort(403, 'You do not have access to this user.');
        }
    }
}