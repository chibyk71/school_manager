<?php

namespace App\Traits;

use App\Models\CustomField;
use App\Models\School;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Trait HasCustomFields
 *
 * Provides functionality to manage custom fields for a model in a multi-tenant SaaS environment.
 * Supports dynamic field definitions with validation, scoped to schools and branches.
 *
 * @package App\Traits
 */
trait HasCustomFields
{
    /**
     * Define a polymorphic relationship to custom fields.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function customFields()
    {
        return $this->morphMany(CustomField::class, 'model');
    }

    /**
     * Add a new custom field to the model, scoped to the active school and optional branch.
     *
     * @param array $fieldData The custom field data (e.g., name, label, rules, branch_id).
     * @return \App\Models\CustomField The created custom field.
     * @throws \Exception If creation fails or no active school is found.
     */
    public function addCustomField(array $fieldData): CustomField
    {
        try {
            $school = $this->getActiveSchool();
            $fieldData['school_id'] = $school->id;

            // Validate field data before creation
            $this->validateFieldData($fieldData);

            // Create the custom field
            $customField = $this->customFields()->create($fieldData);

            // Invalidate cache to ensure updated fields are fetched
            $this->invalidateCustomFieldCache($school);

            return $customField;
        } catch (\Exception $e) {
            Log::error('Failed to add custom field for model ' . get_class($this) . ': ' . $e->getMessage());
            throw new \Exception('Unable to add custom field: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing custom field, ensuring school and branch scoping.
     *
     * @param int $fieldId The custom field ID.
     * @param array $fieldData The updated field data.
     * @return bool True if updated, false otherwise.
     * @throws \Exception If update fails or no active school is found.
     */
    public function updateCustomField(int $fieldId, array $fieldData): bool
    {
        try {
            $school = $this->getActiveSchool();

            // Validate field data
            $this->validateFieldData($fieldData);

            // Update the custom field
            $updated = $this->customFields()
                ->where('id', $fieldId)
                ->where('school_id', $school->id)
                ->update($fieldData);

            // Invalidate cache
            $this->invalidateCustomFieldCache($school);

            return $updated;
        } catch (\Exception $e) {
            Log::error('Failed to update custom field ID ' . $fieldId . ': ' . $e->getMessage());
            throw new \Exception('Unable to update custom field: ' . $e->getMessage());
        }
    }

    /**
     * Delete a custom field, ensuring school scoping.
     *
     * @param int $fieldId The custom field ID.
     * @return bool True if deleted, false otherwise.
     * @throws \Exception If deletion fails or no active school is found.
     */
    public function deleteCustomField(int $fieldId): bool
    {
        try {
            $school = $this->getActiveSchool();

            // Delete the custom field
            $deleted = $this->customFields()
                ->where('id', $fieldId)
                ->where('school_id', $school->id)
                ->delete();

            // Invalidate cache
            $this->invalidateCustomFieldCache($school);

            return $deleted;
        } catch (\Exception $e) {
            Log::error('Failed to delete custom field ID ' . $fieldId . ': ' . $e->getMessage());
            throw new \Exception('Unable to delete custom field: ' . $e->getMessage());
        }
    }

    /**
     * Load custom fields for the model with eager loading.
     *
     * @return $this
     */
    public function withCustomFields()
    {
        $this->load('customFields');
        return $this;
    }

    /**
     * Save custom field responses for the model, with validation and caching.
     *
     * @param array $responses Key-value pairs of custom field names and values.
     * @param bool $validate Whether to validate the responses.
     * @return array The successfully saved responses.
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Exception If saving fails or no active school is found.
     */
    public function saveCustomFieldResponses(array $responses, bool $validate = true): array
    {
        try {
            $school = $this->getActiveSchool();

            // Ensure extra_attributes column exists
            if (!property_exists($this, 'casts') || !isset($this->casts['extra_attributes']) || $this->casts['extra_attributes'] !== 'array') {
                throw new \Exception('Model ' . get_class($this) . ' must define extra_attributes as array in casts.');
            }

            $savedResponses = [];
            $validator = Validator::make([], []);

            // Cache custom fields for performance
            $cacheKey = 'custom_fields_' . $school->id . '_' . md5(get_class($this));
            $allowedFields = Cache::remember($cacheKey, now()->addHour(), function () use ($school) {
                return CustomField::where('model_type', get_class($this))
                    ->where('school_id', $school->id)
                    ->get()
                    ->keyBy('name');
            });

            $rules = [];
            $customAttributes = [];
            $filteredResponses = [];

            // Validate responses against defined custom fields
            foreach ($responses as $fieldName => $value) {
                if (!$allowedFields->has($fieldName)) {
                    $validator->errors()->add($fieldName, "The field '{$fieldName}' is not recognized for this model.");
                    continue;
                }

                $customField = $allowedFields->get($fieldName);
                if ($validate && !empty($customField->rules)) {
                    $rules[$fieldName] = $customField->rules;
                    $customAttributes[$fieldName] = strtolower($customField->label ?? $fieldName);
                }

                $filteredResponses[$fieldName] = $value;
            }

            // Apply validation rules if required
            if ($validate && $rules) {
                $fieldValidator = Validator::make($filteredResponses, $rules, [], $customAttributes);
                if ($fieldValidator->fails()) {
                    foreach ($fieldValidator->errors()->messages() as $field => $errors) {
                        foreach ($errors as $error) {
                            $validator->errors()->add($field, $error);
                        }
                    }
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                throw new ValidationException($validator);
            }

            // Save responses to extra_attributes
            foreach ($filteredResponses as $fieldName => $value) {
                $this->extra_attributes[$fieldName] = $value;
                $savedResponses[$fieldName] = $value;
            }

            $this->save();

            return $savedResponses;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to save custom field responses for model ' . get_class($this) . ': ' . $e->getMessage());
            throw new \Exception('Unable to save custom field responses: ' . $e->getMessage());
        }
    }

    /**
     * Get the active school model, throwing an exception if not found.
     *
     * @return \App\Models\School
     * @throws \Exception
     */
    protected function getActiveSchool(): School
    {
        $school = GetSchoolModel();
        if (!$school) {
            throw new \Exception('No active school found for this operation.');
        }
        return $school;
    }

    /**
     * Validate custom field data before creation or update.
     *
     * @param array $fieldData
     * @return void
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateFieldData(array $fieldData): void
    {
        Validator::make($fieldData, [
            'name' => 'required|string|max:255',
            'label' => 'required|string|max:255',
            'field_type' => 'required|string|in:text,textarea,select,radio,checkbox',
            'rules' => 'nullable|array',
            'rules.*' => 'string',
            'options' => 'nullable|array',
            'options.*' => 'string|max:255',
        ])->validate();
    }

    /**
     * Invalidate the custom fields cache for a given school.
     *
     * @param \App\Models\School $school
     * @return void
     */
    protected function invalidateCustomFieldCache(School $school): void
    {
        $cacheKey = 'custom_fields_' . $school->id . '_' . md5(get_class($this));
        Cache::forget($cacheKey);
    }
}