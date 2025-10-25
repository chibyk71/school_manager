<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Expense;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

/**
 * Controller for managing expenses in the school management system.
 */
class ExpenseController extends Controller
{
    /**
     * Display a listing of expenses with dynamic querying.
     *
     * UI: resources/js/Pages/Finance/Expenses/Index.vue
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        permitted('view-expenses'); // Laratrust permission check

        try {
            // Define extra fields for table query
            $extraFields = [
                [
                    'field' => 'school_name',
                    'relation' => 'school',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
            ];

            // Build query with optional trashed records
            $query = Expense::with(['school:id,name', 'transactions'])
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query (search, filter, sort, paginate)
            $expenses = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($expenses);
            }

            return Inertia::render('Finance/Expenses/Index', [
                'expenses' => $expenses,
                'filters' => $request->only(['search', 'sort', 'sortOrder', 'perPage', 'with_trashed']),
                // TODO load from config in a way that is customizable by schools
                'categories' => config('expenses.categories', ['utilities', 'salaries', 'supplies', 'maintenance']),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch expenses: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch expenses'], 500)
                : redirect()->back()->with('error', 'Failed to load expenses.');
        }
    }

    /**
     * Show the form for creating a new expense.
     *
     * UI: resources/js/Pages/Finance/Expenses/Create.vue
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        permitted('create-expenses');

        return Inertia::render('Finance/Expenses/Create', [
            'categories' => config('expenses.categories', ['utilities', 'salaries', 'supplies', 'maintenance']),
        ]);
    }

    /**
     * Store a newly created expense in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        permitted('create-expenses');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                return $request->wantsJson()
                    ? response()->json(['error' => 'No active school found'], 400)
                    : redirect()->back()->with('error', 'No active school found.');
            }

            $validated = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:0',
                'category' => 'required|string|max:100',
                'description' => 'nullable|string|max:255',
                'expense_date' => 'required|date',
                'status' => 'required|string|in:pending,approved,rejected',
                'branch_id' => 'nullable|exists:branches,id',
            ])->validate();

            $expense = Expense::create(array_merge($validated, [
                'school_id' => $school->id,
                'recorded_by' => auth()->id(),
            ]));

            // Create a transaction for the expense
            $expense->createTransaction([
                'amount' => $expense->amount,
                'transaction_type' => $expense->getTransactionType(),
                'category' => $expense->category,
                'transaction_date' => $expense->expense_date,
                'description' => $expense->description,
            ]);

            return $request->wantsJson()
                ? response()->json(['message' => 'Expense created successfully', 'expense' => $expense], 201)
                : redirect()->route('expenses.index')->with('success', 'Expense created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create expense: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create expense'], 500)
                : redirect()->back()->with('error', 'Failed to create expense.')->withInput();
        }
    }

    /**
     * Display the specified expense.
     *
     * UI: resources/js/Pages/Finance/Expenses/Show.vue
     *
     * @param Request $request
     * @param Expense $expense
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Expense $expense)
    {
        permitted('view-expenses');

        try {
            $expense->load(['school:id,name', 'transactions']);
            return response()->json(['expense' => $expense]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch expense: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch expense'], 500);
        }
    }

    /**
     * Show the form for editing the specified expense.
     *
     * UI: resources/js/Pages/Finance/Expenses/Edit.vue
     *
     * @param Expense $expense
     * @return \Inertia\Response
     */
    public function edit(Expense $expense)
    {
        permitted('edit-expenses');

        return Inertia::render('Finance/Expenses/Edit', [
            'expense' => $expense->load('school:id,name'),
            'categories' => config('expenses.categories', ['utilities', 'salaries', 'supplies', 'maintenance']),
        ]);
    }

    /**
     * Update the specified expense in storage.
     *
     * @param Request $request
     * @param Expense $expense
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Expense $expense)
    {
        permitted('edit-expenses');

        try {
            $validated = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:0',
                'category' => 'required|string|max:100',
                'description' => 'nullable|string|max:255',
                'expense_date' => 'required|date',
                'status' => 'required|string|in:pending,approved,rejected',
                'branch_id' => 'nullable|exists:branches,id',
            ])->validate();

            $expense->update($validated);

            // Update or create a transaction
            $expense->createTransaction([
                'amount' => $expense->amount,
                'transaction_type' => $expense->getTransactionType(),
                'category' => $expense->category,
                'transaction_date' => $expense->expense_date,
                'description' => $expense->description,
            ]);

            return $request->wantsJson()
                ? response()->json(['message' => 'Expense updated successfully', 'expense' => $expense])
                : redirect()->route('expenses.index')->with('success', 'Expense updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update expense: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update expense'], 500)
                : redirect()->back()->with('error', 'Failed to update expense.')->withInput();
        }
    }

    /**
     * Remove the specified expense(s) from storage (soft or force delete).
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        permitted('delete-expenses');

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:expenses,id',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');
            $query = $forceDelete ? Expense::whereIn('id', $ids) : Expense::whereIn('id', $ids)->withTrashed();
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? 'Expense(s) deleted successfully' : 'No expenses were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('expenses.index')->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Failed to delete expenses: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete expense(s)'], 500)
                : redirect()->back()->with('error', 'Failed to delete expense(s).');
        }
    }

    /**
     * Restore a soft-deleted expense.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $expense = Expense::withTrashed()->findOrFail($id);
        permitted('restore-expenses');

        try {
            $expense->restore();
            return response()->json(['message' => 'Expense restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore expense: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore expense'], 500);
        }
    }
}