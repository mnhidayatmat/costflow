<?php

namespace App\Services;

use App\Models\User;
use App\Models\WccRecord;
use App\Notifications\WccDecided;
use App\Notifications\WccSubmitted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class WccWorkflow
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Move a record to a new status, recording history and notifying whoever
     * now has the ball.
     *
     * Authorization is the caller's job (see WccRecordPolicy::transition).
     *
     * @throws RuntimeException when the transition is not legal from the current status.
     */
    public function transition(WccRecord $record, string $to, User $actor, ?string $note = null): WccRecord
    {
        if (! $record->canTransitionTo($to)) {
            throw new RuntimeException("Cannot move {$record->quo_no} from {$record->status} to {$to}.");
        }

        $from = $record->status;

        DB::transaction(function () use ($record, $to, $from, $actor, $note) {
            $changes = ['status' => $to];

            // The date the job is booked against, fixed at the moment of the
            // decision. Analytics buckets months by this, never by updated_at.
            if ($to === WccRecord::APPROVED) {
                $changes['approved_at'] = Carbon::now();
            }

            $record->forceFill($changes)->save();

            $record->histories()->create([
                'user_id' => $actor->id,
                'from_status' => $from,
                'to_status' => $to,
                'note' => $note,
            ]);
        });

        $this->audit->log("Status → {$to}", "{$record->quo_no} — {$record->client}", $actor);

        $this->notify($record->fresh(), $to, $actor, $note);

        return $record;
    }

    /**
     * Submitted → tell Management there is something to review.
     * Approved / Returned → tell the engineer who owns the record.
     */
    private function notify(WccRecord $record, string $to, User $actor, ?string $note): void
    {
        if (! config('costflow.notify_workflow')) {
            return;
        }

        if ($to === WccRecord::SUBMITTED) {
            $managers = User::where('role', User::ROLE_MANAGEMENT)->whereNotNull('email_verified_at')->get();

            if ($managers->isNotEmpty()) {
                Notification::send($managers, new WccSubmitted($record, $actor));
            }

            return;
        }

        if (in_array($to, [WccRecord::APPROVED, WccRecord::RETURNED], true)) {
            $record->owner?->notify(new WccDecided($record, $actor, $to, $note));
        }
    }
}
