<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\WccRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the owning engineer when Management approves or returns their WCC.
 */
class WccDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly WccRecord $record,
        private readonly User $decidedBy,
        private readonly string $decision,
        private readonly ?string $note = null,
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
        $approved = $this->decision === WccRecord::APPROVED;

        $mail = (new MailMessage)
            ->subject(($approved ? 'Approved' : 'Returned for rework').' — '.$r->quo_no)
            ->greeting($approved ? 'Your WCC was approved' : 'Your WCC was returned')
            ->line("**{$this->decidedBy->name}** ".($approved ? 'approved' : 'returned')." {$r->quo_no} ({$r->client}).");

        if (filled($this->note)) {
            $mail->line('Note from management: *'.$this->note.'*');
        }

        if ($approved) {
            $mail->line('Next step: capture the actual costs in WCC2 once the job is complete.');
        } else {
            $mail->line('Next step: revise the costing and submit it again.');
        }

        return $mail
            ->action('Open in COSTFLOW', route('wcc.open', $r))
            ->salutation('BPE Energy Sdn. Bhd. · COSTFLOW');
    }
}
