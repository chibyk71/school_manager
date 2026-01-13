<?php

namespace App\Traits;

use App\Models\CustomField;
use App\Models\CustomFieldResponse;
use App\Models\School;
use App\Services\CustomFieldService;
use App\Support\CustomFieldType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Trait HasCustomFields
 *
 * Adds custom field capabilities to any Eloquent model (Student, Teacher, Staff, etc.).
 *
 * Main responsibilities:
 *   - Define relationships: customFields() and customFieldResponses()
 *   - Provide CRUD helpers for fields (scoped to current school)
 *   - Bulk save responses with validation, file uploads, and transactions
 *   - Fetch effective (merged) fields: global + school overrides
 *   - Eager loading helpers
 *   - Cache management
 *   - Integrate Spatie Media Library for file/image fields
 *
 * Usage on a model:
 *   use HasCustomFields;
 *
 *   $student->saveCustomFieldResponses(['profile_photo' => $uploadedFile]);
 *   $student->getCustomFieldValue('emergency_contact');
 *   $student->effectiveCustomFields();
 */
trait HasCustomFields
{
    /**
     * Relationship: all custom field definitions attached to this model type
     * (mostly used internally — most queries go through effectiveFor())
     */
    public function customFields()
    {
        return $this->morphMany(CustomField::class, 'model');
    }

    /**
     * Relationship: all saved values for this specific record
     */
    public function customFieldResponses()
    {
        return $this->morphMany(CustomFieldResponse::class, 'model');
    }

    /**
     * Get all effective custom fields for this model instance (global + current school overrides)
     *
     * @return Collection<CustomField>
     */
    public function effectiveCustomFields(): Collection
    {
        $school = $this->getActiveSchool();

        $cacheKey = "effective_fields:{$school->id}:" . md5(get_class($this));

        return Cache::remember($cacheKey, now()->addMinutes(60), function () use ($school) {
            return CustomField::effectiveFor($school, get_class($this));
        });
    }

    /**
     * Bulk save custom field values for this record.
     *
     * Handles:
     *   - Validation (delegated to service)
     *   - File/image uploads via Spatie Media Library
     *   - Basic quota check
     *   - Transactional safety
     *
     * @param array $responses [field_name => value|UploadedFile]
     * @param bool $validate Whether to run validation (usually true)
     * @return array Saved values [name => final_value]
     * @throws ValidationException
     * @throws \Exception
     */
    public function saveCustomFieldResponses(array $responses, bool $validate = true): array
    {
        if (empty($responses)) {
            return [];
        }

        $school = $this->getActiveSchool();
        $fields = $this->effectiveCustomFields()->keyBy('name');

        // 1. Prepare data + validate
        $service = app(CustomFieldService::class);
        $prepared = $service->prepareAndValidateResponses($this, $responses, $fields, $validate);

        $saved = [];

        \DB::transaction(function () use ($prepared, &$saved, $fields) {
            foreach ($prepared as $name => $data) {
                $field = $fields->get($name);

                // Handle file uploads if this is a file/image field
                if ($field->isFileField() && $data['value'] instanceof \Illuminate\Http\UploadedFile) {
                    $media = $this->handleFileUpload($field, $data['value']);
                    $data['value'] = json_encode(['media_id' => $media->id]);
                }

                CustomFieldResponse::updateOrCreate(
                    [
                        'custom_field_id' => $field->id,
                        'model_type'      => get_class($this),
                        'model_id'        => $this->getKey(),
                    ],
                    ['value' => $data['value']]
                );

                $saved[$name] = $data['value'];
            }
        });

        // Invalidate cache after successful save
        $this->invalidateCustomFieldCache($school);

        return $saved;
    }

    /**
     * Handle file/image upload using Spatie Media Library
     *
     * @throws FileCannotBeAdded
     */
    protected function handleFileUpload(CustomField $field, \Illuminate\Http\UploadedFile $file): Media
    {
        $collection = $field->field_type === 'image' ? 'images' : 'files';

        // Basic quota check (configurable)
        $maxMb = config('custom_fields.max_upload_mb_per_record', 50);
        if ($this->media()->where('collection_name', $collection)->sum('size') / 1024 / 1024 > $maxMb) {
            throw new \Exception("Upload quota exceeded for this record (max {$maxMb}MB).");
        }

        return $this->addMedia($file)
            ->withCustomProperties([
                'custom_field_id' => $field->id,
                'field_name'      => $field->name,
            ])
            ->toMediaCollection($collection);
    }

    /**
     * Get a single custom field value (with fallback)
     */
    public function getCustomFieldValue(string $fieldName, $default = null)
    {
        $response = $this->customFieldResponses()
            ->whereHas('customField', fn ($q) => $q->where('name', $fieldName))
            ->first();

        if (!$response) {
            return $default;
        }

        $value = $response->value;

        // If it's JSON (e.g. media ID), decode
        if (is_string($value) && str_starts_with($value, '{')) {
            $decoded = json_decode($value, true);
            if (isset($decoded['media_id'])) {
                $media = Media::find($decoded['media_id']);
                return $media ? $media->getUrl() : $default;
            }
        }

        return $value;
    }

    /**
     * Eager load fields + responses for this instance
     */
    public function loadCustomFields()
    {
        $schoolId = $this->getActiveSchool()?->id;

        return $this->loadMissing([
            'customFieldResponses.customField' => fn ($q) => $q
                ->where('school_id', $schoolId)
                ->orderBy('sort'),
        ]);
    }

    /**
     * Scope: eager load with custom fields on query
     */
    public function scopeWithCustomFields(Builder $query): Builder
    {
        $schoolId = GetSchoolModel()?->id;

        return $query->with([
            'customFieldResponses.customField' => fn ($q) => $q
                ->select([
                    'id', 'name', 'label', 'field_type', 'options',
                    'placeholder', 'hint', 'sort', 'rules'
                ])
                ->where('school_id', $schoolId)
                ->orderBy('sort'),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // Field CRUD helpers (scoped to current school)
    // ──────────────────────────────────────────────────────────────

    public function addCustomField(array $fieldData): CustomField
    {
        $school = $this->getActiveSchool();
        $fieldData['school_id'] = $school->id;
        $fieldData['model_type'] = get_class($this);

        $service = app(CustomFieldService::class);
        $field = $this->customFields()->create($service->validateAndPrepareField($fieldData));

        $this->invalidateCustomFieldCache($school);

        return $field;
    }

    public function updateCustomField(int $fieldId, array $fieldData): bool
    {
        $school = $this->getActiveSchool();

        $updated = $this->customFields()
            ->where('id', $fieldId)
            ->where('school_id', $school->id)
            ->update(app(CustomFieldService::class)->validateAndPrepareField($fieldData, true));

        if ($updated) {
            $this->invalidateCustomFieldCache($school);
        }

        return (bool) $updated;
    }

    public function deleteCustomField(int $fieldId): bool
    {
        $school = $this->getActiveSchool();

        $deleted = $this->customFields()
            ->where('id', $fieldId)
            ->where('school_id', $school->id)
            ->delete();

        if ($deleted) {
            $this->invalidateCustomFieldCache($school);
        }

        return (bool) $deleted;
    }

    // ──────────────────────────────────────────────────────────────
    // Internal helpers
    // ──────────────────────────────────────────────────────────────

    protected function getActiveSchool(): School
    {
        $school = GetSchoolModel();

        if (!$school) {
            throw new \RuntimeException('No active school context available.');
        }

        return $school;
    }

    protected function invalidateCustomFieldCache(School $school): void
    {
        Cache::forget("effective_fields:{$school->id}:" . md5(get_class($this)));
        // You can also flush broader tags if needed
        // Cache::tags(["custom_fields:school:{$school->id}"])->flush();
    }

    // Placeholder for future conditional evaluation
    protected function evaluateConditionalRules(CustomField $field, array $allValues): bool
    {
        if (empty($field->conditional_rules)) {
            return true;
        }

        // TODO: implement visibility logic based on other field values
        // e.g. if other_field == 'yes' then show this field
        return true;
    }
}
