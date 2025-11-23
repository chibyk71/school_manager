<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use SaKanjo\EasyMetrics\Enums\Range;

class StatisticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $model   = ucfirst($request->input('model', 'student')); // student, staff, attendance, etc.
            $type    = $request->input('type', 'value');             // value | trend | breakdown
            $interval = $request->input('interval', 'monthly');
            $range   = Range::tryFrom($request->input('range', 'YTD')) ?? Range::YTD;
            $groupBy = $request->input('groupBy');
            $filters = $request->input('filters', []);

            // Special handling for unified attendance
            if ($model === 'attendance') {
                $metric = app(\App\Metrics\AttendanceMetric::class);

                return match ($type) {
                    'trend'      => response()->json(['success' => true, 'data' => $metric->trendDaily($filters['attendable_type'] ?? 'student')]),
                    'breakdown'  => response()->json(['success' => true, 'data' => $metric->statusBreakdown($filters['attendable_type'] ?? 'student')]),
                    default      => response()->json(['success' => true, 'data' => $metric->todayRate($filters['attendable_type'] ?? 'student')]),
                };
            }

            $metricClass = "App\\Metrics\\{$model}Metric";
            if (!class_exists($metricClass)) {
                return response()->json(['error' => 'Invalid model'], 400);
            }

            $metric = app($metricClass);

            $data = match ($type) {
                'trend'     => $metric->getTrend($interval, 'count', null, $range, $filters),
                'breakdown' => $groupBy
                    ? $metric->getBreakdown($groupBy, 'count', null, $filters)
                    : throw new \InvalidArgumentException('groupBy required for breakdown'),
                default     => $metric->getValue('count', null, $filters, true, 30),
            };

            return response()->json([
                'success'    => true,
                'data'       => $data,
                'timestamp'  => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Metrics API error', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch metrics'], 500);
        }
    }
}
