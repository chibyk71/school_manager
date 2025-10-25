<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

/**
 * Controller for managing users in the school management system.
 */
class UserController extends Controller
{
    /**
     * Display a listing of users for the current school.
     *
     * @param Request $request The HTTP request instance.
     * @return \Inertia\Response
     * @throws \Exception If no active school is found.
     */
    public function index(Request $request)
    {
        try {
            permitted('view-users');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $query = User::query()->whereHas('schools', function ($query) use ($school) {
                $query->where('school_id', $school->id);
            })->with(['roles', 'student', 'teacher', 'guardian']);

            if ($request->has('noFallback')) {
                $query->withoutFallback();
            }

            $users = $query->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name'),
                    'is_student' => $user->student !== null,
                    'is_teacher' => $user->teacher !== null,
                    'is_guardian' => $user->guardian !== null,
                ];
            });

            $schools = School::pluck('name', 'id');

            return Inertia::render('UserManagement/User', [
                'users' => $users,
                'schools' => $schools,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch users: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Failed to load users.');
        }
    }

    /**
     * Store a newly created user in storage.
     *
     * @param Request $request The HTTP request instance.
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        try {
            permitted('create-users');

            $school = GetSchoolModel();
            if (!$school) {
                abort(403, 'No active school found.');
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'username' => 'required|string|max:255|unique:users,username',
                'email' => 'nullable|email|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
                'school_ids' => 'required|array',
                'school_ids.*' => 'exists:schools,id',
                'roles' => 'required|array',
                'roles.*' => 'exists:roles,name',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $user = User::create($validator->validated());
            $user->schools()->sync($request->school_ids);
            $user->syncRoles($request->roles);

            return redirect()->route('users.index')->with('success', 'User created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create user: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create user.');
        }
    }

    /**
     * Update the specified user in storage.
     *
     * @param Request $request The HTTP request instance.
     * @param User $user The user instance.
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, User $user)
    {
        try {
            permitted('edit-users');

            $school = GetSchoolModel();
            if (!$school || !$user->schools()->where('school_id', $school->id)->exists()) {
                abort(403, 'Unauthorized access to user.');
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'username' => 'required|string|max:255|unique:users,username,' . $user->id,
                'email' => 'nullable|email|unique:users,email,' . $user->id,
                'password' => 'nullable|string|min:8|confirmed',
                'school_ids' => 'required|array',
                'school_ids.*' => 'exists:schools,id',
                'roles' => 'required|array',
                'roles.*' => 'exists:roles,name',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $data = $validator->validated();
            if (empty($data['password'])) {
                unset($data['password']);
            }
            $user->update($data);
            $user->schools()->sync($request->school_ids);
            $user->syncRoles($request->roles);

            return redirect()->route('users.index')->with('success', 'User updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update user: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update user.');
        }
    }

    /**
     * Remove the specified user from storage.
     *
     * @param User $user The user instance.
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception If no active school is found.
     */
    public function destroy(User $user)
    {
        try {
            permitted('delete-users');

            $school = GetSchoolModel();
            if (!$school || !$user->schools()->where('school_id', $school->id)->exists()) {
                abort(403, 'Unauthorized access to user.');
            }

            if ($user->student || $user->teacher || $user->guardian) {
                return redirect()->route('users.index')->with('error', 'Cannot delete user with associated student, teacher, or guardian profile.');
            }

            $user->delete();

            return redirect()->route('users.index')->with('success', 'User deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete user: ' . $e->getMessage());
            return redirect()->route('users.index')->with('error', 'Failed to delete user.');
        }
    }
}
