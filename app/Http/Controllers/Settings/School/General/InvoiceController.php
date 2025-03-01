<?php

namespace App\Http\Controllers\Settings\School\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function index() {
        $settings = getMergedSettings('website.invoice', GetSchoolModel());

        return Inertia::render('Settings/School/Invoice', compact('settings'));
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'invoice_logo' => 'required|string',
            'invoice_prefix' => 'required|string',
            'invoice_due' => 'required|string',
            'invoice_round_off' => 'required|string',
            'show_company_details' => 'required|boolean',
            'invoice_header_terms' => 'required|string',
            'invoice_footer_terms' => 'required|string',
        ]);

        $settings = getMergedSettings('website.invoice', GetSchoolModel());
        $settings = array_merge($settings, $validated);
        getSchoolModel()->setSetting('website.invoice', $settings);

        return redirect()->route('website.invoice');
    }
}
