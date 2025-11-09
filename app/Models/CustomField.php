<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use App\Traits\HasTableQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * Class CustomField
 *
 * Represents a custom field definition for a model in a multi-tenant school management system.
 *
 * @package App\Models
 */
class CustomField extends Model
{
    /** @use HasFactory<\Database\Factories\CustomFieldFactory> */
    use HasFactory, BelongsToSchool, HasTableQuery, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'label',
        'placeholder',
        'rules',
        'classes',
        'field_type',
        'options',
        'default_value',
        'description',
        'hint',
        'sort',
        'category',
        'extra_attributes',
        'field_options',
        'cast_as',
        'has_options',
        'model_type',
        'school_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rules' => 'array',
        'classes' => 'array',
        'options' => 'array',
        'extra_attributes' => 'array',
        'field_options' => 'array',
        'has_options' => 'boolean',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'custom_fields';

    /**
     * The attributes that should be appended to the model's array form.
     *
     * @var array<string>
     */
    protected $appends = [
        'required',
    ];

    /**
     * Boot the model with global scopes and event listeners.
     */
    protected static function boot()
    {
        parent::boot();

        // Apply default ordering by sort and ID
        static::addGlobalScope('ordered', function ($query) {
            $query->orderBy('sort', 'asc')->orderBy('id', 'asc');
        });

        // Invalidate cache on create/update/delete
        static::saved(function ($model) {
            $cacheKey = 'custom_fields_' . $model->school_id . '_' . md5($model->model_type);
            Cache::forget($cacheKey);
        });

        static::deleted(function ($model) {
            $cacheKey = 'custom_fields_' . $model->school_id . '_' . md5($model->model_type);
            Cache::forget($cacheKey);
        });
    }

    /**
     * Define the polymorphic relationship to the owning model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function model()
    {
        return $this->morphTo();
    }

    /**
     * Get the required attribute based on the rules.
     *
     * @return bool
     */
    public function getRequiredAttribute(): bool
    {
        return in_array('required', $this->rules ?? []);
    }

    public function scopeForSchool($query, $school)
    {
        return $query->where('school_id', $school->id ?? $school);
    }

    public function scopeForModel($query, $model)
    {
        return $query->where('model_type', is_string($model) ? $this->resources[$model] ?? $model : $model);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort')->orderBy('id');
    }

    public function responses()
    {
        return $this->hasMany(CustomFieldResponse::class, 'custom_field_id', 'id');
    }

    /**
     * Get the school ID column name.
     *
     * @return string
     */
    public static function getSchoolIdColumn(): string
    {
        return 'school_id';
    }
}
