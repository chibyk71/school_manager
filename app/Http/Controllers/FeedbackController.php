<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeedbackRequest;
use App\Http\Requests\UpdateFeedbackRequest;
use App\Models\Communication\Feedback;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing Feedback resources in a multi-tenant school management system.
 */
class FeedbackController extends Controller
{
    /**
     * Display a listing of feedback with dynamic querying.
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Feedback::class);

        try {
            $school = GetSchoolModel();

            // Define extra fields for table query
            $extraFields = [
                [
                    'field' => 'feedbackable_name',
                    'relation' => 'feedbackable',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
                [
                    'field' => 'handled_by_name',
                    'relation' => 'handledBy',
                    'relatedField' => 'name',
                    'filterable' => true,
                    'sortable' => true,
                    'filterType' => 'text',
                ],
            ];

            // Build query
            $query = Feedback::with(['feedbackable:id,name', 'handledBy:id,name'])
                ->where('school_id', $school?->id)
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query
            $feedback = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($feedback);
            }

            return Inertia::render('Communication/Feedback/Index', [ // UI path: resources/js/Pages/Communication/Feedback/Index.vue
                'feedback' => $feedback,
                'users' => User::where('school_id', $school?->id)->select('id', 'name')->get(),
                'categories' => ['Complaint', 'Suggestion', 'Appreciation'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch feedback: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch feedback'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch feedback']);
        }
    }

    /**
     * Store a newly created feedback in storage.
     *
     * @param StoreFeedbackRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreFeedbackRequest $request)
    {
        Gate::authorize('create', Feedback::class);

        try {
            $validated = $request->validated();
            $school = GetSchoolModel();
            $validated['school_id'] = $school->id;
            $validated['id'] = (string) \Illuminate\Support\Str::uuid();

            $feedback = Feedback::create($validated);
            if ($request->has('category')) {
                $feedback->addConfig('category', $validated['category']);
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Feedback created successfully'], 201)
                : redirect()->route('feedback.index')->with(['success' => 'Feedback created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create feedback: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create feedback'], 500)
                : redirect()->back()->with(['error' => 'Failed to create feedback'])->withInput();
        }
    }

    /**
     * Display the specified feedback.
     *
     * @param Request $request
     * @param Feedback $feedback
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Feedback $feedback)
    {
        Gate::authorize('view', $feedback);

        try {
            $feedback->load(['feedbackable:id,name', 'handledBy:id,name']);
            return response()->json(['feedback' => $feedback]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch feedback: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch feedback'], 500);
        }
    }

    /**
     * Update the specified feedback in storage.
     *
     * @param UpdateFeedbackRequest $request
     * @param Feedback $feedback
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateFeedbackRequest $request, Feedback $feedback)
    {
        Gate::authorize('update', $feedback);

        try {
            $validated = $request->validated();
            $feedback->update($validated);
            if ($request->has('category')) {
                $feedback->addConfig('category', $validated['category']);
            }

            return $request->wantsJson()
                ? response()->json(['message' => 'Feedback updated successfully'])
                : redirect()->route('feedback.index')->with(['success' => 'Feedback updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update feedback: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update feedback'], 500)
                : redirect()->back()->with(['error' => 'Failed to update feedback'])->withInput();
        }
    }

    /**
     * Remove the specified feedback(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', Feedback::class);

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:feedback,id',
            ]);

            $school = GetSchoolModel();
            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');

            $query = Feedback::whereIn('id', $ids)->where('school_id', $school?->id);
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? 'Feedback(s) deleted successfully' : 'No feedback was deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('feedback.index')->with(['success' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to delete feedback: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete feedback'], 500)
                : redirect()->back()->with(['error' => 'Failed to delete feedback']);
        }
    }

    /**
     * Restore a soft-deleted feedback.
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $feedback = Feedback::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $feedback);

        try {
            $feedback->restore();
            return response()->json(['message' => 'Feedback restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore feedback: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore feedback'], 500);
        }
    }
}
