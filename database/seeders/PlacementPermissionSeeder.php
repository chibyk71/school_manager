<?php
namespace Database\Seeders;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class PlacementPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'placements.view', 'display_name' => 'View Placements', 'description' => 'View student placements and history'],
            ['name' => 'placements.manage', 'display_name' => 'Manage Placements', 'description' => 'Manually place students into sections'],
            ['name' => 'placements.capacity_override', 'display_name' => 'Override Section Capacity', 'description' => 'Place students into full sections with explicit override'],
            ['name' => 'placements.regenerate_registration_number', 'display_name' => 'Regenerate Registration Numbers', 'description' => 'Explicitly regenerate student registration numbers'],
            ['name' => 'placements.change_section', 'display_name' => 'Change Student Section', 'description' => 'Move an active student between sections in the same class level'],
            ['name' => 'placements.change_class', 'display_name' => 'Change Student Class', 'description' => 'Administrative class-level move (not formal promotion)'],
            ['name' => 'students.transfer', 'display_name' => 'Transfer Students', 'description' => 'Transfer a student out of the school'],
            ['name' => 'students.withdraw', 'display_name' => 'Withdraw Students', 'description' => 'Withdraw a student from active enrollment'],
            ['name' => 'students.change-status', 'display_name' => 'Change Student Status', 'description' => 'Change student lifecycle status'],
        ] as $perm) {
            Permission::firstOrCreate(['name' => $perm['name']], $perm);
        }
    }
}
