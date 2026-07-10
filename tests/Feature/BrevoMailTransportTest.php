<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;
use Tests\TestCase;

/**
 * The Brevo transport is an HTTP transport, and symfony/brevo-mailer does not
 * pull in an HTTP client of its own. Without symfony/http-client the mailer
 * blows up with a LogicException the first time anything tries to send — which,
 * because verification and reset emails are the first mail an app sends, means
 * nobody can register.
 *
 * These tests resolve the transport for real, so a missing dependency fails here
 * rather than in production.
 */
class BrevoMailTransportTest extends TestCase
{
    public function test_the_brevo_transport_resolves(): void
    {
        config(['mail.mailers.brevo.key' => 'xkeysib-test']);

        $transport = Mail::mailer('brevo')->getSymfonyTransport();

        $this->assertInstanceOf(BrevoApiTransport::class, $transport);
    }

    public function test_a_missing_api_key_fails_loudly(): void
    {
        config(['mail.mailers.brevo.key' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('BREVO_API_KEY is not set');

        Mail::mailer('brevo')->getSymfonyTransport();
    }

    public function test_the_log_mailer_writes_at_debug_level(): void
    {
        // Production runs LOG_LEVEL=warning; the log mailer emits at debug, so it
        // needs its own channel or every email is discarded without a trace.
        $this->assertSame('debug', config('logging.channels.mail.level'));
        $this->assertSame('mail', config('mail.mailers.log.channel'));
    }
}
