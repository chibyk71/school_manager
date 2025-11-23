<?php

namespace App\Metrics;

use App\Models\Finance\FeeAssignment;
use App\Models\Finance\Payment;
use App\Models\Finance\Transaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class FinanceMetric extends AbstractMetric
{
    protected string $model = Payment::class;

    private const CACHE_TTL = 300; // 5 minutes — perfect for dashboards

    // Fees Collected (Term, Month, or All Time)
    public function collected(array $filters = []): array
    {
        $cacheKey = 'finance.collected.' . GetSchoolModel()->id ?? '' . '.' . md5(serialize($filters));

        $amount = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters) {
            $query = Payment::query()->where('status', 'successful');

            if (isset($filters['term_id'])) {
                $query->where('term_id', $filters['term_id']);
            } elseif (isset($filters['month'])) {
                $query->whereMonth('paid_at', $filters['month'])
                      ->whereYear('paid_at', $filters['year'] ?? now()->year);
            } else {
                // Current term
                $term = GetCurrentTerm();
                if ($term) $query->where('term_id', $term->id);
            }

            return $query->sum('amount');
        });

        return [
            'value'    => '₦' . number_format($amount, 0),
            'title'    => 'Fees Collected',
            'icon'     => 'currency-naira',
            'color'    => 'text-emerald-600',
            'bg'       => 'bg-emerald-100',
            'trend'    => $this->trendIndicator('collected', $amount),
        ];
    }

    // Outstanding Fees
    public function outstanding(): array
    {
        $cacheKey = 'finance.outstanding.' . GetSchoolModel()->id ?? '';

        [$balance, $count] = Cache::remember($cacheKey, self::CACHE_TTL, function () {
            return [
                FeeAssignment::whereColumn('amount_due', '>', 'amount_paid')
                    ->sum(DB::raw('amount_due - amount_paid')),

                FeeAssignment::whereColumn('amount_due', '>', 'amount_paid')
                    ->count(),
            ];
        });

        return [
            'value'    => '₦' . number_format($balance, 0) . " ({$count} students)",
            'title'    => 'Outstanding Fees',
            'icon'     => 'alert-triangle',
            'color'    => $balance > 10_000_000 ? 'text-red-600' : 'text-orange-600',
            'bg'       => $balance > 10_000_000 ? 'bg-red-100' : 'bg-orange-100',
            'severity' => $balance > 10_000_000 ? 'critical' : 'warning',
        ];
    }

    // Collection Rate %
    public function collectionRate(): array
    {
        $cacheKey = 'finance.collection_rate.' . GetSchoolModel()?->id;

        $rate = Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $expected = FeeAssignment::sum('amount_due');
            $collected = Payment::where('payment_status', 'successful')
                ->sum('payment_amount');

            return $expected > 0 ? round(($collected / $expected) * 100, 1) : 0;
        });

        $color = $rate >= 90 ? 'emerald' : ($rate >= 70 ? 'yellow' : 'red');

        return [
            'value'    => $rate . '%',
            'title'    => 'Collection Rate',
            'icon'     => 'trending-up',
            'color'    => "text-{$color}-600",
            'bg'       => "bg-{$color}-100",
            'progress' => $rate,
        ];
    }

    // Payment Method Breakdown (Chart.js ready)
    public function paymentMethods(): array
    {
        $cacheKey = 'finance.payment_methods.' . GetSchoolModel()->id ?? '';

        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $data = Payment::where('school_id', GetSchoolModel()->id)
                ->where('status', 'successful')
                ->select('payment_method', DB::raw('SUM(amount) as total'))
                ->groupBy('payment_method')
                ->orderByDesc('total')
                ->get();

            return [
                'labels' => $data->pluck('payment_method')->map(fn($m) => ucwords(str_replace('_', ' ', $m)))->toArray(),
                'datasets' => [[
                    'label' => 'Amount (₦)',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => [
                        '#10b981', '#f59e0b', '#3b82f6', '#8b5cf6', '#ef4444', '#06b6d4'
                    ],
                ]],
            ];
        });
    }

    // Late Fee Revenue (from Transaction ledger — audit-proof)
    public function lateFeeRevenue(): array
    {
        $cacheKey = 'finance.late_fees.' . GetSchoolModel()?->id;

        $amount = Cache::remember($cacheKey, self::CACHE_TTL, function () {
            return Transaction::where('category', 'late_fee')
                ->where('transaction_type', 'income')
                ->sum('amount');
        });

        return [
            'value' => '₦' . number_format($amount, 0),
            'title' => 'Late Fee Revenue',
            'icon'  => 'clock',
            'color' => 'text-purple-600',
            'bg'    => 'bg-purple-100',
        ];
    }

    // Top Debtors (for admin dashboard)
    public function topDebtors(int $limit = 10): array
    {
        $cacheKey = "finance.debtors.top{$limit}." . GetSchoolModel()?->id;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($limit) {
            return FeeAssignment::with(['student.user', 'student.currentClass'])
                ->whereColumn('amount_due', '>', 'amount_paid')
                ->select('student_id', DB::raw('SUM(amount_due - amount_paid) as balance'))
                ->groupBy('student_id')
                ->orderByDesc('balance')
                ->limit($limit)
                ->get()
                ->map(fn($row) => [
                    'name'    => $row->student?->user?->name ?? 'Unknown Student',
                    'class'   => $row->student?->currentClass?->name ?? '—',
                    'balance' => '₦' . number_format($row->balance),
                    'raw'     => $row->balance,
                ])->toArray();
        });
    }

    // Required by AbstractMetric
    protected function buildBaseQuery(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        return Payment::query()
            ->where('status', 'successful');
    }

    protected function getTitle(): string
    {
        return 'Finance Overview';
    }

    // Simple trend indicator
    private function trendIndicator(string $key, float $current): string
    {
        $previous = Cache::get("trend.{$key}." . GetSchoolModel()?->id, $current);
        $diff = $current - $previous;
        Cache::put("trend.{$key}." . GetSchoolModel()?->id, $current, now()->addHours(24));
        return $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'neutral');
    }
}
