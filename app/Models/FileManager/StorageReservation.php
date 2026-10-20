<?php

namespace App\Models\FileManager;

use App\Models\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Durable reservation for File Manager upload capacity.
 *
 * States:
 * - pending: capacity held, physical write not yet settled
 * - settled: converted to used_bytes against an authoritative File row
 * - released: confirmed cleanup; capacity returned
 * - uncertain: physical outcome unknown; capacity retained until reconciliation
 */
class StorageReservation extends Model
{
    use HasUuids;

    public const STATE_PENDING = 'pending';

    public const STATE_SETTLED = 'settled';

    public const STATE_RELEASED = 'released';

    public const STATE_UNCERTAIN = 'uncertain';

    protected $table = 'fm_storage_reservations';

    protected $fillable = [
        'scope_key',
        'operation_id',
        'reserved_bytes',
        'settled_bytes',
        'state',
        'file_id',
        'notes',
    ];

    protected $casts = [
        'reserved_bytes' => 'integer',
        'settled_bytes' => 'integer',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    public function isOutstanding(): bool
    {
        return in_array($this->state, [self::STATE_PENDING, self::STATE_UNCERTAIN], true);
    }
}
