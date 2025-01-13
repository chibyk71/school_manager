<?php

namespace Database\Seeders\Settings;

use Illuminate\Database\Seeder;
use RuangDeveloper\LaravelSettings\Facades\Settings;

class FeesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define default fees settings
        $defaultSettings = [
            'allow_offline_payment' => true,
            'offline_bank_payment_instruction' => 'Please transfer to bank account XYZ.',
            'lock_student_panel' => true,
            'print_fees_receipt_for' => ['office', 'student', 'bank'],
            'single_page' => true
        ];

        // Save the default settings to the database
        Settings::set('fees', $defaultSettings);
    }
}