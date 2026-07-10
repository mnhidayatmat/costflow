<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Verification happens while signed out — the account is created, then the
 * emailed link both proves the address and signs the user in.
 * The `signed` middleware is what authenticates this request.
 */
class VerifyEmailController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            throw new AccessDeniedHttpException('Invalid verification link.');
        }

        if ($user->hasVerifiedEmail()) {
            return $this->signInAndRedirect($request, $user, 'Your email is already verified.');
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        $this->audit->log('Email verified', $user->email, $user);

        return $this->signInAndRedirect($request, $user, 'Email verified — welcome aboard!');
    }

    private function signInAndRedirect(Request $request, User $user, string $status): RedirectResponse
    {
        if (Auth::check() && Auth::id() !== $user->id) {
            Auth::guard('web')->logout();
        }

        AuthenticatedSessionController::completeLogin($request, $user);

        return redirect()->route('dashboard')->with('status', $status);
    }
}
