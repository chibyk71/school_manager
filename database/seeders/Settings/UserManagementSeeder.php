<?php

namespace Database\Seeders\Settings;

use Illuminate\Database\Seeder;
use RuangDeveloper\LaravelSettings\Facades\Settings;

/**
 * Seed **user_management** defaults.
 *
 * These defaults are safe for a typical Nigerian secondary/primary school
 * and can be overridden per-tenant later.
 */
class UserManagementSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // ── Admission ───────────────────────────────────────
            'online_admission'               => true,
            'online_admission_fee'           => 500.00,               // ₦500
            'online_admission_instruction'   => 'Complete the form, upload required documents and pay the admission fee online.',

            // ── Sign-in permissions ───────────────────────────────
            'allow_student_signin'           => false,                // students do **not** log in directly
            'allow_parent_signin'            => true,
            'allow_teacher_signin'           => true,
            'allow_staff_signin'             => true,

            // ── Enrollment ID (e.g. SCH001-2025) ─────────────────
            'enrollment_id_format'           => '{prefix}-{year}-{number}',
            'enrollment_id_number_length'    => 6,

            // ── Guardian rules ───────────────────────────────────
            'require_guardian_email'         => true,
            'max_guardian_students'          => 5,

            // ── Bulk & custom fields ─────────────────────────────
            'allow_bulk_user_creation'       => true,
        ];

        Settings::set('user_management', $defaults);

        \Log::info('UserManagementSeeder completed', $defaults);
    }
}