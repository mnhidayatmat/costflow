<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wcc_records', function (Blueprint $table) {
            /*
             * When management approved the job.
             *
             * Analytics used to bucket approved jobs by `updated_at`, so merely
             * re-saving an old record moved its selling value into the current
             * month. This is the date the money is actually booked against.
             */
            $table->timestamp('approved_at')->nullable()->after('status')->index();

            /*
             * Bumped on every save of the sheet. A save carrying a stale version
             * is rejected rather than silently overwriting a colleague's work.
             */
            $table->unsignedInteger('version')->default(1)->after('snapshot');
        });

        $this->backfillApprovedAt();
    }

    /**
     * The status history already records exactly when each approval happened.
     * Fall back to updated_at for rows that predate history tracking.
     */
    private function backfillApprovedAt(): void
    {
        $approvals = DB::table('wcc_status_histories')
            ->where('to_status', 'Approved')
            ->select('wcc_record_id', DB::raw('MAX(created_at) as approved_at'))
            ->groupBy('wcc_record_id')
            ->pluck('approved_at', 'wcc_record_id');

        DB::table('wcc_records')
            ->where('status', 'Approved')
            ->orderBy('id')
            ->each(function (object $record) use ($approvals) {
                DB::table('wcc_records')
                    ->where('id', $record->id)
                    ->update(['approved_at' => $approvals[$record->id] ?? $record->updated_at]);
            });
    }

    public function down(): void
    {
        Schema::table('wcc_records', function (Blueprint $table) {
            $table->dropColumn(['approved_at', 'version']);
        });
    }
};
