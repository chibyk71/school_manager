<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Profile Management Controller – Enterprise Edition
 *
 * Features:
 *   • Self-profile editing
 *   • Admin override (via policy)
 *   • Avatar upload with Spatie Media Library
 *   • 2FA status display
 *   • Full policy-based authorization
 *   • Multi-role profile merging support
 */
class ProfileController extends Controller
{
    public function __construct(protected UserService $userService) {}

    /**
     * Show profile edit form.
     *
     * Supports:
     *   • Regular users editing their own profile
     *   • Admins editing any profile (via ?profile_id=123)
     */
    public function edit(Request $request): Response|RedirectResponse
    {
        $actingUser = $request->user();
        $profileId = $request->query('profile_id');

        // Determine which profile to edit
        if ($profileId) {
            $profile = Profile::with('user')->findOrFail($profileId);
            // Policy: can this user edit this specific profile?
            Gate::authorize('update', $profile);
        } else {
            $profile = $actingUser->primaryProfile;
            if (! $profile) {
                return redirect()->route('dashboard')->with('error', 'You do not have a profile yet.');
            }
        }

        return Inertia::render('Profile/Edit', [
            'profile' => [
                'id'               => $profile->id,
                'user_id'          => $profile->user_id,
                'first_name'       => $profile->first_name,
                'last_name'        => $profile->last_name,
                'title'            => $profile->title,
                'gender'           => $profile->gender,
                'phone'            => $profile->phone,
                'email'            => $profile->user->email,
                'photo_url'        => $profile->photo_url,
                'profile_type'     => $profile->profile_type,
                'school_name'      => $profile->school?->name,
                'two_factor_enabled' => $profile->user->hasTwoFactorAuth(),
                'is_primary'       => $profile->is_primary,
            ],
            'is_admin_override' => $profile->user_id !== $actingUser->id,
            'can' => [
                'edit_any_profile' => $actingUser->can('update', Profile::class), // viewAny-like for profiles
            ],
        ]);
    }

    /**
     * Update profile + user data via UserService.
     */
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $profileId = $request->input('profile_id');
        $profile   = $profileId ? Profile::findOrFail($profileId) : $request->user()->primaryProfile;

        // Full policy authorization
        Gate::authorize('update', $profile);

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'title'      => 'nullable|string|max:50',
            'gender'     => 'nullable|in:male,female,other,prefer_not',
            'phone'      => 'nullable|string|max:20',
            'email'      => 'required|email|unique:users,email,' . $profile->user->id,
        ]);

        $data = $request->only([
            'first_name', 'last_name', 'title', 'gender', 'phone', 'email'
        ]);

        $this->userService->update($profile->user, $data);

        return $this->successResponse('Profile updated successfully.');
    }

    /**
     * Upload avatar – now fully policy-driven.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'photo'      => 'required|image|mimes:jpeg,png,webp|max:5120',
            'profile_id' => 'sometimes|exists:profiles,id',
        ]);

        $profileId = $request->input('profile_id');
        $profile   = $profileId ? Profile::findOrFail($profileId) : $request->user()->primaryProfile;

        // Policy-based authorization
        Gate::authorize('update', $profile);

        $profile->clearMediaCollection('photo');

        $media = $profile->addMediaFromRequest('photo')
            ->usingFileName("avatar-{$profile->user->id}-" . now()->timestamp)
            ->toMediaCollection('photo');

        activity()
            ->performedOn($profile)
            ->causedBy($request->user())
            ->log('Profile avatar updated');

        return response()->json([
            'message'    => 'Avatar uploaded successfully.',
            'photo_url'  => $media->getUrl('medium'),
            'thumb_url'  => $media->getUrl('thumb'),
        ]);
    }

    /**
     * Admin: List all profiles with search/filtering.
     */
    public function index(Request $request): Response|JsonResponse
    {
        Gate::authorize('viewAny', Profile::class);

        $profiles = Profile::tableQuery($request, extraFields: [
            'full_name',
            'email'        => ['relation' => 'user', 'field' => 'email'],
            'profile_type',
            'school.name',
            'photo_url',
        ])->with(['user', 'school']);

        if ($request->wantsJson()) {
            return response()->json($profiles);
        }

        return Inertia::render('Profile/Index', [
            'profiles' => $profiles,
            'can' => [
                'edit_any' => $request->user()->can('update', Profile::class),
            ],
        ]);
    }

    /**
     * Merge duplicate profiles (Admin only)
     *
     * Use case: User accidentally created as both Staff + Guardian → merge into one user
     */
    public function merge(Request $request): JsonResponse
    {
        Gate::authorize('merge', Profile::class); // Custom policy ability

        $request->validate([
            'primary_profile_id'   => 'required|exists:profiles,id',
            'duplicate_profile_id' => 'required|exists:profiles,id|different:primary_profile_id',
        ]);

        $primary   = Profile::findOrFail($request->primary_profile_id);
        $duplicate = Profile::findOrFail($request->duplicate_profile_id);

        // Optional: Add business rules (e.g., same school, compatible roles)
        if ($primary->user_id === $duplicate->user_id) {
            return response()->json(['error' => 'Profiles already belong to the same user.'], 400);
        }

        $this->userService->mergeUsers(
            fromUser: $duplicate->user,
            intoUser: $primary->user,
            keepPrimaryProfile: $primary
        );

        return response()->json([
            'message' => 'Profiles merged successfully.',
            'redirect' => route('profiles.edit', ['profile_id' => $primary->id]),
        ]);
    }

    // =================================================================
    // Response Helpers
    // =================================================================

    private function successResponse(string $message, array $extra = []): JsonResponse|RedirectResponse
    {
        $response = ['message' => $message] + $extra;

        return request()->wantsJson()
            ? response()->json($response)
            : redirect()->back()->with('success', $message);
    }

    private function errorResponse(string $message, int $status = 400): JsonResponse|RedirectResponse
    {
        return request()->wantsJson()
            ? response()->json(['error' => $message], $status)
            : redirect()->back()->with('error', $message)->withInput();
    }
}
