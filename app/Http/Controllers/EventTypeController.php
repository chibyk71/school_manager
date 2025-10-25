<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventTypeRequest;
use App\Http\Requests\UpdateEventTypeRequest;
use App\Models\Configuration\EventType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Controller for managing EventType resources in a multi-tenant school management system.
 */
class EventTypeController extends Controller
{
    /**
     * Display a listing of event types with dynamic querying.
     *
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', EventType::class);

        try {
            // Build query
            $query = EventType::withCount('events')
                ->when($request->boolean('with_trashed'), fn($q) => $q->withTrashed());

            // Apply dynamic table query
            $eventTypes = $query->tableQuery($request);

            if ($request->wantsJson()) {
                return response()->json($eventTypes);
            }

            return Inertia::render('Settings/EventTypes/Index', [ // UI path: resources/js/Pages/Settings/EventTypes/Index.vue
                'eventTypes' => $eventTypes,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch event types: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to fetch event types'], 500)
                : redirect()->back()->with(['error' => 'Failed to fetch event types']);
        }
    }

    /**
     * Store a newly created event type in storage.
     *
     * @param StoreEventTypeRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(StoreEventTypeRequest $request)
    {
        Gate::authorize('create', EventType::class);

        try {
            $validated = $request->validated();
            $eventType = EventType::create($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Event type created successfully'], 201)
                : redirect()->route('event-types.index')->with(['success' => 'Event type created successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to create event type: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to create event type'], 500)
                : redirect()->back()->with(['error' => 'Failed to create event type'])->withInput();
        }
    }

    /**
     * Display the specified event type.
     *
     * @param Request $request
     * @param EventType $eventType
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, EventType $eventType)
    {
        Gate::authorize('view', $eventType);

        try {
            $eventType->loadCount('events');
            return response()->json(['eventType' => $eventType]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch event type: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch event type'], 500);
        }
    }

    /**
     * Update the specified event type in storage.
     *
     * @param UpdateEventTypeRequest $request
     * @param EventType $eventType
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(UpdateEventTypeRequest $request, EventType $eventType)
    {
        Gate::authorize('update', $eventType);

        try {
            $validated = $request->validated();
            $eventType->update($validated);

            return $request->wantsJson()
                ? response()->json(['message' => 'Event type updated successfully'])
                : redirect()->route('event-types.index')->with(['success' => 'Event type updated successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to update event type: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to update event type'], 500)
                : redirect()->back()->with(['error' => 'Failed to update event type'])->withInput();
        }
    }

    /**
     * Remove the specified event type(s) from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        Gate::authorize('delete', EventType::class);

        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'exists:event_types,id',
            ]);

            $forceDelete = $request->boolean('force');
            $ids = $request->input('ids');

            $query = EventType::whereIn('id', $ids);
            $deleted = $forceDelete ? $query->forceDelete() : $query->delete();

            $message = $deleted ? 'Event type(s) deleted successfully' : 'No event types were deleted';

            return $request->wantsJson()
                ? response()->json(['message' => $message])
                : redirect()->route('event-types.index')->with(['success' => $message]);
        } catch (\Exception $e) {
            Log::error('Failed to delete event types: ' . $e->getMessage());
            return $request->wantsJson()
                ? response()->json(['error' => 'Failed to delete event type(s)'], 500)
                : redirect()->back()->with(['error' => 'Failed to delete event type(s)']);
        }
    }

    /**
     * Restore a soft-deleted event type.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request, $id)
    {
        $eventType = EventType::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $eventType);

        try {
            $eventType->restore();
            return response()->json(['message' => 'Event type restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore event type: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore event type'], 500);
        }
    }

}
