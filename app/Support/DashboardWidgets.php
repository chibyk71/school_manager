<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Manages available dashboard widgets.
 * Each widget defines description, required permissions, and associated dashboards.
 *
 * @package App\Support
 */
class DashboardWidgets
{
    /**
     * List of all dashboard widgets.
     * Key = widget slug.
     *
     * @var array<string, array{description: string, required_permissions: array<string>, dashboards: array<string>}>
     */
    public const WIDGETS = [
        'enrollment-stats' => [
            'description' => 'Displays current enrollment statistics by class and gender.',
            'required_permissions' => ['view-enrollments', 'view-statistics'],
            'dashboards' => ['admin', 'academic'],
        ],
        'fee-collection' => [
            'description' => 'Shows fee collection status, overdue payments, and revenue summary.',
            'required_permissions' => ['view-fees', 'view-finance-reports'],
            'dashboards' => ['admin', 'finance'],
        ],
        'attendance-overview' => [
            'description' => 'Provides daily/weekly attendance trends and absentee lists.',
            'required_permissions' => ['view-attendance'],
            'dashboards' => ['academic', 'teacher', 'student_support'],
        ],
        'exam-results' => [
            'description' => 'Summarizes recent exam results, top performers, and averages.',
            'required_permissions' => ['view-exams', 'view-results'],
            'dashboards' => ['academic', 'teacher'],
        ],
        'student-profile' => [
            'description' => 'Quick view of personal student details, grades, and activities.',
            'required_permissions' => ['view-own-profile'],
            'dashboards' => ['student'],
        ],
        'child-progress' => [
            'description' => 'Tracks child\'s academic progress, attendance, and notices.',
            'required_permissions' => ['view-child-info'],
            'dashboards' => ['parent'],
        ],
        // Add more widgets as needed...
    ];

    /**
     * Get all widgets.
     *
     * @return array<string, array{description: string, required_permissions: array<string>, dashboards: array<string>}>
     */
    public static function getAll(): array
    {
        return self::WIDGETS;
    }

    /**
     * Get widgets for a specific dashboard type.
     *
     * @param string $dashboard The dashboard type (e.g., 'admin').
     * @return \Illuminate\Support\Collection
     */
    public static function getForDashboard(string $dashboard): Collection
    {
        return collect(self::WIDGETS)->filter(function ($widget) use ($dashboard) {
            return in_array($dashboard, $widget['dashboards']);
        });
    }

    /**
     * Get required permissions for a widget.
     *
     * @param string $key The widget key.
     * @return array<string> The required permissions (empty if invalid).
     */
    public static function getRequiredPermissions(string $key): array
    {
        return self::WIDGETS[$key]['required_permissions'] ?? [];
    }

    /**
     * Check if a widget exists.
     *
     * @param string $key The widget key.
     * @return bool True if exists, false otherwise.
     */
    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::WIDGETS);
    }
}