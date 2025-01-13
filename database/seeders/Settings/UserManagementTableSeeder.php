<?php

namespace Database\Seeders\Settings;

use Illuminate\Database\Seeder;
use RuangDeveloper\LaravelSettings\Facades\Settings;

class UserManagementTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define default user management settings
        $defaultSettings = [
            'online_admission' => true,
            'allow_student_signin' => true,
            'allow_parent_signin' => true,
            'allow_teacher_signin' => true,
            'allow_staff_signin' => true,
            'online_admission_fee' => 50.00,
            'online_admission_instruction' => 'Please follow the instructions to complete your admission process.'
        ];

        // Save the default settings to the database
        Settings::set('user_management', $defaultSettings);
    }
}