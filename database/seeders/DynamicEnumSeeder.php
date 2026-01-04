<?php
/**
 * database/seeders/DynamicEnumSeeder.php
 *
 * Seeds the default global (system-wide) dynamic enum definitions into the dynamic_enums table.
 *
 * Features / Problems Solved:
 * - Provides out-of-the-box, sensible default option lists for common "enum-like" properties
 *   (title, gender, profile_type on Profile; type on Address).
 * - Mirrors the structure and data from the original ConfigSeeder to ensure zero disruption
 *   when migrating away from the generic configs table for these enum-style entries.
 * - Uses indexed arrays for options with explicit 'value' and 'label' keys – this is more
 *   extensible than assoc arrays (allows future per-option metadata like color, icon, sort_order).
 * - Preserves original descriptions and color classes for consistent UI (badges, previews).
 * - All entries are global (school_id = null) so every school inherits them automatically.
 * - Uses updateOrCreate() for idempotency – safe to run multiple times during development/deployments.
 * - Fully compatible with the HasDynamicEnum trait's validation and option fetching logic
 *   (expects options as array of ['value' => ..., 'label' => ...]).
 *
 * Fits into the DynamicEnums Module:
 * - Populates the dynamic_enums table with production-ready defaults immediately after migration.
 * - Ensures that any model using HasDynamicEnum (e.g., Profile, Address) has valid options
 *   available right away, even before any school admin customizes them.
 * - Serves as the migration path from the old Config-based enums – after running this seeder,
 *   the equivalent rows in configs can be safely archived/deleted.
 * - Run via `php artisan db:seed --class=DynamicEnumSeeder` or as part of DatabaseSeeder.
 */

namespace Database\Seeders;

use App\Models\DynamicEnum;
use Illuminate\Database\Seeder;

class DynamicEnumSeeder extends Seeder
{
    public function run(): void
    {
        $enums = [
            // 1. Title – used on Profile
            [
                'label'       => 'Title',
                'name'        => 'title',
                'applies_to'  => \App\Models\Profile::class,
                'description' => 'Prefix that appears before a person\'s name (Mr, Mrs, Dr, …).',
                'color'       => 'bg-indigo-100 text-indigo-800',
                'options'     => [
                    ['value' => 'Mr',     'label' => 'Mr'],
                    ['value' => 'Mrs',    'label' => 'Mrs'],
                    ['value' => 'Miss',   'label' => 'Miss'],
                    ['value' => 'Ms',     'label' => 'Ms'],
                    ['value' => 'Dr',     'label' => 'Dr'],
                    ['value' => 'Prof',   'label' => 'Prof'],
                    ['value' => 'Rev',    'label' => 'Rev'],
                    ['value' => 'Engr',   'label' => 'Engr'],
                ],
                'school_id'   => null,
            ],

            // 2. Gender – used on Profile
            [
                'label'       => 'Gender',
                'name'        => 'gender',
                'applies_to'  => \App\Models\Profile::class,
                'description' => 'Gender identity options for staff, students and guardians.',
                'color'       => 'bg-pink-100 text-pink-800',
                'options'     => [
                    ['value' => 'male',           'label' => 'Male'],
                    ['value' => 'female',         'label' => 'Female'],
                    ['value' => 'other',          'label' => 'Other'],
                    ['value' => 'prefer_not',     'label' => 'Prefer not to say'],
                ],
                'school_id'   => null,
            ],

            // 3. Profile Type – used on Profile
            [
                'label'       => 'Profile Type',
                'name'        => 'profile_type',
                'applies_to'  => \App\Models\Profile::class,
                'description' => 'The role a profile represents inside the school.',
                'color'       => 'bg-teal-100 text-teal-800',
                'options'     => [
                    ['value' => 'staff',     'label' => 'Staff / Teacher'],
                    ['value' => 'student',   'label' => 'Student'],
                    ['value' => 'guardian',  'label' => 'Parent / Guardian'],
                ],
                'school_id'   => null,
            ],

            // 4. Address Type – used on Address
            [
                'label'       => 'Address Type',
                'name'        => 'type',
                'applies_to'  => \App\Models\Address::class,
                'description' => 'Classification of the address (residential, school campus, office, postal, temporary, billing).',
                'color'       => 'bg-yellow-100 text-yellow-800',
                'options'     => [
                    ['value' => 'residential',    'label' => 'Residential'],
                    ['value' => 'school_campus',  'label' => 'School Campus'],
                    ['value' => 'office',         'label' => 'Office'],
                    ['value' => 'postal',         'label' => 'Postal'],
                    ['value' => 'temporary',      'label' => 'Temporary'],
                    ['value' => 'billing',        'label' => 'Billing'],
                ],
                'school_id'   => null,
            ],
        ];

        foreach ($enums as $enum) {
            DynamicEnum::updateOrCreate(
                [
                    'name'       => $enum['name'],
                    'applies_to' => $enum['applies_to'],
                    'school_id'  => $enum['school_id'],
                ],
                $enum
            );
        }
    }
}
