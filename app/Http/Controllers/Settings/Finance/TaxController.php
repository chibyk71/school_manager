<?php

namespace App\Http\Controllers\Settings\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TaxController extends Controller
{
    public function index() {
        // Display Tax settings
        $settings = getMergedSettings('tax', GetSchoolModel()); // Fetch Tax settings from the database
        return Inertia::render('Settings/Financial/Tax', compact('settings'));
    }

    public function store(Request $request) {
        // Save Tax settings
        SaveOrUpdateSchoolSettings('tax', $request->all());
        return redirect()->route('settings.finance.tax')->with('success', 'Tax settings updated successfully.');
    }
}
