<?php

namespace App\Models\FileManager;

use App\Models\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Locked capacity ledger row for File Manager storage quota.
 *
 * Phase 1 uses a single scope_key = "tenant" for the whole tenant database.
 */
class StorageAccount extends Model
{
    use HasUuids;

    public const SCOPE_TENANT = 'tenant';

    protected $table = 'fm_storage_accounts';

    protected $fillable = [
        'scope_key',
        'used_bytes',
        'reserved_bytes',
    ];

    protected $casts = [
        'used_bytes' => 'integer',
        'reserved_bytes' => 'integer',
    ];
}
