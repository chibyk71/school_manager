<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }


    /**
     * Handle an incoming authentication request.
     * This method will accept a LoginRequest instance and attempt to authenticate the user,
     * by checking the credentials against the database column for matching email|enrollment_id and password.
     * If the credentials are valid, the user will be authenticated and redirected to the intended URL.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|string',  // Accepts either email or username
            'password' => 'required|string',
            'remember' => 'required|boolean'
        ]);

        // Determine if login input is an email or username
        $fieldType = filter_var($request->email, FILTER_VALIDATE_EMAIL) ? 'email' : 'enrollment_id';

        $credentials = [
            $fieldType => $request->email,
            'password' => $request->password,
        ];

        Log::info(json_encode($credentials));

        if (Auth::attempt($credentials)) {
            $user = User::where($fieldType, $credentials[$fieldType])->firstOrFail();
            Auth::login($user, $request->remember);
            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
