<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LoginCodeService;
use App\Support\CorporateEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Passwordless sign-in: a 6-digit code, or a one-click signed link, emailed
 * through Brevo.
 */
class OtpLoginController extends Controller
{
    public function __construct(
        private readonly LoginCodeService $codes,
        private readonly AuditLogger $audit,
    ) {}

    public function create(Request $request): View
    {
        return view('auth.otp', [
            'email' => $request->session()->get('otp_email'),
        ]);
    }

    /**
     * Issue a code. Responds identically whether or not the account exists.
     */
    public function send(Request $request): RedirectResponse
    {
        $request->merge(['email' => CorporateEmail::normalize($request->input('email'))]);
        $request->validate(['email' => ['required', 'string', CorporateEmail::rule()]]);

        $email = $request->string('email')->toString();
        $user = User::where('email', $email)->first();

        if ($user && $user->hasVerifiedEmail() && ! $user->isLocked()) {
            $this->codes->issue($user);
        }

        return redirect()
            ->route('otp.create')
            ->with('otp_email', $email)
            ->with('status', 'If that account exists, a sign-in code is on its way. It expires in '.config('costflow.otp.ttl_minutes').' minutes.');
    }

    /**
     * Verify a typed code.
     */
    public function verify(Request $request): RedirectResponse
    {
        $request->merge(['email' => CorporateEmail::normalize($request->input('email'))]);

        $request->validate([
            'email' => ['required', 'string', CorporateEmail::rule()],
            'code' => ['required', 'string', 'digits:'.config('costflow.otp.length')],
        ]);

        $email = $request->string('email')->toString();
        $user = $this->codes->verify($email, $request->string('code')->toString());

        if (! $user) {
            $this->audit->logAnonymous('Failed sign-in code', $email, $email);

            throw ValidationException::withMessages([
                'code' => 'That code is wrong, expired, or already used. Request a new one.',
            ]);
        }

        return $this->finish($request, $user);
    }

    /**
     * One-click sign-in from the emailed link. The `signed` middleware proves
     * the URL was minted by us; the code proves it has not been used yet.
     */
    public function magic(Request $request): RedirectResponse
    {
        $email = CorporateEmail::normalize($request->query('email'));
        $user = $this->codes->verify($email, (string) $request->query('code'));

        if (! $user) {
            return redirect()->route('otp.create')->withErrors([
                'code' => 'That sign-in link has expired or was already used. Request a new one.',
            ]);
        }

        return $this->finish($request, $user);
    }

    private function finish(Request $request, User $user): RedirectResponse
    {
        if ($user->isLocked()) {
            throw ValidationException::withMessages([
                'code' => 'This account is under a temporary security pause. Try again shortly.',
            ]);
        }

        // Signing in with an emailed code proves the address works.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $user->clearLock();
        AuthenticatedSessionController::completeLogin($request, $user);

        return redirect()->intended(route('dashboard'));
    }
}
