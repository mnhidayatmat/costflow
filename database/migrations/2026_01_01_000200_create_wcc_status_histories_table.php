<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wcc_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wcc_record_id')->constrained('wcc_records')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['wcc_record_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wcc_status_histories');
    }
};
