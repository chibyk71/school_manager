<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Ichtrojan\Otp\Otp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use App\Notifications\ResetPasswordNotification;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
            // TODO: add check for sms configuration to allow sms option
        ]);
    }


    /**
     * Handle an incoming password reset link request.
     *
     * This method is responsible for validating the user's credentials and sending them a link to reset their password,
     * it accepts the email addressof the user (student, teacher, parent) and sends a password reset link to the user's email.
     * incase of student without an email address, the system accepts the guardian's email and the student's enrollment Id,
     * it sends the email to the parent with the students detail, he parent can now reset the password for their child.
     * for schools that support sms, an option to get the reset link by sms will be provided nad sent to the provided phone number.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'enrollment_id' => 'nullable|string',
            'phone_number' => 'nullable|string',
        ]);

        if ($request->filled('enrollment_id')) {
            $user = User::where('enrollment_id', $request->enrollment_id)->first('id');
            $request->merge(['email' => $user->student->guardians->user()->email]);
        }else {
            $user = User::where('email', $request->email)->first('id');
        }

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.

        try {

            $res = (new Otp)->generate($user, 'numeric', 6, 5);

            // TODO: send email or sms based on configuration
            Notification::route('mail', $request->email)
                ->notify(new ResetPasswordNotification($res->token, $user));

            return response(['status' => true]);
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'email' => $th->getMessage()]);
        }
    }
}
