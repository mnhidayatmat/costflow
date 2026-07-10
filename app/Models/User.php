<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

#[Fillable(['name', 'email', 'password', 'role', 'phone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ENGINEER = 'engineer';

    public const ROLE_MANAGEMENT = 'management';

    public const ROLE_IT = 'it';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function records(): HasMany
    {
        return $this->hasMany(WccRecord::class, 'created_by');
    }

    /* ----------------------------------------------------------------
     | Roles
     |----------------------------------------------------------------*/

    public function isEngineer(): bool
    {
        return $this->role === self::ROLE_ENGINEER;
    }

    public function isManagement(): bool
    {
        return $this->role === self::ROLE_MANAGEMENT;
    }

    public function isIt(): bool
    {
        return $this->role === self::ROLE_IT;
    }

    public function initials(): string
    {
        return strtoupper(mb_substr(trim($this->name), 0, 1)) ?: 'U';
    }

    /* ----------------------------------------------------------------
     | Sign-in lockout
     |----------------------------------------------------------------*/

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function secondsUntilUnlock(): int
    {
        return $this->isLocked()
            ? (int) ceil(Carbon::now()->diffInSeconds($this->locked_until, false))
            : 0;
    }

    /**
     * Count a failed sign-in, locking the account once the threshold is reached.
     *
     * @return bool True when this particular failure triggered the lock.
     */
    public function registerFailedLogin(): bool
    {
        $this->increment('failed_attempts');

        if ($this->failed_attempts >= (int) config('costflow.max_login_attempts')) {
            $this->forceFill([
                'failed_attempts' => 0,
                'locked_until' => Carbon::now()->addMinutes((int) config('costflow.lock_minutes')),
            ])->save();

            return true;
        }

        return false;
    }

    public function clearLock(): void
    {
        $this->forceFill([
            'failed_attempts' => 0,
            'locked_until' => null,
        ])->save();
    }
}
