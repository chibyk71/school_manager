<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeeConcessionRequest;
use App\Http\Requests\UpdateFeeConcessionRequest;
use App\Models\Finance\FeeConcession;
use App\Models\Finance\FeeType;
use App\Models\School;
use App\Models\User;
use App\Notifications\FeeConcessionNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

/**
 * Controller for managing fee concessions in the school management system.
 */
class FeeConcessionController extends Controller
{
    /**
     * Display a listing of fee concessions with dynamic querying.
     *
     * UI: resources/js/Pages/Finance/FeeConcessions/Index.vue
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        permitted('fee-concessions.view');

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
            ];

            $query = FeeConcession::with(['school:id,name', 'feeType:id,name', 'users:id,name'])
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            $feeConcessions = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($feeConcessions);
            }

            return Inertia::render('Finance/FeeConcessions/Index', [
                'feeConcessions' => $feeConcessions,
                'filters' => $request->only(['search', 'sort', 'sortOrder', 'perPage', 'with_trashed']),
                'feeTypes' => FeeType::select('id', 'name')->get(),
                'users' => User::select('id', 'name')->where('school_id', GetSchoolModel()->id)->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch fee concessions: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch fee concessions'], 500)
                : redirect()->back()->with('error', 'Failed to load fee concessions.');
        }
    }

    /**
     * Show the form for creating a new fee concession.
     *
     * UI: resources/js/Pages/Finance/FeeConcessions/Create.vue
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        permitted('fee-concessions.create');

        return Inertia::render('Finance/FeeConcessions/Create', [
            'feeTypes' => FeeType::select('id', 'name')->get(),
            'users' => User::select('id', 'name')->where('school_id', GetSchoolModel()->id)->get(),
        ]);
    }

    /**
     * Store a newly created fee concession in storage.
     *
     * @param StoreFeeConcessionRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreFeeConcessionRequest $request)
    {
        permitted('fee-concessions.create');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                return $request->wantsJson()
                    ? response()->json(['error' => 'No active school found'], 400)
                    : redirect()->back()->with('error', 'No active school found.');
            }

            $validated = $request->validated();
            $validated['school_id'] = $school->id;

            $feeConcession = FeeConcession::create($validated);

            if (!empty($validated['user_ids'])) {
                $feeConcession->users()->sync($validated['user_ids']);
            }

            // Notify finance managers and affected users
            $recipients = User::where('school_id', $school->id)
                ->whereHas('roles', fn($query) => $query->where('name', 'finance_manager'))
                ->get();

            if (!empty($validated['user_ids'])) {
                $students = User::whereIn('id', $validated['user_ids'])->get();
                $recipients = $recipients->merge($students);
            }

            Notification::send($recipients, new FeeConcessionNotification($feeConcession, 'created'));

            return $request->wantsJson()
                ? response()->json(['message' => 'Fee concession created successfully', 'feeConcession' => $feeConcession], 201)
                : redirect()->route('fee-concessions.index')->with('success', 'Fee concession created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create fee concession: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create fee concession'], 500)
                : redirect()->back()->with('error', 'Failed to create fee concession.')->withInput();
        }
    }

    /**
     * Display the specified fee concession.
     *
     * UI: resources/js/Pages/Finance/FeeConcessions/Show.vue
     *
     * @param Request $request
     * @param FeeConcession $feeConcession
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, FeeConcession $feeConcession)
    {
        permitted('fee-concessions.view');

        try {
            $feeConcession->load(['school:id,name', 'feeType:id,name', 'users:id,name']);
            return response()->json(['feeConcession' => $feeConcession]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch fee concession: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch fee concession'], 500);
        }
    }

    /**
     * Show the form for editing the specified fee concession.
     *
     * UI: resources/js/Pages/Finance/FeeConcessions/Edit.vue
     *
     * @param FeeConcession $feeConcession
     * @return \Inertia\Response
     */
    public function edit(FeeConcession $feeConcession)
    {
        permitted('fee-concessions.edit');

        return Inertia::render('Finance/FeeConcessions/Edit', [
            'feeConcession' => $feeConcession->load(['school:id,name', 'feeType:id,name', 'users:id,name']),
            'feeTypes' => FeeType::select('id', 'name')->get(),
            'users' => User::select('id', 'name')->where('school_id', GetSchoolModel()->id)->get(),
        ]);
    }

    /**
     * Update the specified fee concession in storage.
     *
     * @param UpdateFeeConcessionRequest $request
     * @param FeeConcession $feeConcession
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateFeeConcessionRequest $request, FeeConcession $feeConcession)
    {
        permitted('fee-concessions.edit');

        try {
            $school = GetSchoolModel();
            if (!$school) {
                return $request->wantsJson()
                    ? response()->json(['error' => 'No active school found'], 400)
                    : redirect()->back()->with('error', 'No active school found.');
            }

            $validated = $request->validated();
            $feeConcession->update($validated);

            if (isset($validated['user_ids'])) {
                $feeConcession->users()->sync($validated['user_ids']);
            }

            // Notify finance managers and affected users if key fields changed
            if ($feeConcession->wasChanged(['name', 'amount', 'type', 'start_date', 'end_date'])) {
                $recipients = User::where('school_id', $school->id)
                    ->whereHas('roles', fn($query) => $query->where('name', 'finance_manager'))
                    ->get();

                $students = $feeConcession->users()->get();
                $recipients = $recipients->merge($students);

                Notification::send($recipients, new FeeConcessionNotification($feeConcession, 'updated'));
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Fee concession updated successfully', 'feeConcession' => $feeConcession])
                : redirect()->route('fee-concessions.index')->with('success', 'Fee concession updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update fee concession: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update fee concession'], 500)
                : redirect()->back()->with('error', 'Failed to update fee concession.')->withInput();
        }
    }

    /**
     * Remove the specified fee concession(s) from storage (soft or force delete).
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        permitted('fee-concessions.delete');

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:fee_concessions,id',
                'force' => 'boolean',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');
            $query = $forceDelete ? FeeConcession::whereIn('id', $ids) : FeeConcession::whereIn('id', $ids)->withTrashed();
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? 'Fee concession(s) deleted successfully' : 'No fee concessions were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('fee-concessions.index')->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Failed to delete fee concessions: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete fee concession(s)'], 500)
                : redirect()->back()->with('error', 'Failed to delete fee concession(s).');
        }
    }

    /**
     * Restore a soft-deleted fee concession.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $feeConcession = FeeConcession::withTrashed()->findOrFail($id);
        permitted('fee-concessions.restore');

        try {
            $feeConcession->restore();
            return response()->json(['message' => 'Fee concession restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore fee concession: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore fee concession'], 500);
        }
    }
}