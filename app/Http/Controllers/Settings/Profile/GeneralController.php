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
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
        ]);

        $user = auth()->user();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->save();

        return redirect()->back()->with('success', 'Profile updated successfully.');
    }
}
