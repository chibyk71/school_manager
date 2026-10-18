<?php

namespace App\Models\FileManager;

use App\Models\Model;
use App\Models\School;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-school enablement/configuration for a code-registered File Manager module.
 *
 * Cannot invent module codes or elevate capability ceilings beyond the registry.
 * Disabling does not destroy folders, files, attachments, or this configuration row.
 */
class SchoolModuleConfiguration extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'fm_school_module_configurations';

    protected $fillable = [
        'school_id',
        'module_code',
        'enabled',
        'settings',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'settings' => 'array',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
