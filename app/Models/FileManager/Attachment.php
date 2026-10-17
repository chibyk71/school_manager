<?php

namespace App\Models\FileManager;

use App\Models\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Explicit link between a File and a business owner (File Manager Phase 2).
 *
 * Owner types must come from AttachableOwnerRegistry. Detach does not delete
 * the physical object or the File row.
 */
class Attachment extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'fm_attachments';

    protected $fillable = [
        'file_id',
        'owner_type',
        'owner_id',
        'purpose',
        'lifecycle',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    public function owner(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'owner_type', 'owner_id');
    }
}
