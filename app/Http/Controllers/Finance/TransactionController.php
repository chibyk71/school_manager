<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Finance\Transaction;
use App\Models\School;
use App\Models\SchoolSection;
use App\Models\User;
use App\Notifications\TransactionNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

/**
 * Controller for managing transactions in the school management system.
 */
class TransactionController extends Controller
{
    /**
     * Display a listing of transactions with dynamic querying.
     *
     * UI: resources/js/Pages/Finance/Transactions/Index.vue
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        permitted('transactions.view');

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
                    'field' => 'section_name',
                    'relation' => 'schoolSection',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
                [
                    'field' => 'recorded_by_name',
                    'relation' => 'recordedBy',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
            ];

            $query = Transaction::with(['school:id,name', 'schoolSection:id,name', 'recordedBy:id,name'])
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            $transactions = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($transactions);
            }

            return Inertia::render('Finance/Transactions/Index', [
                'transactions' => $transactions,
                'filters' => $request->only(['search', 'sort', 'sortOrder', 'perPage', 'with_trashed']),
                'schoolSections' => SchoolSection::select('id', 'name')->get(),
                'paymentMethods' => ['cash', 'bank_transfer', 'card', 'cheque'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch transactions: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch transactions'], 500)
                : redirect()->back()->with('error', 'Failed to load transactions.');
        }
    }

    /**
     * Show the form for creating a new transaction.
     *
     * UI: resources/js/Pages/Finance/Transactions/Create.vue
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        permitted('transactions.create');

        return Inertia::render('Finance/Transactions/Create', [
            'schoolSections' => SchoolSection::select('id', 'name')->get(),
            'paymentMethods' => ['cash', 'bank_transfer', 'card', 'cheque'],
        ]);
    }

    /**
     * Store a newly created transaction in storage.
     *
     * @param StoreTransactionRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreTransactionRequest $request)
    {
        permitted('transactions.create');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                return $request->wantsJson()
                    ? response()->json(['error' => 'No active school found'], 400)
                    : redirect()->back()->with('error', 'No active school found.');
            }

            $validated = $request->validated();
            $validated['school_id'] = $school->id;
            $validated['recorded_by'] = auth()->id();

            $transaction = Transaction::create($validated);

            // Notify finance managers
            $recipients = User::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->where('name', 'finance_manager'))
                ->get();

            Notification::send($recipients, new TransactionNotification($transaction, 'created'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Transaction created successfully', 'transaction' => $transaction], 201)
                : redirect()->route('transactions.index')->with('success', 'Transaction created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create transaction: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create transaction'], 500)
                : redirect()->back()->with('error', 'Failed to create transaction.')->withInput();
        }
    }

    /**
     * Display the specified transaction.
     *
     * UI: resources/js/Pages/Finance/Transactions/Show.vue
     *
     * @param Request $request
     * @param Transaction $transaction
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Transaction $transaction)
    {
        permitted('transactions.view');

        try {
            $transaction->load(['school:id,name', 'schoolSection:id,name', 'recordedBy:id,name', 'payable']);
            return response()->json(['transaction' => $transaction]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch transaction: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch transaction'], 500);
        }
    }

    /**
     * Show the form for editing the specified transaction.
     *
     * UI: resources/js/Pages/Finance/Transactions/Edit.vue
     *
     * @param Transaction $transaction
     * @return \Inertia\Response
     */
    public function edit(Transaction $transaction)
    {
        permitted('transactions.edit');

        return Inertia::render('Finance/Transactions/Edit', [
            'transaction' => $transaction->load(['school:id,name', 'schoolSection:id,name', 'recordedBy:id,name']),
            'schoolSections' => SchoolSection::select('id', 'name')->get(),
            'paymentMethods' => ['cash', 'bank_transfer', 'card', 'cheque'],
        ]);
    }

    /**
     * Update the specified transaction in storage.
     *
     * @param UpdateTransactionRequest $request
     * @param Transaction $transaction
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateTransactionRequest $request, Transaction $transaction)
    {
        permitted('transactions.edit');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                return $request->wantsJson()
                    ? response()->json(['error' => 'No active school found'], 400)
                    : redirect()->back()->with('error', 'No active school found.');
            }

            $validated = $request->validated();
            $transaction->update($validated);

            // Notify finance managers if key fields changed
            if ($transaction->wasChanged(['amount', 'transaction_date', 'category'])) {
                $recipients = User::where('school_id', $school->id)
                    ->whereHas('roles', fn($query) => $query->where('name', 'finance_manager'))
                    ->get();

                Notification::send($recipients, new TransactionNotification($transaction, 'updated'));
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Transaction updated successfully', 'transaction' => $transaction])
                : redirect()->route('transactions.index')->with('success', 'Transaction updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update transaction: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update transaction'], 500)
                : redirect()->back()->with('error', 'Failed to update transaction.')->withInput();
        }
    }

    /**
     * Remove the specified transaction(s) from storage (soft or force delete).
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        permitted('transactions.delete');

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:transactions,id',
                'force' => 'boolean',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');
            $query = $forceDelete ? Transaction::whereIn('id', $ids) : Transaction::whereIn('id', $ids)->withTrashed();
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? 'Transaction(s) deleted successfully' : 'No transactions were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('transactions.index')->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Failed to delete transactions: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete transaction(s)'], 500)
                : redirect()->back()->with('error', 'Failed to delete transaction(s).');
        }
    }

    /**
     * Restore a soft-deleted transaction.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $transaction = Transaction::withTrashed()->findOrFail($id);
        permitted('transactions.restore');

        try {
            $transaction->restore();
            return response()->json(['message' => 'Transaction restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore transaction: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore transaction'], 500);
        }
    }
}