<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Profile Management Controller
 *
 * Handles self-editing of user profiles (email, name, phone, etc.).
 * Admins can edit any profile with override.
 *
 * @group Profiles
 * @authenticated
 */
class ProfileController extends Controller
{
    /**
     * Display the current user's primary profile for editing.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Inertia\Response|\Illuminate\Http\RedirectResponse
     */
    public function edit(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $profile = $user->primaryProfile;

        if (!$profile) {
            return redirect()->route('dashboard')->with('error', 'No profile found.');
        }

        return Inertia::render('Profile/Edit', [
            'profile' => $profile->load('user'),
            'can' => [
                'edit_others' => $request->user()->can('edit-profiles'),
            ],
        ]);
    }

    /**
     * Update the authenticated user's primary profile.
     *
     * - Regular users can only edit their own profile
     * - Admins can edit any profile via `profile_id` (override)
     * - Email uniqueness enforced
     * - Activity logged
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $actingUser = $request->user();

        // Determine which profile to edit
        $profileId = $request->input('profile_id');
        $isAdminOverride = $actingUser->can('edit-profiles') && $profileId;

        $profile = $isAdminOverride
            ? Profile::findOrFail($profileId)
            : $actingUser->primaryProfile;

        if (!$profile) {
            return $this->errorResponse('Profile not found.', 404);
        }

        // Authorization: non-admins can only edit their own
        if (!$isAdminOverride && $profile->user_id !== $actingUser->id) {
            return $this->errorResponse('You cannot edit this profile.', 403);
        }

        // Validate input
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $profile->user->id,
            'phone' => 'nullable|string|max:20',
            'title' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female,other,prefer_not',
        ]);

        // Update User (email)
        $oldEmail = $profile->user->email;
        $profile->user->update([
            'email' => $request->email,
            'email_verified_at' => $oldEmail === $request->email ? $profile->user->email_verified_at : null,
        ]);

        // Update Profile
        $profile->update($request->only([
            'first_name',
            'last_name',
            'phone',
            'title',
            'gender'
        ]));

        activity()
            ->performedOn($profile)
            ->causedBy($actingUser)
            ->withProperties([
                'admin_override' => $isAdminOverride,
                'old_email' => $oldEmail,
            ])
            ->log('Profile updated');

        $msg = 'Profile updated successfully.';

        return $request->wantsJson()
            ? response()->json(['message' => $msg, 'profile' => $profile->fresh()])
            : redirect()->back()->with('success', $msg);
    }

    /**
     * [OPTIONAL] Admin-only: List all profiles with filtering/sorting/pagination.
     *
     * Uses Profile::tableQuery() for DataTable support.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request): Response|JsonResponse
    {
        Gate::authorize('viewAny', Profile::class);

        $with = $request->has('with')
            ? explode(',', $request->input('with'))
            : ['user', 'school'];

        $profiles = Profile::tableQuery(
            $request,
            extraFields: ['full_name', 'email', 'profile_type', 'school.name'],
            customModifiers: [
                fn($query) => $query->with($with)
            ]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $profiles->items(),
                'total' => $profiles->total(),
            ]);
        }

        return Inertia::render('Profile/Index', [
            'profiles' => $profiles,
            'can' => [
                'edit' => $request->user()->can('edit-profiles'),
            ],
        ]);
    }

    // =================================================================
    // PRIVATE HELPERS
    // =================================================================

    /**
     * Standardized error response.
     *
     * @param string $message
     * @param int $status
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    private function errorResponse(string $message, int $status): JsonResponse|RedirectResponse
    {
        return request()->wantsJson()
            ? response()->json(['error' => $message], $status)
            : redirect()->back()->with('error', $message);
    }
}
