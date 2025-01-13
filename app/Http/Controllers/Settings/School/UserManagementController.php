<?php

namespace App\Http\Controllers\Settings\School;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuangDeveloper\LaravelSettings\Facades\Settings;

class UserManagementController extends Controller
{
    public function index()
    {
        // Merge settings: Use school-specific if set, otherwise tenant defaults
        $settings = getMergedSettings('user_management', GetSchoolModel());

        return Inertia::render('Settings.UserManagement.General', compact('settings'));
    }

    public function store(Request $request)
    {
        // Validate incoming request data
        $validatedData = $request->validate([
            'online_admission' => 'required|boolean',
            'allow_student_signin' => 'required|boolean',
            'allow_parent_signin' => 'required|boolean',
            'allow_teacher_signin' => 'required|boolean',
            'allow_staff_signin' => 'required|boolean',
            'online_admission_fee' => 'required|numeric|min:0',
            'online_admission_instruction' => 'nullable|string',
        ]);

        // Save or update the school-specific settings
        SaveOrUpdateSchoolSettings('user_management', $validatedData);

        return redirect()
            ->route('settings.user_management.general.index')
            ->with('success', 'User management settings updated successfully.');
    }
}
