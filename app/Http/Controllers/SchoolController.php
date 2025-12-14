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

                // Actions column typically contains Edit, View, Toggle Active, Delete, etc. buttons.
                // This is a common pattern in admin tables.
                'actions' => [
                    'header' => 'Actions',
                    'sortable' => false,
                    'filterable' => false,
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
            // 5. Generate column definitions for the frontend
            // -----------------------------------------------------------------
            // ColumnDefinitionHelper introspects the School model (fillable, casts, etc.)
            // and merges in our $extraFields to produce a complete array of column metadata.
            // This is used by the Vue table component to render headers, enable/disable sorting/filtering,
            // determine input types for filters, etc.
            $columns = ColumnDefinitionHelper::fromModel(new School(), $extraFields);

            // -----------------------------------------------------------------
            // 6. Retrieve global filterable fields from the model
            // -----------------------------------------------------------------
            // The School model defines which fields should be included in global (free-text) search.
            // This is usually a subset of fillable fields (e.g., name, code, email, phone).
            // Passing this explicitly allows the frontend to highlight or configure the global search input.
            $model = new School();
            $globalFilterFields = $model->getGlobalFilterColumns();

            // -----------------------------------------------------------------
            // 7. Render the Inertia page with all required props
            // -----------------------------------------------------------------
            // Returns an Inertia response that will render the Vue component at
            // resources/js/Pages/Settings/School/Index.vue
            // The props are reactive and can be used directly in the component.
            return Inertia::render('Settings/School/Index', [
                'schools' => $paginated,           // Paginated collection with pagination metadata
                'columns' => $columns,             // Full column definitions for table rendering
                'globalFilterFields' => $globalFilterFields,  // Fields included in global search
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
        // 2. Fetch supporting data for the form
        // ---------------------------------------------------------------------
        // Load countries in alphabetical order, selecting only needed fields.
        // This is used in the address section of the create form (country dropdown).
        $countries = Country::query()->orderBy('name')
            ->get(['id', 'name'])
            ->makeHidden(['created_at', 'updated_at']);

        // You can extend this with more shared data:
        // - School types (if enum or config-based)
        // - Timezones, currencies, etc., depending on your form requirements.

        // ---------------------------------------------------------------------
        // 3. Render the Inertia page
        // ---------------------------------------------------------------------
        // Renders resources/js/Pages/Settings/School/Create.vue
        // The passed props will be available in the Vue component as:
        //   props.countries: Array of { id, name }
        return Inertia::render('Settings/School/Create', [
            'countries' => $countries,

            // Example of additional data you might add later:
            // 'schoolTypes' => config('constants.school_types'),
            'timezones'   => DateTimeZone::listIdentifiers(),
            // 'currencies'  => Currency::orderBy('name')->pluck('name', 'code'),
        ]);
    }

    /**
     * Store a new school in the system.
     *
     * This method handles the creation of a new School (branch) record.
     * It uses a dedicated Form Request (StoreSchoolRequest) for validation,
     * delegates the actual creation logic to SchoolService for separation of concerns,
     * and ensures proper authorization, cache invalidation, and user feedback.
     *
     * Key features:
     * - Authorization: Verifies the user has permission to create schools.
     * - Validation: Handled automatically by StoreSchoolRequest before reaching this method.
     * - Business logic: Delegated to SchoolService to keep the controller thin.
     * - Cache management: Clears relevant caches to ensure fresh data on next listing.
     * - User feedback: Redirects back to the index page with a success message.
     * - Error handling: Catches any unexpected exceptions, logs them with context,
     *   and redirects with a user-friendly error message.
     *
     * @param  \App\Http\Requests\StoreSchoolRequest  $request
     *         The validated request containing school data (name, code, email, phones, type, etc.)
     *         along with optional address and media uploads.
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreSchoolRequest $request)
    {
        // ---------------------------------------------------------------------
        // 1. Authorization
        // ---------------------------------------------------------------------
        // Ensure the authenticated user has the 'create' ability on the School model.
        // Uses Laravel's Gate/Policy system. Throws 403 if unauthorized.
        Gate::authorize('create', School::class);

        try {
            // -----------------------------------------------------------------
            // 2. Create the school via service layer
            // -----------------------------------------------------------------
            // All creation logic (including slug generation, address creation,
            // media handling via Spatie, settings initialization, etc.)
            // is encapsulated in SchoolService::createSchool().
            // This keeps the controller focused on HTTP concerns.
            // $request->validated() returns a clean array of only validated fields.
            $school = $this->schoolService->createSchool($request->validated());

            // -----------------------------------------------------------------
            // 3. Invalidate caches
            // -----------------------------------------------------------------
            // Since a new school was added, any cached lists or aggregates are now stale.
            // We clear both a specific key and tagged caches used elsewhere in the app.
            Cache::forget('schools.all');
            Cache::tags(['schools'])->flush();

            // -----------------------------------------------------------------
            // 4. Successful response
            // -----------------------------------------------------------------
            // Redirect back to the schools index page with a flash success message.
            // The message will be available via session('success') in the Inertia/Vue component.
            return redirect()
                ->route('schools.index')
                ->with('success', 'School created successfully');

            // ---------------------------------------------------------------------
            // 5. Error handling
            // ---------------------------------------------------------------------
        } catch (Throwable $th) {
            // Log the error with useful context:
            // - The validated input data (safe to log since it's already validated)
            // - The exception message
            // This helps with debugging issues like database constraints, media upload failures, etc.
            Log::error('Failed to create school', [
                'data' => $request->validated(),
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(), // Optional: include full trace in non-production if needed
                'user_id' => auth()->id() ?? null,
            ]);

            // Redirect back to the form with input preserved and an error flash message.
            // The user will see the error and can correct any issues (though validation should catch most).
            return redirect()
                ->back()
                ->withInput() // Preserves old input for form repopulation
                ->with('error', 'Failed to create school.');
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

    /**
     * Update an existing school record.
     *
     * This method handles both full updates (via form submission) and partial updates
     * (e.g., toggling the `is_active` status from the table row).
     * It supports updating core school attributes, the primary address, and uploading/replacing
     * media files (logos, favicon) using Spatie Media Library.
     *
     * Key features:
     * - Authorization: Ensures the user has permission to update this specific school.
     * - Partial update support: Detects quick toggle of `is_active` and handles it separately
     *   to avoid unnecessary validation or media/address processing.
     * - Address handling: Updates the existing primary address or creates a new one if none exists.
     * - Media management: Replaces media in single-file collections (logo, favicon, etc.)
     *   when new files are uploaded.
     * - Cache invalidation: Clears relevant caches after any change.
     * - Flexible response: Supports both full page redirects (form submission) and JSON responses
     *   (for Inertia partial updates, e.g., toggling active status inline).
     * - Robust error handling: Logs detailed context and returns appropriate user-friendly errors.
     *
     * @param  \App\Http\Requests\UpdateSchoolRequest  $request
     *         The validated request containing updated school data, optional address array,
     *         and/or uploaded media files.
     * @param  \App\Models\School  $school
     *         The School instance resolved via route model binding.
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateSchoolRequest $request, School $school)
    {
        // ---------------------------------------------------------------------
        // 1. Authorization
        // ---------------------------------------------------------------------
        // Verify that the authenticated user has permission to update this specific school.
        // Uses Laravel's Gate/Policy system with the 'update' ability.
        // Throws 403 if not authorized.
        Gate::authorize('update', $school);

        try {
            // -----------------------------------------------------------------
            // 2. Handle partial update: toggling is_active status
            // -----------------------------------------------------------------
            // This is commonly used for inline table actions (e.g., Activate/Deactivate button).
            // We handle it separately to avoid running full validation, address/media logic.
            if ($request->has('is_active')) {
                $school->update([
                    'is_active' => $request->boolean('is_active') // Safely casts 'true'/true to boolean
                ]);
            } else {
                // -------------------------------------------------------------
                // 3. Full update: core school attributes
                // -------------------------------------------------------------
                // Update basic fields from validated data (name, code, email, phones, type, etc.)
                $school->update($request->validated());

                // -------------------------------------------------------------
                // 4. Update or create primary address
                // -------------------------------------------------------------
                if ($request->has('address')) {
                    $addressData = $request->input('address');

                    // If a primary address already exists, update it
                    if ($school->primaryAddress()) {
                        $school->primaryAddress()->update($addressData);
                    } else {
                        // Otherwise, create a new address and mark it as primary
                        // Assumes HasAddress trait provides addAddress($data, $isPrimary = false)
                        $school->addAddress($addressData, true);
                    }
                }

                // -------------------------------------------------------------
                // 5. Handle media uploads (Spatie Media Library)
                // -------------------------------------------------------------
                // Each collection is configured as singleFile(), so uploading a new file
                // automatically replaces the old one.
                foreach ([
                    'logo',
                    'small_logo',
                    'favicon',
                    'dark_logo',
                    'dark_small_logo'
                ] as $collection) {
                    if ($request->hasFile($collection)) {
                        $school->addMediaFromRequest($collection)
                            ->toMediaCollection($collection);
                    }
                }
            }

            // -----------------------------------------------------------------
            // 6. Invalidate caches
            // -----------------------------------------------------------------
            // Any change to a school invalidates list caches and tagged caches.
            Cache::forget('schools.all');
            Cache::tags(['schools'])->flush();

            // -----------------------------------------------------------------
            // 7. Prepare success response
            // -----------------------------------------------------------------
            $message = 'School updated successfully';

            // JSON response for Inertia partial updates (e.g., after toggling active status)
            if ($request->wantsJson()) {
                return response()->json(['message' => $message]);
            }

            // Full page redirect after form submission
            return redirect()
                ->route('schools.index')
                ->with('success', $message);

            // ---------------------------------------------------------------------
            // 8. Comprehensive error handling
            // ---------------------------------------------------------------------
        } catch (Throwable $th) {
            // Log error with context for easier debugging
            Log::error('Failed to update school', [
                'school_id' => $school->id,
                'user_id' => auth()->id() ?? null,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(), // Optional: remove in production if sensitive
            ]);

            // Return appropriate error response based on request type
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'Failed to update school.'
                ], 500);
            }

            // For form submissions: redirect back with input and error message
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
        Gate::authorize('delete', School::class);

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
        Gate::authorize('delete', School::class); // or create a specific 'restore' ability if needed

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
}