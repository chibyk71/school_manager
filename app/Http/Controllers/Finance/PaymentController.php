<?php

namespace App\Http\Controllers\Finance;

use App\Events\PaymentReceived;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Models\Finance\Fee;
use App\Models\Finance\FeeInstallmentDetail;
use App\Models\Finance\Payment;
use App\Models\User;
use App\Notifications\PaymentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

/**
 * Controller for managing payments in the school management system.
 */
class PaymentController extends Controller
{
    /**
     * Display a listing of payments with dynamic querying.
     *
     * UI: resources/js/Pages/Finance/Payments/Index.vue
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        permitted('payments.view');

        try {
            $extraFields = [
                [
                    'field' => 'school_name',
                    'relation' => 'school',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
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

            $query = Payment::with(['school:id,name', 'user:id,name', 'fee:id,name', 'feeInstallmentDetail:id,amount'])
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            $payments = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($payments);
            }

            return Inertia::render('Finance/Payments/Index', [
                'payments' => $payments,
                'filters' => $request->only(['search', 'sort', 'sortOrder', 'perPage', 'with_trashed']),
                'fees' => Fee::select('id', 'name')->where('school_id', GetSchoolModel()->id)->get(),
                'users' => User::select('id', 'name')->where('school_id', GetSchoolModel()->id)->get(),
                'feeInstallmentDetails' => FeeInstallmentDetail::select('id', 'amount')->where('school_id', GetSchoolModel()->id)->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch payments: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch payments'], 500)
                : redirect()->back()->with('error', 'Failed to load payments.');
        }
    }

    /**
     * Show the form for creating a new payment.
     *
     * UI: resources/js/Pages/Finance/Payments/Create.vue
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        permitted('payments.create');

        return Inertia::render('Finance/Payments/Create', [
            'fees' => Fee::select('id', 'name')->where('school_id', GetSchoolModel()->id)->get(),
            'users' => User::select('id', 'name')->where('school_id', GetSchoolModel()->id)->get(),
            'feeInstallmentDetails' => FeeInstallmentDetail::select('id', 'amount')->where('school_id', GetSchoolModel()->id)->get(),
        ]);
    }

    /**
     * Store a newly created payment in storage.
     *
     * @param StorePaymentRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StorePaymentRequest $request)
    {
        permitted('payments.create');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                return $request->wantsJson()
                    ? response()->json(['error' => 'No active school found'], 400)
                    : redirect()->back()->with('error', 'No active school found.');
            }

            $validated = $request->validated();
            $validated['school_id'] = $school->id;

            $payment = Payment::create($validated);

            // Update FeeInstallmentDetail status if applicable
            if ($payment->feeInstallmentDetail && $payment->payment_status === 'success') {
                $payment->feeInstallmentDetail->update([
                    'status' => 'paid',
                    'paid_date' => $payment->payment_date,
                ]);
            }

            PaymentReceived::dispatch($payment);

            // Notify finance managers and the student
            $recipients = User::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->where('name', 'finance_manager'))
                ->get()
                ->merge(User::where('id', $payment->user_id)->get());

            Notification::send($recipients, new PaymentNotification($payment, 'created'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Payment created successfully', 'payment' => $payment], 201)
                : redirect()->route('payments.index')->with('success', 'Payment created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create payment: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create payment'], 500)
                : redirect()->back()->with('error', 'Failed to create payment.')->withInput();
        }
    }

    /**
     * Display the specified payment.
     *
     * UI: resources/js/Pages/Finance/Payments/Show.vue
     *
     * @param Request $request
     * @param Payment $payment
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Payment $payment)
    {
        permitted('payments.view');

        try {
            $payment->load(['school:id,name', 'user:id,name', 'fee:id,name', 'feeInstallmentDetail:id,amount']);
            return response()->json(['payment' => $payment]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch payment: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch payment'], 500);
        }
    }

    /**
     * Show the form for editing the specified payment.
     *
     * UI: resources/js/Pages/Finance/Payments/Edit.vue
     *
     * @param Payment $payment
     * @return \Inertia\Response
     */
    public function edit(Payment $payment)
    {
        permitted('payments.edit');

        return Inertia::render('Finance/Payments/Edit', [
            'payment' => $payment->load(['school:id,name', 'user:id,name', 'fee:id,name', 'feeInstallmentDetail:id,amount']),
            'fees' => Fee::select('id', 'name')->where('school_id', GetSchoolModel()->id)->get(),
            'users' => User::select('id', 'name')->where('school_id', GetSchoolModel()->id)->get(),
            'feeInstallmentDetails' => FeeInstallmentDetail::select('id', 'amount')->where('school_id', GetSchoolModel()->id)->get(),
        ]);
    }

    /**
     * Update the specified payment in storage.
     *
     * @param UpdatePaymentRequest $request
     * @param Payment $payment
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdatePaymentRequest $request, Payment $payment)
    {
        permitted('payments.edit');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                return $request->wantsJson()
                    ? response()->json(['error' => 'No active school found'], 400)
                    : redirect()->back()->with('error', 'No active school found.');
            }

            $validated = $request->validated();
            $payment->update($validated);

            // Update FeeInstallmentDetail status if applicable
            if ($payment->wasChanged('payment_status') && $payment->feeInstallmentDetail) {
                $payment->feeInstallmentDetail->update([
                    'status' => $payment->payment_status === 'success' ? 'paid' : 'pending',
                    'paid_date' => $payment->payment_status === 'success' ? $payment->payment_date : null,
                ]);
            }

            // Notify finance managers and the student if key fields changed
            if ($payment->wasChanged(['payment_amount', 'payment_status', 'payment_date', 'fee_installment_detail_id', 'fee_id'])) {
                $recipients = User::where('school_id', $school->id)
                    ->whereHas('roles', fn($query) => $query->where('name', 'finance_manager'))
                    ->get()
                    ->merge(User::where('id', $payment->user_id)->get());

                Notification::send($recipients, new PaymentNotification($payment, 'updated'));
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Payment updated successfully', 'payment' => $payment])
                : redirect()->route('payments.index')->with('success', 'Payment updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update payment: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update payment'], 500)
                : redirect()->back()->with('error', 'Failed to update payment.')->withInput();
        }
    }

    /**
     * Remove the specified payment(s) from storage (soft or force delete).
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        permitted('payments.delete');

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:payments,id',
                'force' => 'boolean',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');
            $query = $forceDelete ? Payment::whereIn('id', $ids) : Payment::whereIn('id', $ids)->withTrashed();
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? 'Payment(s) deleted successfully' : 'No payments were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('payments.index')->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Failed to delete payments: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete payment(s)'], 500)
                : redirect()->back()->with('error', 'Failed to delete payment(s).');
        }
    }

    /**
     * Restore a soft-deleted payment.
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $payment = Payment::withTrashed()->findOrFail($id);
        permitted('payments.restore');

        try {
            $payment->restore();
            return response()->json(['message' => 'Payment restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore payment: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore payment'], 500);
        }
    }
}
