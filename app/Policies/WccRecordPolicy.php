<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WccRecord;

/**
 * Engineer costs & submits · Management approves · IT administers.
 */
class WccRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WccRecord $record): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isEngineer() || $user->isIt();
    }

    /**
     * The sheet is editable by its owner (or IT) until it is approved.
     * A submitted record is frozen while Management is looking at it.
     */
    public function update(User $user, WccRecord $record): bool
    {
        if ($user->isIt()) {
            return true;
        }

        if ($record->isLocked() || $record->status === WccRecord::SUBMITTED) {
            return false;
        }

        return $user->isEngineer() && $record->created_by === $user->id;
    }

    /**
     * Approve / Return belong to Management. Everything else on the happy path
     * (Costed, Submitted) belongs to the owning engineer, or to IT.
     */
    public function transition(User $user, WccRecord $record, string $to): bool
    {
        if (! $record->canTransitionTo($to)) {
            return false;
        }

        if (in_array($to, WccRecord::MANAGEMENT_ONLY, true)) {
            return $user->isManagement();
        }

        return $user->isIt() || ($user->isEngineer() && $record->created_by === $user->id);
    }

    public function delete(User $user, WccRecord $record): bool
    {
        return $user->isIt();
    }
}
