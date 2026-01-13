<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Class CustomFieldResponse
 *
 * Stores the value entered for a specific custom field on a specific record
 * (e.g. student_id 123 has field 'emergency_contact' = '08031234567')
 *
 * Important:
 *   - value is text → can store string, json, media ID(s), etc.
 *   - polymorphic via model_type + model_id
 *   - logged via Spatie Activitylog (useful for audit trails)
 */
class CustomFieldResponse extends Model
{
    use LogsActivity;

    protected $table = 'custom_field_responses';

    protected $fillable = [
        'custom_field_id',
        'model_type',
        'model_id',
        'value',
    ];

    protected $casts = [
        'value' => 'json', // auto decode/encode arrays/objects
    ];

    // ──────────────────────────────────────────────────────────────
    // Activity Logging
    // ──────────────────────────────────────────────────────────────
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['value'])
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->useLogName('custom-field-response');
    }

    // ──────────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────────

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    // ──────────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────────

    /**
     * Scope: responses for a specific entity
     */
    public function scopeForModel(Builder $query, Model $model): Builder
    {
        return $query->where([
            'model_type' => get_class($model),
            'model_id'   => $model->getKey(),
        ]);
    }

    /**
     * Scope: responses for a specific field
     */
    public function scopeForField(Builder $query, CustomField $field): Builder
    {
        return $query->where('custom_field_id', $field->id);
    }
}
