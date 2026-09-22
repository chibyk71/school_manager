<?php

/**
 * Known Dynamic Enum business consumers (Phase 2R).
 *
 * Maps definition key → model class + column that store the option scalar value.
 * Used for dependency-protected permanent deletion only.
 * Not a generic dependency graph.
 */

namespace App\Services\DynamicEnum;

use App\Models\Address;
use App\Models\Guardian;
use App\Models\Profile;

class DynamicEnumConsumerRegistry
{
    /**
     * @return array<string, list<array{model: class-string, column: string}>>
     */
    public static function map(): array
    {
        return [
            'profile.gender' => [
                ['model' => Profile::class, 'column' => 'gender'],
            ],
            'profile.title' => [
                ['model' => Profile::class, 'column' => 'title'],
            ],
            'address.type' => [
                ['model' => Address::class, 'column' => 'type'],
            ],
            'guardian.relationship' => [
                ['model' => Guardian::class, 'column' => 'relationship'],
            ],
        ];
    }

    /**
     * @return list<array{model: class-string, column: string}>
     */
    public static function consumersFor(string $key): array
    {
        return self::map()[$key] ?? [];
    }
}
