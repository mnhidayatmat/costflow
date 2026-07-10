<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $email = $request->string('email')->toString();
        $user = User::where('email', $email)->first();

        if ($user?->isLocked()) {
            throw ValidationException::withMessages([
                'email' => $this->lockMessage($user),
            ]);
        }

        // Deliberately identical failure paths for "no such user" and "wrong
        // password", so the form never confirms which addresses exist.
        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            $this->recordFailure($user, $email);
        }

        if (! $user->hasVerifiedEmail()) {
            $request->session()->put('unverified_email', $user->email);

            throw ValidationException::withMessages([
                'email' => 'Please verify your email address first. Check your inbox for the link.',
            ]);
        }

        $user->clearLock();
        $this->completeLogin($request, $user);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $reason = $request->query('reason');

        $this->audit->log('Signed out', $reason ?? '');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with(
            'status',
            $reason === 'idle' ? 'Signed out after '.config('costflow.idle_minutes').' minutes of inactivity.' : null
        );
    }

    /**
     * Shared by the password, OTP and magic-link paths.
     */
    public static function completeLogin(Request $request, User $user, bool $remember = false): void
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->session()->put('last_activity', Carbon::now()->timestamp);

        $user->forceFill(['last_login_at' => Carbon::now()])->save();

        app(AuditLogger::class)->log('Signed in', "{$user->name} ({$user->role})", $user);
    }

    /**
     * @throws ValidationException always
     */
    private function recordFailure(?User $user, string $email): never
    {
        $max = (int) config('costflow.max_login_attempts');

        if (! $user) {
            $this->audit->logAnonymous('Failed sign-in', 'Unknown account: '.$email, $email);

            throw ValidationException::withMessages([
                'email' => 'Invalid email or password.',
            ]);
        }

        if ($user->registerFailedLogin()) {
            $this->audit->logAnonymous('Account locked', "{$max} failed sign-ins: {$email}", $email);

            throw ValidationException::withMessages([
                'email' => $this->lockMessage($user->refresh()),
            ]);
        }

        $this->audit->logAnonymous('Failed sign-in', $email, $email);

        throw ValidationException::withMessages([
            'email' => "Invalid email or password ({$user->failed_attempts}/{$max} attempts before a security pause).",
        ]);
    }

    private function lockMessage(User $user): string
    {
        $seconds = $user->secondsUntilUnlock();
        $mins = intdiv($seconds, 60);
        $secs = $seconds % 60;

        return sprintf(
            '🔒 Security pause: account locked after %d wrong passwords. Try again in %d:%02d.',
            config('costflow.max_login_attempts'),
            $mins,
            $secs
        );
    }
}
