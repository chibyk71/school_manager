<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\School;
use App\Http\Requests\StoreSchoolRequest;
use App\Http\Requests\UpdateSchoolRequest;
use App\Services\SchoolService;
use App\Services\School\SchoolLifecycleService;
use App\Services\School\SchoolReadinessEvaluator;
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
     * Primary address handled via polymorphic HasAddress trait on School (multi-address UI is Address Phase 4).
     *
     * Key Changes & Improvements (v2.0):
     * ----------------------------------
     * - Primary address handling delegated to SchoolService -> HasAddress.
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
     * - Multi-address UI deferred to Address Phase 4; backend capability is HasAddress.
     * - Eliminates outdated single-address logic.
     * - Keeps controller thin – business logic (address creation loop) could move to service if preferred.
     * - Ensures only one primary address (first in array).
     *
     * Integration:
     * ------------
     * - Frontend: primary_address / addresses form data processed by SchoolService.
     * - Request: StoreSchoolRequest validates core fields + 'addresses' => 'sometimes|array'.
     * - Service: createSchool() only creates core record.
     * - Trait: HasAddress handles validation/storage per address.
     */
    public function store(StoreSchoolRequest $request)
    {
        try {
            // 1. Create core school + nested addresses[] via service (transactional)
            //    First address is NOT auto-primary; is_primary comes from payload only.
            $school = $this->schoolService->createSchool($request->validated());

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
            $this->invalidateSchoolCaches();

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

        // Load addresses collection for managed AddressManager (primaryAddress is a method, not a relation).
        $school->load(['addresses.country', 'addresses.state', 'addresses.city']);

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
     * Primary address via HasAddress; multi-address UI is Address Phase 4.
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
     * - Address mutations use School -> HasAddress.
     * - Consistent with create flow.
     * - Simple, reliable implementation (full replace avoids complex diff logic).
     */
    public function update(UpdateSchoolRequest $request, School $school)
    {
        Gate::authorize('update', $school);

        try {
            // Core update via SchoolService (strips state/is_active, reevaluates Ready → Draft).
            // Address mutations use the managed Address API and trigger the same reevaluation.
            $payload = $request->validated();
            foreach (['addresses', 'primary_address', 'logo', 'small_logo', 'favicon', 'dark_logo', 'dark_small_logo'] as $drop) {
                unset($payload[$drop]);
            }
            $school = $this->schoolService->updateSchool($school, $payload);

            $mediaCollections = ['logo', 'small_logo', 'favicon', 'dark_logo', 'dark_small_logo'];
            foreach ($mediaCollections as $collection) {
                if ($request->hasFile($collection)) {
                    $school->addMediaFromRequest($collection)
                        ->toMediaCollection($collection);
                }
            }

            $this->invalidateSchoolCaches();

            $message = 'School updated successfully';

            if ($request->wantsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => $message, 'school' => $school->fresh()]);
            }

            return redirect()
                ->back()
                ->with('success', $message);

        } catch (Throwable $th) {
            Log::error('Failed to update school', [
                'school_id' => $school->id,
                'user_id' => auth()->id() ?? null,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            if ($request->wantsJson() && ! $request->header('X-Inertia')) {
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
        // Policy requires a School instance — authorize each target, not School::class.
        $lifecycle = app(SchoolLifecycleService::class);

        try {
            $ids = $request->input('ids', $request->query('ids', []));
            if (! is_array($ids)) {
                $ids = array_filter([(string) $ids]);
            }
            if ($ids === [] && $id) {
                $ids = [(string) $id];
            }
            $ids = array_values(array_filter(array_map('strval', $ids)));

            if ($ids === []) {
                if ($request->expectsJson() || $request->wantsJson()) {
                    return response()->json([
                        'message' => 'At least one school id is required.',
                        'errors' => ['ids' => ['At least one school id is required.']],
                    ], 422);
                }
                throw ValidationException::withMessages([
                    'ids' => 'At least one school id is required.',
                ]);
            }

            $successCount = 0;
            $failures = [];

            foreach ($ids as $schoolId) {
                $school = School::query()->find($schoolId);
                if (! $school) {
                    $failures[] = "School {$schoolId} not found.";
                    continue;
                }

                if (! Gate::forUser($request->user())->allows('delete', $school)) {
                    $failures[] = "Not authorized to delete {$school->name}.";
                    continue;
                }

                if (method_exists($school, 'canDeactivate') && ! $school->canDeactivate()) {
                    $failures[] = "Cannot delete {$school->name}: active dependencies exist.";
                    continue;
                }

                $lifecycle->softDelete($school);
                $successCount++;
            }

            if ($successCount === 0) {
                $authOnly = $failures !== [] && collect($failures)->every(
                    fn (string $f) => str_contains($f, 'Not authorized')
                );
                if ($authOnly) {
                    $msg = implode(' ', $failures);
                    if ($request->expectsJson() || $request->wantsJson()) {
                        return response()->json(['message' => $msg], 403);
                    }
                    abort(403, $msg);
                }
                throw ValidationException::withMessages([
                    'ids' => $failures !== [] ? implode(' ', $failures) : 'No schools were deleted.',
                ]);
            }

            $this->invalidateSchoolCaches();

            $message = $successCount === 1
                ? 'School soft-deleted successfully.'
                : "{$successCount} school(s) soft-deleted successfully.";
            if ($failures !== []) {
                $message .= ' Partial failures: '.implode(' ', $failures);
            }

            return $this->respondWithSuccess($request, $message, 'schools.index');
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $th) {
            Log::error('Failed to soft-delete school(s)', [
                'user_id' => auth()->id() ?? null,
                'input' => $request->all(),
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return $this->respondWithError($request, 'Failed to soft-delete school(s).');
        }
    }

    public function forceDelete(Request $request)
    {
        // Authorize each soft-deleted school instance (policy requires School).
        try {
            $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'required|string',
            ]);

            $ids = $request->input('ids');
            $failedSchools = [];
            $successCount = 0;

            foreach ($ids as $schoolId) {
                $school = School::onlyTrashed()->find($schoolId);
                if (! $school) {
                    $failedSchools[] = "School with ID {$schoolId} not found or not soft-deleted.";
                    continue;
                }

                try {
                    Gate::authorize('forceDelete', $school);
                } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                    $failedSchools[] = "Not authorized to permanently delete {$school->name}.";
                    continue;
                }

                if (method_exists($school, 'canDeactivate') && ! $school->canDeactivate()) {
                    $failedSchools[] = "Cannot permanently delete {$school->name}: active dependencies exist.";
                    continue;
                }

                $school->clearMediaCollection('logo');
                $school->clearMediaCollection('small_logo');
                $school->clearMediaCollection('favicon');
                $school->clearMediaCollection('dark_logo');
                $school->clearMediaCollection('dark_small_logo');
                $school->forceDelete();
                $successCount++;
            }

            $this->invalidateSchoolCaches();

            if ($successCount === 0) {
                $authOnly = $failedSchools !== [] && collect($failedSchools)->every(
                    fn (string $f) => str_contains($f, 'Not authorized')
                );
                if ($authOnly) {
                    abort(403, implode(' ', $failedSchools));
                }
            }

            $message = $successCount > 0
                ? "{$successCount} school(s) permanently deleted."
                : 'No schools were permanently deleted.';
            if ($failedSchools !== []) {
                $message .= ' Failures: '.implode(' ', $failedSchools);
            }

            if ($request->wantsJson() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'message' => $message,
                    'success_count' => $successCount,
                    'failures' => $failedSchools,
                ], $successCount > 0 ? 200 : 422);
            }

            return redirect()->route('schools.index')->with(
                $successCount > 0 ? 'success' : 'error',
                $message
            );
        } catch (ValidationException $e) {
            throw $e;
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

    public function restore(Request $request)
    {
        // Authorize each soft-deleted school instance (policy requires School).
        $lifecycle = app(SchoolLifecycleService::class);

        try {
            $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'required|string',
            ]);

            $restoredCount = 0;
            $failedSchools = [];

            foreach ($request->input('ids') as $schoolId) {
                $school = School::onlyTrashed()->find($schoolId);
                if (! $school) {
                    $failedSchools[] = "School with ID {$schoolId} not found or not deleted.";
                    continue;
                }

                try {
                    Gate::authorize('restore', $school);
                } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                    $failedSchools[] = "Not authorized to restore {$school->name}.";
                    continue;
                }

                try {
                    $lifecycle->restore($school);
                    $restoredCount++;
                } catch (\App\Support\Entitlements\Exceptions\InsufficientCapacityException $e) {
                    $failedSchools[] = "Cannot restore {$school->name}: school capacity limit reached.";
                } catch (Throwable $e) {
                    $failedSchools[] = "Failed to restore {$school->name}: ".$e->getMessage();
                }
            }

            $this->invalidateSchoolCaches();

            if ($restoredCount === 0) {
                $authOnly = $failedSchools !== [] && collect($failedSchools)->every(
                    fn (string $f) => str_contains($f, 'Not authorized')
                );
                if ($authOnly) {
                    abort(403, implode(' ', $failedSchools));
                }
            }

            $message = $restoredCount > 0
                ? "{$restoredCount} school(s) restored successfully."
                : 'No schools were restored.';
            if ($failedSchools !== []) {
                $message .= ' Failures: '.implode(' ', $failedSchools);
            }

            if ($request->wantsJson() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'message' => $message,
                    'restored_count' => $restoredCount,
                    'failures' => $failedSchools,
                ], $restoredCount > 0 ? 200 : 422);
            }

            return redirect()->route('schools.index')->with(
                $restoredCount > 0 ? 'success' : 'error',
                $message
            );
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

    public function bulkToggleStatus(Request $request)
    {
        // Phase 1: lifecycle state is never toggled via bulk is_active.
        $message = 'Bulk status toggle is retired. Use lifecycle actions (activate, suspend, archive) instead.';

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json(['message' => $message, 'error' => $message], 410);
        }

        // Always Gone — do not soft-redirect as success-looking 302.
        return response($message, 410)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    // =================================================================
    // LIFECYCLE (Phase 1)
    // =================================================================

    /**
     * Lifecycle action response: Inertia gets redirect+flash; pure JSON clients get JSON.
     * X-Inertia alone must not force JSON (router.post is an Inertia visit).
     */
    
    /**
     * Invalidate school list caches without requiring a tag-aware driver.
     * database/file drivers throw on Cache::tags(); array/redis support tags.
     */
    protected function invalidateSchoolCaches(): void
    {
        try {
            Cache::forget('schools.all');
        } catch (\Throwable) {
        }

        try {
            if (method_exists(Cache::getStore(), 'tags')) {
                Cache::tags(['schools'])->flush();
            }
        } catch (\Throwable) {
            // Non-tag drivers (database, file) — list key already forgotten.
        }
    }

    protected function respondLifecycle(Request $request, string $message, School $school, int $errorStatus = 200)
    {
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            $payload = ['message' => $message, 'school' => $school->fresh()];
            if ($errorStatus >= 400) {
                return response()->json(['error' => $message] + $payload, $errorStatus);
            }

            return response()->json($payload);
        }

        return redirect()->back()->with($errorStatus >= 400 ? 'error' : 'success', $message);
    }

    protected function respondLifecycleError(Request $request, string $message, int $status = 422)
    {
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['error' => $message, 'message' => $message], $status);
        }

        return redirect()->back()->with('error', $message);
    }



    public function markReady(School $school, SchoolLifecycleService $lifecycle)
    {
        $this->authorize('markReady', $school);

        try {
            $school = $lifecycle->markReady($school);

            return $this->respondLifecycle($request = request(), 'School marked ready.', $school);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $th) {
            Log::error('markReady failed', ['school_id' => $school->id, 'error' => $th->getMessage()]);

            return $this->respondLifecycleError(request(), $th->getMessage() ?: 'Failed to mark school ready.');
        }
    }

    public function activate(School $school, SchoolLifecycleService $lifecycle)
    {
        $this->authorize('activate', $school);

        try {
            $school = $lifecycle->activate($school);

            return $this->respondLifecycle(request(), 'School activated.', $school);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $th) {
            Log::error('activate failed', ['school_id' => $school->id, 'error' => $th->getMessage()]);

            return $this->respondLifecycleError(request(), $th->getMessage() ?: 'Failed to activate school.');
        }
    }

    public function suspend(School $school, SchoolLifecycleService $lifecycle)
    {
        $this->authorize('suspend', $school);

        try {
            $school = $lifecycle->suspend($school);

            return $this->respondLifecycle(request(), 'School suspended.', $school);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $th) {
            Log::error('suspend failed', ['school_id' => $school->id, 'error' => $th->getMessage()]);

            return $this->respondLifecycleError(request(), $th->getMessage() ?: 'Failed to suspend school.');
        }
    }

    public function reactivate(School $school, SchoolLifecycleService $lifecycle)
    {
        $this->authorize('reactivate', $school);

        try {
            $school = $lifecycle->reactivate($school);

            return $this->respondLifecycle(request(), 'School reactivated.', $school);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $th) {
            Log::error('reactivate failed', ['school_id' => $school->id, 'error' => $th->getMessage()]);

            return $this->respondLifecycleError(request(), $th->getMessage() ?: 'Failed to reactivate school.');
        }
    }

    public function archive(School $school, SchoolLifecycleService $lifecycle)
    {
        $this->authorize('archive', $school);

        try {
            $school = $lifecycle->archive($school);

            return $this->respondLifecycle(request(), 'School archived.', $school);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $th) {
            Log::error('archive failed', ['school_id' => $school->id, 'error' => $th->getMessage()]);

            return $this->respondLifecycleError(request(), $th->getMessage() ?: 'Failed to archive school.');
        }
    }

    public function recover(School $school, SchoolLifecycleService $lifecycle)
    {
        $this->authorize('recover', $school);

        try {
            $school = $lifecycle->recover($school);

            return $this->respondLifecycle(request(), 'School recovered.', $school);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $th) {
            Log::error('recover failed', ['school_id' => $school->id, 'error' => $th->getMessage()]);

            return $this->respondLifecycleError(request(), $th->getMessage() ?: 'Failed to recover school.');
        }
    }

    public function readiness(School $school, SchoolReadinessEvaluator $evaluator)
    {
        $this->authorize('view', $school);

        return response()->json($evaluator->evaluate($school));
    }
}
