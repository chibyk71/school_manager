<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomField extends \SpykApp\LaravelCustomFields\Models\CustomField
{
    /** @use HasFactory<\Database\Factories\CustomFieldFactory> */
    use HasFactory, BelongsToSchool;

    public static function getSchoolIdColumn(): string {
        return 'entity_id';
    }
}
