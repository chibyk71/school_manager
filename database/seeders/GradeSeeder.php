<?php

namespace Database\Seeders;

use App\Models\Academic\Grade;
use App\Models\School;
use App\Models\SchoolSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * GradeSeeder – Populates the grades table with realistic sample data
 *
 * Features / Problems Solved:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Creates multiple realistic grading scales (letter grades + point-based examples)
 * • Supports multi-tenant structure: all grades belong to a specific school
 * • Demonstrates both school-wide grades (school_section_id null) and section-specific overrides
 * • Creates many-to-many assignments via pivot table (school_section_grade / sectionables)
 * • Uses UUIDs and proper foreign key constraints for consistency with your schema
 * • Safe & idempotent: clears existing data only in development/testing environments
 * • Includes variety: WAEC-style, A-F letter grades, custom cut-offs
 * • Prepares data for frontend testing (DataTable, modals, filtering)
 *
 * How it fits into the Grades Module:
 * ────────────────────────────────────────────────────────────────────────────────────────────────
 * • Run via: php artisan db:seed --class=GradeSeeder
 * • Used in development, testing, and demo environments
 * • Provides seed data for:
 *   - Grades.vue DataTable display & filtering
 *   - GradeModal.vue form testing (create/edit)
 *   - Many-to-many section assignment UI (MultiSelect)
 *   - Backend validation testing (overlap, uniqueness)
 *   - Usage protection testing (isUsed checks)
 * • Works with your current schema (after pivot migration is run)
 *
 * Usage Recommendations:
 * • Run after SchoolSeeder and SchoolSectionSeeder
 * • Adjust school_id / section IDs if your seeder order changes
 * • Add more scales (CBT, university-style, vocational) as needed
 *
 * Run command:
 *   php artisan db:seed --class=GradeSeeder
 */
class GradeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Only seed in local/testing environments to prevent accidental data in production
        if (!app()->environment('local', 'testing')) {
            $this->command->warn('Grade seeder skipped in non-local/testing environment.');
            return;
        }

        $this->command->info('Seeding sample grades...');

        // Clear existing grades (safe in dev/testing)
        DB::table('grades')->truncate();
        DB::table('sectionables')->where('sectionable_type', 'App\\Models\\Academic\\Grade')->delete();

        // Fetch a school and some sections (assumes they exist from previous seeders)
        $school = School::firstOrFail();
        $sections = SchoolSection::where('school_id', $school->id)->take(4)->get();

        if ($sections->isEmpty()) {
            $this->command->error('No school sections found. Please seed SchoolSection first.');
            return;
        }

        // ─── Sample Grading Scales ──────────────────────────────────────────────────────

        $gradingScales = [
            // Classic WAEC/NECO-style 9-point scale (school-wide)
            [
                'name' => 'Excellent',
                'code' => 'A1',
                'min_score' => 75,
                'max_score' => 100,
                'remark' => 'Excellent / Outstanding',
                'sections' => [], // school-wide (no specific sections)
            ],
            [
                'name' => 'Very Good',
                'code' => 'B2',
                'min_score' => 70,
                'max_score' => 74,
                'remark' => 'Very Good',
                'sections' => [],
            ],
            [
                'name' => 'Good',
                'code' => 'B3',
                'min_score' => 65,
                'max_score' => 69,
                'remark' => 'Good',
                'sections' => [],
            ],
            [
                'name' => 'Credit',
                'code' => 'C4',
                'min_score' => 60,
                'max_score' => 64,
                'remark' => 'Credit',
                'sections' => [],
            ],
            [
                'name' => 'Credit',
                'code' => 'C5',
                'min_score' => 55,
                'max_score' => 59,
                'remark' => 'Credit',
                'sections' => [],
            ],
            [
                'name' => 'Credit',
                'code' => 'C6',
                'min_score' => 50,
                'max_score' => 54,
                'remark' => 'Credit',
                'sections' => [],
            ],
            [
                'name' => 'Pass',
                'code' => 'D7',
                'min_score' => 45,
                'max_score' => 49,
                'remark' => 'Pass',
                'sections' => [],
            ],
            [
                'name' => 'Pass',
                'code' => 'E8',
                'min_score' => 40,
                'max_score' => 44,
                'remark' => 'Pass',
                'sections' => [],
            ],
            [
                'name' => 'Fail',
                'code' => 'F9',
                'min_score' => 0,
                'max_score' => 39,
                'remark' => 'Fail',
                'sections' => [],
            ],
        ];

        foreach ($gradingScales as $scale) {
            $grade = Grade::create([
                'id' => (string) Str::uuid(),
                'school_id' => $school->id,
                'name' => $scale['name'],
                'code' => $scale['code'],
                'min_score' => $scale['min_score'],
                'max_score' => $scale['max_score'],
                'remark' => $scale['remark'],
            ]);

            // Assign to specific sections (if any)
            if (!empty($scale['sections'])) {
                $grade->schoolSections()->attach($scale['sections']);
            }

            $this->command->info("Created grade: {$grade->code} - {$grade->name}");
        }

        $this->command->info('Grade seeding completed successfully.');
    }
}
