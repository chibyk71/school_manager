<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    /** @use HasFactory<\Database\Factories\AddressFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'address',
        'city',
        'lga',
        'state',
        'country',
        'postal_code',
        'landmark',
        'is_primary',
        'addressable_id',
        'addressable_type',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    protected $table = 'addresses';

    /**
     * Define the polymorphic relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function addressable()
    {
        return $this->morphTo();
    }
}
