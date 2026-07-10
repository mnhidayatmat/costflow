<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Signatures and stamps used to live inside the record's snapshot JSON as
     * base64 data URIs. Two of them pushed a save past PHP's post_max_size,
     * where the body is discarded before Laravel runs — the user saw success
     * and lost their work. They are now files, referenced by URL.
     */
    public function up(): void
    {
        Schema::create('wcc_attachments', function (Blueprint $table) {
            $table->id();

            // SHA-256 of the file contents: identical images stored once, and
            // a URL nobody can guess or enumerate.
            $table->string('hash', 64)->unique();

            $table->string('path');
            $table->string('mime', 60);
            $table->unsignedInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wcc_attachments');
    }
};
