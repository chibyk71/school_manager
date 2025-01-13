<?php

namespace Database\Seeders\Settings;

use App\Models\Tenant\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        
        $permissions = [
            [
                'name' => 'view-dashboard',
                'display_name' => 'View Dashboard',
                'description' => 'Access the main dashboard',
            ],
            [
                'name' => 'view-sidebar',
                'display_name' => 'View Sidebar',
                'description' => 'Access the sidebar menu',
            ], //implemented
            [
                'name' => 'view-schools',
                'display_name' => 'View Schools',
                'description' => 'Access the list of schools',
            ], //implemented
            [
                'name' => 'create-school',
                'display_name' => 'Create School',
                'description' => 'Create a new school',
            ], //implemented
            [
                'name' => 'update-school',
                'display_name' => 'Update School',
                'description' => 'Update an existing school',
            ], //implemented
            [
                'name' => 'delete-school',
                'display_name' => 'Delete School',
                'description' => 'Delete a school',
            ],
            [
                'name' => 'customize-sidebar',
                'display_name' => 'Customize Sidebar',
                'description' => 'Customize the sidebar menu',
            ],
            [
                'name' => 'view-administration-settings',
                'display_name' => 'View Administration Settings',
                'description' => 'Access administration settings',
            ],
            [
                'name' => 'edit-administration-settings',
                'display_name' => 'Edit Administration Settings',
                'description' => 'Modify administration settings',
            ],
            [
                'name' => 'view-admission-queries',
                'display_name' => 'View Admission Queries',
                'description' => 'Access admission queries',
            ],
            [
                'name' => 'create-admission-query',
                'display_name' => 'Create Admission Query',
                'description' => 'Create a new admission query',
            ],
            [
                'name' => 'update-admission-query',
                'display_name' => 'Update Admission Query',
                'description' => 'Update an existing admission query',
            ],
            [
                'name' => 'delete-admission-query',
                'display_name' => 'Delete Admission Query',
                'description' => 'Remove an admission query',
            ],
            [
                'name' => 'view-visitor-book',
                'display_name' => 'View Visitor Book',
                'description' => 'Access the visitor book',
            ],
            [
                'name' => 'add-visitor-entry',
                'display_name' => 'Add Visitor Entry',
                'description' => 'Add a new visitor entry',
            ],
            [
                'name' => 'update-visitor-entry',
                'display_name' => 'Update Visitor Entry',
                'description' => 'Update an existing visitor entry',
            ],
            [
                'name' => 'delete-visitor-entry',
                'display_name' => 'Delete Visitor Entry',
                'description' => 'Remove a visitor entry',
            ],
            [
                'name' => 'view-complaints',
                'display_name' => 'View Complaints',
                'description' => 'Access complaints',
            ],
            [
                'name' => 'create-complaint',
                'display_name' => 'Create Complaint',
                'description' => 'Create a new complaint',
            ],
            [
                'name' => 'update-complaint',
                'display_name' => 'Update Complaint',
                'description' => 'Update an existing complaint',
            ],
            [
                'name' => 'delete-complaint',
                'display_name' => 'Delete Complaint',
                'description' => 'Remove a complaint',
            ],
            [
                'name' => 'view-postal-records',
                'display_name' => 'View Postal Records',
                'description' => 'Access postal records',
            ],
            [
                'name' => 'create-postal-record',
                'display_name' => 'Create Postal Record',
                'description' => 'Create a new postal record',
            ],
            [
                'name' => 'update-postal-record',
                'display_name' => 'Update Postal Record',
                'description' => 'Update an existing postal record',
            ],
            [
                'name' => 'delete-postal-record',
                'display_name' => 'Delete Postal Record',
                'description' => 'Remove a postal record',
            ],
            [
                'name' => 'view-phone-call-log',
                'display_name' => 'View Phone Call Log',
                'description' => 'Access phone call log',
            ],
            [
                'name' => 'add-phone-call-log-entry',
                'display_name' => 'Add Phone Call Log Entry',
                'description' => 'Add a new phone call log entry',
            ],
            [
                'name' => 'update-phone-call-log-entry',
                'display_name' => 'Update Phone Call Log Entry',
                'description' => 'Update an existing phone call log entry',
            ],
            [
                'name' => 'delete-phone-call-log-entry',
                'display_name' => 'Delete Phone Call Log Entry',
                'description' => 'Remove a phone call log entry',
            ],
            [
                'name' => 'view-admin-setup',
                'display_name' => 'View Admin Setup',
                'description' => 'Access admin setup',
            ],
            [
                'name' => 'edit-admin-setup',
                'display_name' => 'Edit Admin Setup',
                'description' => 'Modify admin setup',
            ],
            [
                'name' => 'view-id-card-management',
                'display_name' => 'View ID Card and Certificate Management',
                'description' => 'Access ID card and certificate management',
            ],
            [
                'name' => 'create-id-card',
                'display_name' => 'Create ID Card',
                'description' => 'Create a new ID card',
            ],
            [
                'name' => 'create-certificate',
                'display_name' => 'Create Certificate',
                'description' => 'Create a new certificate',
            ],
            [
                'name' => 'update-id-card',
                'display_name' => 'Update ID Card',
                'description' => 'Update an existing ID card',
            ],
            [
                'name' => 'update-certificate',
                'display_name' => 'Update Certificate',
                'description' => 'Update an existing certificate',
            ],
            [
                'name' => 'delete-id-card',
                'display_name' => 'Delete ID Card',
                'description' => 'Remove an ID card',
            ],
            [
                'name' => 'delete-certificate',
                'display_name' => 'Delete Certificate',
                'description' => 'Remove a certificate',
            ],
            [
                'name' => 'view-academics',
                'display_name' => 'View Academics',
                'description' => 'Access academic records',
            ],
            [
                'name' => 'create-class',
                'display_name' => 'Create Class',
                'description' => 'Create a new class',
            ],
            [
                'name' => 'update-class',
                'display_name' => 'Update Class',
                'description' => 'Update an existing class',
            ],
            [
                'name' => 'delete-class',
                'display_name' => 'Delete Class',
                'description' => 'Remove a class',
            ],
            [
                'name' => 'create-subject',
                'display_name' => 'Create Subject',
                'description' => 'Create a new subject',
            ],
            [
                'name' => 'update-subject',
                'display_name' => 'Update Subject',
                'description' => 'Update an existing subject',
            ],
            [
                'name' => 'delete-subject',
                'display_name' => 'Delete Subject',
                'description' => 'Remove a subject',
            ],
            [
                'name' => 'assign-class-teacher',
                'display_name' => 'Assign Class Teacher',
                'description' => 'Assign a teacher to a class',
            ],
            [
                'name' => 'assign-subject',
                'display_name' => 'Assign Subject',
                'description' => 'Assign a subject to a class',
            ],
            [
                'name' => 'view-class-routines',
                'display_name' => 'View Class Routines',
                'description' => 'Access class routines',
            ],
            [
                'name' => 'create-class-routine',
                'display_name' => 'Create Class Routine',
                'description' => 'Create a new class routine',
            ],
            [
                'name' => 'update-class-routine',
                'display_name' => 'Update Class Routine',
                'description' => 'Update an existing class routine',
            ],
            [
                'name' => 'delete-class-routine',
                'display_name' => 'Delete Class Routine',
                'description' => 'Remove a class routine',
            ],
            [
                'name' => 'upload-study-material',
                'display_name' => 'Upload Study Material',
                'description' => 'Upload study materials for classes',
            ],
            [
                'name' => 'view-study-material',
                'display_name' => 'View Study Material',
                'description' => 'Access study materials',
            ],
            [
                'name' => 'create-assignment',
                'display_name' => 'Create Assignment',
                'description' => 'Create a new assignment',
            ],
            [
                'name' => 'update-assignment',
                'display_name' => 'Update Assignment',
                'description' => 'Update an existing assignment',
            ],
            [
                'name' => 'delete-assignment',
                'display_name' => 'Delete Assignment',
                'description' => 'Remove an assignment',
            ],
            [
                'name' => 'view-syllabus',
                'display_name' => 'View Syllabus',
                'description' => 'Access the syllabus',
            ],
            [
                'name' => 'update-syllabus',
                'display_name' => 'Update Syllabus',
                'description' => 'Modify the syllabus',
            ],
            [
                'name' => 'view-lesson-plans',
                'display_name' => 'View Lesson Plans',
                'description' => 'Access lesson plans',
            ],
            [
                'name' => 'create-lesson-plan',
                'display_name' => 'Create Lesson Plan',
                'description' => 'Create a new lesson plan',
            ],
            [
                'name' => 'update-lesson-plan',
                'display_name' => 'Update Lesson Plan',
                'description' => 'Update an existing lesson plan',
            ],
            [
                'name' => 'delete-lesson-plan',
                'display_name' => 'Delete Lesson Plan',
                'description' => 'Remove a lesson plan',
            ],
            [
                'name' => 'view-topics',
                'display_name' => 'View Topics',
                'description' => 'Access topics',
            ],
            [
                'name' => 'create-topic',
                'display_name' => 'Create Topic',
                'description' => 'Create a new topic',
            ],
            [
                'name' => 'update-topic',
                'display_name' => 'Update Topic',
                'description' => 'Update an existing topic',
            ],
            [
                'name' => 'delete-topic',
                'display_name' => 'Delete Topic',
                'description' => 'Remove a topic',
            ],
            [
                'name' => 'bulk-print-id-cards',
                'display_name' => 'Bulk Print ID Cards',
                'description' => 'Print ID cards in bulk',
            ],
            [
                'name' => 'bulk-print-certificates',
                'display_name' => 'Bulk Print Certificates',
                'description' => 'Print certificates in bulk',
            ],
            [
                'name' => 'bulk-print-payroll',
                'display_name' => 'Bulk Print Payroll',
                'description' => 'Print payroll in bulk',
            ],
            [
                'name' => 'bulk-print-fees-invoices',
                'display_name' => 'Bulk Print Fees Invoices',
                'description' => 'Print fees invoices in bulk',
            ],
            [
                'name' => 'view-download-center',
                'display_name' => 'View Download Center',
                'description' => 'Access the download center',
            ],
            [
                'name' => 'upload-content',
                'display_name' => 'Upload Content',
                'description' => 'Upload content to the download center',
            ],
            [
                'name' => 'delete-content',
                'display_name' => 'Delete Content',
                'description' => 'Remove content from the download center',
            ],
            [
                'name' => 'view-content-types',
                'display_name' => 'View Content Types',
                'description' => 'Access content types',
            ],
            [
                'name' => 'create-content-type',
                'display_name' => 'Create Content Type',
                'description' => 'Create a new content type',
            ],
            [
                'name' => 'update-content-type',
                'display_name' => 'Update Content Type',
                'description' => 'Update an existing content type',
            ],
            [
                'name' => 'delete-content-type',
                'display_name' => 'Delete Content Type',
                'description' => 'Remove a content type',
            ],
            [
                'name' => 'view-content-lists',
                'display_name' => 'View Content Lists',
                'description' => 'Access content lists',
            ],
            [
                'name' => 'create-content-list',
                'display_name' => 'Create Content List',
                'description' => 'Create a new content list',
            ],
            [
                'name' => 'update-content-list',
                'display_name' => 'Update Content List',
                'description' => 'Update an existing content list',
            ],
            [
                'name' => 'delete-content-list',
                'display_name' => 'Delete Content List',
                'description' => 'Remove a content list',
            ],
            [
                'name' => 'view-shared-content-lists',
                'display_name' => 'View Shared Content Lists',
                'description' => 'Access shared content lists',
            ],
            [
                'name' => 'view-video-lists',
                'display_name' => 'View Video Lists',
                'description' => 'Access video lists',
            ],
            [
                'name' => 'view-certificate-types-templates',
                'display_name' => 'View Certificate Types and Templates',
                'description' => 'Access certificate types and templates',
            ],
            [
                'name' => 'create-certificate-type',
                'display_name' => 'Create Certificate Type',
                'description' => 'Create a new certificate type',
            ],
            [
                'name' => 'update-certificate-type',
                'display_name' => 'Update Certificate Type',
                'description' => 'Update an existing certificate type',
            ],
            [
                'name' => 'delete-certificate-type',
                'display_name' => 'Delete Certificate Type',
                'description' => 'Remove a certificate type',
            ],
            [
                'name' => 'view-certificate-records',
                'display_name' => 'View Certificate Records',
                'description' => 'Access certificate records',
            ],
            [
                'name' => 'view-student-information',
                'display_name' => 'View Student Information',
                'description' => 'Access student information',
            ],
            [
                'name' => 'add-student',
                'display_name' => 'Add Student',
                'description' => 'Create a new student record',
            ],
            [
                'name' => 'update-student',
                'display_name' => 'Update Student',
                'description' => 'Update an existing student record',
            ],
            [
                'name' => 'delete-student',
                'display_name' => 'Delete Student',
                'description' => 'Remove a student record',
            ],
            [
                'name' => 'view-multi-class-students',
                'display_name' => 'View Multi-Class Students',
                'description' => 'Access students enrolled in multiple classes',
            ],
            [
                'name' => 'view-unassigned-students',
                'display_name' => 'View Unassigned Students',
                'description' => 'Access students not assigned to any class',
            ],
            [
                'name' => 'view-attendance-records',
                'display_name' => 'View Attendance Records',
                'description' => 'Access attendance records',
            ],
            [
                'name' => 'mark-attendance',
                'display_name' => 'Mark Attendance',
                'description' => 'Record attendance for students',
            ],
            [
                'name' => 'update-attendance',
                'display_name' => 'Update Attendance',
                'description' => 'Update existing attendance records',
            ],
            [
                'name' => 'delete-attendance',
                'display_name' => 'Delete Attendance',
                'description' => 'Remove attendance records',
            ],
            [
                'name' => 'view-student-promotion-records',
                'display_name' => 'View Student Promotion Records',
                'description' => 'Access records of student promotions',
            ],
            [
                'name' => 'promote-student',
                'display_name' => 'Promote Student',
                'description' => 'Promote a student to the next class',
            ],
            [
                'name' => 'export-student-records',
                'display_name' => 'Export Student Records',
                'description' => 'Export student records to a file',
            ],
            [
                'name' => 'view-behavior-records',
                'display_name' => 'View Behavior Records',
                'description' => 'Access behavior records of students',
            ],
            [
                'name' => 'create-behavior-record',
                'display_name' => 'Create Behavior Record',
                'description' => 'Create a new behavior record',
            ],
            [
                'name' => 'update-behavior-record',
                'display_name' => 'Update Behavior Record',
                'description' => 'Update an existing behavior record',
            ],
            [
                'name' => 'delete-behavior-record',
                'display_name' => 'Delete Behavior Record',
                'description' => 'Remove a behavior record',
            ],
            [
                'name' => 'view-fees-groups-types',
                'display_name' => 'View Fees Groups and Types',
                'description' => 'Access fees groups and types',
            ],
            [
                'name' => 'create-fees-group',
                'display_name' => 'Create Fees Group',
                'description' => 'Create a new fees group',
            ],
            [
                'name' => 'update-fees-group',
                'display_name' => 'Update Fees Group',
                'description' => 'Update an existing fees group',
            ],
            [
                'name' => 'delete-fees-group',
                'display_name' => 'Delete Fees Group',
                'description' => 'Remove a fees group',
            ],
            [
                'name' => 'view-fees-invoices',
                'display_name' => 'View Fees Invoices',
                'description' => 'Access fees invoices',
            ],
            [
                'name' => 'create-fees-invoice',
                'display_name' => 'Create Fees Invoice',
                'description' => 'Create a new fees invoice',
            ],
            [
                'name' => 'update-fees-invoice',
                'display_name' => 'Update Fees Invoice',
                'description' => 'Update an existing fees invoice',
            ],
            [
                'name' => 'delete-fees-invoice',
                'display_name' => 'Delete Fees Invoice',
                'description' => 'Remove a fees invoice',
            ],
            [
                'name' => 'manage-bank-payments',
                'display_name' => 'Manage Bank Payments',
                'description' => 'Handle bank payment transactions',
            ],
            [
                'name' => 'view-carry-forward-balances',
                'display_name' => 'View Carry Forward Balances',
                'description' => 'Access carry forward balances',
            ],
            [
                'name' => 'view-homework',
                'display_name' => 'View Homework',
                'description' => 'Access homework assignments',
            ],
            [
                'name' => 'create-homework',
                'display_name' => 'Create Homework',
                'description' => 'Create a new homework assignment',
            ],
            [
                'name' => 'update-homework',
                'display_name' => 'Update Homework',
                'description' => 'Update an existing homework assignment',
            ],
            [
                'name' => 'delete-homework',
                'display_name' => 'Delete Homework',
                'description' => 'Remove a homework assignment',
            ],
            [
                'name' => 'view-library-records',
                'display_name' => 'View Library Records',
                'description' => 'Access library records',
            ],
            [
                'name' => 'add-book',
                'display_name' => 'Add Book',
                'description' => 'Add a new book to the library',
            ],
            [
                'name' => 'update-book',
                'display_name' => 'Update Book',
                'description' => 'Update an existing book in the library',
            ],
            [
                'name' => 'delete-book',
                'display_name' => 'Delete Book',
                'description' => 'Remove a book from the library',
            ],
            [
                'name' => 'view-library-members',
                'display_name' => 'View Library Members',
                'description' => 'Access library member records',
            ],
            [
                'name' => 'add-library-member',
                'display_name' => 'Add Library Member',
                'description' => 'Add a new library member',
            ],
            [
                'name' => 'update-library-member',
                'display_name' => 'Update Library Member',
                'description' => 'Update an existing library member',
            ],
            [
                'name' => 'delete-library-member',
                'display_name' => 'Delete Library Member',
                'description' => 'Remove a library member',
            ],
            [
                'name' => 'issue-book',
                'display_name' => 'Issue Book',
                'description' => 'Issue a book to a member',
            ],
            [
                'name' => 'return-book',
                'display_name' => 'Return Book',
                'description' => 'Return a book from a member',
            ],
            [
                'name' => 'view-transport-routes',
                'display_name' => 'View Transport Routes',
                'description' => 'Access transport routes',
            ],
            [
                'name' => 'create-transport-route',
                'display_name' => 'Create Transport Route',
                'description' => 'Create a new transport route',
            ],
            [
                'name' => 'update-transport-route',
                'display_name' => 'Update Transport Route',
                'description' => 'Update an existing transport route',
            ],
            [
                'name' => 'delete-transport-route',
                'display_name' => 'Delete Transport Route',
                'description' => 'Remove a transport route',
            ],
            [
                'name' => 'view-vehicles',
                'display_name' => 'View Vehicles',
                'description' => 'Access vehicle records',
            ],
            [
                'name' => 'add-vehicle',
                'display_name' => 'Add Vehicle',
                'description' => 'Add a new vehicle',
            ],
            [
                'name' => 'update-vehicle',
                'display_name' => 'Update Vehicle',
                'description' => 'Update an existing vehicle',
            ],
            [
                'name' => 'delete-vehicle',
                'display_name' => 'Delete Vehicle',
                'description' => 'Remove a vehicle',
            ],
            [
                'name' => 'assign-vehicle',
                'display_name' => 'Assign Vehicle',
                'description' => 'Assign a vehicle to a route',
            ],
            [
                'name' => 'view-dormitory-rooms',
                'display_name' => 'View Dormitory Rooms',
                'description' => 'Access dormitory room records',
            ],
            [
                'name' => 'create-dormitory-room',
                'display_name' => 'Create Dormitory Room',
                'description' => 'Create a new dormitory room',
            ],
            [
                'name' => 'update-dormitory-room',
                'display_name' => 'Update Dormitory Room',
                'description' => 'Update an existing dormitory room',
            ],
            [
                'name' => 'delete-dormitory-room',
                'display_name' => 'Delete Dormitory Room',
                'description' => 'Remove a dormitory room',
            ],
            [
                'name' => 'view-room-types',
                'display_name' => 'View Room Types',
                'description' => 'Access room type records',
            ],
            [
                'name' => 'create-room-type',
                'display_name' => 'Create Room Type',
                'description' => 'Create a new room type',
            ],
            [
                'name' => 'update-room-type',
                'display_name' => 'Update Room Type',
                'description' => 'Update an existing room type',
            ],
            [
                'name' => 'delete-room-type',
                'display_name' => 'Delete Room Type',
                'description' => 'Remove a room type',
            ],
            [
                'name' => 'view-exam-types',
                'display_name' => 'View Exam Types',
                'description' => 'Access exam type records',
            ],
            [
                'name' => 'create-exam-type',
                'display_name' => 'Create Exam Type',
                'description' => 'Create a new exam type',
            ],
            [
                'name' => 'update-exam-type',
                'display_name' => 'Update Exam Type',
                'description' => 'Update an existing exam type',
            ],
            [
                'name' => 'delete-exam-type',
                'display_name' => 'Delete Exam Type',
                'description' => 'Remove an exam type',
            ],
            [
                'name' => 'view-exam-schedules',
                'display_name' => 'View Exam Schedules',
                'description' => 'Access exam schedules',
            ],
            [
                'name' => 'create-exam-schedule',
                'display_name' => 'Create Exam Schedule',
                'description' => 'Create a new exam schedule',
            ],
            [
                'name' => 'update-exam-schedule',
                'display_name' => 'Update Exam Schedule',
                'description' => 'Update an existing exam schedule',
            ],
            [
                'name' => 'delete-exam-schedule',
                'display_name' => 'Delete Exam Schedule',
                'description' => 'Remove an exam schedule',
            ],
            [
                'name' => 'view-exam-attendance',
                'display_name' => 'View Exam Attendance',
                'description' => 'Access exam attendance records',
            ],
            [
                'name' => 'mark-exam-attendance',
                'display_name' => 'Mark Exam Attendance',
                'description' => 'Record attendance for exams',
            ],
            [
                'name' => 'view-marks-registers',
                'display_name' => 'View Marks Registers',
                'description' => 'Access marks registers',
            ],
            [
                'name' => 'add-marks',
                'display_name' => 'Add Marks',
                'description' => 'Add marks to a student record',
            ],
            [
                'name' => 'update-marks',
                'display_name' => 'Update Marks',
                'description' => 'Update existing marks',
            ],
            [
                'name' => 'delete-marks',
                'display_name' => 'Delete Marks',
                'description' => 'Remove marks from a student record',
            ],
            [
                'name' => 'view-grade-management',
                'display_name' => 'View Grade Management',
                'description' => 'Access grade management records',
            ],
            [
                'name' => 'generate-marksheet-reports',
                'display_name' => 'Generate Marksheet Reports',
                'description' => 'Generate reports for marksheets',
            ],
            [
                'name' => 'manage-exam-plan',
                'display_name' => 'Manage Exam Plan',
                'description' => 'Manage the exam planning process',
            ],
            [
                'name' => 'generate-admit-cards',
                'display_name' => 'Generate Admit Cards',
                'description' => 'Generate admit cards for students',
            ],
            [
                'name' => 'view-seat-plans',
                'display_name' => 'View Seat Plans',
                'description' => 'Access seating arrangements for exams',
            ],
            [
                'name' => 'manage-online-exam',
                'display_name' => 'Manage Online Exam',
                'description' => 'Oversee online examination processes',
            ],
            [
                'name' => 'view-question-bank',
                'display_name' => 'View Question Bank',
                'description' => 'Access the question bank for exams',
            ],
            [
                'name' => 'create-question',
                'display_name' => 'Create Question',
                'description' => 'Add a new question to the question bank',
            ],
            [
                'name' => 'update-question',
                'display_name' => 'Update Question',
                'description' => 'Modify an existing question',
            ],
            [
                'name' => 'delete-question',
                'display_name' => 'Delete Question',
                'description' => 'Remove a question from the question bank',
            ],
            [
                'name' => 'manage-online-exam-management',
                'display_name' => 'Manage Online Exam Management',
                'description' => 'Oversee online exam management settings',
            ],
            [
                'name' => 'view-designations-departments',
                'display_name' => 'View Designations and Departments',
                'description' => 'Access designations and department records',
            ],
            [
                'name' => 'create-designation',
                'display_name' => 'Create Designation',
                'description' => 'Create a new designation',
            ],
            [
                'name' => 'update-designation',
                'display_name' => 'Update Designation',
                'description' => 'Update an existing designation',
            ],
            [
                'name' => 'delete-designation',
                'display_name' => 'Delete Designation',
                'description' => 'Remove a designation',
            ],
            [
                'name' => 'view-staff-directory',
                'display_name' => 'View Staff Directory',
                'description' => 'Access the staff directory',
            ],
            [
                'name' => 'add-staff',
                'display_name' => 'Add Staff',
                'description' => 'Add a new staff member',
            ],
            [
                'name' => 'update-staff',
                'display_name' => 'Update Staff',
                'description' => 'Update an existing staff member',
            ],
            [
                'name' => 'delete-staff',
                'display_name' => 'Delete Staff',
                'description' => 'Remove a staff member',
            ],
            [
                'name' => 'view-staff-attendance',
                'display_name' => 'View Staff Attendance',
                'description' => 'Access staff attendance records',
            ],
            [
                'name' => 'mark-staff-attendance',
                'display_name' => 'Mark Staff Attendance',
                'description' => 'Record attendance for staff members',
            ],
            [
                'name' => 'view-payroll',
                'display_name' => 'View Payroll',
                'description' => 'Access payroll records',
            ],
            [
                'name' => 'generate-teacher-evaluation-reports',
                'display_name' => 'Generate Teacher Evaluation Reports',
                'description' => 'Generate reports for teacher evaluations',
            ],
            [
                'name' => 'view-leave-requests',
                'display_name' => 'View Leave Requests',
                'description' => 'Access leave requests from staff',
            ],
            [
                'name' => 'apply-for-leave',
                'display_name' => 'Apply for Leave',
                'description' => 'Submit a leave application',
            ],
            [
                'name' => 'approve-leave-requests',
                'display_name' => 'Approve Leave Requests',
                'description' => 'Approve leave applications from staff',
            ],
            [
                'name' => 'define-leave-types',
                'display_name' => 'Define Leave Types',
                'description' => 'Set up different types of leave',
            ],
            [
                'name' => 'view-wallet',
                'display_name' => 'View Wallet',
                'description' => 'Access wallet information',
            ],
            [
                'name' => 'manage-transactions',
                'display_name' => 'Manage Transactions',
                'description' => 'Handle financial transactions',
            ],
            [
                'name' => 'process-refunds',
                'display_name' => 'Process Refunds',
                'description' => 'Handle refund transactions',
            ],
            [
                'name' => 'view-profit-loss',
                'display_name' => 'View Profit & Loss',
                'description' => 'Access profit and loss statements',
            ],
            [
                'name' => 'view-income-expense',
                'display_name' => 'View Income and Expense',
                'description' => 'Access income and expense records',
            ],
            [
                'name' => 'view-inventory-categories',
                'display_name' => 'View Inventory Categories',
                'description' => 'Access inventory categories',
            ],
            [
                'name' => 'create-inventory-category',
                'display_name' => 'Create Inventory Category',
                'description' => 'Create a new inventory category',
            ],
            [
                'name' => 'update-inventory-category',
                'display_name' => 'Update Inventory Category',
                'description' => 'Update an existing inventory category',
            ],
            [
                'name' => 'delete-inventory-category',
                'display_name' => 'Delete Inventory Category',
                'description' => 'Remove an inventory category',
            ],
            [
                'name' => 'view-items',
                'display_name' => 'View Items',
                'description' => 'Access inventory items',
            ],
            [
                'name' => 'add-item',
                'display_name' => 'Add Item',
                'description' => 'Add a new item to inventory',
            ],
            [
                'name' => 'update-item',
                'display_name' => 'Update Item',
                'description' => 'Update an existing inventory item',
            ],
            [
                'name' => 'delete-item',
                'display_name' => 'Delete Item',
                'description' => 'Remove an inventory item',
            ],
            [
                'name' => 'manage-suppliers',
                'display_name' => 'Manage Suppliers',
                'description' => 'Handle supplier information',
            ],
            [
                'name' => 'view-chat-settings',
                'display_name' => 'View Chat Settings',
                'description' => 'Access chat settings',
            ],
            [
                'name' => 'send-chat-invitations',
                'display_name' => 'Send Chat Invitations',
                'description' => 'Invite users to chat',
            ],
            [
                'name' => 'block-users',
                'display_name' => 'Block Users',
                'description' => 'Block users from chatting',
            ],
            [
                'name' => 'view-notice-boards',
                'display_name' => 'View Notice Boards',
                'description' => 'Access notice board announcements',
            ],
            [
                'name' => 'send-email-sms-notifications',
                'display_name' => 'Send Email/SMS Notifications',
                'description' => 'Send notifications via email or SMS',
            ],
            [
                'name' => 'generate-student-reports',
                'display_name' => 'Generate Student Reports',
                'description' => 'Create reports for student performance',
            ],
            [
                'name' => 'generate-exam-reports',
                'display_name' => 'Generate Exam Reports',
                'description' => 'Create reports for exam results',
            ],
            [
                'name' => 'generate-staff-reports',
                'display_name' => 'Generate Staff Reports',
                'description' => 'Create reports for staff performance',
            ],
            [
                'name' => 'generate-fees-reports',
                'display_name' => 'Generate Fees Reports',
                'description' => 'Create reports for fees collection',
            ],
            [
                'name' => 'generate-accounts-reports',
                'display_name' => 'Generate Accounts Reports',
                'description' => 'Create reports for financial accounts',
            ],
            [
                'name' => 'manage-custom-fields',
                'display_name' => 'Manage Custom Fields',
                'description' => 'Handle custom fields in the application',
            ],
            [
                'name' => 'view-fees-settings',
                'display_name' => 'View Fees Settings',
                'description' => 'Access fees configuration settings',
            ],
            [
                'name' => 'view-exam-settings',
                'display_name' => 'View Exam Settings',
                'description' => 'Access exam configuration settings',
            ],
            [
                'name' => 'manage-virtual-classroom-integrations',
                'display_name' => 'Manage Virtual Classroom Integrations',
                'description' => 'Handle integrations with virtual classroom tools',
            ],
            [
                'name' => 'manage-virtual-classes-meetings',
                'display_name' => 'Manage Virtual Classes and Meetings',
                'description' => 'Oversee virtual classes and meetings',
            ],
            [
                ' name' => 'manage-ai-content',
                'display_name' => 'Manage AI Content',
                'description' => 'Handle AI-generated content',
            ],
            [
                'name' => 'manage-ai-content-settings',
                'display_name' => 'Manage AI Content Settings',
                'description' => 'Configure settings for AI content generation',
            ],
            [
                'name' => 'manage-whatsapp-support',
                'display_name' => 'Manage WhatsApp Support',
                'description' => 'Oversee WhatsApp support functionalities',
            ],
            [
                'name' => 'manage-whatsapp-agent-management',
                'display_name' => 'Manage WhatsApp Agent Management',
                'description' => 'Handle WhatsApp agent configurations',
            ],
            [
                'name' => 'manage-qr-code-attendance',
                'display_name' => 'Manage QR Code Attendance',
                'description' => 'Oversee QR code attendance tracking',
            ],
            [
                'name' => 'track-attendance',
                'display_name' => 'Track Attendance',
                'description' => 'Monitor attendance records',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }
    }
}