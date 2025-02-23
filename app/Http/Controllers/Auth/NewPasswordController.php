<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Ichtrojan\Otp\Otp;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword');
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $userId = Session::get('sesId') ?: Auth::user()->id;

        if (! $userId) {
            return redirect()->route('login');
        }

        $status = (new Otp)->validate($request->token, $userId);

        if (! $status->status) {
            return back()->withErrors([
                'token' => 'Invalid token',
            ]);
        }

        $user = User::findOrFail($userId)->first();

        $user->password = Hash::make($request->password);
        $user->save();

        event(new PasswordReset($user));

        return redirect()->route('login')->with('status', 'password-updated');
    }
}
