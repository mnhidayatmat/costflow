<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CorporateEmail;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function requestForm(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Always answers the same way, so this endpoint cannot be used to discover
     * which addresses have accounts.
     */
    public function sendLink(Request $request): RedirectResponse
    {
        $request->merge(['email' => CorporateEmail::normalize($request->input('email'))]);
        $request->validate(['email' => ['required', 'string', CorporateEmail::rule()]]);

        Password::sendResetLink($request->only('email'));

        $this->audit->logAnonymous('Password reset requested', $request->string('email')->toString(), $request->string('email')->toString());

        return back()->with('status', 'If that account exists, a reset link has been emailed to you.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->merge(['email' => CorporateEmail::normalize($request->input('email'))]);

        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'string', CorporateEmail::rule()],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                // A successful reset also clears any standing security pause.
                $user->clearLock();

                event(new PasswordReset($user));

                $this->audit->log('Password reset', $user->email, $user);
            }
        );

        if ($status !== Password::PasswordReset) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', 'Password updated — you can sign in now.');
    }
}
