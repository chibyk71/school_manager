<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomField extends Model
{ 
    /** @use HasFactory<\Database\Factories\CustomFieldFactory> */
    use HasFactory, BelongsToSchool;

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
        'entity_id'
    ];

    protected $casts = [
        'rules' => 'array',
        'classes' => 'array',
        'options' => 'array',
        'extra_attributes' => 'array',
        'field_options' => 'array'
    ];

    protected $table = 'custom_fields';

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('ordered', function ($query) {
            $query->orderBy('sort', 'asc')->orderBy('id', 'asc');
        });
    }

    public function model()
    {
        return $this->morphTo();
    }

    protected $appends = [
        'required'
    ];

    public function getRequiredAttribute(): bool
    {
        return in_array('required', $this->rules ?? []);
    }

    public static function getSchoolIdColumn(): string {
        return 'entity_id';
    }
}
