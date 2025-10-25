<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Academic\Term;
use App\Models\Calendar\Event;
use App\Models\Configuration\EventType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing Event resources in a multi-tenant school management system.
 */
class EventController extends Controller
{
    /**
     * Display a listing of events with dynamic querying.
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Event::class);

        try {
            $school = GetSchoolModel();

            // Define extra fields for table query
            $extraFields = [
                [
                    'field' => 'event_type_name',
                    'relation' => 'eventType',
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

            // Build query
            $query = Event::with(['eventType:id,name', 'term:id,name'])
                ->where('school_id', $school?->id)
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query
            $events = $query->tableQuery($request, $extraFields);

            if ($request->wantsJson()) {
                return response()->json($events);
            }

            return Inertia::render('Calendar/Events/Index', [ // UI path: resources/js/Pages/Calendar/Events/Index.vue
                'events' => $events,
                'eventTypes' => EventType::select('id', 'name')->get(),
                'terms' => Term::where('school_id', $school?->id)->select('id', 'name')->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch events: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch events'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch events']);
        }
    }

    /**
     * Store a newly created event in storage.
     *
     * @param StoreEventRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreEventRequest $request)
    {
        Gate::authorize('create', Event::class);

        try {
            $validated = $request->validated();
            $school = GetSchoolModel();
            $validated['school_id'] = $school->id;

            $event = Event::create($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Event created successfully'], 201)
                : redirect()->route('events.index')->with(['success' => 'Event created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create event: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create event'], 500)
                : redirect()->back()->with(['error' => 'Failed to create event'])->withInput();
        }
    }

    /**
     * Display the specified event.
     *
     * @param Request $request
     * @param Event $event
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Event $event)
    {
        Gate::authorize('view', $event);

        try {
            $event->load(['eventType:id,name', 'term:id,name']);
            return response()->json(['event' => $event]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch event: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch event'], 500);
        }
    }

    /**
     * Update the specified event in storage.
     *
     * @param UpdateEventRequest $request
     * @param Event $event
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateEventRequest $request, Event $event)
    {
        Gate::authorize('update', $event);

        try {
            $validated = $request->validated();
            $event->update($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Event updated successfully'])
                : redirect()->route('events.index')->with(['success' => 'Event updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update event: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update event'], 500)
                : redirect()->back()->with(['error' => 'Failed to update event'])->withInput();
        }
    }

    /**
     * Remove the specified event(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', Event::class);

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:events,id',
            ]);

            $school = GetSchoolModel();
            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');

            $query = Event::whereIn('id', $ids)->where('school_id', $school?->id);
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? 'Event(s) deleted successfully' : 'No events were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('events.index')->with(['success' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to delete events: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete event(s)'], 500)
                : redirect()->back()->with(['error' => 'Failed to delete event(s)']);
        }
    }

    /**
     * Restore a soft-deleted event.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $event = Event::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $event);

        try {
            $event->restore();
            return response()->json(['message' => 'Event restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore event: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore event'], 500);
        }
    }

    /**
     * Permanently delete a soft-deleted event.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function forceDestroy(Request $request, $id)
    {
        $event = Event::withTrashed()->findOrFail($id);
        Gate::authorize('forceDelete', $event);

        try {
            $event->forceDelete();
            return response()->json(['message' => 'Event permanently deleted']);
        } catch (\Exception $e) {
            Log::error('Failed to permanently delete event: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to permanently delete event'], 500);
        }
    }
}
