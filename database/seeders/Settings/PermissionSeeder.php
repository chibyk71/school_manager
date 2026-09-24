<?php
// database/seeders/Settings/PermissionSeeder.php

namespace Database\Seeders\Settings;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Seeder for creating initial permissions in the system.
 * Permissions are global (not school-specific) for MVP, but can be assigned to school-scoped roles.
 * Use Laratrust to attach these to roles.
 */
class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Grouped permissions for better organization and readability
        $permissions = [

            // Dashboard
            ['name' => 'dashboard.view', 'display_name' => 'View Dashboard', 'description' => 'Access the main dashboard'],
            ['name' => 'dashboard.edit', 'display_name' => 'Edit Dashboard', 'description' => 'Edit dashboard widgets'],

            // Your single settings permission
            ['name' => 'settings.manage', 'display_name' => 'Manage Settings', 'description' => 'Full access to all settings pages'],

            // dynamic enums (Phase 4)
            ['name' => 'dynamic-enums.view', 'display_name' => 'View Dynamic Enums', 'description' => 'View Dynamic Enum definitions and effective configuration'],
            ['name' => 'dynamic-enums.manage', 'display_name' => 'Manage School Dynamic Enums', 'description' => 'Manage school-level Dynamic Enum overrides and school-only options'],
            ['name' => 'dynamic-enums.manageGlobals', 'display_name' => 'Manage Tenant Dynamic Enums', 'description' => 'Manage tenant/default Dynamic Enum configuration and requiredness'],

            // Schools (Tenant Management)
            ['name' => 'schools.view-any', 'display_name' => 'View All Schools', 'description' => 'Access the list of schools'],
            ['name' => 'schools.view', 'display_name' => 'View School', 'description' => 'Access The detail of individual school'],
            ['name' => 'school.create', 'display_name' => 'Create School', 'description' => 'Create a new school'],
            ['name' => 'school.update', 'display_name' => 'Update School', 'description' => 'Update an existing school'],
            ['name' => 'school.delete', 'display_name' => 'Delete School', 'description' => 'Delete a school'],
            ['name' => 'school.forceDelete', 'display_name' => 'Force Delete School', 'description' => 'Force delete a school'],
            ['name' => 'school.restore', 'display_name' => 'Restore School', 'description' => 'Restore a soft-deleted school'],

            // School Sections
            ['name' => 'school-sections.view-any', 'display_name' => 'View All School Sections', 'description' => 'Can see the list of all school sections/divisions in the current school'],
            ['name' => 'school-sections.view', 'display_name' => 'View School Section Details', 'description' => 'Can view detailed information about a specific school section/division'],
            ['name' => 'school-sections.create', 'display_name' => 'Create School Section', 'description' => 'Can add a new school section/division (e.g. Primary, Junior Secondary)'],
            ['name' => 'school-sections.update', 'display_name' => 'Edit School Section', 'description' => 'Can modify existing school sections/divisions'],
            ['name' => 'school-sections.delete', 'display_name' => 'Delete School Section', 'description' => 'Can soft-delete (move to trash) a school section/division'],
            ['name' => 'school-sections.restore', 'display_name' => 'Restore School Section', 'description' => 'Can restore a soft-deleted school section/division from trash'],
            ['name' => 'school-sections.force-delete', 'display_name' => 'Permanently Delete School Section', 'description' => 'Can permanently delete a school section (usually restricted to super-admins)'],

            // User Account Management (Beyond Staff/Student/Guardian Data)
            ['name' => 'user.assign-roles', 'display_name' => 'Assign Roles to User', 'description' => 'Grant or revoke roles and permissions for any user within scope.'],
            ['name' => 'user.change-status', 'display_name' => 'Activate/Deactivate User Account', 'description' => 'Enable or disable a user\'s ability to log in to the system.'],

            // Security & Access
            ['name' => 'user.reset-password-any', 'display_name' => 'Reset Any User\'s Password', 'description' => 'Force a password reset for any user account.'],
            ['name' => 'user.impersonate', 'display_name' => 'Impersonate User', 'description' => 'Log in as another user for troubleshooting and support purposes. (Highly sensitive)'],

            // Cross-Entity Linking (Handles the complex relationships)
            ['name' => 'user.link-guardian-student', 'display_name' => 'Link Guardian to Student', 'description' => 'Create or manage the official relationship between a Guardian user and one or more Students.'],

            // Students
            ['name' => 'student.view-any', 'display_name' => 'View All Students', 'description' => 'View list of all students in a school'],
            ['name' => 'student.view', 'display_name' => 'View Student', 'description' => 'View individual student details'],
            ['name' => 'student.create', 'display_name' => 'Create Student', 'description' => 'Create new students'],
            ['name' => 'student.update', 'display_name' => 'Update Student', 'description' => 'Update student information'],
            ['name' => 'student.delete', 'display_name' => 'Delete Student', 'description' => 'Soft delete students'],
            ['name' => 'student.restore', 'display_name' => 'Restore Student', 'description' => 'Restore deleted students'],
            ['name' => 'student.force-delete', 'display_name' => 'Force Delete Student', 'description' => 'Permanently delete students'],

            // Staff
            ['name' => 'staff.view-any', 'display_name' => 'View All Staff', 'description' => 'View all staff in a school'],
            ['name' => 'staff.view', 'display_name' => 'View Staff', 'description' => 'View individual staff details'],
            ['name' => 'staff.create', 'display_name' => 'Create Staff', 'description' => 'Create new staff'],
            ['name' => 'staff.update', 'display_name' => 'Update Staff', 'description' => 'Update staff information'],
            ['name' => 'staff.delete', 'display_name' => 'Delete Staff', 'description' => 'Soft delete staff'],
            ['name' => 'staff.restore', 'display_name' => 'Restore Staff', 'description' => 'Restore deleted staff'],
            ['name' => 'staff.force-delete', 'display_name' => 'Force Delete Staff', 'description' => 'Permanently delete staff'],

            // Guardians
            ['name' => 'guardian.view-any', 'display_name' => 'View All Guardians', 'description' => 'View list of all guardians in a school'],
            ['name' => 'guardian.view', 'display_name' => 'View Guardian', 'description' => 'View individual guardian details'],
            ['name' => 'guardian.create', 'display_name' => 'Create Guardian', 'description' => 'Create new guardians'],
            ['name' => 'guardian.update', 'display_name' => 'Update Guardian', 'description' => 'Update guardian information'],
            ['name' => 'guardian.delete', 'display_name' => 'Delete Guardian', 'description' => 'Soft delete guardians'],
            ['name' => 'guardian.restore', 'display_name' => 'Restore Guardian', 'description' => 'Restore deleted guardians'],
            ['name' => 'guardian.force-delete', 'display_name' => 'Force Delete Guardian', 'description' => 'Permanently delete guardians'],

            ['name' => 'profile.view-any', 'display_name' => 'View All Profiles', 'description' => 'View the list of all profiles (admin-level access)',],
            ['name' => 'profile.view', 'display_name' => 'View Profile', 'description' => 'View a specific profile (own profile or permitted others)',],
            ['name' => 'profile.update-own', 'display_name' => 'Update Own Profile', 'description' => 'Edit personal profile information (name, phone, photo, etc.)',],
            ['name' => 'profile.update-any', 'display_name' => 'Update Any Profile', 'description' => 'Edit any user\'s profile (admin override)',],
            ['name' => 'profile.avatar.upload-own', 'display_name' => 'Upload Own Avatar', 'description' => 'Change own profile photo/avatar',],
            ['name' => 'profile.avatar.upload-any', 'display_name' => 'Upload Avatar for Any Profile', 'description' => 'Change avatar/photo for any user (admin)',],
            ['name' => 'profile.delete-any', 'display_name' => 'Delete Any Profile', 'description' => 'Soft-delete any profile (admin action)',],
            ['name' => 'profile.force-delete', 'display_name' => 'Force Delete Profile', 'description' => 'Permanently delete a profile (super-admin only, bypass soft-delete)',],
            ['name' => 'profile.restore', 'display_name' => 'Restore Profile', 'description' => 'Restore a soft-deleted profile (admin)',],
            ['name' => 'profile.create-login', 'display_name' => 'Create Login for Profile', 'description' => 'Create a User/login account for an existing profile',],
            ['name' => 'profile.reset-password', 'display_name' => 'Reset Password', 'description' => 'Force reset password or initiate reset for any profile',],
            ['name' => 'profile.toggle-status', 'display_name' => 'Toggle Profile Status', 'description' => 'Activate/deactivate a profile (admin)',],
            ['name' => 'profile.merge', 'display_name' => 'Merge Profiles', 'description' => 'Merge duplicate profiles and move associated roles',],

            // Grades (Grading Scales)
            [
                'name' => 'grades.view-any',
                'display_name' => 'View All Grades',
                'description' => 'Access the full list of grading scales / grades in the system',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'grades.view',
                'display_name' => 'View Grade Details',
                'description' => 'View detailed information about a specific grade (including score ranges and assigned sections)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'grades.create',
                'display_name' => 'Create New Grade',
                'description' => 'Create a new grading scale entry (define name, code, score range, remark, and assign sections)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'grades.update',
                'display_name' => 'Update Grade',
                'description' => 'Edit an existing grade\'s details (name, code, score range, remark, section assignments)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'grades.delete',
                'display_name' => 'Delete Grade',
                'description' => 'Soft-delete a grade (only allowed if not currently used in any student results/assessments)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'grades.restore',
                'display_name' => 'Restore Deleted Grade',
                'description' => 'Restore a previously soft-deleted grade back to active status',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'grades.force-delete',
                'display_name' => 'Permanently Delete Grade',
                'description' => 'Force delete (permanently remove) a grade from the system (restricted to super-admins)',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Class Sections
            ['name' => 'class-sections.view-any', 'display_name' => 'View Any Class Section', 'description' => 'View the list and data table of class sections'],
            ['name' => 'class-sections.view', 'display_name' => 'View Class Section', 'description' => 'View detailed information for a specific class section'],
            ['name' => 'class-sections.create', 'display_name' => 'Create Class Section', 'description' => 'Manually create a new class section'],
            ['name' => 'class-sections.update', 'display_name' => 'Update Class Section', 'description' => 'Edit the details of an existing class section'],
            ['name' => 'class-sections.delete', 'display_name' => 'Delete Class Section', 'description' => 'Soft-delete a class section record'],
            ['name' => 'class-sections.restore', 'display_name' => 'Restore Class Section', 'description' => 'Restore a previously trashed class section'],
            ['name' => 'class-sections.force-delete', 'display_name' => 'Force Delete Class Section', 'description' => 'Permanently delete a class section from the database'],
            ['name' => 'class-sections.bulk-generate', 'display_name' => 'Bulk Generate Class Sections', 'description' => 'Generate multiple class arms or sections at once'],
            ['name' => 'class-sections.assign-teacher', 'display_name' => 'Assign Teacher', 'description' => 'Set or change the form teacher for a class section'],
            ['name' => 'class-sections.manage-subjects', 'display_name' => 'Manage Subject Assignments', 'description' => 'Assign or manage subjects for a class section'],
            ['name' => 'class-sections.reorder', 'display_name' => 'Reorder Class Sections', 'description' => 'Change the display sort order of class sections'],
            ['name' => 'class-sections.toggle-status', 'display_name' => 'Toggle Class Status', 'description' => 'Activate or deactivate a class section'],

            // ── Class Levels ──────────────────────────────────────────────────────────
            ['name' => 'class-levels.view', 'display_name' => 'View Class Levels', 'description' => 'View all class levels within a school section'],
            ['name' => 'class-levels.create', 'display_name' => 'Create Class Levels', 'description' => 'Create new class levels within a school section'],
            ['name' => 'class-levels.update', 'display_name' => 'Update Class Levels', 'description' => 'Edit and update existing class levels'],
            ['name' => 'class-levels.delete', 'display_name' => 'Delete Class Levels', 'description' => 'Soft-delete class levels from a school section'],
            ['name' => 'class-levels.restore', 'display_name' => 'Restore Class Levels', 'description' => 'Restore soft-deleted class levels from trash'],
            ['name' => 'class-levels.force-delete', 'display_name' => 'Permanently Delete Class Levels', 'description' => 'Permanently delete soft-deleted class levels — irreversible'],


            // Subjects
            ['name' => 'subjects.view', 'display_name' => 'View Subjects', 'description' => 'View all subjects for a school'],
            ['name' => 'subjects.create', 'display_name' => 'Create Subjects', 'description' => 'Create new subjects for a school'],
            ['name' => 'subjects.update', 'display_name' => 'Update Subjects', 'description' => 'Update existing subjects for a school'],
            ['name' => 'subjects.delete', 'display_name' => 'Delete Subjects', 'description' => 'Delete subjects for a school'],
            ['name' => 'subjects.restore', 'display_name' => 'Restore Subjects', 'description' => 'Restore deleted subjects for a school'],
            ['name' => 'subjects.force-delete', 'display_name' => 'Force Delete Subjects', 'description' => 'Permanently delete subjects for a school'],

            // Teacher Assignments
            ['name' => 'teacher-assignments.view', 'display_name' => 'View Teacher Assignments', 'description' => 'View all teacher assignments for a school'],
            ['name' => 'teacher-assignments.create', 'display_name' => 'Create Teacher Assignments', 'description' => 'Create new teacher assignments for a school'],
            ['name' => 'teacher-assignments.update', 'display_name' => 'Update Teacher Assignments', 'description' => 'Update existing teacher assignments for a school'],
            ['name' => 'teacher-assignments.delete', 'display_name' => 'Delete Teacher Assignments', 'description' => 'Delete teacher assignments for a school'],
            ['name' => 'teacher-assignments.restore', 'display_name' => 'Restore Teacher Assignments', 'description' => 'Restore deleted teacher assignments for a school'],
            ['name' => 'teacher-assignments.force-delete', 'display_name' => 'Force Delete Teacher Assignments', 'description' => 'Permanently delete teacher assignments for a school'],

            // Terms
            ['name' => 'terms.view', 'display_name' => 'View Terms', 'description' => 'View all terms for a school'],
            ['name' => 'terms.create', 'display_name' => 'Create Terms', 'description' => 'Create new terms for a school'],
            ['name' => 'terms.update', 'display_name' => 'Update Terms', 'description' => 'Update existing terms for a school'],
            ['name' => 'terms.delete', 'display_name' => 'Delete Terms', 'description' => 'Delete terms for a school'],
            ['name' => 'terms.restore', 'display_name' => 'Restore Terms', 'description' => 'Restore deleted terms for a school'],
            ['name' => 'terms.force-delete', 'display_name' => 'Force Delete Terms', 'description' => 'Permanently delete terms for a school'],

            // Academic Sessions permissions (full CRUD + lifecycle)
            ['name' => 'academic-sessions.view', 'display_name' => 'View Academic Sessions', 'description' => 'View all academic sessions for a school'],
            ['name' => 'academic-sessions.create', 'display_name' => 'Create Academic Sessions', 'description' => 'Create new academic sessions for a school'],
            ['name' => 'academic-sessions.update', 'display_name' => 'Update Academic Sessions', 'description' => 'Update existing academic sessions for a school'],
            ['name' => 'academic-sessions.delete', 'display_name' => 'Delete Academic Sessions', 'description' => 'Delete academic sessions for a school'],
            ['name' => 'academic-sessions.restore', 'display_name' => 'Restore Academic Sessions', 'description' => 'Restore deleted academic sessions for a school'],
            ['name' => 'academic-sessions.force-delete', 'display_name' => 'Force Delete Academic Sessions', 'description' => 'Permanently delete academic sessions for a school'],

            // Specialized lifecycle permissions (highly recommended for separation of duties)
            ['name' => 'academic-sessions.activate', 'display_name' => 'Activate Academic Sessions', 'description' => 'Activate academic sessions (make current/active)'],
            ['name' => 'academic-sessions.close', 'display_name' => 'Close Academic Sessions', 'description' => 'Close academic sessions'],

            // Term lifecycle permissions (already discussed, added here for completeness)
            ['name' => 'terms.close', 'display_name' => 'Close Terms', 'description' => 'Close active terms'],
            ['name' => 'terms.reopen', 'display_name' => 'Reopen Terms', 'description' => 'Reopen closed terms'],

            // Custom Fields
            ['name' => 'custom-fields.viewAny', 'display_name' => 'View Any Custom Field', 'description' => 'View list of custom fields'],
            ['name' => 'custom-fields.view', 'display_name' => 'View Custom Field', 'description' => 'View a custom field'],
            ['name' => 'custom-fields.create', 'display_name' => 'Create Custom Field', 'description' => 'Create custom fields'],
            ['name' => 'custom-fields.update', 'display_name' => 'Update Custom Field', 'description' => 'Update custom fields'],
            ['name' => 'custom-fields.delete', 'display_name' => 'Delete Custom Field', 'description' => 'Delete custom fields'],
            ['name' => 'custom-fields.restore', 'display_name' => 'Restore Custom Field', 'description' => 'Restore soft-deleted custom fields'],
            ['name' => 'custom-fields.forceDelete', 'display_name' => 'Force Delete Custom Field', 'description' => 'Permanently delete custom fields'],
            ['name' => 'custom-fields.reorder', 'display_name' => 'Reorder Custom Fields', 'description' => 'Reorder custom fields'],
            ['name' => 'custom-fields.apply-preset', 'display_name' => 'Apply Custom Field Preset', 'description' => 'Apply custom field presets'],

            // Timetable
            ['name' => 'timetable.view-any', 'display_name' => 'View All Timetables', 'description' => 'View all timetables for a school'],
            ['name' => 'timetable.view', 'display_name' => 'View Timetable', 'description' => 'View a specific timetable'],
            ['name' => 'timetable.create', 'display_name' => 'Create Timetable', 'description' => 'Create timetables'],
            ['name' => 'timetable.update', 'display_name' => 'Update Timetable', 'description' => 'Update timetables'],
            ['name' => 'timetable.delete', 'display_name' => 'Delete Timetable', 'description' => 'Delete timetables'],
            ['name' => 'timetable.restore', 'display_name' => 'Restore Timetable', 'description' => 'Restore soft-deleted timetables'],
            ['name' => 'timetable.force-delete', 'display_name' => 'Force Delete Timetable', 'description' => 'Permanently delete timetables'],

            // Timetable Details
            ['name' => 'timetable-detail.view-any', 'display_name' => 'View All Timetable Details', 'description' => 'View all timetable details'],
            ['name' => 'timetable-detail.view', 'display_name' => 'View Timetable Detail', 'description' => 'View a timetable detail'],
            ['name' => 'timetable-detail.create', 'display_name' => 'Create Timetable Detail', 'description' => 'Create timetable details'],
            ['name' => 'timetable-detail.update', 'display_name' => 'Update Timetable Detail', 'description' => 'Update timetable details'],
            ['name' => 'timetable-detail.delete', 'display_name' => 'Delete Timetable Detail', 'description' => 'Delete timetable details'],
            ['name' => 'timetable-detail.restore', 'display_name' => 'Restore Timetable Detail', 'description' => 'Restore soft-deleted timetable details'],
            ['name' => 'timetable-detail.force-delete', 'display_name' => 'Force Delete Timetable Detail', 'description' => 'Permanently delete timetable details'],

            // Departments
            ['name' => 'department.view-any', 'display_name' => 'View All Departments', 'description' => 'View all departments'],
            ['name' => 'department.view', 'display_name' => 'View Department', 'description' => 'View a department'],
            ['name' => 'department.create', 'display_name' => 'Create Department', 'description' => 'Create departments'],
            ['name' => 'department.update', 'display_name' => 'Update Department', 'description' => 'Update departments'],
            ['name' => 'department.delete', 'display_name' => 'Delete Department', 'description' => 'Delete departments'],
            ['name' => 'department.restore', 'display_name' => 'Restore Department', 'description' => 'Restore soft-deleted departments'],
            ['name' => 'department.force-delete', 'display_name' => 'Force Delete Department', 'description' => 'Permanently delete departments'],

            // Notices
            ['name' => 'notice.view-any', 'display_name' => 'View All Notices', 'description' => 'View all notices'],
            ['name' => 'notice.view', 'display_name' => 'View Notice', 'description' => 'View a notice'],
            ['name' => 'notice.create', 'display_name' => 'Create Notice', 'description' => 'Create notices'],
            ['name' => 'notice.update', 'display_name' => 'Update Notice', 'description' => 'Update notices'],
            ['name' => 'notice.delete', 'display_name' => 'Delete Notice', 'description' => 'Delete notices'],
            ['name' => 'notice.restore', 'display_name' => 'Restore Notice', 'description' => 'Restore soft-deleted notices'],
            ['name' => 'notice.force-delete', 'display_name' => 'Force Delete Notice', 'description' => 'Permanently delete notices'],

            // Routes
            ['name' => 'route.view-any', 'display_name' => 'View All Routes', 'description' => 'View all transport routes'],
            ['name' => 'route.view', 'display_name' => 'View Route', 'description' => 'View a transport route'],
            ['name' => 'route.create', 'display_name' => 'Create Route', 'description' => 'Create transport routes'],
            ['name' => 'route.update', 'display_name' => 'Update Route', 'description' => 'Update transport routes'],
            ['name' => 'route.delete', 'display_name' => 'Delete Route', 'description' => 'Delete transport routes'],
            ['name' => 'route.restore', 'display_name' => 'Restore Route', 'description' => 'Restore soft-deleted routes'],
            ['name' => 'route.force-delete', 'display_name' => 'Force Delete Route', 'description' => 'Permanently delete routes'],

            // Vehicles
            ['name' => 'vehicle.view-any', 'display_name' => 'View All Vehicles', 'description' => 'View all vehicles'],
            ['name' => 'vehicle.view', 'display_name' => 'View Vehicle', 'description' => 'View a vehicle'],
            ['name' => 'vehicle.create', 'display_name' => 'Create Vehicle', 'description' => 'Create vehicles'],
            ['name' => 'vehicle.update', 'display_name' => 'Update Vehicle', 'description' => 'Update vehicles'],
            ['name' => 'vehicle.delete', 'display_name' => 'Delete Vehicle', 'description' => 'Delete vehicles'],
            ['name' => 'vehicle.restore', 'display_name' => 'Restore Vehicle', 'description' => 'Restore soft-deleted vehicles'],
            ['name' => 'vehicle.force-delete', 'display_name' => 'Force Delete Vehicle', 'description' => 'Permanently delete vehicles'],

            // Vehicle Documents
            ['name' => 'vehicle-document.view-any', 'display_name' => 'View All Vehicle Documents', 'description' => 'View all vehicle documents'],
            ['name' => 'vehicle-document.view', 'display_name' => 'View Vehicle Document', 'description' => 'View a vehicle document'],
            ['name' => 'vehicle-document.create', 'display_name' => 'Create Vehicle Document', 'description' => 'Create vehicle documents'],
            ['name' => 'vehicle-document.update', 'display_name' => 'Update Vehicle Document', 'description' => 'Update vehicle documents'],
            ['name' => 'vehicle-document.delete', 'display_name' => 'Delete Vehicle Document', 'description' => 'Delete vehicle documents'],
            ['name' => 'vehicle-document.restore', 'display_name' => 'Restore Vehicle Document', 'description' => 'Restore soft-deleted vehicle documents'],
            ['name' => 'vehicle-document.force-delete', 'display_name' => 'Force Delete Vehicle Document', 'description' => 'Permanently delete vehicle documents'],

            // Vehicle Expenses
            ['name' => 'vehicle-expense.view-any', 'display_name' => 'View All Vehicle Expenses', 'description' => 'View all vehicle expenses'],
            ['name' => 'vehicle-expense.view', 'display_name' => 'View Vehicle Expense', 'description' => 'View a vehicle expense'],
            ['name' => 'vehicle-expense.create', 'display_name' => 'Create Vehicle Expense', 'description' => 'Create vehicle expenses'],
            ['name' => 'vehicle-expense.update', 'display_name' => 'Update Vehicle Expense', 'description' => 'Update vehicle expenses'],
            ['name' => 'vehicle-expense.delete', 'display_name' => 'Delete Vehicle Expense', 'description' => 'Delete vehicle expenses'],
            ['name' => 'vehicle-expense.restore', 'display_name' => 'Restore Vehicle Expense', 'description' => 'Restore soft-deleted vehicle expenses'],
            ['name' => 'vehicle-expense.force-delete', 'display_name' => 'Force Delete Vehicle Expense', 'description' => 'Permanently delete vehicle expenses'],

            // Hostels
            ['name' => 'hostel.view-any', 'display_name' => 'View All Hostels', 'description' => 'Allows the user to view all hostels for a school.'],
            ['name' => 'hostel.view', 'display_name' => 'View Hostel', 'description' => 'Allows the user to view a specific hostel for a school.'],
            ['name' => 'hostel.create', 'display_name' => 'Create Hostels', 'description' => 'Allows the user to create new hostels for a school.'],
            ['name' => 'hostel.update', 'display_name' => 'Update Hostels', 'description' => 'Allows the user to update existing hostels for a school.'],
            ['name' => 'hostel.delete', 'display_name' => 'Delete Hostels', 'description' => 'Allows the user to delete hostels for a school.'],
            ['name' => 'hostel.restore', 'display_name' => 'Restore Hostels', 'description' => 'Allows the user to restore soft-deleted hostels for a school.'],
            ['name' => 'hostel.force-delete', 'display_name' => 'Permanently Delete Hostels', 'description' => 'Allows the user to permanently delete hostels for a school.'],

            // Hostel Rooms
            ['name' => 'hostel-room.view-any', 'display_name' => 'View All Hostel Rooms', 'description' => 'Allows the user to view all hostel rooms for a school.'],
            ['name' => 'hostel-room.view', 'display_name' => 'View Hostel Room', 'description' => 'Allows the user to view a specific hostel room for a school.'],
            ['name' => 'hostel-room.create', 'display_name' => 'Create Hostel Rooms', 'description' => 'Allows the user to create new hostel rooms for a school.'],
            ['name' => 'hostel-room.update', 'display_name' => 'Update Hostel Rooms', 'description' => 'Allows the user to update existing hostel rooms for a school.'],
            ['name' => 'hostel-room.delete', 'display_name' => 'Delete Hostel Rooms', 'description' => 'Allows the user to delete hostel rooms for a school.'],
            ['name' => 'hostel-room.restore', 'display_name' => 'Restore Hostel Rooms', 'description' => 'Allows the user to restore soft-deleted hostel rooms for a school.'],
            ['name' => 'hostel-room.force-delete', 'display_name' => 'Permanently Delete Hostel Rooms', 'description' => 'Allows the user to permanently delete hostel rooms for a school.'],

            // Hostel Assignments
            ['name' => 'hostel-assignment.view-any', 'display_name' => 'View All Hostel Assignments', 'description' => 'Allows the user to view all hostel assignments for a school.'],
            ['name' => 'hostel-assignment.view', 'display_name' => 'View Hostel Assignment', 'description' => 'Allows the user to view a specific hostel assignment for a school.'],
            ['name' => 'hostel-assignment.create', 'display_name' => 'Create Hostel Assignments', 'description' => 'Allows the user to create new hostel assignments for a school.'],
            ['name' => 'hostel-assignment.update', 'display_name' => 'Update Hostel Assignments', 'description' => 'Allows the user to update existing hostel assignments for a school.'],
            ['name' => 'hostel-assignment.delete', 'display_name' => 'Delete Hostel Assignments', 'description' => 'Allows the user to delete hostel assignments for a school.'],
            ['name' => 'hostel-assignment.restore', 'display_name' => 'Restore Hostel Assignments', 'description' => 'Allows the user to restore soft-deleted hostel assignments for a school.'],
            ['name' => 'hostel-assignment.force-delete', 'display_name' => 'Permanently Delete Hostel Assignments', 'description' => 'Allows the user to permanently delete hostel assignments for a school.'],

            // Finance Reports
            ['name' => 'finance-reports.view', 'display_name' => 'View Financial Reports', 'description' => 'View financial reporting dashboard for a school'],
        ];

        // Batch create to optimize database inserts
        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission['name']], $permission);
        }
    }
}
