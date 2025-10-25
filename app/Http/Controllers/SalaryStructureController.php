<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalaryStructureRequest;
use App\Http\Requests\UpdateSalaryStructureRequest;
use App\Models\DepartmentRole;
use App\Models\Employee\Salary;
use App\Models\Employee\SalaryStructure;
use App\Models\User;
use App\Notifications\SalaryStructureUpdatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing SalaryStructure resources.
 */
class SalaryStructureController extends Controller
{
    /**
     * Display a listing of salary structures with dynamic querying.
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', SalaryStructure::class); // Policy-based authorization

        try {
            // Define extra fields for table query
            $extraFields = [
                [
                    'field' => 'salary_name',
                    'relation' => 'salary',
                    'relatedField' => 'base_salary',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'number',
                ],
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
            $query = SalaryStructure::with([
                'salary:id,base_salary',
                'departmentRole:id,name',
            ])->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query (search, filter, sort, paginate)
            $salaryStructures = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($salaryStructures);
            }

            return Inertia::render('HRM/SalaryStructures', [
                'salaryStructures' => $salaryStructures,
                'salaries' => Salary::select('id', 'base_salary')->get(),
                'departmentRoles' => DepartmentRole::select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch salary structures: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch salary structures'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch salary structures']);
        }
    }

    /**
     * Show the form for creating a new salary structure.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        Gate::authorize('create', SalaryStructure::class); // Policy-based authorization

        try {
            return Inertia::render('HRM/SalaryStructureCreate', [
                'salaries' => Salary::select('id', 'base_salary')->get(),
                'departmentRoles' => DepartmentRole::select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load create form: ' . $e->getMessage());
            return redirect()->back()->with(['error' => 'Failed to load create form']);
        }
    }

    /**
     * Store a newly created salary structure in storage.
     *
     * @param StoreSalaryStructureRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreSalaryStructureRequest $request)
    {
        Gate::authorize('create', SalaryStructure::class); // Policy-based authorization

        try {
            $validated = $request->validated();
            $school = GetSchoolModel();
            $validated['school_id'] = $school->id; // Ensure school_id is set

            $salaryStructure = DB::transaction(function () use ($validated) {
                $salaryStructure = SalaryStructure::create($validated);
                // Notify users assigned to the department role
                $users = User::where('department_role_id', $validated['department_role_id'])->get();
                foreach ($users as $user) {
                    $user->notify(new SalaryStructureUpdatedNotification($salaryStructure));
                }
                return $salaryStructure;
            });

            return $request->wantsJson()
                ? response()->json(['message' => 'Salary structure created successfully'], 201)
                : redirect()->route('salary-structures.index')->with(['success' => 'Salary structure created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create salary structure: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create salary structure'], 500)
                : redirect()->back()->with(['error' => 'Failed to create salary structure'])->withInput();
        }
    }

    /**
     * Display the specified salary structure.
     *
     * @param Request $request
     * @param SalaryStructure $salaryStructure
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, SalaryStructure $salaryStructure)
    {
        Gate::authorize('view', $salaryStructure); // Policy-based authorization

        try {
            $salaryStructure->load([
                'salary:id,base_salary',
                'departmentRole:id,name',
            ]);
            return response()->json(['salary_structure' => $salaryStructure]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch salary structure: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch salary structure'], 500);
        }
    }

    /**
     * Show the form for editing the specified salary structure.
     *
     * @param SalaryStructure $salaryStructure
     * @return \Inertia\Response
     */
    public function edit(SalaryStructure $salaryStructure)
    {
        Gate::authorize('update', $salaryStructure); // Policy-based authorization

        try {
            $salaryStructure->load([
                'salary:id,base_salary',
                'departmentRole:id,name',
            ]);
            return Inertia::render('HRM/SalaryStructureEdit', [
                'salaryStructure' => $salaryStructure,
                'salaries' => Salary::select('id', 'base_salary')->get(),
                'departmentRoles' => DepartmentRole::select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load edit form: ' . $e->getMessage());
            return redirect()->back()->with(['error' => 'Failed to load edit form']);
        }
    }

    /**
     * Update the specified salary structure in storage.
     *
     * @param UpdateSalaryStructureRequest $request
     * @param SalaryStructure $salaryStructure
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateSalaryStructureRequest $request, SalaryStructure $salaryStructure)
    {
        Gate::authorize('update', $salaryStructure); // Policy-based authorization

        try {
            $validated = $request->validated();

            $salaryStructure = DB::transaction(function () use ($validated, $salaryStructure) {
                $salaryStructure->update($validated);
                // Notify users assigned to the department role
                $users = User::where('department_role_id', $salaryStructure->department_role_id)->get();
                foreach ($users as $user) {
                    $user->notify(new SalaryStructureUpdatedNotification($salaryStructure));
                }
                return $salaryStructure;
            });

            return $request->wantsJson()
                ? response()->json(['message' => 'Salary structure updated successfully'])
                : redirect()->route('salary-structures.index')->with(['success' => 'Salary structure updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update salary structure: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update salary structure'], 500)
                : redirect()->back()->with(['error' => 'Failed to update salary structure'])->withInput();
        }
    }

    /**
     * Remove the specified salary structure(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', SalaryStructure::class); // Policy-based authorization

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:salary_structures,id',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');
            $deleted = $forceDelete
                ? SalaryStructure::whereIn('id', $ids)->forceDelete()
                : SalaryStructure::whereIn('id', $ids)->delete();

            $message = $deleted ? 'Salary structure(s) deleted successfully' : 'No salary structures were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('salary-structures.index')->with(['success' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to delete salary structures: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete salary structure(s)'], 500)
                : redirect()->back()->with(['error' => 'Failed to delete salary structure(s)']);
        }
    }

    /**
     * Restore a soft-deleted salary structure.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $salaryStructure = SalaryStructure::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $salaryStructure); // Policy-based authorization

        try {
            $salaryStructure->restore();
            return response()->json(['message' => 'Salary structure restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore salary structure: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore salary structure'], 500);
        }
    }
}