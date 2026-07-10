<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function edit(Request $request): View
    {
        return view('pages.profile', ['user' => $request->user()]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user->forceFill(['password' => $data['password']])->save();

        // Invalidate the sessions of anyone else holding the old credentials.
        $request->session()->regenerate();

        $this->audit->log('Password changed', $user->email);

        return back()->with('status', 'Password updated.');
    }
}
