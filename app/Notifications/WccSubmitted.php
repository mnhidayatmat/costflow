<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\WccRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every Management user when an engineer submits a WCC for approval.
 */
class WccSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly WccRecord $record,
        private readonly User $submittedBy,
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
        $r = $this->record;

        return (new MailMessage)
            ->subject("WCC awaiting your review — {$r->quo_no}")
            ->greeting('A WCC needs a decision')
            ->line("**{$this->submittedBy->name}** submitted {$r->quo_no} for approval.")
            ->line("Client: {$r->client}")
            ->line("Project: {$r->title}")
            ->line("Department: {$r->dept}")
            ->line('Planned cost: RM '.number_format((float) $r->planned_cost, 2))
            ->line('Selling price: RM '.number_format((float) $r->selling, 2))
            ->line('Margin: '.number_format($r->marginPercent(), 1).'%')
            ->action('Review in COSTFLOW', route('records.index', ['status' => WccRecord::SUBMITTED]))
            ->salutation('BPE Energy Sdn. Bhd. · COSTFLOW');
    }
}
