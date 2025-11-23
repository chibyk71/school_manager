<?php

namespace App\Metrics;

use App\Models\Exam\TermResult;
use App\Models\Resource\Assignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AcademicPerformanceMetric extends AbstractMetric
{
    protected string $model = Result::class;   // primary model

    /* ------------------------------------------------------------------ */
    /* PUBLIC API – custom because we join multiple tables                */
    /* ------------------------------------------------------------------ */

    /** Average grade for the current term */
    public function averageGrade(): array
    {
        $avg = TermResult::where('term_id', currentTerm()?->id)->avg('score');

        return [
            'value' => round($avg, 1),
            'title' => 'Average Grade',
            'image' => '/assets/img/icons/grade.svg',
        ];
    }

    /** Students below 50% (at-risk) */
    public function atRisk(): array
    {
        $count = TermResult::where('term_id', currentTerm()?->id)
            ->where('score', '<', 50)
            ->distinct('student_id')
            ->count('student_id');

        return [
            'value' => $count,
            'title' => 'Students Below 50%',
            'severity' => $count > 0 ? 'bg-red-200/50' : 'bg-green-200/50',
        ];
    }

    /** Assignment / CA submission rate */
    public function submissionRate(): array
    {
        $assigned = Assignment::where('term_id', currentTerm()?->id)->count();
        $submitted = Assignment::where('term_id', currentTerm()?->id)
            ->whereNotNull('submitted_at')
            ->count();

        $rate = $assigned > 0 ? round(($submitted / $assigned) * 100, 1) : 0;

        return [
            'value' => "{$rate}%",
            'title' => 'CA Submission Rate',
        ];
    }

    /** Subject performance doughnut */
    public function subjectBreakdown(): array
    {
        $data = TermResult::where('term_id', currentTerm()?->id)
            ->join('subjects', 'results.subject_id', '=', 'subjects.id')
            ->select('subjects.name', DB::raw('AVG(results.score) as avg_score'))
            ->groupBy('subjects.id', 'subjects.name')
            ->orderByDesc('avg_score')
            ->get();

        return [
            'labels' => $data->pluck('name')->toArray(),
            'data'   => $data->pluck('avg_score')->map(fn($v) => round($v, 1))->toArray(),
        ];
    }

    /** Termly grade trend */
    public function termTrend(): array
    {
        $base = TermResult::query()
            ->selectRaw('terms.name as term, AVG(results.score) as avg')
            ->join('terms', 'results.term_id', '=', 'terms.id')
            ->groupBy('terms.id', 'terms.name')
            ->orderBy('terms.start_date');

        $sub = $base->getQuery();
        $wrapped = DB::table(DB::raw("({$sub->toSql()}) as t"))->mergeBindings($sub);

        $metric = \SaKanjo\EasyMetrics\Metrics\Trend::make($this->model)
            ->modifyQuery(fn($q) => $q->fromSub($wrapped, 't'));

        // EasyMetrics expects a date column – we fake it with a dummy
        $metric->range(\SaKanjo\EasyMetrics\Enums\Range::YTD);

        // Manual extraction
        $rows = $base->get();
        $labels = $rows->pluck('term')->toArray();
        $data   = $rows->pluck('avg')->map(fn($v) => round($v, 1))->toArray();

        return compact('labels', 'data');
    }

    /* ------------------------------------------------------------------ */
    /* BASE QUERY – default to current term                               */
    /* ------------------------------------------------------------------ */
    protected function buildBaseQuery(array $filters): Builder
    {
        return TermResult::query()->where('term_id', currentTerm()?->id);
    }

    protected function getTitle(): string { return 'Academic Performance'; }
}
