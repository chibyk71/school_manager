<?php

// Permission catalogue part 4 (Phase 2)
return [
    ['name' => 'terms.restore', 'display_name' => 'Restore Terms', 'description' => 'Restore deleted terms for a school'],
    ['name' => 'terms.force-delete', 'display_name' => 'Force Delete Terms', 'description' => 'Permanently delete terms for a school'],
    ['name' => 'academic-sessions.view', 'display_name' => 'View Academic Sessions', 'description' => 'View all academic sessions for a school'],
    ['name' => 'academic-sessions.create', 'display_name' => 'Create Academic Sessions', 'description' => 'Create new academic sessions for a school'],
    ['name' => 'academic-sessions.update', 'display_name' => 'Update Academic Sessions', 'description' => 'Update existing academic sessions for a school'],
    ['name' => 'academic-sessions.delete', 'display_name' => 'Delete Academic Sessions', 'description' => 'Delete academic sessions for a school'],
    ['name' => 'academic-sessions.restore', 'display_name' => 'Restore Academic Sessions', 'description' => 'Restore deleted academic sessions for a school'],
    ['name' => 'academic-sessions.force-delete', 'display_name' => 'Force Delete Academic Sessions', 'description' => 'Permanently delete academic sessions for a school'],
    ['name' => 'academic-sessions.activate', 'display_name' => 'Activate Academic Sessions', 'description' => 'Activate academic sessions (make current/active)'],
    ['name' => 'academic-sessions.close', 'display_name' => 'Close Academic Sessions', 'description' => 'Close academic sessions'],
    ['name' => 'terms.close', 'display_name' => 'Close Terms', 'description' => 'Close active terms'],
    ['name' => 'terms.reopen', 'display_name' => 'Reopen Terms', 'description' => 'Reopen previously closed terms (restricted)'],
    [
                'name' => 'custom-fields.viewAny',
                'display_name' => 'View Custom Fields List',
                'description' => 'View the list of all custom fields (global + school overrides)',
            ],
    [
                'name' => 'custom-fields.view',
                'display_name' => 'View Custom Field Details',
                'description' => 'View detailed information about a specific custom field',
            ],
    [
                'name' => 'custom-fields.create',
                'display_name' => 'Create Custom Field',
                'description' => 'Create new custom fields (global or school-specific overrides)',
            ],
    [
                'name' => 'custom-fields.update',
                'display_name' => 'Edit Custom Field',
                'description' => 'Update existing custom fields (restricted for global fields)',
            ],
    [
                'name' => 'custom-fields.delete',
                'display_name' => 'Delete Custom Field',
                'description' => 'Soft-delete custom fields (restricted for global fields)',
            ],
    [
                'name' => 'custom-fields.restore',
                'display_name' => 'Restore Custom Field',
                'description' => 'Restore soft-deleted custom fields',
            ],
    [
                'name' => 'custom-fields.forceDelete',
                'display_name' => 'Permanently Delete Custom Field',
                'description' => 'Force-delete custom fields (permanent removal)',
            ],
    [
                'name' => 'custom-fields.reorder',
                'display_name' => 'Reorder Custom Fields',
                'description' => 'Change sort order of custom fields via drag & drop',
            ],
    [
                'name' => 'custom-fields.apply-preset',
                'display_name' => 'Apply Custom Field Preset',
                'description' => 'Apply a global preset/template to the current school',
            ],
    ['name' => 'timetables.view', 'display_name' => 'View Timetables', 'description' => 'View all timetables for a school'],
    ['name' => 'timetables.create', 'display_name' => 'Create Timetables', 'description' => 'Create new timetables for a school'],
    ['name' => 'timetables.update', 'display_name' => 'Update Timetables', 'description' => 'Update existing timetables for a school'],
    ['name' => 'timetables.delete', 'display_name' => 'Delete Timetables', 'description' => 'Delete timetables for a school'],
    ['name' => 'timetables.restore', 'display_name' => 'Restore Timetables', 'description' => 'Restore deleted timetables for a school'],
    ['name' => 'timetables.force-delete', 'display_name' => 'Force Delete Timetables', 'description' => 'Permanently delete timetables for a school'],
    ['name' => 'timetable-details.view', 'display_name' => 'View Timetable Details', 'description' => 'View all timetable details for a school'],
    ['name' => 'timetable-details.create', 'display_name' => 'Create Timetable Details', 'description' => 'Create new timetable details for a school'],
    ['name' => 'timetable-details.update', 'display_name' => 'Update Timetable Details', 'description' => 'Update existing timetable details for a school'],
    ['name' => 'timetable-details.delete', 'display_name' => 'Delete Timetable Details', 'description' => 'Delete timetable details for a school'],
    ['name' => 'timetable-details.restore', 'display_name' => 'Restore Timetable Details', 'description' => 'Restore deleted timetable details for a school'],
    ['name' => 'timetable-details.force-delete', 'display_name' => 'Force Delete Timetable Details', 'description' => 'Permanently delete timetable details for a school'],
];
