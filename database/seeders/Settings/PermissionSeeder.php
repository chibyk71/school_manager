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

            // Schools (Tenant Management)
            ['name' => 'view-schools', 'display_name' => 'View Schools', 'description' => 'Access the list of schools'],
            ['name' => 'create-school', 'display_name' => 'Create School', 'description' => 'Create a new school'],
            ['name' => 'update-school', 'display_name' => 'Update School', 'description' => 'Update an existing school'],
            ['name' => 'delete-school', 'display_name' => 'Delete School', 'description' => 'Delete a school'],

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

            ['name' => 'profile.view-any', 'display_name' => 'View All Profiles', 'description' => 'View list of all profiles (admin)'],
            ['name' => 'profile.update-any', 'display_name' => 'Update Any Profile', 'description' => 'Edit any user profile (admin override)'],
            ['name' => 'profile.merge', 'display_name' => 'Merge Profiles', 'description' => 'Merge duplicate user accounts'],

            // Class Sections
            ['name' => 'class-sections.view', 'display_name' => 'View Class Sections', 'description' => 'View all class sections for a school'],
            ['name' => 'class-sections.create', 'display_name' => 'Create Class Sections', 'description' => 'Create new class sections for a school'],
            ['name' => 'class-sections.update', 'display_name' => 'Update Class Sections', 'description' => 'Update existing class sections for a school'],
            ['name' => 'class-sections.delete', 'display_name' => 'Delete Class Sections', 'description' => 'Delete class sections for a school'],
            ['name' => 'class-sections.restore', 'display_name' => 'Restore Class Sections', 'description' => 'Restore deleted class sections for a school'],
            ['name' => 'class-sections.force-delete', 'display_name' => 'Force Delete Class Sections', 'description' => 'Permanently delete class sections for a school'],

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

            // Timetables
            ['name' => 'timetables.view', 'display_name' => 'View Timetables', 'description' => 'View all timetables for a school'],
            ['name' => 'timetables.create', 'display_name' => 'Create Timetables', 'description' => 'Create new timetables for a school'],
            ['name' => 'timetables.update', 'display_name' => 'Update Timetables', 'description' => 'Update existing timetables for a school'],
            ['name' => 'timetables.delete', 'display_name' => 'Delete Timetables', 'description' => 'Delete timetables for a school'],
            ['name' => 'timetables.restore', 'display_name' => 'Restore Timetables', 'description' => 'Restore deleted timetables for a school'],
            ['name' => 'timetables.force-delete', 'display_name' => 'Force Delete Timetables', 'description' => 'Permanently delete timetables for a school'],

            // Timetable Details
            ['name' => 'timetable-details.view', 'display_name' => 'View Timetable Details', 'description' => 'View all timetable details for a school'],
            ['name' => 'timetable-details.create', 'display_name' => 'Create Timetable Details', 'description' => 'Create new timetable details for a school'],
            ['name' => 'timetable-details.update', 'display_name' => 'Update Timetable Details', 'description' => 'Update existing timetable details for a school'],
            ['name' => 'timetable-details.delete', 'display_name' => 'Delete Timetable Details', 'description' => 'Delete timetable details for a school'],
            ['name' => 'timetable-details.restore', 'display_name' => 'Restore Timetable Details', 'description' => 'Restore deleted timetable details for a school'],
            ['name' => 'timetable-details.force-delete', 'display_name' => 'Force Delete Timetable Details', 'description' => 'Permanently delete timetable details for a school'],

            // Departments
            ['name' => 'departments.view', 'display_name' => 'View Departments', 'description' => 'View all departments for a school'],
            ['name' => 'departments.create', 'display_name' => 'Create Departments', 'description' => 'Create new departments for a school'],
            ['name' => 'departments.update', 'display_name' => 'Update Departments', 'description' => 'Update existing departments for a school'],
            ['name' => 'departments.delete', 'display_name' => 'Delete Departments', 'description' => 'Delete departments for a school'],
            ['name' => 'departments.restore', 'display_name' => 'Restore Departments', 'description' => 'Restore deleted departments for a school'],
            ['name' => 'departments.force-delete', 'display_name' => 'Force Delete Departments', 'description' => 'Permanently delete departments for a school'],
            ['name' => 'departments.assign-role', 'display_name' => 'Assign Roles to Departments', 'description' => 'Assign roles to departments'],

            // Notices
            ['name' => 'notices.view', 'display_name' => 'View Notices', 'description' => 'View all notices for a school'],
            ['name' => 'notices.create', 'display_name' => 'Create Notices', 'description' => 'Create new notices for a school'],
            ['name' => 'notices.update', 'display_name' => 'Update Notices', 'description' => 'Update existing notices for a school'],
            ['name' => 'notices.delete', 'display_name' => 'Delete Notices', 'description' => 'Delete notices for a school'],
            ['name' => 'notices.restore', 'display_name' => 'Restore Notices', 'description' => 'Restore deleted notices for a school'],
            ['name' => 'notices.force-delete', 'display_name' => 'Force Delete Notices', 'description' => 'Permanently delete notices for a school'],
            ['name' => 'notices.mark-read', 'display_name' => 'Mark Notices as Read', 'description' => 'Mark notices as read for a user'],

            // Routes
            ['name' => 'routes.view', 'display_name' => 'View Routes', 'description' => 'View all routes for a school'],
            ['name' => 'routes.create', 'display_name' => 'Create Routes', 'description' => 'Create new routes for a school'],
            ['name' => 'routes.update', 'display_name' => 'Update Routes', 'description' => 'Update existing routes for a school'],
            ['name' => 'routes.delete', 'display_name' => 'Delete Routes', 'description' => 'Delete routes for a school'],
            ['name' => 'routes.restore', 'display_name' => 'Restore Routes', 'description' => 'Restore deleted routes for a school'],
            ['name' => 'routes.force-delete', 'display_name' => 'Force Delete Routes', 'description' => 'Permanently delete routes for a school'],

            // Vehicles
            ['name' => 'vehicles.view', 'display_name' => 'View Vehicles', 'description' => 'View all vehicles for a school'],
            ['name' => 'vehicles.create', 'display_name' => 'Create Vehicles', 'description' => 'Create new vehicles for a school'],
            ['name' => 'vehicles.update', 'display_name' => 'Update Vehicles', 'description' => 'Update existing vehicles for a school'],
            ['name' => 'vehicles.delete', 'display_name' => 'Delete Vehicles', 'description' => 'Delete vehicles for a school'],
            ['name' => 'vehicles.restore', 'display_name' => 'Restore Vehicles', 'description' => 'Restore deleted vehicles for a school'],
            ['name' => 'vehicles.force-delete', 'display_name' => 'Force Delete Vehicles', 'description' => 'Permanently delete vehicles for a school'],
            ['name' => 'vehicles.assign-driver', 'display_name' => 'Assign Driver', 'description' => 'Assign a driver to a vehicle'],

            // Vehicle Documents
            ['name' => 'vehicle-documents.view', 'display_name' => 'View Vehicle Documents', 'description' => 'View all vehicle documents for a school'],
            ['name' => 'vehicle-documents.create', 'display_name' => 'Create Vehicle Documents', 'description' => 'Create new vehicle documents for a school'],
            ['name' => 'vehicle-documents.update', 'display_name' => 'Update Vehicle Documents', 'description' => 'Update existing vehicle documents for a school'],
            ['name' => 'vehicle-documents.delete', 'display_name' => 'Delete Vehicle Documents', 'description' => 'Delete vehicle documents for a school'],
            ['name' => 'vehicle-documents.restore', 'display_name' => 'Restore Vehicle Documents', 'description' => 'Restore deleted vehicle documents for a school'],
            ['name' => 'vehicle-documents.force-delete', 'display_name' => 'Force Delete Vehicle Documents', 'description' => 'Permanently delete vehicle documents for a school'],

            // Vehicle Expenses
            ['name' => 'vehicle-expenses.view', 'display_name' => 'View Vehicle Expenses', 'description' => 'View all vehicle expenses for a school'],
            ['name' => 'vehicle-expenses.create', 'display_name' => 'Create Vehicle Expenses', 'description' => 'Create new vehicle expenses for a school'],
            ['name' => 'vehicle-expenses.update', 'display_name' => 'Update Vehicle Expenses', 'description' => 'Update existing vehicle expenses for a school'],
            ['name' => 'vehicle-expenses.delete', 'display_name' => 'Delete Vehicle Expenses', 'description' => 'Delete vehicle expenses for a school'],
            ['name' => 'vehicle-expenses.restore', 'display_name' => 'Restore Vehicle Expenses', 'description' => 'Restore deleted vehicle expenses for a school'],
            ['name' => 'vehicle-expenses.force-delete', 'display_name' => 'Force Delete Vehicle Expenses', 'description' => 'Permanently delete vehicle expenses for a school'],

            // Hostel
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
