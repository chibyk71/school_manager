<?php

namespace Database\Seeders;

use App\Models\Employee\Department;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         // Define the mapping of departments to roles
         $departmentRoles = [
            'Sciences' => ['teacher', 'hod', 'lab-assistant','class-teacher','assistant-teacher','subject-coordinator'],
            'Humanities' => ['teacher', 'hod','class-teacher','assistant-teacher','subject-coordinator'],
            'Languages' => ['teacher', 'hod','class-teacher','assistant-teacher','subject-coordinator'],
            'Vocational/Technical Studies' => ['teacher', 'hod', 'lab-assistant','class-teacher','assistant-teacher','subject-coordinator'],
            'Arts and Creative Studies' => ['teacher', 'hod', 'lab-assistant','class-teacher','assistant-teacher','subject-coordinator'],
            'Physical and Health Education' => ['teacher', 'hod','class-teacher','assistant-teacher','subject-coordinator'],
            'Administration' => ['principal', 'vice_principal_academic', 'vice_principal_admin', 'school_secretary','assistant_secretary','administrative_officer'],
            'Bursary/Accounts Department' => ['bursar', 'accountant', 'assistant_accountant', 'accounts_clerk'],
            'Guidance and Counseling Unit' => ['head_guidance_counseling', 'counselor'],
            'Examinations and Records Department' => ['examinations_officer', 'records_officer'],
            'Library Department' => ['librarian', 'assistant_librarian', 'library_assistant'],
            'ICT/MIS Department' => ['head_ict_mis', 'ict_officer', 'systems_administrator', 'it_technician'],
            'Student Affairs/Welfare Department' => ['head_student_affairs', 'welfare_officer', 'discipline_master'],
            'Boarding House Department' => ['boarding_house_master', 'assistant_boarding_master', 'warden', 'matron'],
            'Security Department' => ['head_security', 'security_officer', 'security_guard'],
            'Maintenance Department (Works & Services)' => ['head_maintenance', 'electrician', 'groundskeeper'],
            'Transport Department' => ['transport_officer', 'school_driver'],
            'Catering Department' => ['catering_manager', 'cook', 'kitchen_staff'],
            'Public Relations/Information Department' => ['public_relations_officer', 'information_officer'],
        ];

        // Clear the pivot table before seeding
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('department_role')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        foreach ($departmentRoles as $departmentName => $roles) {
            // Find the department by name
            $department = Department::withoutFallback()->where('name', $departmentName)->first();

            if ($department) {
                foreach ($roles as $roleName) {
                    // Find the role by name
                    $role = Role::where('name', $roleName)->first();

                    if ($role) {
                        // Attach the role to the department
                        DB::table('department_role')->insert([
                            'department_id' => $department->id,
                            'role_id' => $role->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }
}
