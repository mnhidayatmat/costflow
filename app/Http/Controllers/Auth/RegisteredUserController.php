<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Create the account and email a verification link. The user is not signed
     * in until they click it — the address has to be proven to exist first.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::create($request->safe()->only('name', 'email', 'password', 'role', 'phone'));

        event(new Registered($user));

        $this->audit->logAnonymous('Account created', "{$user->email} ({$user->role})", $user->email);

        return redirect()
            ->route('verification.notice')
            ->with('registered_email', $user->email);
    }
}
