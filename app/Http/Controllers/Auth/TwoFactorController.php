<?php
// app/Http/Controllers/Auth/TwoFactorController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laragear\TwoFactor\Facades\Auth2FA;

class TwoFactorController extends Controller
{
    public function enable()
    {
        $user = Auth::user();

        if ($user->hasTwoFactorAuth()) {
            return redirect()->route('profile.edit')->with('info', 'Two-factor authentication is already enabled.');
        }

        $totp = $user->createTwoFactorAuth();

        return Inertia::render('Auth/TwoFactor/Enable', [
            'qrCode' => $totp->toQr(),
            'uri' => $totp->toUri(),
            'secret' => $totp->toString(),
        ]);
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'code' => 'required|totp:' . Auth::id(),
        ]);

        $user = Auth::user();

        if (!$user->confirmTwoFactorAuth($request->code)) {
            throw ValidationException::withMessages(['code' => 'Invalid or expired code.']);
        }

        activity()
            ->performedOn($user)
            ->log('Two-factor authentication enabled');

        return Inertia::render('Auth/TwoFactor/RecoveryCodes', [
            'recoveryCodes' => $user->getRecoveryCodes()->toArray(),
        ]);
    }

    public function disable(Request $request)
    {
        $request->validate([
            'code' => 'required|totp:' . Auth::id(),
        ]);

        $user = Auth::user();
        $user->disableTwoFactorAuth();

        activity()
            ->performedOn($user)
            ->log('Two-factor authentication disabled');

        return redirect()->route('profile.edit')->with('success', 'Two-factor authentication disabled.');
    }

    // Called after email/password login if 2FA is enabled
    public function challenge()
    {
        if (!Auth::check() || !Auth::user()->hasTwoFactorAuth()) {
            return redirect()->route('dashboard');
        }

        // Skip if safe device cookie exists
        if (Auth2FA::hasSafeDeviceCookie(request())) {
            return redirect()->intended();
        }

        return Inertia::render('Auth/TwoFactor/Challenge');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|totp:' . Auth::id(),
            'remember' => 'sometimes|boolean',
        ]);

        $user = Auth::user();

        if (!$user->confirmTwoFactorAuth($request->code)) {
            throw ValidationException::withMessages(['code' => 'Invalid code.']);
        }

        // Optional: Set safe device cookie
        if ($request->boolean('remember')) {
            Auth2FA::setSafeDeviceCookie($request);
        }

        return redirect()->intended();
    }
}
