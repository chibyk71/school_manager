<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeeInstallmentRequest;
use App\Http\Requests\UpdateFeeInstallmentRequest;
use App\Models\Finance\Fee;
use App\Models\Finance\FeeInstallment;
use App\Models\School;
use App\Notifications\FeeInstallmentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

/**
 * Controller for managing fee installments in the school management system.
 */
class FeeInstallmentController extends Controller
{
    /**
     * Display a listing of fee installments with dynamic querying.
     *
     * UI: resources/js/Pages/Finance/FeeInstallments/Index.vue
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        permitted('fee-installments.view');

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
                    'field' => 'fee_name',
                    'relation' => 'fee',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
            ];

            $query = FeeInstallment::with(['school:id,name', 'fee:id,name'])
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            $feeInstallments = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($feeInstallments);
            }

            return Inertia::render('Finance/FeeInstallments/Index', [
                'feeInstallments' => $feeInstallments,
                'filters' => $request->only(['search', 'sort', 'sortOrder', 'perPage', 'with_trashed']),
                'fees' => Fee::select('id', 'name')->where('school_id', GetSchoolModel()->id)->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch fee installments: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch fee installments'], 500)
                : redirect()->back()->with('error', 'Failed to load fee installments.');
        }
    }

    /**
     * Show the form for creating a new fee installment.
     *
     * UI: resources/js/Pages/Finance/FeeInstallments/Create.vue
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        permitted('fee-installments.create');

        return Inertia::render('Finance/FeeInstallments/Create', [
            'fees' => Fee::select('id', 'name')->where('school_id', GetSchoolModel()->id)->get(),
        ]);
    }

    /**
     * Store a newly created fee installment in storage.
     *
     * @param StoreFeeInstallmentRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreFeeInstallmentRequest $request)
    {
        permitted('fee-installments.create');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                return $request->wantsJson()
                    ? response()->json(['error' => 'No active school found'], 400)
                    : redirect()->back()->with('error', 'No active school found.');
            }

            $validated = $request->validated();
            $validated['school_id'] = $school->id;

            $feeInstallment = FeeInstallment::create($validated);

            // Notify finance managers
            $recipients = User::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->where('name', 'finance_manager'))
                ->get();

            Notification::send($recipients, new FeeInstallmentNotification($feeInstallment, 'created'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Fee installment created successfully', 'feeInstallment' => $feeInstallment], 201)
                : redirect()->route('fee-installments.index')->with('success', 'Fee installment created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create fee installment: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create fee installment'], 500)
                : redirect()->back()->with('error', 'Failed to create fee installment.')->withInput();
        }
    }

    /**
     * Display the specified fee installment.
     *
     * UI: resources/js/Pages/Finance/FeeInstallments/Show.vue
     *
     * @param Request $request
     * @param FeeInstallment $feeInstallment
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, FeeInstallment $feeInstallment)
    {
        permitted('fee-installments.view');

        try {
            $feeInstallment->load(['school:id,name', 'fee:id,name']);
            return response()->json(['feeInstallment' => $feeInstallment]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch fee installment: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch fee installment'], 500);
        }
    }

    /**
     * Show the form for editing the specified fee installment.
     *
     * UI: resources/js/Pages/Finance/FeeInstallments/Edit.vue
     *
     * @param FeeInstallment $feeInstallment
     * @return \Inertia\Response
     */
    public function edit(FeeInstallment $feeInstallment)
    {
        permitted('fee-installments.edit');

        return Inertia::render('Finance/FeeInstallments/Edit', [
            'feeInstallment' => $feeInstallment->load(['school:id,name', 'fee:id,name']),
            'fees' => Fee::select('id', 'name')->where('school_id', GetSchoolModel()->id)->get(),
        ]);
    }

    /**
     * Update the specified fee installment in storage.
     *
     * @param UpdateFeeInstallmentRequest $request
     * @param FeeInstallment $feeInstallment
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateFeeInstallmentRequest $request, FeeInstallment $feeInstallment)
    {
        permitted('fee-installments.edit');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                return $request->wantsJson()
                    ? response()->json(['error' => 'No active school found'], 400)
                    : redirect()->back()->with('error', 'No active school found.');
            }

            $validated = $request->validated();
            $feeInstallment->update($validated);

            // Notify finance managers if key fields changed
            if ($feeInstallment->wasChanged(['no_of_installment', 'initial_amount_payable', 'due_date'])) {
                $recipients = User::where('school_id', $school->id)
                    ->whereHas('roles', fn($query) => $query->where('name', 'finance_manager'))
                    ->get();

                Notification::send($recipients, new FeeInstallmentNotification($feeInstallment, 'updated'));
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Fee installment updated successfully', 'feeInstallment' => $feeInstallment])
                : redirect()->route('fee-installments.index')->with('success', 'Fee installment updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update fee installment: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update fee installment'], 500)
                : redirect()->back()->with('error', 'Failed to update fee installment.')->withInput();
        }
    }

    /**
     * Remove the specified fee installment(s) from storage (soft or force delete).
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        permitted('fee-installments.delete');

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:fee_installments,id',
                'force' => 'boolean',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');
            $query = $forceDelete ? FeeInstallment::whereIn('id', $ids) : FeeInstallment::whereIn('id', $ids)->withTrashed();
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? 'Fee installment(s) deleted successfully' : 'No fee installments were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('fee-installments.index')->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Failed to delete fee installments: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete fee installment(s)'], 500)
                : redirect()->back()->with('error', 'Failed to delete fee installment(s).');
        }
    }

    /**
     * Restore a soft-deleted fee installment.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $feeInstallment = FeeInstallment::withTrashed()->findOrFail($id);
        permitted('fee-installments.restore');

        try {
            $feeInstallment->restore();
            return response()->json(['message' => 'Fee installment restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore fee installment: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore fee installment'], 500);
        }
    }
}