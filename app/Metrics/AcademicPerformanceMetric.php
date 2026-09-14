<?php

namespace App\Metrics;

use App\Models\Academic\Assignment;
use App\Models\Academic\TermResult;
use Illuminate\Support\Facades\Cache;

final class AcademicPerformanceMetric extends AbstractMetric
{
    private const CACHE_TTL = 300; // 5 minutes

    // Average Score Across All Subjects (Current Term)
    public function averageScore(): array
    {
        $cacheKey = 'academic.avg_score.' . GetSchoolModel()?->id;

        $avg = Cache::remember($cacheKey, self::CACHE_TTL, function () {
            return TermResult::where('term_id', \App\Facades\Academic::currentTerm()?->id)->avg('score');
        });

        return [
            'value'    => $avg ? number_format($avg, 1) . '%' : '—',
            'title'    => 'Average Score',
            'icon'     => 'chart-line',
            'color'    => $avg >= 70 ? 'text-green-600' : ($avg >= 50 ? 'text-yellow-600' : 'text-red-600'),
            'bg'       => $avg >= 70 ? 'bg-green-100' : ($avg >= 50 ? 'bg-yellow-100' : 'bg-red-100'),
            'severity' => $avg < 50 ? 'warning' : 'info',
        ];
    }

    // Number of Students Below 40% (At Risk)
    public function studentsAtRisk(): array
    {
        $cacheKey = 'academic.at_risk.' . GetSchoolModel()?->id;

        $count = Cache::remember($cacheKey, self::CACHE_TTL, function () {
            return TermResult::where('term_id', \App\Facades\Academic::currentTerm()?->id)
                ->where('score', '<', 40)
                ->distinct('student_id')
                ->count('student_id');
        });

        return [
            'value'    => $count,
            'title'    => 'Students At Risk',
            'icon'     => 'exclamation-triangle',
            'color'    => $count > 0 ? 'text-red-600' : 'text-green-600',
            'bg'       => $count > 0 ? 'bg-red-100' : 'bg-green-100',
            'severity' => $count > 10 ? 'critical' : ($count > 0 ? 'warning' : 'info'),
        ];
    }

    // Assignment Completion Rate
    public function assignmentCompletionRate(): array
    {
        $cacheKey = 'academic.assignment_rate.' . GetSchoolModel()?->id;

        $rate = Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $assigned = Assignment::where('term_id', \App\Facades\Academic::currentTerm()?->id)->count();
            $submitted = Assignment::where('term_id', \App\Facades\Academic::currentTerm()?->id)
                ->whereHas('submissions')
                ->count();

            return $assigned > 0 ? round(($submitted / $assigned) * 100, 1) : 0;
        });

        return [
            'value'    => $rate . '%',
            'title'    => 'Assignment Completion',
            'icon'     => 'clipboard-check',
            'color'    => $rate >= 80 ? 'text-green-600' : ($rate >= 60 ? 'text-yellow-600' : 'text-red-600'),
            'bg'       => $rate >= 80 ? 'bg-green-100' : ($rate >= 60 ? 'bg-yellow-100' : 'bg-red-100'),
            'severity' => $rate < 60 ? 'warning' : 'info',
        ];
    }

    // Top Performing Class (by average)
    public function topClass(): array
    {
        $cacheKey = 'academic.top_class.' . GetSchoolModel()?->id;

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () {
            return TermResult::where('term_id', \App\Facades\Academic::currentTerm()?->id)
                ->selectRaw('class_section_id, AVG(score) as avg_score')
                ->groupBy('class_section_id')
                ->orderByDesc('avg_score')
                ->with('classSection:id,name')
                ->first();
        });

        return [
            'value'    => $data?->classSection?->name ?? '—',
            'title'    => 'Top Class',
            'icon'     => 'trophy',
            'color'    => 'text-indigo-600',
            'bg'       => 'bg-indigo-100',
            'severity' => 'info',
            'subtitle' => $data ? number_format($data->avg_score, 1) . '%' : null,
        ];
    }

    protected function baseQuery()
    {
        return TermResult::query()->where('term_id', \App\Facades\Academic::currentTerm()?->id);
    }
}
