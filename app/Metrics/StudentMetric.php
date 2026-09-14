<?php

namespace App\Metrics;

use App\Models\Student\Student;
use Illuminate\Support\Facades\Cache;

final class StudentMetric extends AbstractMetric
{
    private const CACHE_TTL = 300;

    public function totalStudents(): array
    {
        $cacheKey = 'student.total.' . GetSchoolModel()?->id;

        $count = Cache::remember($cacheKey, self::CACHE_TTL, function () {
            return Student::query()->count();
        });

        return [
            'value'    => number_format($count),
            'title'    => 'Total Students',
            'icon'     => 'users',
            'color'    => 'text-blue-600',
            'bg'       => 'bg-blue-100',
            'severity' => 'info',
        ];
    }

    public function activeStudents(): array
    {
        $cacheKey = 'student.active.' . GetSchoolModel()?->id;

        $count = Cache::remember($cacheKey, self::CACHE_TTL, function () {
            // Historical: prefer explicit session selection or Academic::currentSession()
            return Student::query()
                ->where('status', 'active')
                ->count();
        });

        return [
            'value'    => number_format($count),
            'title'    => 'Active Students',
            'icon'     => 'user-check',
            'color'    => 'text-green-600',
            'bg'       => 'bg-green-100',
            'severity' => 'info',
        ];
    }
}
