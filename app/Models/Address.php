<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use App\Traits\BelongsToSections;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Address extends Model
{
    /** @use HasFactory<\Database\Factories\AddressFactory> */
    use HasFactory;

    protected $fillable = [
        'address',
        'city',
        'state',
        'country_id',
        'postal_code',
        'addressable_id',
        'addressable_type',
        'is_primary',
    ];

    protected $casts = [
        'address' => 'string',
        'city' => 'string',
        'state' => 'string',
        'country' => 'string',
        'postal_code' => 'string',
    ];

    public function addressable()
    {
        return $this->morphTo();
    }
}
