<?php

namespace App\Http\Controllers\Settings\School\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuangDeveloper\LaravelSettings\Facades\Settings;

class FeesController extends Controller
{
    public function index()
    {
        // Merge settings: Use school-specific if set, otherwise tenant defaults
        $settings = getMergedSettings('fees', GetSchoolModel());

        return Inertia::render('Settings/Financial/Fees', compact('settings'));
    }

    public function store(Request $request)
    {
        // Validate incoming request data
        $validatedData = $request->validate([
            'allow_offline_payment' => 'required|boolean',
            'offline_bank_payment_instruction' => 'nullable|string',
            'lock_student_panel' => 'required|boolean',
            'print_fees_receipt_for' => 'required|array',
            'single_page' => 'required|boolean'
        ]);

        // Save or update the school-specific settings
        SaveOrUpdateSchoolSettings('fees', $validatedData);

        return redirect()
            ->route('settings.school.fees.index')
            ->with('success', 'Fees settings updated successfully.');
    }
}