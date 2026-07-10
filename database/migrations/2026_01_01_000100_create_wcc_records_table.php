<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wcc_records', function (Blueprint $table) {
            $table->id();

            // Header fields lifted out of the WCC1 sheet so records stay queryable.
            $table->string('quo_no')->index();
            $table->string('client');
            $table->string('title');
            $table->string('dept')->nullable()->index();
            $table->string('manager')->nullable()->index();

            // Money, in MYR. WCC1 total planned cost, BPE Price grand total, WCC2 actual.
            $table->decimal('planned_cost', 15, 2)->default(0);
            $table->decimal('selling', 15, 2)->default(0);
            $table->decimal('actual', 15, 2)->default(0);

            // Draft | Costed | Submitted | Approved | Returned
            $table->string('status', 20)->default('Draft')->index();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            /*
             * Verbatim JSON produced by the template engine's cap() function.
             * Source of truth for the spreadsheet; never parsed server-side.
             * longText because embedded signature images are base64 data URIs.
             */
            $table->longText('snapshot')->nullable();

            $table->timestamps();

            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wcc_records');
    }
};
