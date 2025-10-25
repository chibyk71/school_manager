<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Http\Requests\StoreSchoolRequest;
use App\Http\Requests\UpdateSchoolRequest;
use App\Services\SchoolService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * Controller for managing schools in a single-tenant system.
 */
class SchoolController extends Controller
{
    protected $schoolService;

    /**
     * Create a new controller instance.
     *
     * @param SchoolService $schoolService The school service instance.
     */
    public function __construct(SchoolService $schoolService)
    {
        $this->schoolService = $schoolService;
    }

    /**
     * Display a listing of schools.
     *
     * Retrieves schools based on user permissions and renders the school view.
     *
     * @return \Inertia\Response The Inertia response with schools data.
     *
     * @throws \Exception If school retrieval fails.
     */
    public function index()
    {
        try {
            Gate::authorize('viewAny', School::class);

            $cacheKey = auth()->user()->hasRole('super-admin') ? 'schools.all' : 'schools.user.' . auth()->id();
            $schools = Cache::remember($cacheKey, now()->addHour(), function () {
                return auth()->user()->hasRole('super-admin')
                    ? School::with(['users', 'academicSessions', 'addresses'])->get()
                    : auth()->user()->schools()->with(['users', 'academicSessions', 'addresses'])->get();
            });

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
                        'type' => $school->type,
                        'primary_address' => $school->primaryAddress(),
                        'users' => $school->users->map(fn($user) => ['id' => $user->id, 'name' => $user->name]),
                    ];
                }),
            ], 'resources/js/Pages/Academic/School.vue');
        } catch (\Exception $e) {
            Log::error('Failed to fetch schools: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load schools.');
        }
    }

    /**
     * Store a newly created school.
     *
     * Creates a school with validated data using the school service.
     *
     * @param StoreSchoolRequest $request The validated request.
     * @return \Illuminate\Http\RedirectResponse Redirects on success.
     *
     * @throws \Exception If school creation fails.
     */
    public function store(StoreSchoolRequest $request)
    {
        try {
            Gate::authorize('create', School::class);

            $school = $this->schoolService->createSchool($request->validated());
            return redirect()->route('schools.index')->with('success', 'School created successfully');
        } catch (\Exception $e) {
            Log::error('Failed to create school: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create school: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Update an existing school.
     *
     * Updates a school with validated data.
     *
     * @param UpdateSchoolRequest $request The validated request.
     * @param School $school The school to update.
     * @return \Illuminate\Http\RedirectResponse Redirects on success.
     *
     * @throws \Exception If school update fails.
     */
    public function update(UpdateSchoolRequest $request, School $school)
    {
        try {
            Gate::authorize('update', $school);

            $school->update($request->validated());
            return redirect()->route('schools.index')->with('success', 'School updated successfully');
        } catch (\Exception $e) {
            Log::error('Failed to update school: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update school: ' . $e->getMessage());
        }
    }

    /**
     * Delete one or more schools.
     *
     * Supports single or bulk deletion of schools.
     *
     * @param Request $request The incoming HTTP request.
     * @param string|null $id The ID of the school to delete (optional).
     * @return \Illuminate\Http\RedirectResponse Redirects on success.
     *
     * @throws \Exception If school deletion fails.
     */
    public function destroy(Request $request, $id = null)
    {
        try {
            Gate::authorize('delete', School::class);

            if ($request->has('ids')) {
                $deleted = School::whereIn('id', $request->ids)->delete();
                $message = $deleted ? 'Schools deleted successfully' : 'No schools deleted';
                return redirect()->route('schools.index')->with('success', $message);
            }

            $school = School::findOrFail($id);
            $school->delete();
            return redirect()->route('schools.index')->with('success', 'School deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete school(s): ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete school(s): ' . $e->getMessage());
        }
    }
}
