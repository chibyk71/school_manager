<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTimeTableRequest;
use App\Http\Requests\UpdateTimeTableRequest;
use App\Jobs\GenerateTimeTableEntries;
use App\Models\Academic\TimeTable;
use App\Models\Academic\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TimeTableController extends Controller
{
    public function index(Request $request, ?Term $term = null)
    {
        Gate::authorize('viewAny', TimeTable::class);

        $query = TimeTable::query()
            ->with(['term:id,name', 'schoolSections:id,name'])
            ->withCount('slots');

        if ($term) {
            $query->where('term_id', $term->id);
        }

        if ($request->boolean('trashed')) {
            $query->onlyTrashed();
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $timetables = $query->latest()->paginate($request->integer('per_page', 15))->withQueryString();

        if ($request->wantsJson()) {
            return response()->json($timetables);
        }

        return Inertia::render('Academic/Timetable/Index', [
            'timetables' => $timetables,
            'filters' => $request->only(['search', 'status', 'trashed']),
            'term' => $term,
        ]);
    }

    public function store(StoreTimeTableRequest $request)
    {
        Gate::authorize('create', TimeTable::class);

        try {
            $data = $request->validated();
            $data['status'] = $data['status'] ?? TimeTable::STATUS_DRAFT;
            $data['school_id'] = $request->user()->school_id;

            $timetable = TimeTable::create($data);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Timetable created successfully successfully',
                    'data' => $timetable,
                ], 201);
            }

            return redirect()
                ->route('timetables.show', $timetable)
                ->with('success', 'Timetable created successfully');
        } catch (\Exception $e) {
            Log::error('Failed to create timetable: ' . $e->getMessage());

            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to create timetable'], 500);
            }

            return redirect()->back()->with('error', 'Failed to create timetable');
        }
    }

    public function show(Request $request, TimeTable $timetable)
    {
        Gate::authorize('view', $timetable);

        $timetable->load(['term:id,name', 'schoolSections:id,name', 'slots']);

        if ($request->wantsJson()) {
            return response()->json($timetable);
        }

        return Inertia::render('Academic/Timetable/Builder', [
            'timetable' => $timetable,
            'slots' => $timetable->slots,
        ]);
    }

    public function update(UpdateTimeTableRequest $request, TimeTable $timetable)
    {
        Gate::authorize('update', $timetable);

        try {
            $timetable->update($request->validated());

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Timetable updated successfully',
                    'data' => $timetable->fresh(),
                ]);
            }

            return redirect()
                ->back()
                ->with('success', 'Timetable updated successfully');
        } catch (\Exception $e) {
            Log::error('Failed to update timetable: ' . $e->getMessage());

            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to update timetable'], 500);
            }

            return redirect()->back()->with('error', 'Failed to update timetable');
        }
    }

    public function destroy(Request $request)
    {
        $ids = $request->input('ids', []);
        if (!is_array($ids) || empty($ids)) {
            $ids = [$request->route('timetable')];
        }

        try {
            $timetables = TimeTable::whereIn('id', $ids)->get();
            foreach ($timetables as $timetable) {
                Gate::authorize('delete', $timetable);
                $timetable->delete();
            }

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Timetable(s) deleted successfully']);
            }

            return redirect()->back()->with('success', 'Timetable(s) deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete timetable: ' . $e->getMessage());

            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to delete timetable'], 500);
            }

            return redirect()->back()->with('error', 'Failed to delete timetable');
        }
    }

    public function restore(Request $request, $id)
    {
        $timetable = TimeTable::withTrashed()->findOrFail($id);
        Gate::authorize('restore', $timetable);

        try {
            $timetable->restore();
            return response()->json(['message' => 'Timetable restored successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to restore timetable: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to restore timetable'], 500);
        }
    }

    public function generate(Request $request, TimeTable $timetable)
    {
        Gate::authorize('update', $timetable);

        try {
            GenerateTimeTableEntries::dispatch($timetable, $request->boolean('dry_run'));

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Timetable generation queued']);
            }

            return redirect()
                ->back()
                ->with('success', 'Timetable generation queued');
        } catch (\Exception $e) {
            Log::error('Failed to queue timetable generation: ' . $e->getMessage());

            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to queue timetable generation'], 500);
            }

            return redirect()->back()->with('error', 'Failed to queue timetable generation');
        }
    }

    /**
     * Activate a draft timetable (archives any currently active one for the same section+term).
     */
    public function activate(Request $request, TimeTable $timetable)
    {
        Gate::authorize('update', $timetable);

        try {
            $timetable->activate();

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Timetable activated successfully',
                    'data' => $timetable->fresh(),
                ]);
            }

            return redirect()
                ->back()
                ->with('success', 'Timetable activated successfully');
        } catch (\RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Failed to activate timetable: ' . $e->getMessage());

            if ($request->wantsJson()) {
                return response()->json(['error' => 'Failed to activate timetable'], 500);
            }

            return redirect()->back()->with('error', 'Failed to activate timetable');
        }
    }
}
