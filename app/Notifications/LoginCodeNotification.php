<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $code,
        private readonly string $magicLink,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = (int) config('costflow.otp.ttl_minutes');

        return (new MailMessage)
            ->subject('Your COSTFLOW sign-in code: '.$this->code)
            ->greeting('Sign in to COSTFLOW')
            ->line('Use this one-time code to sign in. It expires in '.$minutes.' minutes.')
            ->line('**'.$this->code.'**')
            ->action('Or sign in with one click', $this->magicLink)
            ->line('If you did not request this code, you can safely ignore this email — nobody can sign in without it.')
            ->salutation('BPE Energy Sdn. Bhd. · COSTFLOW');
    }
}
