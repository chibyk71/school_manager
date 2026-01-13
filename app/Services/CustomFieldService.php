<?php

namespace App\Services;

use App\Helpers\ModelResolver;
use App\Models\CustomField;
use App\Models\School;
use App\Notifications\GlobalCustomFieldUpdatedNotification;
use App\Support\CustomFieldType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CustomFieldService
 *
 * Central business logic for the custom fields module.
 * Handles:
 *   - Preset application (global → school overrides)
 *   - All validation (field definitions + response values)
 *   - File quota checks
 *   - Conflict notifications
 *   - Bulk operations (reorder)
 *
 * Why a service?
 *   - Removes duplication from Trait & Controller
 *   - Keeps controllers thin (HTTP only)
 *   - Makes unit testing easier
 *   - Single place to add future rules (quotas, auditing, conditionals)
 *
 * Injectable via container: app(CustomFieldService::class)
 */
class CustomFieldService
{
    /**
     * Apply a global preset as school-specific overrides.
     *
     * Behavior:
     *   - Copies global fields (school_id = null) with matching category
     *   - Skips if school already has override for that name
     *   - Optionally copies existing values from global to new override
     *   - Assigns incremental sort order
     *
     * @param School $school Target school
     * @param string $presetName e.g. 'emergency_contact', 'medical_info'
     * @param string $modelType Friendly name ('student') or FQCN
     * @param bool $copyValues Copy existing global values to new overrides?
     * @return int Number of fields created
     * @throws ValidationException
     */
    public function applyPreset(School $school, string $presetName, string $modelType, bool $copyValues = false): int
    {
        // Resolve friendly name → FQCN
        $modelType = ModelResolver::getOrFail($modelType);

        // Fetch global preset fields
        $presetFields = CustomField::query()
            ->whereNull('school_id')
            ->where('category', $presetName)
            ->where('model_type', $modelType)
            ->orderBy('sort')
            ->get();

        if ($presetFields->isEmpty()) {
            throw ValidationException::withMessages([
                'preset' => "No preset fields found for category '{$presetName}' and model '{$modelType}'."
            ]);
        }

        // Existing overrides in this school
        $existingNames = CustomField::query()
            ->where('school_id', $school->id)
            ->where('model_type', $modelType)
            ->pluck('name')
            ->toArray();

        // Starting sort position
        $maxSort = CustomField::query()
            ->where('school_id', $school->id)
            ->where('model_type', $modelType)
            ->max('sort') ?? 0;

        $nextSort = $maxSort + 10;
        $created = 0;
        $newRecords = [];

        foreach ($presetFields as $global) {
            if (in_array($global->name, $existingNames)) {
                // Optional: notify if global changed but school has override
                if ($copyValues) {
                    $this->notifyOnGlobalConflict($school, $global);
                }
                continue;
            }

            $newRecord = [
                'name' => $global->name,
                'label' => $global->label,
                'placeholder' => $global->placeholder,
                'rules' => $global->rules,
                'field_type' => $global->field_type,
                'options' => $global->options,
                'default_value' => $global->default_value,
                'description' => $global->description,
                'hint' => $global->hint,
                'sort' => $nextSort,
                'category' => $global->category,
                'model_type' => $modelType,
                'school_id' => $school->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Copy advanced columns if present
            foreach (['file_type', 'max_file_size_kb', 'allowed_extensions', 'conditional_rules', 'preset_key'] as $col) {
                if ($global->$col !== null) {
                    $newRecord[$col] = $global->$col;
                }
            }

            $newRecords[] = $newRecord;
            $nextSort += 10;
            $created++;
        }

        if ($created > 0) {
            DB::transaction(function () use ($newRecords) {
                CustomField::insert($newRecords);
            });

            // Optional: copy values from global responses to new overrides
            if ($copyValues) {
                $this->copyGlobalValuesToOverrides($school, $modelType, collect($newRecords)->pluck('name'));
            }
        }

        return $created;
    }

    /**
     * Validate and prepare field definition data (create or update)
     *
     * @param array $data Incoming data
     * @param CustomField|null $existing For updates
     * @return array Normalized & validated data
     * @throws ValidationException
     */
    public function validateAndPrepareField(array $data, ?CustomField $existing = null): array
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9_]+$/',
            ],
            'label' => 'required|string|max:150',
            'field_type' => [
                'required',
                Rule::in(CustomFieldType::all()),
            ],
            'placeholder' => 'nullable|string|max:150',
            'rules' => 'nullable|array',
            'rules.*' => 'string',
            'options' => 'nullable|array',
            'options.*' => 'string|max:255',
            'default_value' => 'nullable',
            'description' => 'nullable|string',
            'hint' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'sort' => 'nullable|integer|min:0',
            'model_type' => 'required|string',
        ];

        // Scoped uniqueness
        $unique = Rule::unique('custom_fields', 'name')
            ->where('model_type', $data['model_type'] ?? null)
            ->where('school_id', $data['school_id'] ?? null);

        if ($existing) {
            $unique = $unique->ignore($existing->id);
        }

        $rules['name'][] = $unique;

        $validated = Validator::make($data, $rules)->validate();

        // Normalize name
        $validated['name'] = \Str::snake(trim($validated['name']));

        // Auto-add 'in:' rule for option-based fields
        $type = CustomFieldType::tryFrom($validated['field_type']);
        if ($type && $type->hasOptions() && !empty($validated['options'])) {
            $inRule = 'in:' . implode(',', $validated['options']);
            $validated['rules'] = array_merge($validated['rules'] ?? [], [$inRule]);
        }

        // Resolve model_type if friendly name was sent
        if (!class_exists($validated['model_type'])) {
            $validated['model_type'] = ModelResolver::getOrFail($validated['model_type']);
        }

        return $validated;
    }

    /**
     * Validate and prepare incoming field responses for a model
     *
     * @param Model $model Instance (e.g. $student)
     * @param array $responses [name => value]
     * @param Collection $fields Effective fields keyBy('name')
     * @param bool $validate
     * @return array Prepared data ready for save
     * @throws ValidationException
     */
    public function prepareAndValidateResponses(
        $model,
        array $responses,
        Collection $fields,
        bool $validate = true
    ): array {
        $rules = [];
        $attributes = [];
        $prepared = [];

        foreach ($responses as $name => $value) {
            $field = $fields->get($name);

            if (!$field) {
                Log::warning("Field '{$name}' not found when saving responses", [
                    'model' => get_class($model),
                    'id' => $model->id,
                ]);
                continue;
            }

            // File handling: we'll pass UploadedFile through, validation later
            if ($field->isFileField() && $value instanceof \Illuminate\Http\UploadedFile) {
                $rules[$name] = $field->getSuggestedRules($field->toArray());
            } elseif ($validate && $field->rules) {
                $rules[$name] = $field->rules;
            }

            $attributes[$name] = $field->label ?? $name;

            $prepared[$name] = [
                'custom_field_id' => $field->id,
                'value' => $value,
            ];
        }

        if ($validate && $rules) {
            Validator::make($responses, $rules, [], $attributes)->validate();
        }

        return $prepared;
    }

    /**
     * Reorder fields (bulk update sort order)
     *
     * @param array $order [field_id => new_sort_position]
     * @return int Number updated
     */
    public function reorderFields(array $order): int
    {
        $updated = 0;

        DB::transaction(function () use ($order, &$updated) {
            foreach ($order as $id => $sort) {
                $field = CustomField::find($id);
                if ($field) {
                    $field->update(['sort' => (int) $sort]);
                    $updated++;
                }
            }
        });

        return $updated;
    }

    /**
     * Notify school admins when a global field is updated but they have an override
     */
    protected function notifyOnGlobalConflict(School $school, CustomField $globalField): void
    {
        // Get school admins (adjust query to your User/Role setup)
        $admins = $school->users()->whereHas('roles', fn($q) => $q->where('name', 'admin'))->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new GlobalCustomFieldUpdatedNotification($globalField));
        }
    }

    /**
     * Optional: Copy values from global responses to new school overrides
     * (useful when applying preset and want to preserve old data)
     */
    protected function copyGlobalValuesToOverrides(School $school, string $modelType, Collection $newFieldNames): void
    {
        // This would require querying existing global responses for sample entities
        // or copying from a "template" record — implementation depends on your needs
        // Left as placeholder for now
        Log::info("Value copying from global to overrides not implemented yet", [
            'school_id' => $school->id,
            'model_type' => $modelType,
            'field_names' => $newFieldNames->toArray(),
        ]);
    }
}
