<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomField extends \SpykApp\LaravelCustomFields\Models\CustomField
{
    /** @use HasFactory<\Database\Factories\CustomFieldFactory> */
    use HasFactory, BelongsToSchool;

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
