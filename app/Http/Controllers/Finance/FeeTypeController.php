<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeeTypeRequest;
use App\Http\Requests\UpdateFeeTypeRequest;
use App\Models\Finance\FeeType;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing fee types in the school management system.
 */
class FeeTypeController extends Controller
{
    /**
     * Display a listing of fee types with dynamic querying.
     *
     * UI: resources/js/Pages/Finance/FeeTypes/Index.vue
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        permitted('fee-types.view');

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
            ];

            $query = FeeType::with(['school:id,name'])
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            $feeTypes = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($feeTypes);
            }

            return Inertia::render('Finance/FeeTypes/Index', [
                'feeTypes' => $feeTypes,
                'filters' => $request->only(['search', 'sort', 'sortOrder', 'perPage', 'with_trashed']),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch fee types: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch fee types'], 500)
                : redirect()->back()->with('error', 'Failed to load fee types.');
        }
    }

    /**
     * Show the form for creating a new fee type.
     *
     * UI: resources/js/Pages/Finance/FeeTypes/Create.vue
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        permitted('fee-types.create');

        return Inertia::render('Finance/FeeTypes/Create');
    }

    /**
     * Store a newly created fee type in storage.
     *
     * @param StoreFeeTypeRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreFeeTypeRequest $request)
    {
        permitted('fee-types.create');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                return $request->wantsJson()
                    ? response()->json(['error' => 'No active school found'], 400)
                    : redirect()->back()->with('error', 'No active school found.');
            }

            $validated = $request->validated();
            $validated['school_id'] = $school->id;

            $feeType = FeeType::create($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Fee type created successfully', 'feeType' => $feeType], 201)
                : redirect()->route('fee-types.index')->with('success', 'Fee type created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create fee type: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create fee type'], 500)
                : redirect()->back()->with('error', 'Failed to create fee type.')->withInput();
        }
    }

    /**
     * Display the specified fee type.
     *
     * UI: resources/js/Pages/Finance/FeeTypes/Show.vue
     *
     * @param Request $request
     * @param FeeType $feeType
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, FeeType $feeType)
    {
        permitted('fee-types.view');

        try {
            $feeType->load(['school:id,name']);
            return response()->json(['feeType' => $feeType]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch fee type: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch fee type'], 500);
        }
    }

    /**
     * Show the form for editing the specified fee type.
     *
     * UI: resources/js/Pages/Finance/FeeTypes/Edit.vue
     *
     * @param FeeType $feeType
     * @return \Inertia\Response
     */
    public function edit(FeeType $feeType)
    {
        permitted('fee-types.edit');

        return Inertia::render('Finance/FeeTypes/Edit', [
            'feeType' => $feeType->load('school:id,name'),
        ]);
    }

    /**
     * Update the specified fee type in storage.
     *
     * @param UpdateFeeTypeRequest $request
     * @param FeeType $feeType
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateFeeTypeRequest $request, FeeType $feeType)
    {
        permitted('fee-types.edit');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                return $request->wantsJson()
                    ? response()->json(['error' => 'No active school found'], 400)
                    : redirect()->back()->with('error', 'No active school found.');
            }

            $validated = $request->validated();
            $feeType->update($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Fee type updated successfully', 'feeType' => $feeType])
                : redirect()->route('fee-types.index')->with('success', 'Fee type updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update fee type: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update fee type'], 500)
                : redirect()->back()->with('error', 'Failed to update fee type.')->withInput();
        }
    }

    /**
     * Remove the specified fee type(s) from storage (soft or force delete).
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        permitted('fee-types.delete');

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:fee_types,id',
                'force' => 'boolean',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');
            $query = $forceDelete ? FeeType::whereIn('id', $ids) : FeeType::whereIn('id', $ids)->withTrashed();
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? 'Fee type(s) deleted successfully' : 'No fee types were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('fee-types.index')->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Failed to delete fee types: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete fee type(s)'], 500)
                : redirect()->back()->with('error', 'Failed to delete fee type(s).');
        }
    }

    /**
     * Restore a soft-deleted fee type.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $feeType = FeeType::withTrashed()->findOrFail($id);
        permitted('fee-types.restore');

        try {
            $feeType->restore();
            return response()->json(['message' => 'Fee type restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore fee type: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore fee type'], 500);
        }
    }
}