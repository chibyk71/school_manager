<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\School;
use App\Http\Requests\StoreSchoolRequest;
use App\Http\Requests\UpdateSchoolRequest;
use App\Services\SchoolService;
use App\Support\ColumnDefinitionHelper;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

/**
 * SchoolController
 *
 * Purpose & Context:
 * ------------------
 * This controller handles all HTTP operations related to School (tenant/branch) management
 * in the multi-tenant school SaaS application. It serves both administrative users
 * (super-admins managing multiple schools) and public onboarding flows.
 *
 * Key Design Principles:
 * ----------------------
 * - Thin controller: Business logic is delegated to SchoolService and traits
 * - Inertia-first: Returns Inertia responses for SPA reactivity
 * - Secure by default: Uses Gates/Policies for authorization, validated Form Requests,
 *   and robust error handling with logging
 * - Flexible onboarding: Supports public school creation (unauthenticated) and
 *   authenticated flows with optional admin assignment
 * - Media handling: Uses Spatie Media Library directly in controller (HTTP concern)
 * - Cache-aware: Invalidates relevant caches after create/update/delete operations
 *
 * Important Notes:
 * ----------------
 * - The store() method is the primary entry point for tenant onboarding
 * - Admin creation is fully decoupled — handled optionally after school creation
 * - Section context is managed via SchoolService (active section resolution)
 * - All destructive actions (deactivate, forceDelete, restore) include safety checks
 *   and support bulk operations
 *
 * Future Extensibility:
 * ---------------------
 * - Easy to add API-specific responses or additional onboarding steps
 * - Bulk operations can be extended with queued jobs for large datasets
 */
class SchoolController extends BaseSchoolController
{
    protected $schoolService;

    public function __construct(SchoolService $schoolService)
    {
        $this->schoolService = $schoolService;
    }

    /**
     * Display a paginated listing of schools with dynamic searching, filtering, sorting, and pagination.
     *
     * This method is responsible for rendering the main schools management page (Settings/School/Index).
     * It leverages the reusable HasTableQuery trait to handle all table-related query operations in a clean,
     * consistent, and maintainable way.
     *
     * Key features:
     * - Authorization check: Ensures the authenticated user has permission to view the list of schools.
     * - Efficient querying: Eager loads only necessary relations and computes active user count via withCount().
     * - Virtual columns: Adds non-database columns like "Active Users" and "Actions" for display purposes.
     * - Dynamic column definitions: Uses ColumnDefinitionHelper to automatically generate column metadata
     *   (headers, types, filterable/sortable flags, etc.) based on the School model and extra fields.
     * - Global search support: Passes the model's defined globalFilterFields to the frontend so the global
     *   search input knows which columns to include in free-text searches.
     * - Graceful error handling: Logs detailed errors and provides user-friendly fallbacks for both
     *   Inertia (SPA) and regular HTTP requests.
     *
     * @param  \Illuminate\Http\Request  $request  The incoming HTTP request containing query parameters
     *                                             such as search, filters, sort, perPage, etc.
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // ---------------------------------------------------------------------
        // 1. Authorization
        // ---------------------------------------------------------------------
        // Ensure the current user has the 'viewAny' ability on the School model.
        // This uses Laravel's Gate/Policies system. If unauthorized, a 403 response is thrown automatically.
        Gate::authorize('viewAny', School::class);

        try {
            // -----------------------------------------------------------------
            // 2. Build the base Eloquent query
            // -----------------------------------------------------------------
            // Start with a fresh query on the School model.
            $query = School::query()
                // Eager load the addresses relationship if the frontend table or any column needs it.
                // This avoids N+1 queries when rendering address-related data.
                ->with(['addresses'])

                // Compute the count of active users (is_active = true) and alias it as 'active_users_count'.
                // This uses Laravel's withCount() which adds a SELECT COUNT(*) subquery.
                // The result will be available as $school->active_users_count on each model.
                // This is far more efficient than loading the full 'users' relationship.
                ->withCount([
                    'users as active_users_count' => function ($q) {
                        $q->where('is_active', true);
                    }
                ]);

            // -----------------------------------------------------------------
            // 3. Define virtual/extra columns for the frontend table
            // -----------------------------------------------------------------
            // These columns do not exist in the database but are needed for display.
            // They are passed to the HasTableQuery trait and ColumnDefinitionHelper.
            $extraFields = [
                // Virtual column showing the number of active users.
                // Not sortable or filterable since it's a computed aggregate.
                'active_users_count' => [
                    'header' => 'Active Users',   // Displayed header in the table
                    'sortable' => false,            // Cannot be sorted via standard column sort
                    'filterable' => false,            // Not available for column-specific filters
                    'type' => 'number',         // Helps frontend render appropriately (e.g., right-align)
                ],
            ];

            // -----------------------------------------------------------------
            // 4. Apply dynamic table operations
            // -----------------------------------------------------------------
            // The tableQuery() scope (from HasTableQuery trait) handles:
            // - Global search across defined fields
            // - Column-specific filters (via Purity or custom logic)
            // - Sorting (single or multi-column)
            // - Pagination (standard or simple)
            // - Scoping to current school (if multi-tenant)
            // It returns a LengthAwarePaginator or Collection depending on perPage.
            $paginated = $query->tableQuery($request, $extraFields);

            // -----------------------------------------------------------------
            // 5. Render the Inertia page with all required props
            // -----------------------------------------------------------------
            // Returns an Inertia response that will render the Vue component at
            // resources/js/Pages/Settings/School/Index.vue
            // The props are reactive and can be used directly in the component.
            return Inertia::render('Settings/School/Index', [
                ...$paginated
            ]);

            // ---------------------------------------------------------------------
            // 8. Comprehensive error handling
            // ---------------------------------------------------------------------
        } catch (Throwable $th) {
            // Log the full error with context for debugging in production.
            // Includes message, stack trace, and authenticated user ID.
            Log::error('Failed to fetch schools list', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
                'user_id' => auth()->id() ?? null,
            ]);
            return $this->respondWithError($request, 'Failed to fetch schools.', 500);
        }
    }

    /**
     * Display the form for creating a new school.
     *
     * This method renders the Create School page (typically a modal or dedicated form page)
     * where administrators can input details for a new school/branch.
     *
     * It pre-loads supporting data required by the form:
     * - A list of countries for the primary address dropdown/select.
     *
     * Additional data (e.g., school types enum, available timezones, currencies) can be
     * added here in the future without changing the frontend significantly.
     *
     * Key features:
     * - No authorization check required here if the route is already protected by middleware
     *   (e.g., 'can:create,App\Models\School'). Add Gate::authorize('create', School::class)
     *   if needed for extra safety.
     * - Efficient query: Only selects 'id' and 'name' columns from countries table
     *   and orders alphabetically for better UX.
     * - Lean response: Only passes data actually needed by the Create.vue component.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        // ---------------------------------------------------------------------
        // 1. (Optional) Authorization
        // ---------------------------------------------------------------------
        // Uncomment if you want explicit authorization at method level.
        // Usually handled by policy middleware on the route, but adding here is safe.
        Gate::authorize('create', School::class);

        // ---------------------------------------------------------------------
        // 2. Render the Inertia page
        // ---------------------------------------------------------------------
        // Renders resources/js/Pages/Settings/School/Create.vue
        // The passed props will be available in the Vue component as:
        //   props.countries: Array of { id, name }
        return Inertia::render('Settings/School/CreateEdit');
    }

    /**
     * SchoolController::store() v2.0 – Create School with Multi-Address Support
     *
     * Purpose & Context:
     * ------------------
     * Handles HTTP POST for creating a new school (tenant/branch) in the multi-tenant SaaS.
     * Updated to support **multiple addresses** via the AddressManager component and polymorphic HasAddress trait.
     *
     * Key Changes & Improvements (v2.0):
     * ----------------------------------
     * - Removed manual primary address handling – now fully delegated to AddressManager.vue.
     * - Expects 'addresses' array in request (array of AddressFormData from frontend).
     * - Passes entire validated payload to SchoolService::createSchool() – service handles core creation.
     * - Address creation now handled in a dedicated loop using $school->addAddress($addrData, $isPrimary).
     *   • First address marked as primary, others as regular.
     * - Media handling unchanged (Spatie single-file collections).
     * - Cache invalidation and active school context preserved.
     * - Success response supports Inertia (JSON) and traditional redirect.
     * - Comprehensive logging on failure.
     *
     * Problems Solved:
     * ----------------
     * - Supports full multi-address workflow (add/edit/delete via AddressManager).
     * - Eliminates outdated single-address logic.
     * - Keeps controller thin – business logic (address creation loop) could move to service if preferred.
     * - Ensures only one primary address (first in array).
     *
     * Integration:
     * ------------
     * - Frontend: AddressManager v-model="form.addresses" sends full array.
     * - Request: StoreSchoolRequest validates core fields + 'addresses' => 'sometimes|array'.
     * - Service: createSchool() only creates core record.
     * - Trait: HasAddress handles validation/storage per address.
     */
    public function store(StoreSchoolRequest $request)
    {
        try {
            // 1. Create core school record via service (validated data passed through)
            $school = $this->schoolService->createSchool($request->validated());

            // 2. Handle multiple addresses if provided
            if ($request->has('addresses') && is_array($request->input('addresses'))) {
                $addresses = $request->input('addresses');

                foreach ($addresses as $index => $addressData) {
                    // First address = primary, others = regular
                    $isPrimary = $index === 0;

                    // HasAddress trait validates and assigns school_id automatically
                    $school->addAddress($addressData, $isPrimary);
                }
            }

            // 3. Optional admin assignment (unchanged – supports onboarding flows)
            if ($request->hasAny(['admin_id', 'admin_name', 'admin_email'])) {
                $adminData = [
                    'name' => $request->input('admin_name'),
                    'email' => $request->input('admin_email'),
                    'password' => $request->input('admin_password'),
                    'id' => $request->input('admin_id'),
                ];

                $this->schoolService->assignAdmin($adminData, $school);
            }

            // 4. Handle media uploads (Spatie – single-file collections)
            $mediaCollections = ['logo', 'small_logo', 'favicon', 'dark_logo', 'dark_small_logo'];
            foreach ($mediaCollections as $collection) {
                if ($request->hasFile($collection)) {
                    $school->addMediaFromRequest($collection)
                        ->toMediaCollection($collection);
                }
            }

            // 5. Set active context for immediate onboarding
            $this->schoolService->setActiveSchool($school);

            // 6. Cache invalidation
            Cache::forget('schools.all');
            Cache::tags(['schools'])->flush();

            // 7. Success response
            $message = 'School created successfully!';

            if ($request->wantsJson() || $request->header('X-Inertia')) {
                return response()->json([
                    'message' => $message,
                    'school' => $school->load('addresses'), // Include all addresses
                ]);
            }

            return redirect()
                ->route('dashboard')
                ->with('success', $message);

        } catch (Throwable $th) {
            Log::error('Failed to create school', [
                'validated_data' => $request->validated(),
                'user_id' => auth()->id() ?? 'guest',
                'ip' => $request->ip(),
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            $errorMessage = 'Failed to create school. Please try again.';

            if ($request->wantsJson() || $request->header('X-Inertia')) {
                return response()->json(['error' => $errorMessage], 500);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', $errorMessage);
        }
    }

    /**
     * Retrieve a single school record with related data for editing (typically in a modal).
     *
     * This method is used to pre-fill an edit form/modal when a user clicks "Edit" on a school row.
     * It returns a JSON response containing only the fields needed by the frontend form.
     * The School model is automatically resolved via route model binding ({school}).
     *
     * Key features:
     * - Authorization: Ensures the user has permission to view the specific school instance.
     * - Eager loading: Loads related addresses and users to avoid N+1 queries when accessing
     *   primaryAddress() or potentially displaying associated users.
     * - Selective data: Returns only necessary attributes and accessors (e.g., media URLs from Spatie).
     * - Security: Avoids exposing sensitive or unnecessary fields (e.g., 'data' JSON column,
     *   timestamps, pivot data).
     * - Error resilience: Wraps logic in try-catch to prevent raw exceptions from leaking
     *   to the frontend, logs context, and returns a clean error response.
     *
     * This endpoint is typically called via an AJAX/Inertia request from the frontend
     * when opening the edit modal.
     *
     * @param  \App\Models\School  $school
     *         The School instance resolved via route model binding (e.g., /schools/{school}).
     * @return \Illuminate\Http\JsonResponse
     *         JSON response with school data on success, or error message on failure.
     */
    public function show(School $school)
    {
        // ---------------------------------------------------------------------
        // 1. Authorization
        // ---------------------------------------------------------------------
        // Verify that the authenticated user has permission to view this specific school.
        // Uses Laravel's Gate/Policy system with the 'view' ability.
        // Throws a 403 AuthorizationException if not allowed.
        Gate::authorize('view', $school);

        try {
            // -----------------------------------------------------------------
            // 2. Eager load required relationships
            // -----------------------------------------------------------------
            // Load addresses (needed for primaryAddress()) and users (if needed elsewhere in the modal).
            // This prevents additional queries when accessing $school->primaryAddress() or $school->users.
            $school->load(['addresses', 'users']);

            // -----------------------------------------------------------------
            // 3. Return formatted JSON response
            // -----------------------------------------------------------------
            // Manually construct the response array to:
            // - Include only fields required by the edit form
            // - Use accessors for media URLs (e.g., logo_url from Spatie Media Library)
            // - Include the computed primary address (via model's primaryAddress() method)
            // This keeps the payload lean and secure.
            return response()->json([
                'id' => $school->id,
                'name' => $school->name,
                'code' => $school->code,
                'email' => $school->email,
                'phone_one' => $school->phone_one,
                'phone_two' => $school->phone_two,
                'type' => $school->type,
                'is_active' => $school->is_active,
                // Computed primary address (returns associated Address model or null)
                'primaryAddress' => $school->primaryAddress(),
                // Media URLs provided by Spatie Media Library accessors
                'logo_url' => $school->logo_url,
                'small_logo_url' => $school->small_logo_url,
                'favicon_url' => $school->favicon_url,
                'dark_logo_url' => $school->dark_logo_url,
                'dark_small_logo_url' => $school->dark_small_logo_url,
            ]);

            // ---------------------------------------------------------------------
            // 4. Comprehensive error handling
            // ---------------------------------------------------------------------
        } catch (Throwable $th) {
            // Log the failure with context for debugging:
            // - The school ID being accessed
            // - Exception message (and optionally trace in lower environments)
            Log::error('Failed to load school for edit', [
                'school_id' => $school->id,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(), // Optional: remove trace in production if sensitive
                'user_id' => auth()->id() ?? null,
            ]);

            // Return a clean, user-friendly JSON error response.
            // Frontend can handle this (e.g., show toast notification).
            return response()->json([
                'error' => 'Failed to load school data.'
            ], 500);
        }
    }

    public function edit(School $school)
    {
        Gate::authorize('update', $school);

        // Pre-load primary address data for form population
        $school->load(['primaryAddress']);

        return Inertia::render('Settings/School/CreateEdit', [
            'school' => $school->append(['logo_url', 'small_logo_url', 'favicon_url', 'dark_logo_url', 'dark_small_logo_url']),
        ]);
    }

    /**
     * SchoolController::update() v2.0 – Update School with Multi-Address Support
     *
     * Purpose & Context:
     * ------------------
     * Handles full or partial updates to an existing school.
     * Now supports **multiple addresses** via AddressManager workflow.
     *
     * Key Changes & Improvements (v2.0):
     * ----------------------------------
     * - Removed outdated single-address handling.
     * - If 'addresses' array present: deletes all existing addresses, then recreates from payload.
     *   • First = primary, others = regular.
     *   • Alternative: Could implement diff-based updates (future enhancement).
     * - Partial is_active toggle preserved (for inline actions).
     * - Media handling unchanged.
     * - Cache invalidation on any change.
     *
     * Problems Solved:
     * ----------------
     * - Full sync with AddressManager (replace all addresses on save).
     * - Consistent with create flow.
     * - Simple, reliable implementation (full replace avoids complex diff logic).
     */
    public function update(UpdateSchoolRequest $request, School $school)
    {
        Gate::authorize('update', $school);

        try {
            // 1. Handle quick status toggle (inline table actions)
            if ($request->has('is_active')) {
                $school->update(['is_active' => $request->boolean('is_active')]);
            } else {
                // 2. Full update: core attributes
                $school->update($request->safe()->except(['addresses']));

                // 3. Sync addresses – full replace (simplest reliable approach)
                if ($request->has('addresses') && is_array($request->input('addresses'))) {
                    // Delete all existing addresses (soft or force depending on Address model)
                    $school->addresses()->delete(); // or forceDelete() if needed

                    $addresses = $request->input('addresses');

                    foreach ($addresses as $index => $addressData) {
                        $isPrimary = $index === 0;
                        $school->addAddress($addressData, $isPrimary);
                    }
                }

                // 4. Handle media replacements
                $mediaCollections = ['logo', 'small_logo', 'favicon', 'dark_logo', 'dark_small_logo'];
                foreach ($mediaCollections as $collection) {
                    if ($request->hasFile($collection)) {
                        $school->addMediaFromRequest($collection)
                            ->toMediaCollection($collection);
                    }
                }
            }

            // 5. Cache invalidation
            Cache::forget('schools.all');
            Cache::tags(['schools'])->flush();

            // 6. Success response
            $message = 'School updated successfully';

            if ($request->wantsJson()) {
                return response()->json(['message' => $message]);
            }

            return redirect()
                ->route('schools.index')
                ->with('success', $message);

        } catch (Throwable $th) {
            Log::error('Failed to update school', [
                'school_id' => $school->id,
                'user_id' => auth()->id() ?? null,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to update school.'], 500);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to update school.');
        }
    }

    /**
     * Deactivate (soft delete) one or multiple schools.
     *
     * This method handles both single and bulk deactivation of schools.
     * Deactivation consists of:
     *   1. Setting `is_active = false`
     *   2. Applying soft deletion (sets `deleted_at` timestamp)
     *
     * It performs a safety check using the model's `canDeactivate()` method to prevent
     * deactivation when active dependencies exist (e.g., enrolled students, active users,
     * ongoing academic sessions, etc.).
     *
     * Supports two usage patterns:
     * - Single deletion: via route parameter `{id}` (typically from a direct delete button)
     * - Bulk deletion: via POST/PATCH with `ids` array (from table multi-select actions)
     *
     * Key features:
     * - Authorization: Requires general 'delete' permission on School model.
     * - Dependency protection: Blocks deactivation if dependencies exist.
     * - Consistent behavior: Both paths set is_active = false before soft deleting.
     * - Cache invalidation: Clears relevant caches after successful operation.
     * - Flexible responses: JSON for Inertia/AJAX requests, redirects for form submissions.
     * - Detailed error handling: Proper validation exceptions for dependency issues,
     *   logged errors for unexpected failures.
     *
     * @param  \Illuminate\Http\Request  $request
     *         The incoming request. May contain `ids` array for bulk operations.
     * @param  string|null  $id
     *         Optional school ID from route parameter (for single deletion).
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, ?string $id = null)
    {
        // ---------------------------------------------------------------------
        // 1. Authorization
        // ---------------------------------------------------------------------
        // Ensure the user has general permission to delete schools.
        // Uses Laravel's Gate/Policy with 'delete' ability on the School class.
        // A specific instance is not checked here — canDeactivate() handles business rules.
        Gate::authorize('delete', School::class);

        try {
            // -----------------------------------------------------------------
            // 2. Determine operation mode: bulk or single
            // -----------------------------------------------------------------
            if ($request->has('ids')) {
                // -------------------------------------------------------------
                // 2a. Bulk deactivation
                // -------------------------------------------------------------
                $ids = $request->input('ids', []);

                foreach ($ids as $schoolId) {
                    // Load the school; throws 404 if not found
                    $school = School::findOrFail($schoolId);

                    // Safety check: prevent deactivation if active dependencies exist
                    if (!$school->canDeactivate()) {
                        // Throw ValidationException to trigger proper form/error handling
                        throw ValidationException::withMessages([
                            'ids' => "Cannot deactivate school {$school->name}: active dependencies exist."
                        ]);
                    }

                    // Mark as inactive and soft delete
                    $school->update(['is_active' => false]);
                    $school->delete();
                }

                $message = 'Schools deactivated successfully';

            } else {
                // -------------------------------------------------------------
                // 2b. Single deactivation
                // -------------------------------------------------------------
                // $id comes from route model binding fallback (nullable string)
                $school = School::findOrFail($id);

                if (!$school->canDeactivate()) {
                    // For single operations (often AJAX), return JSON error immediately
                    return response()->json([
                        'error' => "Cannot deactivate {$school->name}: active users or students exist."
                    ], 422);
                }

                $school->update(['is_active' => false]);
                $school->delete();

                $message = 'School deactivated successfully';
            }

            // -----------------------------------------------------------------
            // 3. Invalidate caches
            // -----------------------------------------------------------------
            // Deactivation changes the visible list of schools, so clear caches
            Cache::forget('schools.all');
            Cache::tags(['schools'])->flush();

            // -----------------------------------------------------------------
            // 4. Return appropriate response
            // -----------------------------------------------------------------
            return $this->respondWithSuccess($request, $message, 'schools.index');

            // ---------------------------------------------------------------------
            // 5. Error handling
            // ---------------------------------------------------------------------
        } catch (ValidationException $e) {
            // Re-throw validation exceptions so Laravel/Inertia handles them properly
            // (e.g., shows field errors on 'ids' in bulk operations)
            throw $e;

        } catch (Throwable $th) {
            // Log unexpected errors with full context for debugging
            Log::error('Failed to deactivate school(s)', [
                'user_id' => auth()->id() ?? null,
                'input' => $request->all(),
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            $errorMessage = 'Failed to deactivate school(s).';

            return $this->respondWithError($request, $errorMessage);
        }
    }

    /**
     * Permanently delete (force delete) one or multiple soft-deleted schools.
     *
     * This bypasses soft deletion and removes records permanently from the database.
     * Only allowed if the school has no active dependencies (e.g., users, students, sessions).
     * Accepts bulk operation via `ids` array in request.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function forceDelete(Request $request)
    {
        // Authorize: user must have general delete permission on School model
        Gate::authorize('forceDelete', School::class);

        try {
            // Validate that ids are provided and are array of UUIDs/numeric IDs
            $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'required|string|exists:schools,id', // assumes UUID or string ID
            ]);

            $ids = $request->input('ids');
            $failedSchools = [];
            $successCount = 0;

            foreach ($ids as $schoolId) {
                // Use onlyTrashed() to ensure we only force-delete already soft-deleted schools
                $school = School::onlyTrashed()->find($schoolId);

                if (!$school) {
                    $failedSchools[] = "School with ID {$schoolId} not found or not soft-deleted.";
                    continue;
                }

                /*
                 * IMPORTANT: Even for force delete, we should prevent permanent removal
                 * if there are active dependencies (e.g., enrolled students, active users, etc.).
                 * We reuse the existing canDeactivate() method as a safety check.
                 * Adjust this logic if force delete should bypass certain checks.
                 */
                if (!$school->canDeactivate()) {
                    $failedSchools[] = "Cannot permanently delete {$school->name}: active dependencies exist.";
                    continue;
                }

                // Permanently delete media files associated with Spatie Media Library
                $school->clearMediaCollection('logo');
                $school->clearMediaCollection('small_logo');
                $school->clearMediaCollection('favicon');
                $school->clearMediaCollection('dark_logo');
                $school->clearMediaCollection('dark_small_logo');

                // Force delete the record permanently
                $school->forceDelete();

                $successCount++;
            }

            // Invalidate related caches
            Cache::forget('schools.all');
            Cache::tags(['schools'])->flush();

            $message = $successCount > 0
                ? "{$successCount} school(s) permanently deleted."
                : 'No schools were permanently deleted.';

            if (!empty($failedSchools)) {
                $message .= ' Some failures: ' . implode(' ', $failedSchools);
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $message,
                    'success_count' => $successCount,
                    'failures' => $failedSchools,
                ]);
            }

            return redirect()->route('schools.index')
                ->with('success', $message);

        } catch (ValidationException $e) {
            throw $e; // Let Laravel handle validation errors (e.g., for Inertia)
        } catch (Throwable $th) {
            Log::error('Failed to force delete school(s)', [
                'user_id' => auth()->id(),
                'input_ids' => $request->input('ids'),
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            $errorMessage = 'Failed to permanently delete school(s).';

            if ($request->wantsJson()) {
                return response()->json(['error' => $errorMessage], 500);
            }

            return redirect()->back()->with('error', $errorMessage);
        }
    }

    /**
     * Restore one or multiple soft-deleted schools.
     *
     * This recovers schools that were previously soft-deleted (deactivated).
     * Sets `is_active = true` and removes `deleted_at` timestamp.
     * Accepts bulk operation via `ids` array in request.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function restore(Request $request)
    {
        // Authorize: restoring is typically allowed under 'delete' or 'restore' policy
        Gate::authorize('restore', School::class); // or create a specific 'restore' ability if needed

        try {
            // Validate input
            $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'required|string|exists:schools,id',
            ]);

            $ids = $request->input('ids');
            $restoredCount = 0;
            $failedSchools = [];

            foreach ($ids as $schoolId) {
                $school = School::onlyTrashed()->find($schoolId);

                if (!$school) {
                    $failedSchools[] = "School with ID {$schoolId} not found or not deleted.";
                    continue;
                }

                // Restore the soft-deleted record
                $school->restore();

                // Reactivate the school
                $school->update(['is_active' => true]);

                $restoredCount++;
            }

            // Invalidate caches after restoration
            Cache::forget('schools.all');
            Cache::tags(['schools'])->flush();

            $message = $restoredCount > 0
                ? "{$restoredCount} school(s) restored successfully."
                : 'No schools were restored.';

            if (!empty($failedSchools)) {
                $message .= ' Failures: ' . implode(' ', $failedSchools);
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $message,
                    'restored_count' => $restoredCount,
                    'failures' => $failedSchools,
                ]);
            }

            return redirect()->route('schools.index')
                ->with('success', $message);

        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $th) {
            Log::error('Failed to restore school(s)', [
                'user_id' => auth()->id(),
                'input_ids' => $request->input('ids'),
                'error' => $th->getMessage(),
            ]);

            $errorMessage = 'Failed to restore school(s).';

            if ($request->wantsJson()) {
                return response()->json(['error' => $errorMessage], 500);
            }

            return redirect()->back()->with('error', $errorMessage);
        }
    }

    /**
     * Bulk toggle the active status (is_active) of one or multiple schools.
     *
     * This method handles both single and bulk updates of the `is_active` boolean flag
     * on School models. It is designed specifically for quick administrative actions
     * such as mass activation or deactivation of schools from the data table
     * (e.g., "Activate Selected" or "Deactivate Selected" bulk actions).
     *
     * Key Features & Design Decisions:
     * --------------------------------
     * - Thin controller: Only performs authorization, validation, looping, and cache invalidation.
     *   Business rules (if any) remain in the model or service.
     * - Accepts `ids` (array of school IDs) and `is_active` (boolean) in the request body.
     * - Supports both authenticated Inertia/AJAX requests (JSON response) and traditional form posts.
     * - Authorization: Uses the general 'update' ability on the School class.
     *   Granular per-school checks can be added in a policy if required.
     * - Safety: Skips schools that are soft-deleted (trashed) – status toggle is disabled on trashed rows
     *   in the frontend, but we double-check here to prevent accidental API misuse.
     * - Efficiency: Uses mass update where possible, but falls back to individual updates
     *   to allow future per-school hooks or events.
     * - Cache handling: Invalidates school-related caches after any change.
     * - Comprehensive feedback: Returns detailed success/failure counts and messages.
     * - Error resilience: Validation exceptions are re-thrown for proper Inertia/form error handling;
     *   unexpected errors are logged with full context.
     *
     * Expected Routes (examples):
     * ---------------------------
     * POST /settings/schools/bulk-toggle  ->  settings.schools.bulk-toggle
     *
     * Request Payload Example:
     * -----------------------
     * {
     *     "ids": ["1", "5", "12"],
     *     "is_active": true   // or false
     * }
     *
     * Response Examples:
     * ------------------
     * JSON (Inertia/AJAX):
     * {
     *     "message": "3 school(s) activated successfully.",
     *     "updated_count": 3,
     *     "skipped_count": 0,
     *     "failures": []
     * }
     *
     * Redirect (traditional):
     * Redirect to schools.index with flash success message.
     *
     * @param  \Illuminate\Http\Request  $request
     *         The incoming request containing:
     *         - ids: required array of school IDs (string|numeric)
     *         - is_active: required boolean (true to activate, false to deactivate)
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function bulkToggleStatus(Request $request)
    {
        // ---------------------------------------------------------------------
        // 1. Authorization
        // ---------------------------------------------------------------------
        // Requires general update permission on the School model.
        // More granular per-school checks can be implemented in a policy if needed.
        Gate::authorize('update', School::class);

        try {
            // -----------------------------------------------------------------
            // 2. Validation
            // -----------------------------------------------------------------
            $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'required|string|exists:schools,id',
                'is_active' => 'required|boolean',
            ]);

            $ids = $request->input('ids');
            $isActive = $request->boolean('is_active'); // Safely converts to boolean

            $updatedCount = 0;
            $skippedCount = 0;
            $failures = [];

            // -----------------------------------------------------------------
            // 3. Process each school individually
            // -----------------------------------------------------------------
            // Individual updates allow future event firing, logging, or per-school logic.
            foreach ($ids as $schoolId) {
                $school = School::find($schoolId);

                // Safety guard: skip trashed (soft-deleted) schools.
                // The frontend already disables the action on trashed rows,
                // but we protect the endpoint from direct API calls.
                if (!$school || $school->trashed()) {
                    $skippedCount++;
                    $failures[] = "School ID {$schoolId} not found or already trashed.";
                    continue;
                }

                // Update the active status
                $school->update(['is_active' => $isActive]);
                $updatedCount++;
            }

            // -----------------------------------------------------------------
            // 4. Invalidate caches
            // -----------------------------------------------------------------
            // Any status change affects listings, dashboards, etc.
            Cache::forget('schools.all');
            Cache::tags(['schools'])->flush();

            // -----------------------------------------------------------------
            // 5. Build response message
            // -----------------------------------------------------------------
            $action = $isActive ? 'activated' : 'deactivated';
            $message = "{$updatedCount} school(s) {$action} successfully.";

            if ($skippedCount > 0) {
                $message .= " {$skippedCount} school(s) were skipped (not found or trashed).";
            }

            // -----------------------------------------------------------------
            // 6. Return appropriate response
            // -----------------------------------------------------------------
            if ($request->wantsJson() || $request->header('X-Inertia')) {
                return response()->json([
                    'message' => $message,
                    'updated_count' => $updatedCount,
                    'skipped_count' => $skippedCount,
                    'failures' => $failures,
                ]);
            }

            return redirect()
                ->route('schools.index')
                ->with('success', $message);

            // -----------------------------------------------------------------
            // 7. Error handling
            // -----------------------------------------------------------------
        } catch (ValidationException $e) {
            // Re-throw to let Laravel/Inertia handle field errors properly
            throw $e;
        } catch (Throwable $th) {
            Log::error('Failed to bulk toggle school status', [
                'user_id' => auth()->id() ?? null,
                'input' => $request->all(),
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            $errorMessage = 'Failed to update school status.';

            if ($request->wantsJson() || $request->header('X-Inertia')) {
                return response()->json(['error' => $errorMessage], 500);
            }

            return redirect()
                ->back()
                ->with('error', $errorMessage);
        }
    }
}
