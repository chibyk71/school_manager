<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalaryAddonRequest;
use App\Http\Requests\UpdateSalaryAddonRequest;
use App\Models\Employee\SalaryAddon;
use App\Models\Employee\Staff;
use App\Notifications\SalaryAddonUpdatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SalaryAddonController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', SalaryAddon::class);

        try {
            $extraFields = [
                [
                    'field' => 'staff_user_name',
                    'relation' => 'staff.user',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
            ];

            $query = SalaryAddon::with(['staff.user:id,name'])
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            $salaryAddons = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($salaryAddons);
            }

            return Inertia::render('HRM/SalaryAddons', [
                'salaryAddons' => $salaryAddons,
                'staff' => Staff::with('user:id,name')->where('school_id', GetSchoolModel()->id)->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch salary addons: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch salary addons'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch salary addons']);
        }
    }

    public function create()
    {
        Gate::authorize('create', SalaryAddon::class);

        try {
            return Inertia::render('HRM/SalaryAddonCreate', [
                'staff' => Staff::with('user:id,name')->where('school_id', GetSchoolModel()->id)->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load create form: ' . $e->getMessage());
            return redirect()->back()->with(['error' => 'Failed to load create form']);
        }
    }

    public function store(StoreSalaryAddonRequest $request)
    {
        Gate::authorize('create', SalaryAddon::class);

        try {
            $school = GetSchoolModel();
            if (!$school) {
                throw new \Exception('No active school found.');
            }

            $salaryAddon = DB::transaction(function () use ($request, $school) {
                $validated = $request->validated();
                $validated['school_id'] = $school->id;
                $salaryAddon = SalaryAddon::create($validated);
                $salaryAddon->staff->user->notify(new SalaryAddonUpdatedNotification($salaryAddon));
                return $salaryAddon;
            });

            return $request->wantsJson()
                ? response()->json(['message' => 'Salary addon created successfully'], 201)
                : redirect()->route('salary-addons.index')->with(['success' => 'Salary addon created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create salary addon: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create salary addon'], 500)
                : redirect()->back()->with(['error' => 'Failed to create salary addon'])->withInput();
        }
    }

    public function show(Request $request, SalaryAddon $salaryAddon)
    {
        Gate::authorize('view', $salaryAddon);

        try {
            $salaryAddon->load(['staff.user:id,name']);
            return response()->json(['salary_addon' => $salaryAddon]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch salary addon: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch salary addon'], 500);
        }
    }

    public function edit(SalaryAddon $salaryAddon)
    {
        Gate::authorize('update', $salaryAddon);

        try {
            $salaryAddon->load(['staff.user:id,name']);
            return Inertia::render('HRM/SalaryAddonEdit', [
                'salaryAddon' => $salaryAddon,
                'staff' => Staff::with('user:id,name')->where('school_id', GetSchoolModel()->id)->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load edit form: ' . $e->getMessage());
            return redirect()->back()->with(['error' => 'Failed to load edit form']);
        }
    }

    public function update(UpdateSalaryAddonRequest $request, SalaryAddon $salaryAddon)
    {
        Gate::authorize('update', $salaryAddon);

        try {
            $salaryAddon = DB::transaction(function () use ($request, $salaryAddon) {
                $validated = $request->validated();
                $salaryAddon->update($validated);
                $salaryAddon->staff->user->notify(new SalaryAddonUpdatedNotification($salaryAddon));
                return $salaryAddon;
            });

            return $request->wantsJson()
                ? response()->json(['message' => 'Salary addon updated successfully'])
                : redirect()->route('salary-addons.index')->with(['success' => 'Salary addon updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update salary addon: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update salary addon'], 500)
                : redirect()->back()->with(['error' => 'Failed to update salary addon'])->withInput();
        }
    }

    public function destroy(Request $request)
    {
        Gate::authorize('delete', SalaryAddon::class);

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:salary_addons,id',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');
            $deleted = $forceDelete
                ? SalaryAddon::whereIn('id', $ids)->forceDelete()
                : SalaryAddon::whereIn('id', $ids)->delete();

            $message = $deleted ? 'Salary addon(s) deleted successfully' : 'No salary addons were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('salary-addons.index')->with(['success' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to delete salary addons: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete salary addon(s)'], 500)
                : redirect()->back()->with(['error' => 'Failed to delete salary addon(s)']);
        }
    }

    public function restore(Request $request, $id)
    {
        $salaryAddon = SalaryAddon::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $salaryAddon);

        try {
            $salaryAddon->restore();
            $salaryAddon->staff->user->notify(new SalaryAddonUpdatedNotification($salaryAddon));
            return response()->json(['message' => 'Salary addon restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore salary addon: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore salary addon'], 500);
        }
    }
}