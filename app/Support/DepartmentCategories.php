<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Manages fixed department categories for grouping departments and roles.
 * These categories are predefined and cannot be modified by users.
 * Used for determining dashboard types, widget visibility, and priority resolution.
 *
 * @package App\Support
 */
class DepartmentCategories
{
    /**
     * Fixed department categories with labels, priorities, and descriptions.
     * Priorities: Lower value = higher priority (used for multi-role conflict resolution).
     *
     * @var array<string, array{label: string, priority: int, description: string}>
     */
    public const CATEGORIES = [
        'leadership' => [
            'label' => 'Leadership & Administration',
            'priority' => 10,
            'description' => 'School owners, principals, vice principals, and top-level managers.',
        ],
        'academic' => [
            'label' => 'Academic Affairs',
            'priority' => 20,
            'description' => 'Teaching staff, heads of departments, academic coordination, examinations.',
        ],
        'finance' => [
            'label' => 'Finance & Accounts',
            'priority' => 30,
            'description' => 'Bursary, accountants, fee management, budgeting, payroll.',
        ],
        'student_support' => [
            'label' => 'Student Support & Welfare',
            'priority' => 40,
            'description' => 'Counseling, discipline, health, clinic, special needs, and wellbeing.',
        ],
        'hostel' => [
            'label' => 'Hostel & Boarding',
            'priority' => 50,
            'description' => 'Housemasters, hostel supervisors, and boarding operations.',
        ],
        'sport' => [
            'label' => 'Sports & Physical Education',
            'priority' => 60,
            'description' => 'Sports masters, coaches, and extracurricular activities.',
        ],
        'transport' => [
            'label' => 'Transport & Logistics',
            'priority' => 70,
            'description' => 'School bus management, drivers, transport officers.',
        ],
        'ict' => [
            'label' => 'ICT & Library Services',
            'priority' => 80,
            'description' => 'ICT administrators, library staff, digital learning support.',
        ],
        'operations' => [
            'label' => 'Operations & Maintenance',
            'priority' => 90,
            'description' => 'Security, maintenance, cleaning, catering, groundskeeping.',
        ],
        'communication' => [
            'label' => 'Communication & Engagement',
            'priority' => 100,
            'description' => 'Notices, events, parent communication, newsletters, PR.',
        ],
        'admissions' => [
            'label' => 'Admissions & Records',
            'priority' => 110,
            'description' => 'Admissions, student records, student registration, and data management.',
        ],
        'hr' => [
            'label' => 'Human Resource Management',
            'priority' => 120,
            'description' => 'Recruitment, staff welfare, and performance management.',
        ],
        'student' => [
            'label' => 'Student',
            'priority' => 5,
            'description' => 'Students accessing their personal dashboard.',
        ],
        'parent' => [
            'label' => 'Parent/Guardian',
            'priority' => 6,
            'description' => 'Parents and guardians accessing child-related information.',
        ],
        'general' => [
            'label' => 'General / Miscellaneous',
            'priority' => 999,
            'description' => 'Fallback category for unclassified or custom departments/roles.',
        ],
    ];

    /**
     * Get all department categories.
     *
     * @return array<string, array{label: string, priority: int, description: string}>
     */
    public static function getAll(): array
    {
        return self::CATEGORIES;
    }

    /**
     * Get all valid category keys.
     *
     * @return array<string>
     */
    public static function getKeys(): array
    {
        return array_keys(self::CATEGORIES);
    }

    /**
     * Get the priority for a given category key.
     *
     * @param string $key The category key.
     * @return int The priority (defaults to 999 if invalid).
     */
    public static function getPriority(string $key): int
    {
        return self::CATEGORIES[$key]['priority'] ?? 999;
    }

    /**
     * Get the label for a given category key.
     *
     * @param string $key The category key.
     * @return string The label (defaults to 'Unknown' if invalid).
     */
    public static function getLabel(string $key): string
    {
        return self::CATEGORIES[$key]['label'] ?? 'Unknown';
    }

    /**
     * Validate if a category key is valid.
     *
     * @param string $key The category key.
     * @return bool True if valid, false otherwise.
     */
    public static function isValid(string $key): bool
    {
        return array_key_exists($key, self::CATEGORIES);
    }

    /**
     * Get categories sorted by priority (ascending).
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getSortedByPriority(): Collection
    {
        return collect(self::CATEGORIES)->sortBy('priority');
    }
}