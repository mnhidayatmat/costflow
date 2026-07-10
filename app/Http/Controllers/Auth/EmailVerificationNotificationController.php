<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CorporateEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationNotificationController extends Controller
{
    /**
     * "We emailed you a link" screen, shown right after registering.
     */
    public function notice(Request $request): View
    {
        return view('auth.verify-email', [
            'email' => $request->session()->get('registered_email')
                ?? $request->session()->get('unverified_email'),
        ]);
    }

    /**
     * Resend the link. Always reports success — an unverified address is not
     * something this endpoint should confirm or deny.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => CorporateEmail::normalize($request->input('email'))]);
        $request->validate(['email' => ['required', 'string', CorporateEmail::rule()]]);

        $user = User::where('email', $request->string('email'))->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('status', 'If that account exists and is unverified, a fresh link is on its way.');
    }
}
