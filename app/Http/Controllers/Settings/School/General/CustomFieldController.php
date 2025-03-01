<?php

namespace App\Http\Controllers\Settings\School\General;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomFieldController extends Controller
{
    public function index() {
        $settings = getMergedSettings('school.custom_field', GetSchoolModel());

        return Inertia::render('Settings/School/CustomField', [
            'settings' => $settings
        ]);
    }

    public function store(Request $request) {
        $validated = $request->validate([

        ]);

        $settings = getMergedSettings('school.custom_field', GetSchoolModel());

        foreach ($settings as $key => $value) {
            if ($request->has($key)) {
                $settings[$key] = $request->get($key);
            }
        }
    }
}
