<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeeInstallmentDetailRequest;
use App\Http\Requests\UpdateFeeInstallmentDetailRequest;
use App\Models\Finance\FeeInstallment;
use App\Models\Finance\FeeInstallmentDetail;
use App\Models\User;
use App\Notifications\FeeInstallmentDetailNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

/**
 * Controller for managing fee installment details in the school management system.
 */
class FeeInstallmentDetailController extends Controller
{
    /**
     * Display a listing of fee installment details with dynamic querying.
     *
     * UI: resources/js/Pages/Finance/FeeInstallmentDetails/Index.vue
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        permitted('fee-installment-details.view');

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
                    'field' => 'fee_installment_number',
                    'relation' => 'feeInstallment',
                    'relatedField' => 'no_of_installment',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'number',
                ],
                [
                    'field' => 'student_name',
                    'relation' => 'user',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
            ];

            $query = FeeInstallmentDetail::with(['school:id,name', 'feeInstallment:id,no_of_installment', 'user:id,name'])
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            $feeInstallmentDetails = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($feeInstallmentDetails);
            }

            return Inertia::render('Finance/FeeInstallmentDetails/Index', [
                'feeInstallmentDetails' => $feeInstallmentDetails,
                'filters' => $request->only(['search', 'sort', 'sortOrder', 'perPage', 'with_trashed']),
                'feeInstallments' => FeeInstallment::select('id', 'no_of_installment')->where('school_id', GetSchoolModel()->id)->get(),
                'users' => User::select('id', 'name')->where('school_id', GetSchoolModel()->id)->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch fee installment details: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch fee installment details'], 500)
                : redirect()->back()->with('error', 'Failed to load fee installment details.');
        }
    }

    /**
     * Show the form for creating a new fee installment detail.
     *
     * UI: resources/js/Pages/Finance/FeeInstallmentDetails/Create.vue
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        permitted('fee-installment-details.create');

        return Inertia::render('Finance/FeeInstallmentDetails/Create', [
            'feeInstallments' => FeeInstallment::select('id', 'no_of_installment')->where('school_id', GetSchoolModel()->id)->get(),
            'users' => User::select('id', 'name')->where('school_id', GetSchoolModel()->id)->get(),
        ]);
    }

    /**
     * Store a newly created fee installment detail in storage.
     *
     * @param StoreFeeInstallmentDetailRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreFeeInstallmentDetailRequest $request)
    {
        permitted('fee-installment-details.create');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                return $request->wantsJson()
                    ? response()->json(['error' => 'No active school found'], 400)
                    : redirect()->back()->with('error', 'No active school found.');
            }

            $validated = $request->validated();
            $validated['school_id'] = $school->id;

            $feeInstallmentDetail = FeeInstallmentDetail::create($validated);

            // Notify finance managers and the student
            $recipients = User::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->where('name', 'finance_manager'))
                ->get()
                ->merge(User::where('id', $feeInstallmentDetail->user_id)->get());

            Notification::send($recipients, new FeeInstallmentDetailNotification($feeInstallmentDetail, 'created'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Fee installment detail created successfully', 'feeInstallmentDetail' => $feeInstallmentDetail], 201)
                : redirect()->route('fee-installment-details.index')->with('success', 'Fee installment detail created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create fee installment detail: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create fee installment detail'], 500)
                : redirect()->back()->with('error', 'Failed to create fee installment detail.')->withInput();
        }
    }

    /**
     * Display the specified fee installment detail.
     *
     * UI: resources/js/Pages/Finance/FeeInstallmentDetails/Show.vue
     *
     * @param Request $request
     * @param FeeInstallmentDetail $feeInstallmentDetail
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, FeeInstallmentDetail $feeInstallmentDetail)
    {
        permitted('fee-installment-details.view');

        try {
            $feeInstallmentDetail->load(['school:id,name', 'feeInstallment:id,no_of_installment', 'user:id,name']);
            return response()->json(['feeInstallmentDetail' => $feeInstallmentDetail]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch fee installment detail: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch fee installment detail'], 500);
        }
    }

    /**
     * Show the form for editing the specified fee installment detail.
     *
     * UI: resources/js/Pages/Finance/FeeInstallmentDetails/Edit.vue
     *
     * @param FeeInstallmentDetail $feeInstallmentDetail
     * @return \Inertia\Response
     */
    public function edit(FeeInstallmentDetail $feeInstallmentDetail)
    {
        permitted('fee-installment-details.edit');

        return Inertia::render('Finance/FeeInstallmentDetails/Edit', [
            'feeInstallmentDetail' => $feeInstallmentDetail->load(['school:id,name', 'feeInstallment:id,no_of_installment', 'user:id,name']),
            'feeInstallments' => FeeInstallment::select('id', 'no_of_installment')->where('school_id', GetSchoolModel()->id)->get(),
            'users' => User::select('id', 'name')->where('school_id', GetSchoolModel()->id)->get(),
        ]);
    }

    /**
     * Update the specified fee installment detail in storage.
     *
     * @param UpdateFeeInstallmentDetailRequest $request
     * @param FeeInstallmentDetail $feeInstallmentDetail
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateFeeInstallmentDetailRequest $request, FeeInstallmentDetail $feeInstallmentDetail)
    {
        permitted('fee-installment-details.edit');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                return $request->wantsJson()
                    ? response()->json(['error' => 'No active school found'], 400)
                    : redirect()->back()->with('error', 'No active school found.');
            }

            $validated = $request->validated();
            $feeInstallmentDetail->update($validated);

            // Notify finance managers and the student if key fields changed
            if ($feeInstallmentDetail->wasChanged(['amount', 'due_date', 'status', 'punishment', 'paid_date'])) {
                $recipients = User::where('school_id', $school->id)
                    ->whereHas('roles', fn($query) => $query->where('name', 'finance_manager'))
                    ->get()
                    ->merge(User::where('id', $feeInstallmentDetail->user_id)->get());

                Notification::send($recipients, new FeeInstallmentDetailNotification($feeInstallmentDetail, 'updated'));
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Fee installment detail updated successfully', 'feeInstallmentDetail' => $feeInstallmentDetail])
                : redirect()->route('fee-installment-details.index')->with('success', 'Fee installment detail updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update fee installment detail: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update fee installment detail'], 500)
                : redirect()->back()->with('error', 'Failed to update fee installment detail.')->withInput();
        }
    }

    /**
     * Remove the specified fee installment detail(s) from storage (soft or force delete).
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        permitted('fee-installment-details.delete');

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:fee_installment_details,id',
                'force' => 'boolean',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');
            $query = $forceDelete ? FeeInstallmentDetail::whereIn('id', $ids) : FeeInstallmentDetail::whereIn('id', $ids)->withTrashed();
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? 'Fee installment detail(s) deleted successfully' : 'No fee installment details were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('fee-installment-details.index')->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Failed to delete fee installment details: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete fee installment detail(s)'], 500)
                : redirect()->back()->with('error', 'Failed to delete fee installment detail(s).');
        }
    }

    /**
     * Restore a soft-deleted fee installment detail.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $feeInstallmentDetail = FeeInstallmentDetail::withTrashed()->findOrFail($id);
        permitted('fee-installment-details.restore');

        try {
            $feeInstallmentDetail->restore();
            return response()->json(['message' => 'Fee installment detail restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore fee installment detail: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore fee installment detail'], 500);
        }
    }
}