<?php

namespace App\Providers;

use App\Models\User;
use App\Models\WccRecord;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::shouldBeStrict($this->app->isLocal());

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Governance surfaces that only IT may reach.
        Gate::define('manage-users', fn (User $user) => $user->isIt());
        Gate::define('clear-audit-log', fn (User $user) => $user->isIt());

        // Management is the only role that decides on a submitted WCC.
        Gate::define('approve-wcc', fn (User $user) => $user->isManagement());

        // The nav badge showing how many records exist.
        View::composer('partials.sidebar', fn ($view) => $view->with('recordCount', WccRecord::count()));

        $this->brandAuthEmails();
    }

    /**
     * Laravel's stock verification / reset emails, reworded for BPE.
     */
    private function brandAuthEmails(): void
    {
        VerifyEmail::toMailUsing(fn (User $user, string $url) => (new MailMessage)
            ->subject('Verify your COSTFLOW account')
            ->greeting("Welcome, {$user->name}")
            ->line('Confirm this address to activate your COSTFLOW account. Until you do, you will not be able to sign in.')
            ->action('Verify email address', $url)
            ->line('If you did not create a COSTFLOW account, no further action is required.')
            ->salutation('BPE Energy Sdn. Bhd. · COSTFLOW'));

        ResetPassword::toMailUsing(fn (User $user, string $token) => (new MailMessage)
            ->subject('Reset your COSTFLOW password')
            ->greeting('Password reset requested')
            ->line('Click below to choose a new password for '.$user->email.'.')
            ->action('Reset password', route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->line('This link expires in '.config('auth.passwords.users.expire').' minutes.')
            ->line('If you did not request a reset, ignore this email — your password will not change.')
            ->salutation('BPE Energy Sdn. Bhd. · COSTFLOW'));
    }
}
