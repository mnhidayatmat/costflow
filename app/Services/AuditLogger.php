<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * Append an entry to the immutable activity trail.
     *
     * The actor email is denormalized so the trail still reads correctly
     * after the user row is deleted.
     */
    public function log(string $action, string $detail = '', ?User $actor = null): AuditLog
    {
        $actor ??= Auth::user();

        return AuditLog::create([
            'user_id' => $actor?->id,
            'actor' => $actor?->email ?? 'system',
            'action' => $action,
            'detail' => $detail,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ]);
    }

    /**
     * Log an action for someone who is not signed in yet — a failed sign-in,
     * an account lock, an OTP request.
     */
    public function logAnonymous(string $action, string $detail, string $actor): AuditLog
    {
        return AuditLog::create([
            'user_id' => null,
            'actor' => $actor,
            'action' => $action,
            'detail' => $detail,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ]);
    }
}
