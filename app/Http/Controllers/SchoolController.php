<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Http\Requests\StoreSchoolRequest;
use App\Http\Requests\UpdateSchoolRequest;
use App\Services\SchoolService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SchoolController extends Controller
{
    protected $schoolService;

    public function __construct(SchoolService $schoolService)
    {
        $this->schoolService = $schoolService;
    }

    public function index()
    {
        Gate::authorize('viewAny', School::class);

        // Load schools based on user permissions and tenancy
        $schools = auth()->user()->hasRole('super-admin')
            ? School::with(['users', 'academicSessions'])->get()
            : auth()->user()->schools()->with(['users', 'academicSessions'])->get();

        return Inertia::render('Academic/School', [
            'schools' => $schools->map(function ($school) {
                return [
                    'id' => $school->id,
                    'name' => $school->name,
                    'slug' => $school->slug,
                    'email' => $school->email,
                    'phone_one' => $school->phone_one,
                    'phone_two' => $school->phone_two,
                    'logo' => $school->logo,
                    'tenancy_type' => $school->tenancy_type,
                    'parent_id' => $school->parent_id,
                ];
            }),
        ]);
    }

    public function store(StoreSchoolRequest $request)
    {
        Gate::authorize('create', School::class);

        try {
            $school = $this->schoolService->createSchool($request->validated());
            return redirect()->route('schools.index')->with('message', 'School created successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function update(UpdateSchoolRequest $request, School $school)
    {
        Gate::authorize('update', $school);

        try {
            $school->update($request->validated());
            return redirect()->route('schools.index')->with('message', 'School updated successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update school']);
        }
    }

    public function destroy(Request $request, $id = null)
    {
        Gate::authorize('delete', School::class);

        try {
            if ($request->has('ids')) {
                $deleted = School::whereIn('id', $request->ids)->delete();
                $message = $deleted ? 'Schools deleted successfully' : 'No schools deleted';
                return back()->with('message', $message);
            }

            $school = School::findOrFail($id);
            $school->delete();
            return redirect()->route('schools.index')->with('message', 'School deleted successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete school(s)']);
        }
    }
}
