<?php

namespace App\Providers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;

/**
 * Registers Brevo's transactional API as a first-class Laravel mail transport,
 * so `MAIL_MAILER=brevo` works the same way `ses` or `postmark` do.
 */
class BrevoMailServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Mail::extend('brevo', function (array $config): BrevoApiTransport {
            $key = $config['key'] ?? null;

            if (blank($key)) {
                throw new RuntimeException(
                    'BREVO_API_KEY is not set. Add it to your .env, or set MAIL_MAILER=log to write emails to the log instead.'
                );
            }

            return new BrevoApiTransport($key);
        });
    }
}
