<?php

namespace App\Traits;

use App\Models\CustomField;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

trait HasCustomFields
{
    public function customFields()
    {
        return $this->morphMany(CustomField::class, 'model');
    }

    public function addCustomField(array $fieldData)
    {
        return $this->customFields()->create($fieldData);
    }

    public function updateCustomField($fieldId, array $fieldData)
    {
        return $this->customFields()->where('id', $fieldId)->update($fieldData);
    }

    public function deleteCustomField($fieldId)
    {
        return $this->customFields()->where('id', $fieldId)->delete();
    }

    public function withCustomFields()
    {
        $this->load('customFields');
        return $this;
    }


    /**
     * Save custom field responses for the model.
     *
     * @param array $responses Key-value pairs of custom field names and values
     * @param bool $validate Whether to validate the responses
     * @return array The successfully saved responses
     * @throws ValidationException If validation fails
     */
    public function saveCustomFieldResponses(array $responses, bool $validate = true): array
    {
        $savedResponses = [];
        $validator = Validator::make([], []); // main error collector

        // Load allowed fields (cached)
        $cacheKey = 'custom_fields_' . $this->school('id') . '_' . md5(get_class($this));
        $allowedFields = Cache::remember($cacheKey, 3600, function () {
            return CustomField::where('model_type', get_class($this))
                ->get()->keyBy('name');
        });

        // Holds Laravel validation rules for each allowed custom field
        $rules = [];

        // Maps field names to human-readable labels for friendlier error messages
        $customAttributes = [];

        // Stores only the user responses that match allowed custom fields (filters out unknown ones)
        $filteredResponses = [];

        foreach ($responses as $fieldName => $value) {
            // 1. Check if field exists in schema
            if (!$allowedFields->has($fieldName)) {
                $validator->errors()->add($fieldName, "The field '{$fieldName}' is not recognized.");
                continue;
            }

            $customField = $allowedFields->get($fieldName);

            // 2. Build validation rules
            if ($validate && !empty($customField->rules)) {
                $rules[$fieldName] = $customField->rules;
                $customAttributes[$fieldName] = strtolower($customField->label ?? $fieldName);
            }

            // 3. Collect for validation (only allowed fields)
            $filteredResponses[$fieldName] = $value;
        }

        // 4. Run validation for allowed fields only
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

        // 5. Throw if any errors collected
        if ($validator->errors()->isNotEmpty()) {
            throw new ValidationException($validator);
        }

        // 6. Save values only after passing validation
        foreach ($filteredResponses as $fieldName => $value) {
            $this->extra_attributes->{$fieldName} = $value;
            $savedResponses[$fieldName] = $value;
        }

        try {
            $this->save();
        } catch (Exception $e) {
            throw new Exception("Failed to save custom field responses: {$e->getMessage()}");
        }

        return $savedResponses;
    }


}
