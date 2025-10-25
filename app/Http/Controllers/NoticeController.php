<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNoticeRequest;
use App\Http\Requests\UpdateNoticeRequest;
use App\Models\Communication\Notice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing Notice resources in a multi-tenant school management system.
 */
class NoticeController extends Controller
{
    /**
     * Display a listing of notices with dynamic querying.
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Notice::class);

        try {
            $school = GetSchoolModel();

            // Define extra fields for table query
            $extraFields = [
                [
                    'field' => 'sender_name',
                    'relation' => 'sender',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
            ];

            // Build query
            $query = Notice::with(['sender:id,name', 'recipients:id'])
                ->withCount('recipients')
                ->where(function ($q) use ($school) {
                    $q->where('school_id', $school?->id)->orWhere('is_public', true);
                })
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query
            $notices = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($notices);
            }

            return Inertia::render('Communication/Notices/Index', [ // UI path: resources/js/Pages/Communication/Notices/Index.vue
                'notices' => $notices,
                'users' => User::where('school_id', $school?->id)->select('id', 'name')->get(),
                'types' => ['Announcement', 'Alert', 'Reminder'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch notices: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch notices'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch notices']);
        }
    }

    /**
     * Store a newly created notice in storage.
     *
     * @param StoreNoticeRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreNoticeRequest $request)
    {
        Gate::authorize('create', Notice::class);

        try {
            $validated = $request->validated();
            $school = GetSchoolModel();
            $validated['school_id'] = $validated['is_public'] ? null : $school->id;
            $validated['sender_id'] = auth()->id();

            $notice = Notice::create($validated);
            if ($request->has('type')) {
                $notice->addConfig('type', $validated['type']);
            }
            if ($request->has('recipient_ids')) {
                $notice->recipients()->sync($validated['recipient_ids']);
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Notice created successfully'], 201)
                : redirect()->route('notices.index')->with(['success' => 'Notice created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create notice: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create notice'], 500)
                : redirect()->back()->with(['error' => 'Failed to create notice'])->withInput();
        }
    }

    /**
     * Display the specified notice.
     *
     * @param Request $request
     * @param Notice $notice
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Notice $notice)
    {
        Gate::authorize('view', $notice);

        try {
            $notice->load(['sender:id,name', 'recipients:id,name']);
            return response()->json(['notice' => $notice]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch notice: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch notice'], 500);
        }
    }

    /**
     * Update the specified notice in storage.
     *
     * @param UpdateNoticeRequest $request
     * @param Notice $notice
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateNoticeRequest $request, Notice $notice)
    {
        Gate::authorize('update', $notice);

        try {
            $validated = $request->validated();
            $school = GetSchoolModel();
            $validated['school_id'] = $validated['is_public'] ? null : $school->id;

            $notice->update($validated);
            if ($request->has('type')) {
                $notice->addConfig('type', $validated['type']);
            }
            if ($request->has('recipient_ids')) {
                $notice->recipients()->sync($validated['recipient_ids']);
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Notice updated successfully'])
                : redirect()->route('notices.index')->with(['success' => 'Notice updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update notice: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update notice'], 500)
                : redirect()->back()->with(['error' => 'Failed to update notice'])->withInput();
        }
    }

    /**
     * Remove the specified notice(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', Notice::class);

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:notices,id',
            ]);

            $school = GetSchoolModel();
            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');

            $query = Notice::whereIn('id', $ids)->where('school_id', $school?->id);
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? 'Notice(s) deleted successfully' : 'No notices were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('notices.index')->with(['success' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to delete notices: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete notices'], 500)
                : redirect()->back()->with(['error' => 'Failed to delete notices']);
        }
    }

    /**
     * Restore a soft-deleted notice.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $notice = Notice::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $notice);

        try {
            $notice->restore();
            return response()->json(['message' => 'Notice restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore notice: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore notice'], 500);
        }
    }

    /**
     * Mark a notice as read for the authenticated user.
     *
     * @param Request $request
     * @param Notice $notice
     * @return \Illuminate\Http\JsonResponse
     */
    public function markRead(Request $request, Notice $notice)
    {
        Gate::authorize('markRead', $notice);

        try {
            $notice->recipients()->updateExistingPivot(auth()->id(), ['is_read' => true]);
            return response()->json(['message' => 'Notice marked as read']);
        } catch (\Exception $e) {
            Log::error('Failed to mark notice as read: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to mark notice as read'], 500);
        }
    }
}
