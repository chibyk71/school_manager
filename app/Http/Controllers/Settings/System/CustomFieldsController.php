<?php

namespace App\Http\Controllers\Settings\System;

use App\Helpers\ModelResolver;
use App\Http\Controllers\Controller;
use App\Models\CustomField;
use App\Services\CustomFieldService;
use App\Support\CustomFieldType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CustomFieldsController
 *
 * Main Inertia controller for managing custom fields in the admin settings area.
 *
 * Responsibilities:
 *   - Render the main management page (list + builder UI)
 *   - Handle CRUD operations on custom fields (create, update, delete)
 *   - Apply presets to schools
 *   - Reorder fields (drag & drop sort)
 *   - Support filtering by resource/model type
 *   - Enforce authorization via Policy
 *   - Use CustomFieldService for business logic & validation
 *   - Return Inertia responses with merged effective fields + metadata
 *
 * Routing (example):
 *   GET    /settings/custom-fields                → index
 *   POST   /settings/custom-fields                → store
 *   PATCH  /settings/custom-fields/{id}           → update
 *   DELETE /settings/custom-fields/{id}           → destroy
 *   PATCH  /settings/custom-fields/order          → reorder
 *   POST   /settings/custom-fields/preset/apply   → applyPreset
 *
 * Middleware expectations:
 *   - auth
 *   - tenancy (switch DB connection if multi-tenant)
 *   - verified (email verification if required)
 */
class CustomFieldsController extends Controller
{
    protected CustomFieldService $service;

    public function __construct(CustomFieldService $service)
    {
        $this->service = $service;
    }

    /**
     * Display the main custom fields management page.
     *
     * What it will do:
     *   - Authorize viewAny
     *   - Resolve optional ?resource=student query param via ModelResolver
     *   - Build base effective query (global + current school overrides) using scopeEffectiveQuery
     *   - Apply HasTableQuery scope for filtering, sorting, pagination, columns
     *   - Share field types metadata (from CustomFieldType::toFrontendArray())
     *   - Return Inertia::render('Settings/CustomFields/Index', [...])
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', CustomField::class);

        // Resolve resource alias → FQCN (e.g. ?resource=student → App\Models\Academic\Student)
        $modelClass = null;
        if ($request->filled('resource')) {
            $alias = $request->string('resource');
            $modelClass = ModelResolver::get($alias);

            if (!$modelClass) {
                return Inertia::render('Settings/System/CustomField', [
                    'error' => "Invalid resource type: {$alias}",
                ]);
            }
        }

        // Current school context
        $school = GetSchoolModel();

        // Build base query with effective fields
        $query = CustomField::query()
            ->effectiveQuery($school, $modelClass ?? ''); // empty string = all models if no filter

        // Apply advanced DataTable logic (Purity filters, sorts, pagination, columns)
        $tableData = $query->tableQuery(
            $request,
            extraFields: [], // ← add custom column overrides here if needed
            customModifiers: [
                // Optional: add permission-based scope, e.g. hide certain categories
                function (Builder $q) use ($request) {
                    if ($request->filled('category')) {
                        $q->where('category', $request->string('category'));
                    }
                },
            ]
        );

        // Prepare Inertia props
        return Inertia::render('Settings/System/CustomField', [
            ...$tableData,
            'fieldTypes' => CustomFieldType::toFrontendArray(),
            'currentResource' => $request->string('resource', null),
        ]);
    }

    /**
     * Store a newly created custom field.
     *
     * What it will do:
     *   - Authorize create
     *   - Get current school context
     *   - Validate & prepare data via service
     *   - Create the field (scoped to school or global if allowed)
     *   - Return redirect back with success message
     *   - Handle Inertia errors if validation fails
     */
    public function store(Request $request)
    {
        // 1. Authorize general create permission
        Gate::authorize('create', CustomField::class);

        // 2. Get current school context
        $school = GetSchoolModel();

        // 3. If trying to create a global field (school_id null), require special permission
        if ($request->boolean('is_global') || $request->missing('school_id')) {
            Gate::authorize('manageGlobals', $request->user());
            $schoolId = null; // global preset
        } else {
            $schoolId = $school?->id;
            if (!$schoolId) {
                return back()->withErrors(['school' => 'No active school context available for creating school-scoped field.']);
            }
        }

        try {
            // 4. Prepare and validate data through the service
            $validated = $this->service->validateAndPrepareField(
                $request->all() + ['school_id' => $schoolId]
            );

            // 5. Additional safety: ensure model_type is resolved FQCN
            if (!class_exists($validated['model_type'])) {
                $validated['model_type'] = ModelResolver::getOrFail($validated['model_type']);
            }

            // 6. Create the field
            $field = CustomField::create($validated);

            // 7. Success redirect with message
            return redirect()
                ->back()
                ->with('success', "Custom field '{$field->label}' ({$field->name}) created successfully.")
                ->with('newFieldId', $field->id); // optional: pass back ID for frontend focus

        } catch (ValidationException $e) {
            // Inertia-friendly error handling
            return back()
                ->withErrors($e->errors())
                ->withInput();

        } catch (\Exception $e) {
            // General error (e.g. database issue, permission edge case)
            Log::error('Failed to create custom field', [
                'error' => $e->getMessage(),
                'request' => $request->except(['_token', 'password']),
                'user_id' => $request->user()?->id,
            ]);

            return back()
                ->withErrors(['error' => 'An unexpected error occurred while creating the field. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Update an existing custom field.
     *
     * What it will do:
     *   - Authorize update (or manageGlobals if global)
     *   - Validate & prepare via service
     *   - Update the field
     *   - Invalidate caches
     *   - Return redirect or Inertia response
     */
    public function update(Request $request, CustomField $customField)
    {
        // 1. Authorize based on whether this is a global or school-scoped field
        if (is_null($customField->school_id)) {
            Gate::authorize('manageGlobals', $request->user());
        } else {
            Gate::authorize('update', $customField);

            // Extra safety: ensure the field belongs to current school
            $school = GetSchoolModel();
            if ($school && $customField->school_id !== $school->id) {
                abort(403, 'You can only update custom fields belonging to your current school.');
            }
        }

        try {
            // 2. Prepare and validate data through the service
            // Pass the existing field for unique rule ignore
            $validated = $this->service->validateAndPrepareField(
                $request->all(),
                $customField
            );

            // 3. Update the field
            $customField->update($validated);

            // 4. Invalidate related caches (handled by model events, but explicit here for safety)
            $customField->invalidateRelatedCache();

            // 5. If this was a global field update, fire the event to notify schools with overrides
            if (is_null($customField->school_id)) {
                event(new \App\Events\CustomFieldGlobalUpdated($customField));
            }

            // 6. Success redirect with message
            return redirect()
                ->back()
                ->with('success', "Custom field '{$customField->label}' ({$customField->name}) updated successfully.");

        } catch (ValidationException $e) {
            // Inertia-friendly validation errors
            return back()
                ->withErrors($e->errors())
                ->withInput();

        } catch (\Exception $e) {
            // General error handling
            Log::error('Failed to update custom field', [
                'field_id' => $customField->id,
                'error' => $e->getMessage(),
                'request' => $request->except(['_token', 'password']),
                'user_id' => $request->user()?->id,
            ]);

            return back()
                ->withErrors(['error' => 'An unexpected error occurred while updating the field. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Permanently delete (force delete) one or more custom fields (bulk supported).
     *
     * What it will do:
     *   - Accept array of IDs via request (e.g. ['ids' => [1, 2, 3]])
     *   - Authorize forceDelete on each field
     *   - Force-delete only allowed fields (respecting policy & school scoping)
     *   - Invalidate caches for affected fields
     *   - Return redirect back with success count and skipped count
     *   - Inertia-friendly errors
     */
    public function forceDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:custom_fields,id',
        ]);

        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return back()->with('warning', 'No fields selected for permanent deletion.');
        }

        $deletedCount = 0;
        $skippedCount = 0;
        $skippedIds = [];

        foreach ($ids as $id) {
            $field = CustomField::withTrashed()->find($id);

            if (!$field) {
                continue;
            }

            // Authorize per field
            if (Gate::denies('forceDelete', $field)) {
                $skippedCount++;
                $skippedIds[] = $field->id;
                continue;
            }

            // Permanent delete
            $field->forceDelete();

            // Cache invalidation (explicit, since forceDelete may bypass saved event)
            $field->invalidateRelatedCache();

            $deletedCount++;
        }

        $message = "Permanently deleted {$deletedCount} custom field(s).";
        if ($skippedCount > 0) {
            $message .= " {$skippedCount} field(s) skipped (permission denied or invalid).";
            Log::warning('Bulk force-delete skipped some fields due to permissions', [
                'user_id' => $request->user()?->id,
                'skipped_ids' => $skippedIds,
            ]);
        }

        return redirect()
            ->back()
            ->with('success', $message);
    }

    /**
     * Bulk reorder fields (drag & drop sort update).
     *
     * What it will do:
     *   - Authorize reorder permission
     *   - Expect array like [field_id => new_sort_position] in request
     *   - Validate input (ids exist, sort values are integers >= 0)
     *   - Call service->reorderFields() to perform bulk update
     *   - Invalidate caches for affected fields
     *   - Return redirect back with success message (count updated)
     *   - Handle errors gracefully for Inertia
     */
    public function reorder(Request $request)
    {
        // 1. Authorize general reorder permission
        Gate::authorize('reorder', CustomField::class);

        // 2. Validate incoming data
        $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|min:0', // each value should be a sort position >= 0
        ]);

        $order = $request->input('order', []); // e.g. [5 => 10, 3 => 20, 8 => 0]

        if (empty($order)) {
            return back()->with('warning', 'No fields selected for reordering.');
        }

        $fieldIds = array_keys($order);

        // Quick existence check
        $existingCount = CustomField::whereIn('id', $fieldIds)->count();
        if ($existingCount !== count($fieldIds)) {
            return back()->withErrors(['order' => 'One or more field IDs do not exist.']);
        }

        try {
            // 3. Perform bulk reorder via service
            $updatedCount = $this->service->reorderFields($order);

            // 4. If any fields were global, we could fire events, but usually not necessary for reorder

            // 5. Success response
            return redirect()
                ->back()
                ->with('success', "Reordered {$updatedCount} custom field(s) successfully.");

        } catch (\Exception $e) {
            Log::error('Failed to reorder custom fields', [
                'order' => $order,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
            ]);

            return back()
                ->withErrors(['error' => 'An error occurred while reordering the fields. Please try again.']);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Optional future methods
    // ──────────────────────────────────────────────────────────────

    /**
     * Restore one or more soft-deleted custom fields (bulk supported).
     *
     * What it will do:
     *   - Accept array of IDs via request (e.g. ['ids' => [1, 2, 3]])
     *   - Authorize restore on each field
     *   - Restore only allowed soft-deleted fields (respecting policy & school scoping)
     *   - Invalidate caches for restored fields
     *   - Return redirect back with success count and skipped count (if any)
     *   - Inertia-friendly errors
     */
    public function restore(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:custom_fields,id,deleted_at,NULL', // only soft-deleted records
        ]);

        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return back()->with('warning', 'No fields selected for restoration.');
        }

        $restoredCount = 0;
        $skippedCount = 0;
        $skippedIds = [];

        foreach ($ids as $id) {
            $field = CustomField::withTrashed()->find($id);

            if (!$field || !$field->trashed()) {
                continue;
            }

            // Authorize per field
            if (Gate::denies('restore', $field)) {
                $skippedCount++;
                $skippedIds[] = $field->id;
                continue;
            }

            // Perform restore
            $field->restore();

            // Cache invalidation (via model event, but explicit for safety)
            $field->invalidateRelatedCache();

            $restoredCount++;
        }

        $message = "Restored {$restoredCount} custom field(s) successfully.";
        if ($skippedCount > 0) {
            $message .= " {$skippedCount} field(s) skipped (permission denied, not deleted, or invalid).";
            Log::warning('Bulk restore skipped some fields due to permissions or status', [
                'user_id' => $request->user()?->id,
                'skipped_ids' => $skippedIds,
            ]);
        }

        return redirect()
            ->back()
            ->with('success', $message);
    }

    /**
     * Export fields as JSON schema (for form builder preview)
     *
     * What it will do:
     *   - Authorize viewAny (or more specific if needed)
     *   - Accept optional ?resource=student query param
     *   - Resolve resource alias to FQCN via ModelResolver
     *   - Fetch effective fields for current school (global + overrides)
     *   - Format as clean, frontend-friendly JSON schema:
     *     - field name, label, type, required, options, placeholder, hint, etc.
     *     - File/image fields include constraints (max size, extensions)
     *   - Return as JSON response (downloadable or consumable by Vue)
     *   - Inertia-friendly (can be called via axios/fetch)
     */
    public function exportSchema(Request $request)
    {
        Gate::authorize('viewAny', CustomField::class);

        // Resolve optional resource filter
        $modelClass = null;
        if ($request->filled('resource')) {
            $alias = $request->string('resource');
            $modelClass = ModelResolver::get($alias);

            if (!$modelClass) {
                return response()->json([
                    'error' => "Invalid resource type: {$alias}"
                ], 422);
            }
        }

        // Get current school context
        $school = GetSchoolModel();

        // Fetch effective fields (merged global + school overrides)
        $fields = CustomField::effectiveFor($school, $modelClass ?? '');

        // Build clean schema array for frontend/form builder
        $schema = $fields->map(function (CustomField $field) {
            $typeEnum = CustomFieldType::tryFrom($field->field_type);

            return [
                'name' => $field->name,
                'label' => $field->label ?? Str::title(str_replace('_', ' ', $field->name)),
                'type' => $field->field_type,
                'component' => $typeEnum?->getComponent() ?? 'InputText',
                'required' => $field->required,
                'placeholder' => $field->placeholder,
                'hint' => $field->hint,
                'description' => $field->description,
                'default_value' => $field->default_value,
                'sort' => $field->sort,
                'options' => $field->options ?? [],
                'rules' => $field->rules ?? [],
                'is_file' => $typeEnum?->isFileType() ?? false,
                // File-specific constraints (for FileUpload component)
                'file_constraints' => $field->isFileField() ? [
                    'max_size_kb' => $field->max_file_size_kb ?? config('custom_fields.default_max_file_kb', 2048),
                    'allowed_extensions' => $field->allowed_extensions ?? [],
                    'multiple' => $field->file_type === 'multiple',
                ] : null,
                // Future extensibility
                'extra_attributes' => $field->extra_attributes ?? [],
                'conditional_rules' => $field->conditional_rules ?? [],
            ];
        })->values()->sortBy('sort')->toArray();

        // Optional: add metadata about the export
        $metadata = [
            'resource' => $request->string('resource', 'all'),
            'school_id' => $school?->id,
            'total_fields' => $fields->count(),
            'generated_at' => now()->toDateTimeString(),
        ];

        // Return clean JSON (can be downloaded or consumed via fetch/axios)
        return response()->json([
            'schema' => $schema,
            'metadata' => $metadata,
        ])->header('Content-Disposition', 'inline; filename="custom-fields-schema-' . ($request->string('resource', 'all')) . '.json"');
    }
}
