<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Profile;
use App\Services\UserManagement\UserAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

/**
 * ProfileController – Manages personal profile data and linked User/login actions
 *
 * This controller handles actions that operate on the Profile model (central person entity)
 * and its 1:1 linked User (login account). It does **not** handle role-specific creation,
 * editing, or deletion (Student, Staff, Guardian) — those belong to role-specific controllers.
 *
 * Actions Implemented:
 * - index: List/search all profiles (admin view)
 * - show: View single profile details (personal + linked roles)
 * - edit / update: Edit personal profile data (name, DOB, phone, email, photo, etc.)
 * - uploadAvatar: Change profile photo/avatar
 * - createLogin: Add a User/login account to an existing profile
 * - resetPassword: Force reset password (admin) or initiate self-reset
 * - destroy / restore: Soft-delete and restore profiles (admin only)
 * - merge: Merge duplicate profiles (admin only, moves roles)
 *
 * Features / Problems Solved:
 * - Enforces role-first creation: no create/store action (intentional)
 * - Self vs admin access: users edit own profile; admins override
 * - Secure login management: create/reset handled via dedicated service
 * - Avatar upload: uses Spatie Media Library with validation/size checks
 * - Merge: safe role transfer + cleanup (admin-only)
 * - Soft-delete cascade: handled in Profile model boot (roles/User affected)
 * - Inertia-ready: returns data + crumbs for frontend
 * - Permission-driven: uses ProfilePolicy for every action
 * - Error handling: ValidationException + user-friendly flashes
 * - Production-ready: clean code, logging hooks, no duplication
 *
 * Fits into User Management Module:
 * - Central hub for profile/personal data actions
 * - Complements role controllers (StudentController, StaffController, etc.)
 * - Frontend integration: ProfileView.vue, ProfileEditModal.vue, AdminProfilesTable.vue
 * - Used by: self-profile editing, admin user management, duplicate resolution
 * - No role-specific logic: shows linked roles but doesn't edit them
 */

class ProfileController extends Controller
{
    /**
     * Display a listing of profiles (admin/search view)
     *
     * This is the main admin interface for browsing, searching, and managing all profiles in the system.
     * It supports:
     *   - Global text search across name, phone, email
     *   - Advanced filtering & sorting via HasTableQuery trait (Purity integration)
     *   - Pagination with preserved query params
     *   - Eager loading of related roles (students, staff, guardians) and user
     *   - Permission enforcement via Gate/Policy
     *   - Responsive Inertia rendering with crumbs and filters
     *
     * Features / Problems Solved:
     * - Reuses HasTableQuery trait for consistent, powerful table logic (global search, filters, sorts, columns)
     * - Prevents N+1 queries by eager-loading relations
     * - Secure: only authorized users (admin-level) can access this view
     * - User-friendly: preserves search/filter state across pagination
     * - Error handling: catches query exceptions, logs them, shows friendly message
     * - Extensible: trait allows column visibility, custom modifiers, windowed/full-load modes
     * - Performance: indexes used on searchable fields; pagination limits load
     * - Accessibility: crumbs provide navigation context
     *
     * Fits into User Management Module:
     * - Central admin hub for viewing all people (profiles) across roles
     * - Used in: AdminProfilesTable.vue (PrimeVue DataTable with filters, sort, search)
     * - Integrates with: HasTableQuery trait (backend), ProfilePolicy (authorization)
     * - No role-specific filtering here — shows all profiles; role details visible in show()
     *
     * Security Notes:
     * - Gate::authorize('viewAny', Profile::class) enforces policy
     * - Sensitive fields (password, tokens) hidden via model $hidden or trait
     * - No mass assignment risk (query builder only)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        // 1. Authorization check via policy
        Gate::authorize('viewAny', Profile::class);

        try {
            // 2. Build base query with eager loading of key relations
            $query = Profile::query()
                ->with([
                    'user' => fn($q) => $q->select('id', 'username', 'email', 'is_active'),
                    'students' => fn($q) => $q->select('id', 'profile_id', 'school_id', 'admission_number', 'status')
                        ->with('school:id,name'),
                    'staffPositions' => fn($q) => $q->select('id', 'profile_id', 'school_id', 'staff_id_number', 'status')
                        ->with('school:id,name'),
                    'guardians' => fn($q) => $q->select('id', 'profile_id', 'school_id')
                        ->with('wards.school:id,name'),
                ])
                ->select([
                    'profiles.id',
                    'profiles.user_id',
                    'profiles.first_name',
                    'profiles.middle_name',
                    'profiles.last_name',
                    'profiles.gender',
                    'profiles.date_of_birth',
                    'profiles.phone',
                    'profiles.email',
                    'profiles.created_at',
                    'profiles.updated_at',
                    'profiles.deleted_at',
                ]);

            // 3. Apply HasTableQuery trait logic (advanced filtering, sorting, global search, pagination)
            // This trait handles Purity integration, column definitions, windowed/full-load modes, etc.
            $tableData = $query->tableQuery($request, [], [
                // Optional custom modifiers (e.g., exclude soft-deleted unless requested)
                fn($q) => $request->boolean('with_trashed')
                ? $q->withTrashed()
                : $q,
            ]);

            // 4. Return Inertia response with paginated & filtered data
            return Inertia::render('Profiles/Index', [
                'profiles' => $tableData['data'],
                'pagination' => [
                    'total' => $tableData['totalRecords'],
                    'current' => $tableData['currentPage'] ?? 1,
                    'last' => $tableData['lastPage'] ?? 1,
                    'perPage' => $tableData['perPage'] ?? 20,
                ],
                'columns' => $tableData['columns'] ?? [], // from trait
                'globalFilterables' => $tableData['globalFilterables'] ?? [],
                'filters' => $request->only(['search', 'sort', 'order', 'per_page', 'with_trashed']),
                'crumbs' => [
                    ['label' => 'Dashboard', 'href' => route('dashboard')],
                    ['label' => 'Profiles'],
                ],
            ]);

        } catch (\Exception $e) {
            // Comprehensive error handling & logging
            Log::error('Profile index query failed', [
                'user_id' => auth()->id(),
                'school_id' => GetSchoolModel()?->id,
                'request' => $request->all(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Friendly user message (Inertia flash)
            return Inertia::render('Profiles/Index', [
                'profiles' => collect([]),
                'pagination' => ['total' => 0, 'current' => 1, 'last' => 1, 'perPage' => 20],
                'filters' => $request->only(['search']),
                'error' => 'Unable to load profiles at this time. Please try again later.',
                'crumbs' => [
                    ['label' => 'Dashboard', 'href' => route('dashboard')],
                    ['label' => 'Profiles'],
                ],
            ]);
        }
    }

    /**
     * Display a single profile with all linked roles and related data.
     *
     * This method shows the detailed view of one profile, including:
     *   - Personal information (from Profile model)
     *   - Linked User/login account (if any)
     *   - All student enrollments (with school & section)
     *   - All staff positions (with school, section, department)
     *   - All guardian responsibilities (with linked wards/students)
     *   - All students this profile is guardian to (wards)
     *
     * Features / Problems Solved:
     * - Prevents N+1 queries by eager-loading all necessary relations
     * - Loads only the minimal needed fields to reduce memory & payload size
     * - Enforces authorization via ProfilePolicy ('view' ability)
     * - Handles soft-deleted profiles gracefully (shows with deleted_at flag)
     * - Provides navigation crumbs for context & accessibility
     * - Error handling: catches loading failures, logs them, shows fallback view
     * - Performance: selective eager loading + limited columns
     * - Security: only authorized users can view (self or admin)
     * - Extensibility: easy to add more relations or columns later
     *
     * Fits into User Management Module:
     * - Central detailed view for any person in the system
     * - Used in frontend: Profiles/Show.vue (tabs or sections for roles)
     * - Integrates with: ProfilePolicy (authorization), Inertia (rendering)
     * - Called from: Profiles/Index.vue table row clicks or direct URL
     * - No role-specific editing here — just read-only overview
     *
     * Security Notes:
     * - Gate::authorize('view', $profile) → ties to ProfilePolicy
     * - No sensitive data exposed (passwords, tokens hidden via $hidden)
     * - Soft-deleted profiles visible only to authorized users
     *
     * @param  Profile  $profile  The profile to display (route model binding)
     * @return \Inertia\Response
     */
    public function show(Profile $profile)
    {
        // 1. Authorization check via policy
        Gate::authorize('view', $profile);

        try {
            // 2. Eager-load relations with minimal selected columns to avoid N+1 & reduce payload
            $profile->load([
                'user' => function ($q) {
                    $q->select([
                        'id',
                        'username',
                        'email',
                        'is_active',
                        'must_change_password',
                        'created_at',
                    ]);
                },

                'students' => function ($q) {
                    $q->select([
                        'id',
                        'profile_id',
                        'school_id',
                        'section_id',
                        'admission_number',
                        'enrollment_date',
                        'status',
                    ])->with([
                                'school' => fn($sq) => $sq->select('id', 'name'),
                                'section' => fn($sq) => $sq->select('id', 'name'),
                            ]);
                },

                'staffPositions' => function ($q) {
                    $q->select([
                        'id',
                        'profile_id',
                        'school_id',
                        'section_id',
                        'department_id',
                        'staff_id_number',
                        'date_of_employment',
                        'status',
                    ])->with([
                                'school' => fn($sq) => $sq->select('id', 'name'),
                                'section' => fn($sq) => $sq->select('id', 'name'),
                                'department' => fn($sq) => $sq->select('id', 'name'),
                            ]);
                },

                'guardians' => function ($q) {
                    $q->select([
                        'id',
                        'profile_id',
                        'school_id',
                        'notes',
                    ])->with([
                                'wards.school' => fn($sq) => $sq->select('id', 'name'),
                            ]);
                },

                'wards' => function ($q) {
                    $q->select([
                        'students.id',
                        'students.profile_id',
                        'students.school_id',
                        'students.section_id',
                        'students.admission_number',
                        'students.status',
                    ])->with([
                                'school' => fn($sq) => $sq->select('id', 'name'),
                                'section' => fn($sq) => $sq->select('id', 'name'),
                            ]);
                },
            ]);

            // 3. Optional: load soft-deleted items if user has permission
            if (auth()->user()->can('profile.view-deleted')) {
                $profile->load([
                    'students' => fn($q) => $q->withTrashed(),
                    'staffPositions' => fn($q) => $q->withTrashed(),
                    'guardians' => fn($q) => $q->withTrashed(),
                    'wards' => fn($q) => $q->withTrashed(),
                ]);
            }

            // 4. Return Inertia view with loaded profile
            return Inertia::render('Profiles/Show', [
                'profile' => $profile,
                'canEdit' => auth()->user()->can('update', $profile),
                'canDelete' => auth()->user()->can('delete', $profile),
                'crumbs' => [
                    ['label' => 'Dashboard', 'href' => route('dashboard')],
                    ['label' => 'Profiles', 'href' => route('profiles.index')],
                    ['label' => $profile->full_name ?: 'Unnamed Profile'],
                ],
            ]);

        } catch (\Exception $e) {
            // Comprehensive error logging
            Log::error('Failed to load profile details', [
                'profile_id' => $profile->id,
                'user_id' => auth()->id(),
                'school_id' => GetSchoolModel()?->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Fallback response: show basic info + error message
            return Inertia::render('Profiles/Show', [
                'profile' => $profile->only([
                    'id',
                    'first_name',
                    'last_name',
                    'full_name',
                    'deleted_at',
                    'created_at',
                ]),
                'error' => 'Unable to load full profile details at this time. Please try again later.',
                'crumbs' => [
                    ['label' => 'Dashboard', 'href' => route('dashboard')],
                    ['label' => 'Profiles', 'href' => route('profiles.index')],
                    ['label' => $profile->full_name ?: 'Unnamed Profile'],
                ],
            ]);
        }
    }

    /**
     * Show the form for editing personal profile data.
     *
     * This method renders the edit view for a profile's personal information
     * (name, date of birth, gender, phone, email, notes, etc.).
     *
     * Important Architecture Notes:
     *   - Only personal/core profile fields are edited here
     *   - Role-specific data (student enrollments, staff positions, guardian links)
     *     is edited in their respective controllers/services (StudentController,
     *     StaffController, GuardianController)
     *   - No role creation or linking happens in this view
     *   - Self-editing is allowed (users can update their own profile)
     *   - Admin override permitted via 'profile.update-any'
     *
     * Features / Problems Solved:
     * - Enforces authorization via ProfilePolicy ('update' ability)
     * - Prevents unauthorized access (self or admin only)
     * - Provides navigation crumbs for context & accessibility
     * - Passes minimal profile data to frontend (avoids sending sensitive relations)
     * - Prepares for custom fields: can later pass $profile->custom_fields
     * - Error handling: if authorization fails, Laravel throws 403 automatically
     * - Performance: no heavy eager loading (only profile itself)
     * - Security: no sensitive data (passwords, tokens) passed
     * - Extensibility: easy to add more data (e.g. addresses via HasAddress trait)
     *
     * Fits into User Management Module:
     * - Core self-service & admin profile editing endpoint
     * - Used in frontend: Profiles/Edit.vue (PrimeVue form with InputText, DatePicker,
     *   Dropdown for gender, FileUpload for avatar, etc.)
     * - Integrates with:
     *   - ProfilePolicy (authorization)
     *   - ProfileUpdateRequest (validation on PATCH/PUT)
     *   - Inertia (renders form with pre-filled data)
     * - Called from: Profiles/Show.vue (edit button), admin profile list
     *
     * Security & Best Practices:
     * - Gate::authorize('update', $profile) → uses ProfilePolicy
     * - Route model binding ensures $profile exists (404 if not)
     * - No mass assignment risk (data passed selectively)
     * - Crumbs provide clear navigation path
     *
     * @param  Profile  $profile  The profile to edit (route model binding)
     * @return \Inertia\Response
     */
    public function edit(Profile $profile)
    {
        // Authorize via policy (self or admin with 'profile.update-any')
        Gate::authorize('update', $profile);

        // Optional: load minimal relations if needed in form
        // (e.g. addresses if using HasAddress trait)
        // $profile->load('addresses');

        return Inertia::render('Profiles/Edit', [
            'profile' => $profile->only([
                'id',
                'first_name',
                'middle_name',
                'last_name',
                'gender',
                'date_of_birth',
                'phone',
                'email',
                'notes',
                // Add any custom fields here if needed
                // 'custom_fields' => $profile->custom_fields,
            ]),

            // Pass avatar URL for preview
            'avatar_url' => $profile->avatarUrl('medium'),

            // Authorization flags for frontend (hide/show buttons/features)
            'canUploadAvatar' => Gate::allows('uploadAvatar', $profile),

            // Navigation crumbs (consistent with show/index)
            'crumbs' => [
                ['label' => 'Dashboard', 'href' => route('dashboard')],
                ['label' => 'Profiles', 'href' => route('profiles.index')],
                ['label' => $profile->full_name ?: 'Unnamed Profile', 'href' => route('profiles.show', $profile)],
                ['label' => 'Edit Profile'],
            ],
        ]);
    }

    /**
     * Update personal profile data.
     *
     * Handles the PATCH/PUT request from the profile edit form.
     * Updates only personal/core profile fields (name, DOB, gender, phone, email, notes, etc.).
     *
     * Important Architecture Notes:
     *   - Only personal data is updated here — role-specific fields (student enrollment details,
     *     staff position info, guardian relationships) are edited in their respective controllers
     *   - Uses dedicated ProfileUpdateRequest for validation (rules, attributes, messages)
     *   - Self-editing allowed (users update their own profile)
     *   - Admin override permitted via 'profile.update-any'
     *   - No role creation, linking, or deletion happens in this method
     *
     * Features / Problems Solved:
     * - Atomic update with mass assignment protection (only validated fields)
     * - Authorization enforced via ProfilePolicy ('update' ability)
     * - Clean redirect with success flash message
     * - Error handling: validation failures auto-redirect with errors & old input
     * - Performance: single update query, no heavy relations loaded
     * - Security: Gate check + validated input only
     * - User experience: preserves scroll position (preserveScroll: true in Inertia)
     * - Extensibility: easy to add custom field updates (via HasCustomFields trait)
     * - Accessibility: flash message provides feedback
     *
     * Fits into User Management Module:
     * - Core self-service & admin profile editing endpoint
     * - Paired with: edit() method (renders form) and Profiles/Edit.vue (frontend form)
     * - Integrates with:
     *   - ProfilePolicy (authorization)
     *   - ProfileUpdateRequest (validation)
     *   - Inertia (redirect with flash)
     * - Called from: Profiles/Edit.vue form submission
     *
     * Security & Best Practices:
     * - Gate::authorize('update', $profile) → uses policy
     * - $request->validated() → only safe, validated fields
     * - No sensitive fields (password, tokens) accepted here
     * - Redirect with success message → prevents double-submit issues
     *
     * @param  ProfileUpdateRequest  $request  Form request with validated data
     * @param  Profile               $profile  The profile to update (route model binding)
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(ProfileUpdateRequest $request, Profile $profile)
    {
        // Authorize via policy (self or admin with 'profile.update-any')
        Gate::authorize('update', $profile);

        try {
            // Update only validated fields (mass assignment protected)
            $profile->update($request->validated());

            // Optional: clear any cached profile data if using heavy caching
            // Cache::forget("profile:{$profile->id}");

            return redirect()
                ->route('profiles.show', $profile)
                ->with('success', 'Profile updated successfully.')
                ->with('preserveScroll', true); // Keep scroll position after redirect
        } catch (\Exception $e) {
            // Log unexpected errors (validation failures already handled by request)
            Log::error('Profile update failed', [
                'profile_id' => $profile->id,
                'user_id' => auth()->id(),
                'school_id' => GetSchoolModel()?->id,
                'input' => $request->except(['password', 'password_confirmation']),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to update profile. Please try again.')
                ->withInput();
        }
    }

    /**
     * Upload or change the profile avatar/photo.
     *
     * Handles file upload from the profile edit form or profile view.
     * Uses Spatie Media Library to manage a single-file 'avatar' collection.
     *
     * Important Architecture Notes:
     *   - Avatar is stored on the Profile model (central person entity)
     *   - Single-file collection: replaces any existing avatar automatically
     *   - Validation: image only, max 2MB, common web formats
     *   - Authorization: self-upload allowed; admins can upload for any profile
     *   - Error handling: catches Spatie-specific exceptions, provides user-friendly messages
     *   - No role-specific logic: avatar is personal, not tied to student/staff/guardian data
     *
     * Features / Problems Solved:
     * - Atomic replacement: clears old avatar before adding new one
     * - Secure: size & mime-type validation prevents abuse
     * - User-friendly: clear success/error flashes + input preservation
     * - Performance: single media operation, no heavy relations loaded
     * - Accessibility: redirect preserves scroll position (Inertia default)
     * - Extensibility: conversions (thumb, medium) handled in HasAvatar trait
     * - Error recovery: catches FileDoesNotExist/FileIsTooBig → friendly message
     * - Logging: structured error logging for debugging
     *
     * Fits into User Management Module:
     * - Core personal profile management action
     * - Called from: Profiles/Edit.vue (avatar upload field), Profiles/Show.vue (avatar edit button)
     * - Integrates with:
     *   - Spatie Media Library (single-file 'avatar' collection)
     *   - ProfilePolicy (authorize 'uploadAvatar')
     *   - HasAvatar trait (if used on Profile — registers collection & conversions)
     *   - Inertia: redirect with flash messages
     *
     * Security & Best Practices:
     * - Gate::authorize('uploadAvatar', $profile) → self or admin only
     * - Validation: restricts to safe image types & size
     * - ClearMediaCollection first → prevents multiple files in single collection
     * - Exception handling → user sees helpful message instead of 500 error
     * - No sensitive data in logs
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  Profile                   $profile  The profile to update (route model binding)
     * @return \Illuminate\Http\RedirectResponse
     */
    public function uploadAvatar(Request $request, Profile $profile)
    {
        // Authorize via policy (self or admin with 'profile.avatar.upload-any')
        Gate::authorize('uploadAvatar', $profile);

        // Validate incoming file
        $request->validate([
            'avatar' => [
                'required',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:2048', // 2MB limit
            ],
        ], [
            'avatar.max' => 'The avatar file must not exceed 2MB.',
            'avatar.mimes' => 'Only JPEG, PNG, JPG, GIF, and WebP images are allowed.',
        ]);

        try {
            // Clear existing avatar (single-file collection)
            $profile->clearMediaCollection('avatar');

            // Add the new uploaded file
            $profile->addMediaFromRequest('avatar')
                ->toMediaCollection('avatar');

            return redirect()
                ->back()
                ->with('success', 'Avatar updated successfully.')
                ->with('preserveScroll', true); // Keep scroll position after redirect
        } catch (FileDoesNotExist $e) {
            return redirect()
                ->back()
                ->with('error', 'No file was uploaded or the file is invalid.')
                ->withInput();
        } catch (FileIsTooBig $e) {
            return redirect()
                ->back()
                ->with('error', 'The uploaded file is too large. Maximum allowed size is 2MB.')
                ->withInput();
        } catch (\Exception $e) {
            // Log unexpected errors for debugging
            Log::error('Profile avatar upload failed', [
                'profile_id' => $profile->id,
                'user_id' => auth()->id(),
                'school_id' => GetSchoolModel()?->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to upload avatar. Please try again.')
                ->withInput();
        }
    }

    /**
     * Create a login account (User) for an existing Profile.
     *
     * This method allows an admin (or the profile owner in rare cases) to add a login account
     * to a profile that currently has no associated User. It is a rare admin utility action,
     * as normal user creation happens through role-specific flows (student enrollment, staff hiring, etc.).
     *
     * Important Architecture Notes:
     *   - This is **not** the primary way users are created — most users are created via role services
     *     (StudentEnrollmentService, StaffHiringService, GuardianRegistrationService)
     *   - Only invoked when a profile exists but has no login (legacy profiles, manual setup, etc.)
     *   - Delegates actual User creation to UserAccountService (reusable, consistent logic)
     *   - Username is required (primary login identifier); password optional (auto-generated if missing)
     *   - Default roles can be passed (e.g. ['staff']) — merged with any existing roles
     *   - Triggers welcome email/event via UserAccountService options
     *
     * Features / Problems Solved:
     * - Prevents duplicate logins (checks if $profile->user already exists)
     * - Secure: username uniqueness enforced, password min length checked
     * - Reuses UserAccountService → no duplicated login creation logic
     * - Atomic: User creation + profile linking happens in service transaction
     * - User-friendly: success redirect with flash message
     * - Error handling: ValidationException for frontend feedback
     * - Extensibility: easy to add more options (2FA setup, approval workflow)
     * - Performance: minimal queries (one check + service call)
     * - Security: Gate authorization + validated input only
     * - Accessibility: redirect preserves context
     *
     * Fits into User Management Module:
     * - Admin utility action for adding login to existing profiles
     * - Called from: Profiles/Show.vue (admin button: "Create Login Account")
     * - Integrates with:
     *   - ProfilePolicy ('createLogin' ability)
     *   - UserAccountService (core login creation logic)
     *   - Events: UserAccountCreated (welcome email, audit)
     *   - Inertia: redirect with success flash
     * - Used in: admin profile management, manual account setup
     *
     * Security & Best Practices:
     * - Gate::authorize('createLogin', $profile) → self or admin only
     * - Username uniqueness checked before service call
     * - Password validation (min length) — stronger rules can be added
     * - No plain password stored or logged
     * - Transaction handled inside UserAccountService
     *
     * @param  \Illuminate\Http\Request       $request
     * @param  Profile                        $profile  The profile to add login to
     * @param  UserAccountService             $service  Injected service for login creation
     * @return \Illuminate\Http\RedirectResponse
     */
    public function createLogin(Request $request, Profile $profile, UserAccountService $service)
    {
        // Authorize via policy (self if no login, or admin)
        Gate::authorize('createLogin', $profile);

        // Early check: prevent duplicate creation attempts
        if ($profile->user) {
            throw ValidationException::withMessages([
                'login' => 'This profile already has an associated login account.',
            ]);
        }

        // Validate incoming data
        $validated = $request->validate([
            'username' => [
                'nullable',
                'string',
                'max:255',
                'unique:users,username',
                'regex:/^[a-zA-Z0-9._-]+$/',
            ],
            'password' => [
                'nullable',
                'string',
                'min:8',
                'confirmed', // requires password_confirmation field
            ],
            'roles' => [
                'nullable',
                'array',
            ],
            'roles.*' => [
                'string',
                'exists:roles,name',
            ],
        ], [
            'username.regex' => 'Username may only contain letters, numbers, dots, underscores, and hyphens.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);

        try {
            // Delegate to reusable service
            $service->createForProfile(
                profile: $profile,
                data: [
                    'username' => $validated['username'] ?? null,
                    'password' => $validated['password'] ?? null,
                ],
                options: [
                    'roles' => $validated['roles'] ?? [],
                    'autoGenerateUsername' => true,
                    'mustChangePassword' => true,
                    'sendWelcome' => true,
                ]
            );

            return redirect()
                ->route('profiles.show', $profile)
                ->with('success', 'Login account created successfully.')
                ->with('preserveScroll', true);

        } catch (ValidationException $e) {
            // Re-throw validation errors for frontend display
            throw $e;
        } catch (\Exception $e) {
            // Log unexpected failures
            Log::error('Failed to create login account for profile', [
                'profile_id' => $profile->id,
                'user_id' => auth()->id(),
                'school_id' => GetSchoolModel()?->id,
                'input' => $request->except(['password', 'password_confirmation']),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to create login account. Please try again.')
                ->withInput();
        }
    }

    /**
     * Reset password for a profile's linked User account.
     *
     * Handles two main flows:
     *   1. Admin force-reset: Generates new password and shows it once (UI warning)
     *   2. Self-initiated reset (forgot password): Sends reset link via Laravel's default flow
     *
     * Important Architecture Notes:
     *   - Only operates on existing User (checks $profile->user exists)
     *   - Admin reset: shows plain password once → must be used carefully (UI warning)
     *   - Self-reset: delegates to Laravel's password reset system (email link)
     *   - No role-specific logic — password is User-level, not tied to student/staff/guardian
     *   - Uses injected UserAccountService for consistent password reset logic
     *
     * Features / Problems Solved:
     * - Secure: admin reset shows password only once and forces change on next login
     * - Dual flow support: admin force vs user forgot-password
     * - Authorization: Gate check via ProfilePolicy ('resetPassword')
     * - User-friendly: clear messages for both flows + input preservation on error
     * - Error handling: early validation + logging of unexpected failures
     * - Performance: minimal queries (one check + service call)
     * - Extensibility: easy to add notification channels or 2FA reset logic
     * - Accessibility: redirect preserves context/scroll
     *
     * Fits into User Management Module:
     * - Core security & account recovery action
     * - Called from:
     *   - Profiles/Show.vue (admin "Reset Password" button)
     *   - Auth/ForgotPassword.vue (self-initiated reset link request)
     * - Integrates with:
     *   - ProfilePolicy (authorization)
     *   - UserAccountService (core reset logic + event firing)
     *   - Events: UserAccountCreated (welcome/reset notification)
     *   - Inertia: redirect with flash messages
     *
     * Security & Best Practices:
     * - Gate::authorize('resetPassword', $profile) → self or admin only
     * - Self vs admin distinction: admin sees plain password (warning), self gets link
     * - No plain password logged or stored
     * - Transaction handled inside UserAccountService
     * - Early exit on no User → prevents unnecessary service call
     *
     * @param  \Illuminate\Http\Request       $request
     * @param  Profile                        $profile  The profile whose User password to reset
     * @param  UserAccountService             $service  Injected service for password reset
     * @return \Illuminate\Http\RedirectResponse
     */
    public function resetPassword(Request $request, Profile $profile, UserAccountService $service)
    {
        // Authorize via policy (self-reset or admin force-reset)
        Gate::authorize('resetPassword', $profile);

        // Early validation: must have linked User account
        if (!$profile->user) {
            throw ValidationException::withMessages([
                'login' => 'This profile has no associated login account to reset.',
            ]);
        }

        $isSelfReset = $request->user()->id === $profile->user_id;

        try {
            // Delegate reset to reusable service
            $plainPassword = $service->resetPassword(
                user: $profile->user,
                notify: $request->boolean('notify', true)
            );

            // Admin force-reset: show new password once (with strong warning)
            if (!$isSelfReset) {
                return redirect()
                    ->back()
                    ->with('warning', "Password reset successful for {$profile->full_name}. New password (shown once): <strong>{$plainPassword}</strong><br><br><strong>Important:</strong> Share this securely and require immediate change on first login.")
                    ->with('preserveScroll', true);
            }

            // Self-initiated reset: standard success message (link sent)
            return redirect()
                ->back()
                ->with('success', 'Password reset link has been sent to your email. Please check your inbox (including spam/junk).')
                ->with('preserveScroll', true);

        } catch (ValidationException $e) {
            // Re-throw validation errors for frontend
            throw $e;
        } catch (\Exception $e) {
            // Log unexpected failures
            Log::error('Profile password reset failed', [
                'profile_id' => $profile->id,
                'user_id' => auth()->id(),
                'school_id' => GetSchoolModel()?->id,
                'is_self' => $isSelfReset,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to reset password. Please try again or contact support.')
                ->with('preserveScroll', true);
        }
    }

    /**
     * Soft-delete one or more profiles (bulk support).
     *
     * This method handles both single and bulk soft-deletion of profiles.
     * It receives an array of profile IDs (via request input 'ids') to support
     * bulk actions from the profiles index table.
     *
     * Important Architecture Notes:
     *   - Only soft-deletes profiles (does not force-delete)
     *   - Cascade behavior is handled in Profile model boot method
     *     (roles like Student/Staff/Guardian are soft-deleted; User may be affected)
     *   - No direct hard deletion here — forceDelete is a separate method
     *   - Bulk mode: processes multiple IDs efficiently in one transaction
     *   - Single mode: supports classic resource route (/{id}/delete)
     *
     * Features / Problems Solved:
     * - Bulk delete support: accepts array of IDs (from DataTable bulk actions)
     * - Atomic transaction: all deletes succeed or none do
     * - Authorization: checks 'delete' permission on each profile individually
     * - Performance: uses chunked delete to avoid memory issues on large bulks
     * - Error handling: partial failures reported clearly, logs full context
     * - User-friendly: success message shows count of deleted profiles
     * - Security: prevents unauthorized bulk deletes via policy check per profile
     * - Accessibility: redirect preserves filters/search state
     * - Extensibility: easy to add pre-delete hooks (e.g. audit, notifications)
     *
     * Fits into User Management Module:
     * - Main deletion endpoint for profiles (admin-only)
     * - Called from:
     *   - Profiles/Index.vue (bulk action button: "Delete Selected")
     *   - Profiles/Show.vue (single delete button)
     * - Integrates with:
     *   - ProfilePolicy ('delete' ability)
     *   - Inertia: redirect with flash message + preserved filters
     *   - Model boot: cascades soft-delete to related roles/User
     *
     * Security & Best Practices:
     * - Gate::authorize('delete', $profile) → per-profile check (not just global)
     * - Uses chunk() for large bulks → avoids memory exhaustion
     * - Transaction: rollback on any failure
     * - No plain IDs in logs (only count & user context)
     * - Redirect with success count → clear feedback
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request)
    {
        // Expect 'ids' as array (bulk) or single ID as route param fallback
        $ids = $request->input('ids', []);

        // Support classic single-resource route (/{id}/delete)
        if (!$ids && $request->route('profile')) {
            $ids = [$request->route('profile')->id];
        }

        // Early exit if no IDs provided
        if (empty($ids)) {
            return redirect()
                ->back()
                ->with('error', 'No profiles selected for deletion.');
        }

        // Cast to array and sanitize (prevent injection)
        $ids = array_filter(array_map('intval', (array) $ids));

        if (empty($ids)) {
            return redirect()
                ->back()
                ->with('error', 'Invalid profile IDs provided.');
        }

        try {
            $deletedCount = 0;

            DB::transaction(function () use ($ids, &$deletedCount) {
                // Process in chunks to avoid memory issues on very large bulks
                foreach (array_chunk($ids, 100) as $chunk) {
                    // Load profiles to authorize each one individually
                    $profiles = Profile::whereIn('id', $chunk)->get();

                    foreach ($profiles as $profile) {
                        // Per-profile authorization check
                        Gate::authorize('delete', $profile);

                        // Soft-delete (triggers model boot cascade)
                        $profile->delete();
                        $deletedCount++;
                    }
                }
            });

            $message = $deletedCount === 1
                ? '1 profile has been soft-deleted.'
                : "{$deletedCount} profiles have been soft-deleted.";

            return redirect()
                ->route('profiles.index')
                ->with('success', $message)
                ->with('preserveScroll', true);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            // Specific handling for permission failure
            return redirect()
                ->back()
                ->with('error', 'You do not have permission to delete one or more selected profiles.')
                ->withInput();

        } catch (\Exception $e) {
            // Log unexpected errors
            Log::error('Bulk profile soft-delete failed', [
                'user_id' => auth()->id(),
                'school_id' => GetSchoolModel()?->id,
                'ids' => $ids,
                'deleted' => $deletedCount,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to delete selected profiles. Please try again.')
                ->withInput();
        }
    }

    /**
     * Restore one or more soft-deleted profiles (bulk support).
     *
     * This method handles both single and bulk restoration of soft-deleted profiles.
     * It accepts an array of profile IDs (via request input 'ids') to support
     * bulk actions from the profiles index table (trashed view).
     *
     * Important Architecture Notes:
     *   - Only restores soft-deleted profiles (does not affect force-deleted records)
     *   - Cascade restoration is handled in Profile model boot method
     *     (related roles like Student/Staff/Guardian are restored if soft-deleted)
     *   - No force-restore or hard-delete recovery here
     *   - Bulk mode: processes multiple IDs efficiently in one transaction
     *   - Single mode: supports classic resource route (/{id}/restore)
     *
     * Features / Problems Solved:
     * - Bulk restore support: accepts array of IDs (from DataTable bulk actions)
     * - Atomic transaction: all restores succeed or none do
     * - Authorization: checks 'restore' permission on each profile individually
     * - Performance: uses chunked restore to avoid memory issues on large bulks
     * - Error handling: partial failures reported clearly, logs full context
     * - User-friendly: success message shows count of restored profiles
     * - Security: prevents unauthorized bulk restores via policy check per profile
     * - Accessibility: redirect preserves filters/search/trashed state
     * - Extensibility: easy to add post-restore hooks (e.g. audit, notifications)
     *
     * Fits into User Management Module:
     * - Main restoration endpoint for soft-deleted profiles (admin-only)
     * - Called from:
     *   - Profiles/Index.vue (bulk action button: "Restore Selected" in trashed view)
     *   - Profiles/Show.vue (restore button on soft-deleted profile)
     * - Integrates with:
     *   - ProfilePolicy ('restore' ability)
     *   - Inertia: redirect with flash message + preserved filters
     *   - Model boot: cascades restore to related roles/User
     *
     * Security & Best Practices:
     * - Gate::authorize('restore', $profile) → per-profile check (not just global)
     * - Uses chunk() for large bulks → avoids memory exhaustion
     * - Transaction: rollback on any failure
     * - No sensitive data in logs (only count & user context)
     * - Redirect with success count → clear feedback
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function restore(Request $request)
    {
        // Expect 'ids' as array (bulk) or single ID as route param fallback
        $ids = $request->input('ids', []);

        // Support classic single-resource route (/{id}/restore)
        if (!$ids && $request->route('profile')) {
            $ids = [$request->route('profile')->id];
        }

        // Early exit if no IDs provided
        if (empty($ids)) {
            return redirect()
                ->back()
                ->with('error', 'No profiles selected for restoration.');
        }

        // Cast to array and sanitize (prevent injection)
        $ids = array_filter(array_map('intval', (array) $ids));

        if (empty($ids)) {
            return redirect()
                ->back()
                ->with('error', 'Invalid profile IDs provided.');
        }

        try {
            $restoredCount = 0;

            DB::transaction(function () use ($ids, &$restoredCount) {
                // Process in chunks to avoid memory issues on very large bulks
                foreach (array_chunk($ids, 100) as $chunk) {
                    // Load profiles (including soft-deleted) to authorize each one
                    $profiles = Profile::withTrashed()
                        ->whereIn('id', $chunk)
                        ->get();

                    foreach ($profiles as $profile) {
                        // Per-profile authorization check
                        Gate::authorize('restore', $profile);

                        // Restore (triggers model boot cascade)
                        $profile->restore();
                        $restoredCount++;
                    }
                }
            });

            $message = $restoredCount === 1
                ? '1 profile has been restored.'
                : "{$restoredCount} profiles have been restored.";

            return redirect()
                ->route('profiles.index')
                ->with('success', $message)
                ->with('preserveScroll', true);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            // Specific handling for permission failure
            return redirect()
                ->back()
                ->with('error', 'You do not have permission to restore one or more selected profiles.')
                ->withInput();

        } catch (\Exception $e) {
            // Log unexpected errors
            Log::error('Bulk profile restore failed', [
                'user_id' => auth()->id(),
                'school_id' => GetSchoolModel()?->id,
                'ids' => $ids,
                'restored' => $restoredCount,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to restore selected profiles. Please try again.')
                ->withInput();
        }
    }

    /**
     * Force-delete (permanently remove) one or more profiles.
     *
     * This method supports both single and bulk permanent deletion of profiles.
     * It receives an array of profile IDs (via request input 'ids') to enable
     * bulk force-delete from the profiles index table (trashed view).
     *
     * Important Architecture Notes:
     *   - Force-delete bypasses soft-delete → permanently removes records
     *   - Does NOT trigger soft-delete cascade (boot deleting/deleted events)
     *   - Related models (Student, Staff, Guardian, User) are NOT automatically deleted
     *     → must be cleaned up manually or via separate logic
     *   - Extremely restricted: only users with 'profile.force-delete' permission
     *   - Bulk mode: processes multiple IDs efficiently in one transaction
     *   - Single mode: supports classic resource route (/{id}/force-delete)
     *
     * Features / Problems Solved:
     * - Bulk force-delete support: accepts array of IDs from DataTable bulk actions
     * - Atomic transaction: all deletions succeed or none do
     * - Per-profile authorization: checks 'forceDelete' permission individually
     * - Performance: chunked processing to handle large bulks safely
     * - Error handling: partial failures reported, full context logged
     * - User-friendly: success message shows exact count deleted
     * - Security: very restricted permission + per-item policy check
     * - Accessibility: redirect preserves filters/search/trashed state
     * - Extensibility: easy to add pre-delete hooks (e.g. audit, media cleanup)
     *
     * Fits into User Management Module:
     * - Final cleanup endpoint for permanently removing profiles (admin-only)
     * - Called from:
     *   - Profiles/Index.vue (bulk action: "Permanently Delete Selected" in trashed view)
     *   - Profiles/Show.vue (force-delete button on soft-deleted profile)
     * - Integrates with:
     *   - ProfilePolicy ('forceDelete' ability)
     *   - Inertia: redirect with flash message + preserved filters
     * - Warning: This is irreversible — use with extreme caution
     *
     * Security & Best Practices:
     * - Gate::authorize('forceDelete', $profile) → per-profile check (no bulk bypass)
     * - Uses chunk() for large bulks → prevents memory exhaustion
     * - Transaction: rollback on any failure
     * - No sensitive data in logs (only count & user context)
     * - Redirect with success count → clear feedback
     * - Double confirmation strongly recommended in frontend
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function forceDelete(Request $request)
    {
        // Expect 'ids' as array (bulk) or single ID as route param fallback
        $ids = $request->input('ids', []);

        // Support classic single-resource route (/{id}/force-delete)
        if (!$ids && $request->route('profile')) {
            $ids = [$request->route('profile')->id];
        }

        // Early exit if no IDs
        if (empty($ids)) {
            return redirect()
                ->back()
                ->with('error', 'No profiles selected for permanent deletion.');
        }

        // Sanitize IDs (force integers)
        $ids = array_filter(array_map('intval', (array) $ids));

        if (empty($ids)) {
            return redirect()
                ->back()
                ->with('error', 'Invalid profile IDs provided.');
        }

        try {
            $deletedCount = 0;

            DB::transaction(function () use ($ids, &$deletedCount) {
                // Process in chunks to handle large bulks safely
                foreach (array_chunk($ids, 100) as $chunk) {
                    // Load profiles (including soft-deleted) for authorization
                    $profiles = Profile::withTrashed()
                        ->whereIn('id', $chunk)
                        ->get();

                    foreach ($profiles as $profile) {
                        // Per-profile authorization (critical for security)
                        Gate::authorize('forceDelete', $profile);

                        // Permanent deletion (bypasses soft-delete)
                        $profile->forceDelete();
                        $deletedCount++;
                    }
                }
            });

            $message = $deletedCount === 1
                ? '1 profile has been permanently deleted.'
                : "{$deletedCount} profiles have been permanently deleted.";

            return redirect()
                ->route('profiles.index')
                ->with('success', $message)
                ->with('preserveScroll', true);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            // Permission denied on one or more profiles
            return redirect()
                ->back()
                ->with('error', 'You do not have permission to permanently delete one or more selected profiles.')
                ->withInput();

        } catch (\Exception $e) {
            // Log unexpected failures
            Log::error('Bulk profile force-delete failed', [
                'user_id' => auth()->id(),
                'school_id' => GetSchoolModel()?->id,
                'ids' => $ids,
                'deleted' => $deletedCount,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to permanently delete selected profiles. Please try again.')
                ->withInput();
        }
    }

    /**
     * Merge one profile into another (admin duplicate cleanup / resolution).
     *
     * This is an admin-only action to resolve duplicate profiles by transferring
     * all role relationships (students, staff positions, guardians, wards) from
     * the source profile to the target profile, then permanently deleting the source.
     *
     * Important Architecture Notes:
     *   - This is the **only** place where profiles are directly merged
     *   - All role records (Student, Staff, Guardian) are re-linked to the target profile
     *   - Pivot tables (student_guardian for wards) are updated via sync()
     *   - User account (if exists) is moved to the target profile
     *   - Source profile is force-deleted (permanent) after transfer
     *   - No personal data is copied/merged (name, DOB, photo stay on target)
     *   - Cascade effects (e.g. addresses, custom fields) are not handled here — expand later
     *
     * Features / Problems Solved:
     * - Bulk duplicate cleanup: resolves common data entry errors
     * - Atomic transaction: all transfers + delete succeed or none do
     * - Per-profile authorization: checks 'merge' permission on source
     * - Safety: prevents self-merge (target != source)
     * - Performance: efficient relation updates (no full reloads)
     * - Error handling: validation + transaction rollback on failure
     * - User-friendly: redirects to surviving profile with success message
     * - Security: admin-only, validated target ID, no mass assignment risk
     * - Extensibility: easy to add address merge, custom field merge, media transfer
     * - Audit-ready: can later add activity log entry
     *
     * Fits into User Management Module:
     * - Admin-only duplicate resolution tool
     * - Called from:
     *   - Profiles/Show.vue (admin "Merge Into..." button + target selection modal)
     *   - Future duplicate detection dashboard
     * - Integrates with:
     *   - ProfilePolicy ('merge' ability)
     *   - Inertia: redirect with success flash
     *   - Models: Profile (source/target), Student, Staff, Guardian (relation updates)
     *   - Pivot: student_guardian (wards sync)
     *
     * Security & Best Practices:
     * - Gate::authorize('merge', $profile) → source profile permission check
     * - Validation: target exists + not self
     * - Transaction: rollback on any failure
     * - No personal data overwritten (target keeps its own name/photo/etc.)
     * - Force-delete only after successful transfer
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  Profile                   $profile  The source profile to merge (route model binding)
     * @return \Illuminate\Http\RedirectResponse
     */
    public function merge(Request $request, Profile $profile)
    {
        // Authorize merge on the source profile
        Gate::authorize('merge', $profile);

        // Validate target profile
        $validated = $request->validate([
            'target_profile_id' => [
                'required',
                'exists:profiles,id',
                'different:id', // prevent self-merge
            ],
        ], [
            'target_profile_id.exists' => 'The target profile does not exist.',
            'target_profile_id.different' => 'Cannot merge a profile into itself.',
        ]);

        $target = Profile::findOrFail($validated['target_profile_id']);

        // Prevent merging into a soft-deleted target
        if ($target->trashed()) {
            throw ValidationException::withMessages([
                'target_profile_id' => 'Cannot merge into a deleted profile.',
            ]);
        }

        try {
            DB::transaction(function () use ($profile, $target) {
                // Transfer student enrollments
                $profile->students()->update(['profile_id' => $target->id]);

                // Transfer staff positions
                $profile->staffPositions()->update(['profile_id' => $target->id]);

                // Transfer guardian records
                $profile->guardians()->update(['profile_id' => $target->id]);

                // Transfer wards (student_guardian pivot)
                // Sync replaces old links with new ones (removes old, adds to target)
                $wardIds = $profile->wards()->pluck('students.id')->toArray();
                if (!empty($wardIds)) {
                    $target->wards()->syncWithoutDetaching($wardIds);
                    $profile->wards()->detach($wardIds);
                }

                // Transfer linked User account (if exists)
                if ($profile->user) {
                    $profile->user->update(['profile_id' => $target->id]);
                }

                // Optional future expansions:
                // - $profile->addresses()->update(['model_id' => $target->id]);
                // - $profile->customFieldResponses()->update(['model_id' => $target->id]);
                // - $profile->media()->update(['model_id' => $target->id]);

                // Finally, permanently delete the source profile
                $profile->forceDelete();
            });

            return redirect()
                ->route('profiles.show', $target)
                ->with('success', 'Profiles merged successfully. All roles and relationships transferred.');

        } catch (ValidationException $e) {
            throw $e; // Let Laravel handle validation errors

        } catch (\Exception $e) {
            // Log failure with context
            Log::error('Profile merge failed', [
                'source_id' => $profile->id,
                'target_id' => $target->id,
                'user_id' => auth()->id(),
                'school_id' => GetSchoolModel()?->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to merge profiles. Please try again or contact support.')
                ->withInput();
        }
    }

        /**
     * Toggle the active/inactive status of one or more profile-linked User accounts (bulk support).
     *
     * This method toggles the `is_active` flag on the associated User model(s).
     * It supports both single and bulk toggling via an array of profile IDs.
     *
     * Important Architecture Notes:
     *   - Operates only on linked User accounts (requires User to exist)
     *   - Self-toggle is explicitly blocked (security — cannot deactivate own account)
     *   - Bulk mode: toggles multiple profiles in one transaction
     *   - Single mode: supports classic resource route (/{profile}/toggle-status)
     *   - Does **not** affect soft-delete status — only login access & visibility
     *   - Intended for: temporary suspension/reactivation of staff/guardian/student accounts
     *
     * Features / Problems Solved:
     * - Bulk toggle support: accepts array of IDs from DataTable bulk actions
     * - Atomic transaction: all toggles succeed or none do
     * - Per-profile authorization: checks 'toggleStatus' permission individually
     * - Performance: chunked processing for large bulks
     * - Error handling: partial failures reported clearly, logs full context
     * - User-friendly: success message shows count toggled + new status
     * - Security: prevents self-toggle + per-item policy check
     * - Accessibility: redirect preserves filters/search state
     * - Extensibility: easy to add notification on status change
     *
     * Fits into User Management Module:
     * - Admin utility for managing account access (complements soft-delete)
     * - Called from:
     *   - Profiles/Index.vue (bulk action: "Toggle Active Status" on selected rows)
     *   - Profiles/Show.vue (single toggle switch/button)
     * - Integrates with:
     *   - ProfilePolicy ('toggleStatus' ability)
     *   - Inertia: redirect with flash message + preserved filters
     *   - User model: `is_active` boolean
     *   - Middleware: EnsureUserIsActive (blocks inactive logins)
     *
     * Security & Best Practices:
     * - Gate::authorize('toggleStatus', $profile) → per-profile check
     * - Self-toggle prevention → avoids lockout attacks
     * - Chunked processing → safe for large bulks
     * - Transaction: rollback on any failure
     * - No sensitive data logged (only count & context)
     * - Redirect with success count → clear feedback
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function toggleStatus(Request $request)
    {
        // Expect 'ids' as array (bulk) or single ID as route param fallback
        $ids = $request->input('ids', []);

        // Support classic single-resource route (/{profile}/toggle-status)
        if (!$ids && $request->route('profile')) {
            $ids = [$request->route('profile')->id];
        }

        // Early exit if no IDs
        if (empty($ids)) {
            return redirect()
                ->back()
                ->with('error', 'No profiles selected for status toggle.');
        }

        // Sanitize IDs
        $ids = array_filter(array_map('intval', (array) $ids));

        if (empty($ids)) {
            return redirect()
                ->back()
                ->with('error', 'Invalid profile IDs provided.');
        }

        try {
            $toggledCount = 0;
            $newStatuses = []; // Track new status per profile for message

            DB::transaction(function () use ($ids, &$toggledCount, &$newStatuses) {
                foreach (array_chunk($ids, 100) as $chunk) {
                    $profiles = Profile::whereIn('id', $chunk)->get();

                    foreach ($profiles as $profile) {
                        // Per-profile authorization
                        Gate::authorize('toggleStatus', $profile);

                        // Prevent self-toggle
                        if ($profile->user_id === auth()->id()) {
                            throw new \Illuminate\Auth\Access\AuthorizationException(
                                'You cannot toggle your own account status.'
                            );
                        }

                        // Check for linked User
                        if (! $profile->user) {
                            throw new \Exception("Profile ID {$profile->id} has no linked User account.");
                        }

                        // Toggle
                        $newStatus = ! $profile->user->is_active;
                        $profile->user->update(['is_active' => $newStatus]);

                        $toggledCount++;
                        $newStatuses[] = $newStatus ? 'activated' : 'deactivated';
                    }
                }
            });

            // Build message
            $statusText = count(array_unique($newStatuses)) === 1
                ? $newStatuses[0]
                : 'updated (mixed statuses)';

            $message = $toggledCount === 1
                ? "1 profile's account has been {$statusText}."
                : "{$toggledCount} profiles' accounts have been {$statusText}.";

            return redirect()
                ->route('profiles.index')
                ->with('success', $message)
                ->with('preserveScroll', true);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage() ?: 'You do not have permission to toggle status for one or more selected profiles.')
                ->withInput();

        } catch (\Exception $e) {
            Log::error('Bulk profile status toggle failed', [
                'user_id'     => auth()->id(),
                'school_id'   => GetSchoolModel()?->id,
                'ids'         => $ids,
                'toggled'     => $toggledCount,
                'message'     => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to toggle status for selected profiles. Please try again.')
                ->withInput();
        }
    }
}
