<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Ichtrojan\Otp\Otp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use App\Notifications\EmailVerificationNotification;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        $response = (new Otp)->generate($request->user()->id, 'numeric', 4, 10);

        if (! $response->status) {
            return back()->withErrors([
                'otp' => 'Failed to generate OTP',
            ]);
        }

        $otp = $response->otp;

        Notification::send($request->user(), new EmailVerificationNotification($request->user(), $otp));

        return back()->with('status', 'verification-link-sent');
    }
}
