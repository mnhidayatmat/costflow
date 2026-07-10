<?php

namespace App\Services;

use App\Models\LoginCode;
use App\Models\User;
use App\Notifications\LoginCodeNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;

class LoginCodeService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Issue a fresh one-time code and email it. Any codes still outstanding for
     * this address are consumed first, so only the newest one ever works.
     */
    public function issue(User $user): void
    {
        LoginCode::where('email', $user->email)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => Carbon::now()]);

        $code = $this->randomCode();

        $record = LoginCode::create([
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'expires_at' => Carbon::now()->addMinutes((int) config('costflow.otp.ttl_minutes')),
            'ip_address' => request()->ip(),
        ]);

        $user->notify(new LoginCodeNotification($code, $this->magicLink($record, $code)));

        $this->audit->logAnonymous('Sign-in code sent', $user->email, $user->email);
    }

    /**
     * Verify a submitted code. Consumes it on success; counts the attempt on
     * failure so a code cannot be brute-forced.
     */
    public function verify(string $email, string $code): ?User
    {
        $record = LoginCode::where('email', $email)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $record || ! $record->isUsable()) {
            return null;
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            return null;
        }

        $record->forceFill(['consumed_at' => Carbon::now()])->save();

        return User::where('email', $email)->first();
    }

    /**
     * A signed URL carrying the code, so the recipient can sign in with one click.
     * Expires with the code itself.
     */
    private function magicLink(LoginCode $record, string $code): string
    {
        return URL::temporarySignedRoute('otp.magic', $record->expires_at, [
            'email' => $record->email,
            'code' => $code,
        ]);
    }

    private function randomCode(): string
    {
        $length = (int) config('costflow.otp.length');

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}
