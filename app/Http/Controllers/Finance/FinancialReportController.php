<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Expense;
use App\Models\Finance\Fee;
use App\Models\Finance\FeeInstallmentDetail;
use App\Models\Finance\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for generating financial reports for the school management system.
 */
class FinancialReportController extends Controller
{
    /**
     * Display the financial reporting dashboard.
     *
     * UI: resources/js/Pages/Finance/Reports/Index.vue
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        permitted('finance-reports.view');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                return $request->wantsJson()
                    ? response()->json(['error' => 'No active school found'], 400)
                    : redirect()->back()->with('error', 'No active school found.');
            }

            // Validate date range
            $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'fee_type_id' => 'nullable|exists:fee_types,id,school_id,' . $school->id,
                'payment_status' => 'nullable|in:pending,success,failed',
            ]);

            $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : Carbon::now()->startOfYear();
            $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : Carbon::now()->endOfDay();

            // Total Fees
            $totalFeesQuery = Fee::where('school_id', $school->id)
                ->when($request->input('fee_type_id'), fn($q, $feeTypeId) => $q->where('fee_type_id', $feeTypeId))
                ->whereBetween('created_at', [$startDate, $endDate]);
            $totalFees = $totalFeesQuery->sum('amount');
            $feesByType = $totalFeesQuery->join('fee_types', 'fees.fee_type_id', '=', 'fee_types.id')
                ->groupBy('fee_types.id', 'fee_types.name')
                ->select('fee_types.name as fee_type', DB::raw('SUM(fees.amount) as total'))
                ->get();

            // Total Payments
            $totalPaymentsQuery = Payment::where('school_id', $school->id)
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->when($request->input('payment_status'), fn($q, $status) => $q->where('payment_status', $status));
            $totalPayments = $totalPaymentsQuery->sum('payment_amount');
            $paymentsByMonth = $totalPaymentsQuery->groupBy(DB::raw('DATE_FORMAT(payment_date, "%Y-%m")'))
                ->select(DB::raw('DATE_FORMAT(payment_date, "%Y-%m") as month'), DB::raw('SUM(payment_amount) as total'))
                ->get();
            $paymentsByMethod = $totalPaymentsQuery->groupBy('payment_method')
                ->select('payment_method', DB::raw('SUM(payment_amount) as total'))
                ->get();

            // Outstanding Balances
            $outstandingBalances = FeeInstallmentDetail::where('school_id', $school->id)
                ->whereIn('status', ['pending', 'overdue'])
                ->whereBetween('due_date', [$startDate, $endDate])
                ->sum('amount');
            $overdueBalances = FeeInstallmentDetail::where('school_id', $school->id)
                ->where('status', 'overdue')
                ->whereBetween('due_date', [$startDate, $endDate])
                ->sum('amount');

            // Expenses
            $totalExpensesQuery = Expense::where('school_id', $school->id)
                ->whereBetween('expense_date', [$startDate, $endDate]);
            $totalExpenses = $totalExpensesQuery->sum('amount');
            $expensesByCategory = $totalExpensesQuery->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
                ->groupBy('expense_categories.id', 'expense_categories.name')
                ->select('expense_categories.name as category', DB::raw('SUM(expenses.amount) as total'))
                ->get();

            // Detailed Payments (for DataTable)
            $extraFields = [
                [
                    'field' => 'student_name',
                    'relation' => 'user',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
                [
                    'field' => 'fee_name',
                    'relation' => 'fee',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
            ];
            $detailedPayments = Payment::where('school_id', $school->id)
                ->with(['user:id,name', 'fee:id,name', 'feeInstallmentDetail:id,amount'])
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed())
                ->tableQuery($request, $extraFields);

            $data = [
                'total_fees' => $totalFees,
                'fees_by_type' => $feesByType,
                'total_payments' => $totalPayments,
                'payments_by_month' => $paymentsByMonth,
                'payments_by_method' => $paymentsByMethod,
                'outstanding_balances' => $outstandingBalances,
                'overdue_balances' => $overdueBalances,
                'total_expenses' => $totalExpenses,
                'expenses_by_category' => $expensesByCategory,
                'detailed_payments' => $detailedPayments,
                'filters' => $request->only(['start_date', 'end_date', 'fee_type_id', 'payment_status', 'search', 'sort', 'sortOrder', 'perPage', 'with_trashed']),
            ];

            if ($request->wantsJson()) {
                return response()->json($data);
            }

            return Inertia::render('Finance/Reports/Index', $data);
        } catch (\Exception $e) {
            Log::error('Failed to generate financial report: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to generate financial report'], 500)
                : redirect()->back()->with('error', 'Failed to load financial report.');
        }
    }

    /**
     * Export financial report as CSV.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function export(Request $request)
    {
        permitted('finance-reports.view');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                return redirect()->back()->with('error', 'No active school found.');
            }

            $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'fee_type_id' => 'nullable|exists:fee_types,id,school_id,' . $school->id,
                'payment_status' => 'nullable|in:pending,success,failed',
            ]);

            $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : Carbon::now()->startOfYear();
            $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : Carbon::now()->endOfDay();

            $payments = Payment::where('school_id', $school->id)
                ->with(['user:id,name', 'fee:id,name', 'feeInstallmentDetail:id,amount'])
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->when($request->input('payment_status'), fn($q, $status) => $q->where('payment_status', $status))
                ->get();

            $callback = function () use ($payments) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Student', 'Fee', 'Installment Amount', 'Payment Amount', 'Currency', 'Status', 'Reference', 'Date', 'Description']);
                foreach ($payments as $payment) {
                    fputcsv($file, [
                        $payment->user->name ?? 'N/A',
                        $payment->fee->name ?? 'N/A',
                        $payment->feeInstallmentDetail->amount ?? 'N/A',
                        $payment->payment_amount,
                        $payment->payment_currency,
                        $payment->payment_status,
                        $payment->payment_reference,
                        $payment->payment_date->format('Y-m-d H:i:s'),
                        $payment->payment_description,
                    ]);
                }
                fclose($file);
            };

            return response()->streamDownload($callback, 'financial-report-' . now()->format('Y-m-d') . '.csv', [
                'Content-Type' => 'text/csv',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to export financial report: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export financial report.');
        }
    }
}