<?php

namespace App\Http\Controllers\Settings\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class GeneralController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $settings = getMergedSettings('profile.general', $user);

        return Inertia::render('Settings/Profile/General', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'two_factor' => 'sometimes|boolean',
            'is_email_verified' => 'sometimes|boolean',
            'is_phone_verified' => 'sometimes|boolean'
        ]);

        $user = auth()->user();
        $settings = getMergedSettings('profile.general', $user);

        foreach ($validated as $field => $value) {
            $settings[$field] = $value;
        }

        $user->setSetting('profile.general', $settings);

        return redirect()->back()->with('success', 'Profile updated successfully.');
    }
}
