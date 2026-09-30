<?php

// Permission catalogue part 1 (Phase 2)
return [

            // Dashboard
            ['name' => 'dashboard.view', 'display_name' => 'View Dashboard', 'description' => 'Access the main dashboard'],
            ['name' => 'dashboard.edit', 'display_name' => 'Edit Dashboard', 'description' => 'Edit dashboard widgets'],

            // Your single settings permission
            ['name' => 'settings.manage', 'display_name' => 'Manage Settings', 'description' => 'Full access to all settings pages'],

            // dynamic enums (Phase 4)
            ['name' => 'dynamic-enums.view', 'display_name' => 'View Dynamic Enums', 'description' => 'View Dynamic Enum definitions and effective configuration'],
            ['name' => 'dynamic-enums.manage', 'display_name' => 'Manage Dynamic Enums', 'description' => 'Manage Dynamic Enum configuration in the current authorization context (tenant or school)'],

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
            ['name' => 'school-sections.restore', 'display_name' => 'Restore School Section', 'description' => 'Can restore a soft-deleted school section/division'],
            ['name' => 'school-sections.force-delete', 'display_name' => 'Permanently Delete School Section', 'description' => 'Can permanently delete a school section/division'],

            // Users
            ['name' => 'user.view-any', 'display_name' => 'View All Users', 'description' => 'View list of all users'],
            ['name' => 'user.view', 'display_name' => 'View User', 'description' => 'View individual user details'],
            ['name' => 'user.create', 'display_name' => 'Create User', 'description' => 'Create new users'],
            ['name' => 'user.update', 'display_name' => 'Update User', 'description' => 'Update user information'],
            ['name' => 'user.delete', 'display_name' => 'Delete User', 'description' => 'Soft delete users'],
            ['name' => 'user.restore', 'display_name' => 'Restore User', 'description' => 'Restore deleted users'],
            ['name' => 'user.force-delete', 'display_name' => 'Force Delete User', 'description' => 'Permanently delete users'],
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

            // Roles
            ['name' => 'role.view-any', 'display_name' => 'View All Roles', 'description' => 'View list of roles'],
            ['name' => 'role.view', 'display_name' => 'View Role', 'description' => 'View role details'],
            ['name' => 'role.create', 'display_name' => 'Create Role', 'description' => 'Create new roles'],
            ['name' => 'role.update', 'display_name' => 'Update Role', 'description' => 'Update roles'],
            ['name' => 'role.delete', 'display_name' => 'Delete Role', 'description' => 'Delete roles'],

            // Permissions management
            ['name' => 'permission.view-any', 'display_name' => 'View All Permissions', 'description' => 'View list of permissions'],
            ['name' => 'permission.view', 'display_name' => 'View Permission', 'description' => 'View permission details'],

            // Class levels / sections
            ['name' => 'class-level.view-any', 'display_name' => 'View All Class Levels', 'description' => 'View class levels'],
            ['name' => 'class-level.view', 'display_name' => 'View Class Level', 'description' => 'View class level details'],
            ['name' => 'class-level.create', 'display_name' => 'Create Class Level', 'description' => 'Create class levels'],
            ['name' => 'class-level.update', 'display_name' => 'Update Class Level', 'description' => 'Update class levels'],
            ['name' => 'class-level.delete', 'display_name' => 'Delete Class Level', 'description' => 'Delete class levels'],
            ['name' => 'class-section.view-any', 'display_name' => 'View All Class Sections', 'description' => 'View class sections'],
            ['name' => 'class-section.view', 'display_name' => 'View Class Section', 'description' => 'View class section details'],
            ['name' => 'class-section.create', 'display_name' => 'Create Class Section', 'description' => 'Create class sections'],
            ['name' => 'class-section.update', 'display_name' => 'Update Class Section', 'description' => 'Update class sections'],
            ['name' => 'class-section.delete', 'display_name' => 'Delete Class Section', 'description' => 'Delete class sections'],
];
