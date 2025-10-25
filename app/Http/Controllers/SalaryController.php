<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalaryRequest;
use App\Http\Requests\UpdateSalaryRequest;
use App\Models\DepartmentRole;
use App\Models\Employee\Salary;
use App\Notifications\SalaryUpdatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing Salary resources.
 */
class SalaryController extends Controller
{
    /**
     * Display a listing of salaries with dynamic querying.
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Salary::class); // Policy-based authorization

        try {
            // Define extra fields for table query
            $extraFields = [
                [
                    'field' => 'department_role_name',
                    'relation' => 'departmentRole',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
            ];

            // Build query
            $query = Salary::with(['departmentRole:id,name'])
                ->withCount('payrolls')
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query (search, filter, sort, paginate)
            $salaries = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($salaries);
            }

            return Inertia::render('HRM/Salaries', [
                'salaries' => $salaries,
                'departmentRoles' => DepartmentRole::select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch salaries: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch salaries'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch salaries']);
        }
    }

    /**
     * Show the form for creating a new salary.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        Gate::authorize('create', Salary::class); // Policy-based authorization

        try {
            return Inertia::render('HRM/SalaryCreate', [
                'departmentRoles' => DepartmentRole::select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load create form: ' . $e->getMessage());
            return redirect()->back()->with(['error' => 'Failed to load create form']);
        }
    }

    /**
     * Store a newly created salary in storage.
     *
     * @param StoreSalaryRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreSalaryRequest $request)
    {
        Gate::authorize('create', Salary::class); // Policy-based authorization

        try {
            $validated = $request->validated();
            $school = GetSchoolModel();
            $validated['school_id'] = $school->id; // Ensure school_id is set

            $salary = DB::transaction(function () use ($validated) {
                $salary = Salary::create($validated);
                // Notify users assigned to the department role
                $users = User::where('department_role_id', $validated['department_role_id'])->get();
                foreach ($users as $user) {
                    $user->notify(new SalaryUpdatedNotification($salary));
                }
                return $salary;
            });

            return $request->wantsJson()
                ? response()->json(['message' => 'Salary created successfully'], 201)
                : redirect()->route('salaries.index')->with(['success' => 'Salary created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create salary: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create salary'], 500)
                : redirect()->back()->with(['error' => 'Failed to create salary'])->withInput();
        }
    }

    /**
     * Display the specified salary.
     *
     * @param Request $request
     * @param Salary $salary
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Salary $salary)
    {
        Gate::authorize('view', $salary); // Policy-based authorization

        try {
            $salary->load(['departmentRole:id,name']);
            return response()->json(['salary' => $salary]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch salary: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch salary'], 500);
        }
    }

    /**
     * Show the form for editing the specified salary.
     *
     * @param Salary $salary
     * @return \Inertia\Response
     */
    public function edit(Salary $salary)
    {
        Gate::authorize('update', $salary); // Policy-based authorization

        try {
            $salary->load(['departmentRole:id,name']);
            return Inertia::render('HRM/SalaryEdit', [
                'salary' => $salary,
                'departmentRoles' => DepartmentRole::select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load edit form: ' . $e->getMessage());
            return redirect()->back()->with(['error' => 'Failed to load edit form']);
        }
    }

    /**
     * Update the specified salary in storage.
     *
     * @param UpdateSalaryRequest $request
     * @param Salary $salary
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateSalaryRequest $request, Salary $salary)
    {
        Gate::authorize('update', $salary); // Policy-based authorization

        try {
            $validated = $request->validated();

            $salary = DB::transaction(function () use ($validated, $salary) {
                $salary->update($validated);
                // Notify users assigned to the department role
                $users = User::where('department_role_id', $salary->department_role_id)->get();
                foreach ($users as $user) {
                    $user->notify(new SalaryUpdatedNotification($salary));
                }
                return $salary;
            });

            return $request->wantsJson()
                ? response()->json(['message' => 'Salary updated successfully'])
                : redirect()->route('salaries.index')->with(['success' => 'Salary updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update salary: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update salary'], 500)
                : redirect()->back()->with(['error' => 'Failed to update salary'])->withInput();
        }
    }

    /**
     * Remove the specified salary(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', Salary::class); // Policy-based authorization

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:salaries,id',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');
            $deleted = $forceDelete
                ? Salary::whereIn('id', $ids)->forceDelete()
                : Salary::whereIn('id', $ids)->delete();

            $message = $deleted ? 'Salary(s) deleted successfully' : 'No salaries were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('salaries.index')->with(['success' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to delete salaries: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete salary(s)'], 500)
                : redirect()->back()->with(['error' => 'Failed to delete salary(s)']);
        }
    }

    /**
     * Restore a soft-deleted salary.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $salary = Salary::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $salary); // Policy-based authorization

        try {
            $salary->restore();
            return response()->json(['message' => 'Salary restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore salary: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore salary'], 500);
        }
    }
}