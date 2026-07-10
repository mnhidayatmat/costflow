<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CorporateEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * IT-only administration. The `can:manage-users` middleware guards every route.
 */
class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('pages.users', [
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => CorporateEmail::normalize($request->input('email'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'string', CorporateEmail::rule(), Rule::unique(User::class, 'email')],
            'role' => ['required', Rule::in(config('costflow.roles'))],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'email.regex' => 'Only @'.CorporateEmail::domain().' addresses are accepted.',
            'email.unique' => 'That email already exists.',
        ]);

        $user = User::create($data);

        // Created by IT with a temporary password, so the address is trusted.
        $user->markEmailAsVerified();

        $this->audit->log('User added', "{$user->email} ({$user->role})");

        return back()->with('status', "User added: {$user->email}");
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(config('costflow.roles'))],
        ]);

        if ($user->id === $request->user()->id && $data['role'] !== User::ROLE_IT) {
            return back()->withErrors(['role' => 'You cannot remove your own IT access.']);
        }

        $user->forceFill(['role' => $data['role']])->save();

        $this->audit->log('Role changed', "{$user->email} → {$data['role']}");

        return back()->with('status', "{$user->email} → {$data['role']}");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', Password::min(8)],
        ]);

        $user->forceFill(['password' => $data['password']])->save();
        $user->clearLock();

        $this->audit->log('Password reset', $user->email);

        return back()->with('status', "Password reset for {$user->email}");
    }

    public function unlock(User $user): RedirectResponse
    {
        $user->clearLock();

        $this->audit->log('Account unlocked', $user->email);

        return back()->with('status', "Unlocked {$user->email}");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'You cannot remove your own account.']);
        }

        $email = $user->email;
        $user->delete();

        $this->audit->log('User removed', $email);

        return back()->with('status', "User removed: {$email}");
    }
}
