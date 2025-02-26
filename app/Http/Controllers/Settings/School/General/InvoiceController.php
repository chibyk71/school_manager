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

    public function store() {
        
    }
}
