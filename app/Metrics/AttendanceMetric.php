<?php

namespace App\Metrics;

use App\Models\Misc\AttendanceLedger;
use App\Models\Misc\AttendanceSession;
use App\Models\Academic\Student;
use App\Models\Employee\Staff;
use App\Models\Employee\LeaveRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use SaKanjo\EasyMetrics\Enums\Range;
use Illuminate\Support\Facades\DB;

/**
 * UNIFIED ATTENDANCE METRIC – Fixed for multi-tenant + no duplicate id
 * Works perfectly with your SAAS setup (multi-school, branches, sections)
 */
class AttendanceMetric extends AbstractMetric
{
    protected string $model = AttendanceLedger::class;

    /* ------------------------------------------------------------------ */
    /* PUBLIC API                                                         */
    /* ------------------------------------------------------------------ */

    public function studentTodayRate(): array     { return $this->todayRate('student'); }
    public function studentSevenDayAvg(): array   { return $this->sevenDayAvg('student'); }
    public function studentTrend(): array         { return $this->trendDaily('student'); }
    public function studentStatusBreakdown(): array { return $this->statusBreakdown('student'); }

    public function staffTodayRate(): array       { return $this->todayRate('staff'); }
    public function staffSevenDayAvg(): array     { return $this->sevenDayAvg('staff'); }
    public function staffTrend(): array           { return $this->trendDaily('staff'); }
    public function staffStatusBreakdown(): array { return $this->statusBreakdown('staff'); }

    public function pendingLeaves(): array        { return $this->pendingLeaveRequests(); }
    // public function approvedLeavesToday(): array  { return $this->approvedLeavesToday(); }

    /* ------------------------------------------------------------------ */
    /* CORE METHODS                                                       */
    /* ------------------------------------------------------------------ */

    public function todayRate(string $type): array
    {
        $rate = $this->calculateTodayRate($type);

        return [
            'value'    => $rate . '%',
            'title'    => ucfirst($type) . ' Attendance Today',
            'image'    => "/assets/img/icons/{$type}-attendance.svg",
            'severity' => $rate >= 90 ? 'bg-green-200/50' : ($rate >= 70 ? 'bg-yellow-200/50' : 'bg-red-200/50'),
        ];
    }

    public function sevenDayAvg(string $type): array
    {
        $total = 0;
        $days  = 0;

        for ($i = 6; $i >= 0; $i--) {
            $date = today()->subDays($i)->toDateString();
            $rate = $this->calculateRateForDate($date, $type);
            if ($rate !== null) {
                $total += $rate;
                $days++;
            }
        }

        $avg = $days > 0 ? round($total / $days, 1) : 0;

        return [
            'value' => $avg . '%',
            'title' => ucfirst($type) . ' 7-Day Average',
            'image' => "/assets/img/icons/{$type}-attendance.svg",
        ];
    }

    public function trendDaily(string $type): array
    {
        return $this->trend('daily', Range::MTD, ['type' => $type]);
    }

    public function statusBreakdown(string $type): array
    {
        return $this->breakdown('status', ['type' => $type, 'date' => today()->toDateString()]);
    }

    /* ------------------------------------------------------------------ */
    /* LEAVE METRICS                                                       */
    /* ------------------------------------------------------------------ */

    public function pendingLeaveRequests(): array
    {
        $count = LeaveRequest::where('status', 'pending')
            ->when($this->currentSchoolId(), fn($q) => $q->where('school_id', $this->currentSchoolId()))
            ->count();

        return [
            'value'    => $count,
            'title'    => 'Pending Leave Requests',
            'image'    => '/assets/img/icons/leave-pending.svg',
            'severity' => $count > 5 ? 'bg-red-200/50' : 'bg-yellow-200/50',
        ];
    }

    public function approvedLeavesToday(): array
    {
        $count = LeaveRequest::where('status', 'approved')
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->when($this->currentSchoolId(), fn($q) => $q->where('school_id', $this->currentSchoolId()))
            ->count();

        return [
            'value' => $count,
            'title' => 'On Leave Today',
            'image' => '/assets/img/icons/leave-approved.svg',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* PRIVATE HELPERS                                                    */
    /* ------------------------------------------------------------------ */

    private function calculateTodayRate(string $type): float
    {
        $session = $this->getTodaySession($type);
        if (!$session) return 0.0;

        $total   = AttendanceLedger::where('attendance_session_id', $session->id)->count();
        $present = AttendanceLedger::where('attendance_session_id', $session->id)
            ->whereIn('status', ['present', 'late'])
            ->count();

        return $total > 0 ? round(($present / $total) * 100, 1) : 0.0;
    }

    private function calculateRateForDate(string $date, string $type): ?float
    {
        $session = AttendanceSession::whereDate('date_effective', $date)
            ->when($this->currentSchoolId(), fn($q) => $q->where('school_id', $this->currentSchoolId()))
            ->where('type', $type)
            ->first();

        if (!$session) return null;

        $total   = AttendanceLedger::where('attendance_session_id', $session->id)->count();
        $present = AttendanceLedger::where('attendance_session_id', $session->id)
            ->whereIn('status', ['present', 'late'])
            ->count();

        return $total > 0 ? round(($present / $total) * 100, 1) : null;
    }

    private function getTodaySession(string $type): ?AttendanceSession
    {
        return AttendanceSession::whereDate('date_effective', today())
            ->when($this->currentSchoolId(), fn($q) => $q->where('school_id', $this->currentSchoolId()))
            ->where('type', $type)
            ->first();
    }

    /** Safely get current school ID (handles null auth + multi-tenant) */
    private function currentSchoolId(): ?string
    {
        return Auth::check() ? (Auth::user()->school_id ?? GetSchoolModel()?->id) : GetSchoolModel()?->id;
    }

    /* ------------------------------------------------------------------ */
    /* BASE QUERY – FIXED: No duplicate id + safe school scoping          */
    /* ------------------------------------------------------------------ */

    /**
     * This is the ONLY version that survives EasyMetrics double wrapping.
     * We select ONLY the columns we need from attendance_ledgers
     * and prefix everything → no chance of duplicate id/school_id/created_at
     */
    protected function buildBaseQuery(array $filters): Builder
    {
        $ledger = (new AttendanceLedger)->getTable();
        $session = (new AttendanceSession)->getTable();

        $query = AttendanceLedger::query()
            ->from($ledger)
            ->select([
                "{$ledger}.id as ledger_id",
                "{$ledger}.attendable_type",
                "{$ledger}.attendable_id",
                "{$ledger}.status",
                "{$ledger}.created_at",
                "{$ledger}.updated_at",
                "{$ledger}.deleted_at",
                "{$session}.type as session_type",
                "{$session}.date_effective",
                "{$session}.school_id",
            ])
            ->join($session, "{$ledger}.attendance_session_id", '=', "{$session}.id")
            ->when($this->schoolId(), fn($q) => $q->where("{$session}.school_id", $this->schoolId()));

        if (!empty($filters['type'])) {
            $modelClass = $filters['type'] === 'student' ? Student::class : Staff::class;
            $query->where("{$ledger}.attendable_type", $modelClass)
                  ->where("{$session}.type", $filters['type']);
        }

        if (!empty($filters['date'])) {
            $query->whereDate("{$session}.date_effective", $filters['date']);
        }

        if (!empty($filters['status'])) {
            $query->where("{$ledger}.status", $filters['status']);
        }

        return $query;
    }

    /* ------------------------------------------------------------------ */
    /* METADATA                                                           */
    /* ------------------------------------------------------------------ */

    protected function getTitle(): string
    {
        return 'Attendance Overview';
    }

    private function schoolId(): ?string
    {
        $schoolId = GetSchoolModel()?->id;

        if ($schoolId !== null) {
            return $schoolId;
        }

        if (auth()->check()) {
            return auth()->user()->schools()->first()?->id;
        }

        return null;
    }

    protected function getImage(): string
    {
        return '/assets/img/icons/attendance.svg';
    }
}