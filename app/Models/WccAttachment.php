<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A signature or stamp image referenced by a WCC sheet.
 *
 * Content-addressed: the primary lookup key is the SHA-256 of the bytes, so the
 * same signature uploaded by five people is stored once, and the URL cannot be
 * guessed or walked.
 */
#[Fillable(['hash', 'path', 'mime', 'size', 'uploaded_by'])]
class WccAttachment extends Model
{
    /** Only formats a browser will render as an image, and never SVG. */
    public const ALLOWED_MIMES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    public const MAX_BYTES = 2 * 1024 * 1024;

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getRouteKeyName(): string
    {
        return 'hash';
    }

    public function url(): string
    {
        return route('wcc.attachments.show', $this->hash);
    }
}
