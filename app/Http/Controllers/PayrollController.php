<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePayrollRequest;
use App\Http\Requests\UpdatePayrollRequest;
use App\Models\Employee\Payroll;
use App\Models\Employee\Salary;
use App\Models\Employee\SalaryAddon;
use App\Models\Employee\SalaryStructure;
use App\Models\User;
use App\Notifications\PayrollProcessedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing Payroll resources.
 */
class PayrollController extends Controller
{
    /**
     * Display a listing of payrolls with dynamic querying.
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Payroll::class); // Policy-based authorization

        try {
            // Define extra fields for table query
            $extraFields = [
                [
                    'field' => 'user_name',
                    'relation' => 'user',
                    'relatedField' => 'full_name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
                [
                    'field' => 'salary_name',
                    'relation' => 'salary',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
            ];

            // Build query
            $query = Payroll::with([
                'user:id,first_name,last_name',
                'salary:id,name',
            ])->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query (search, filter, sort, paginate)
            $payrolls = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($payrolls);
            }

            return Inertia::render('HRM/Payrolls', [
                'payrolls' => $payrolls,
                'users' => User::select('id', 'first_name', 'last_name')->get(),
                'salaries' => Salary::select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch payrolls: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch payrolls'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch payrolls']);
        }
    }

    /**
     * Show the form for creating a new payroll.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        Gate::authorize('create', Payroll::class); // Policy-based authorization

        try {
            return Inertia::render('HRM/PayrollCreate', [
                'users' => User::select('id', 'first_name', 'last_name')->get(),
                'salaries' => Salary::select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load create form: ' . $e->getMessage());
            return redirect()->back()->with(['error' => 'Failed to load create form']);
        }
    }

    /**
     * Store a newly created payroll in storage.
     *
     * @param StorePayrollRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StorePayrollRequest $request)
    {
        Gate::authorize('create', Payroll::class); // Policy-based authorization

        try {
            $validated = $request->validated();
            $school = GetSchoolModel();
            $validated['school_id'] = $school->id; // Ensure school_id is set
            $validated['status'] = 'unpaid'; // Default status

            // Calculate net_salary
            $salary = Salary::findOrFail($validated['salary_id']);
            $structures = SalaryStructure::where('salary_id', $validated['salary_id'])
                ->where('department_role_id', User::findOrFail($validated['user_id'])->department_role_id)
                ->where('effective_date', '<=', now())
                ->get();
            $totalStructures = $structures->sum('amount');
            $addons = SalaryAddon::where('user_id', $validated['user_id'])
                ->where('effective_date', '<=', now())
                ->where(function ($query) {
                    $query->whereNull('recurrence_end_date')
                        ->orWhere('recurrence_end_date', '>=', now());
                })
                ->get();
            $totalAddons = $addons->whereIn('type', ['bonus', 'allowance', 'overtime'])->sum('amount')
                - $addons->where('type', 'deduction')->sum('amount');
            $validated['net_salary'] = $salary->base_salary + $totalStructures + $totalAddons;

            $payroll = Payroll::create($validated);

            $admins = User::whereRoleIs('admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new PayrollProcessedNotification($payroll));
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Payroll created successfully'], 201)
                : redirect()->route('payrolls.index')->with(['success' => 'Payroll created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create payroll: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create payroll'], 500)
                : redirect()->back()->with(['error' => 'Failed to create payroll'])->withInput();
        }
    }

    /**
     * Display the specified payroll.
     *
     * @param Request $request
     * @param Payroll $payroll
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Payroll $payroll)
    {
        Gate::authorize('view', $payroll); // Policy-based authorization

        try {
            $payroll->load([
                'user:id,first_name,last_name',
                'salary:id,name,base_salary',
            ]);
            return response()->json(['payroll' => $payroll]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch payroll: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch payroll'], 500);
        }
    }

    /**
     * Show the form for editing the specified payroll.
     *
     * @param Payroll $payroll
     * @return \Inertia\Response
     */
    public function edit(Payroll $payroll)
    {
        Gate::authorize('update', $payroll); // Policy-based authorization

        try {
            $payroll->load([
                'user:id,first_name,last_name',
                'salary:id,name,base_salary',
            ]);
            return Inertia::render('HRM/PayrollEdit', [
                'payroll' => $payroll,
                'users' => User::select('id', 'first_name', 'last_name')->get(),
                'salaries' => Salary::select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load edit form: ' . $e->getMessage());
            return redirect()->back()->with(['error' => 'Failed to load edit form']);
        }
    }

    /**
     * Update the specified payroll in storage.
     *
     * @param UpdatePayrollRequest $request
     * @param Payroll $payroll
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdatePayrollRequest $request, Payroll $payroll)
    {
        Gate::authorize('update', $payroll); // Policy-based authorization

        try {
            $validated = $request->validated();

            // Recalculate net_salary if necessary
            if (isset($validated['salary_id'], $validated['bonus'], $validated['deduction'])) {
                $salary = Salary::findOrFail($validated['salary_id']);
                $validated['net_salary'] = $salary->base_salary + ($validated['bonus'] ?? 0) - ($validated['deduction'] ?? 0);
            }

            $payroll->update($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Payroll updated successfully'])
                : redirect()->route('payrolls.index')->with(['success' => 'Payroll updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update payroll: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update payroll'], 500)
                : redirect()->back()->with(['error' => 'Failed to update payroll'])->withInput();
        }
    }

    /**
     * Remove the specified payroll(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', Payroll::class); // Policy-based authorization

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:payrolls,id',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');
            $deleted = $forceDelete
                ? Payroll::whereIn('id', $ids)->forceDelete()
                : Payroll::whereIn('id', $ids)->delete();

            $message = $deleted ? 'Payroll(s) deleted successfully' : 'No payrolls were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('payrolls.index')->with(['success' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to delete payrolls: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete payroll(s)'], 500)
                : redirect()->back()->with(['error' => 'Failed to delete payroll(s)']);
        }
    }

    /**
     * Restore a soft-deleted payroll.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $payroll = Payroll::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $payroll); // Policy-based authorization

        try {
            $payroll->restore();
            return response()->json(['message' => 'Payroll restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore payroll: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore payroll'], 500);
        }
    }

    /**
     * Mark a payroll as paid.
     *
     * @param Request $request
     * @param Payroll $payroll
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function markAsPaid(Request $request, Payroll $payroll)
    {
        Gate::authorize('markAsPaid', $payroll); // Policy-based authorization

        try {
            DB::transaction(function () use ($payroll, $request) {
                $payroll->update(['status' => 'paid']);
                $payroll->user->notify(new PayrollProcessedNotification($payroll));
            });

            return $request->wantsJson()
                ? response()->json(['message' => 'Payroll marked as paid successfully'])
                : redirect()->route('payrolls.index')->with(['success' => 'Payroll marked as paid successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to mark payroll as paid: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to mark payroll as paid'], 500)
                : redirect()->back()->with(['error' => 'Failed to mark payroll as paid']);
        }
    }
}