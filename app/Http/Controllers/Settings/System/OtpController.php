<?php

namespace App\Http\Controllers\Settings\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OtpController extends Controller
{
    public function index()
    {
        $setting = getMergedSettings('opt', GetSchoolModel());

        return Inertia::render('Settings/System/OTP', compact('setting'));
    }

    public function store(Request $request)
    {
        // Save OTP settings
        $validated = $request->validate([
            'otp_type' => 'required|string',
            'limit' => 'required|number|max:10|min:4',
            "eol" => 'required|numbermax:60|min:1'
        ]);

        GetSchoolModel()->setSetting('otp',$validated);

        return back()->with('success', "OTP settings updated successfully.");
    }
}
