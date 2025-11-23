<?php

namespace App\Http\Controllers\Finance;

use App\Events\FeeAssignedToClasses;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeeRequest;
use App\Http\Requests\UpdateFeeRequest;
use App\Models\Finance\Fee;
use App\Models\School;
use App\Models\Academic\Term;
use App\Models\Finance\FeeType;
use App\Models\Academic\ClassSection;
use App\Models\User;
use App\Notifications\FeeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

/**
 * Controller for managing fees in the school management system.
 */
class FeeController extends Controller
{
    /**
     * Display a listing of fees with dynamic querying.
     *
     * UI: resources/js/Pages/Finance/Fees/Index.vue
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        permitted('fees.view');

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
                    'field' => 'fee_type_name',
                    'relation' => 'feeType',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
                [
                    'field' => 'term_name',
                    'relation' => 'term',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
            ];

            $query = Fee::with(['school:id,name', 'feeType:id,name', 'term:id,name', 'classSections:id,name'])
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            $fees = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($fees);
            }

            return Inertia::render('Finance/Fees/Index', [
                'fees' => $fees,
                'filters' => $request->only(['search', 'sort', 'sortOrder', 'perPage', 'with_trashed']),
                'feeTypes' => FeeType::select('id', 'name')->get(),
                'terms' => Term::select('id', 'name')->get(),
                'classSections' => ClassSection::select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch fees: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch fees'], 500)
                : redirect()->back()->with('error', 'Failed to load fees.');
        }
    }

    /**
     * Show the form for creating a new fee.
     *
     * UI: resources/js/Pages/Finance/Fees/Create.vue
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        permitted('fees.create');

        return Inertia::render('Finance/Fees/Create', [
            'feeTypes' => FeeType::select('id', 'name')->get(),
            'terms' => Term::select('id', 'name')->get(),
            'classSections' => ClassSection::select('id', 'name')->get(),
        ]);
    }

    /**
     * Store a newly created fee in storage.
     *
     * @param StoreFeeRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreFeeRequest $request)
    {
        permitted('fees.create');

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

            $fee = Fee::create($validated);

            if (!empty($validated['class_section_ids'])) {
                $classSectionIds = $validated['class_section_ids'];
                $fee->classSections()->sync($classSectionIds);
                // Fire event (queued)
                FeeAssignedToClasses::dispatch($fee, $classSectionIds, $request->isMethod('put'));
            }

            $fee->createTransaction([
                'amount' => $fee->amount,
                'transaction_type' => $fee->getTransactionType(),
                'category' => $fee->getCategory(),
                'transaction_date' => $fee->due_date,
                'description' => $fee->description,
            ]);

            // Notify finance managers and teachers of assigned class sections
            $recipients = User::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->where('name', 'finance_manager'))
                ->get();

            if (!empty($validated['class_section_ids'])) {
                $teachers = User::where('school_id', $school->id)
                    ->whereHas('classSections', fn($query) => $query->whereIn('id', $validated['class_section_ids']))
                    ->get();
                $recipients = $recipients->merge($teachers);
            }

            Notification::send($recipients, new FeeNotification($fee, 'created'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Fee created successfully', 'fee' => $fee], 201)
                : redirect()->route('fees.index')->with('success', 'Fee created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create fee: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create fee'], 500)
                : redirect()->back()->with('error', 'Failed to create fee.')->withInput();
        }
    }

    /**
     * Display the specified fee.
     *
     * UI: resources/js/Pages/Finance/Fees/Show.vue
     *
     * @param Request $request
     * @param Fee $fee
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Fee $fee)
    {
        permitted('fees.view');

        try {
            $fee->load(['school:id,name', 'feeType:id,name', 'term:id,name', 'classSections:id,name', 'transactions']);
            return response()->json(['fee' => $fee]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch fee: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch fee'], 500);
        }
    }

    /**
     * Show the form for editing the specified fee.
     *
     * UI: resources/js/Pages/Finance/Fees/Edit.vue
     *
     * @param Fee $fee
     * @return \Inertia\Response
     */
    public function edit(Fee $fee)
    {
        permitted('fees.edit');

        return Inertia::render('Finance/Fees/Edit', [
            'fee' => $fee->load(['school:id,name', 'feeType:id,name', 'term:id,name', 'classSections:id,name']),
            'feeTypes' => FeeType::select('id', 'name')->get(),
            'terms' => Term::select('id', 'name')->get(),
            'classSections' => ClassSection::select('id', 'name')->get(),
        ]);
    }

    /**
     * Update the specified fee in storage.
     *
     * @param UpdateFeeRequest $request
     * @param Fee $fee
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateFeeRequest $request, Fee $fee)
    {
        permitted('fees.edit');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                return $request->wantsJson()
                    ? response()->json(['error' => 'No active school found'], 400)
                    : redirect()->back()->with('error', 'No active school found.');
            }

            $validated = $request->validated();
            $fee->update($validated);

            if (!empty($validated['class_section_ids'])) {
                $classSectionIds = $validated['class_section_ids'];
                $fee->classSections()->sync($classSectionIds);
                // Fire event (queued)
                FeeAssignedToClasses::dispatch($fee, $classSectionIds, true);
            }

            $fee->createTransaction([
                'amount' => $fee->amount,
                'transaction_type' => $fee->getTransactionType(),
                'category' => $fee->getCategory(),
                'transaction_date' => $fee->due_date,
                'description' => $fee->description,
            ]);

            // Notify finance managers and teachers of assigned class sections
            $recipients = User::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->where('name', 'finance_manager'))
                ->get();

            if (!empty($validated['class_section_ids'])) {
                $teachers = User::where('school_id', $school->id)
                    ->whereHas('classSections', fn($query) => $query->whereIn('id', $validated['class_section_ids']))
                    ->get();
                $recipients = $recipients->merge($teachers);
            }

            Notification::send($recipients, new FeeNotification($fee, 'updated'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Fee updated successfully', 'fee' => $fee])
                : redirect()->route('fees.index')->with('success', 'Fee updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update fee: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update fee'], 500)
                : redirect()->back()->with('error', 'Failed to update fee.')->withInput();
        }
    }

    /**
     * Remove the specified fee(s) from storage (soft or force delete).
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        permitted('fees.delete');

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:fees,id',
                'force' => 'boolean',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');
            $query = $forceDelete ? Fee::whereIn('id', $ids) : Fee::whereIn('id', $ids)->withTrashed();
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? 'Fee(s) deleted successfully' : 'No fees were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('fees.index')->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Failed to delete fees: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete fee(s)'], 500)
                : redirect()->back()->with('error', 'Failed to delete fee(s).');
        }
    }

    /**
     * Restore a soft-deleted fee.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $fee = Fee::withTrashed()->findOrFail($id);
        permitted('fees.restore');

        try {
            $fee->restore();
            return response()->json(['message' => 'Fee restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore fee: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore fee'], 500);
        }
    }
}
