<?php

namespace App\Http\Controllers\Settings\School;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentsController extends Controller
{
    public function index()
    {
        // Display Payments settings
        $settings = getMergedSettings('payment', GetSchoolModel()); // Fetch Payments settings from the database
        return Inertia::render('Settings.School.Payment', compact('settings'));
    }

    public function store(Request $request)
    {
        // Save Payments settings
        SaveOrUpdateSchoolSettings('payment', $request->all());
        return redirect()->route('settings.school.payment.index')->with('success', 'Payments settings updated successfully.');
    }
}
