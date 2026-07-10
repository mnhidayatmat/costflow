<?php

namespace App\Http\Controllers;

use App\Models\WccAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Signature and stamp images for the WCC sheets.
 *
 * These used to be base64 data URIs inside the record's snapshot JSON, which
 * made saves grow past PHP's post_max_size — where the request body is thrown
 * away before Laravel ever sees it, and the user is told the save succeeded.
 */
class WccAttachmentController extends Controller
{
    /**
     * Store an image, keyed by the hash of its contents. Re-uploading the same
     * bytes returns the existing row rather than a duplicate file.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'image',                       // rejects SVG, which can carry script
                'mimetypes:'.implode(',', array_keys(WccAttachment::ALLOWED_MIMES)),
                'max:'.(WccAttachment::MAX_BYTES / 1024),
            ],
        ], [
            'file.mimetypes' => 'Only PNG, JPEG or WebP images are accepted.',
            'file.max' => 'Images must be 2 MB or smaller. Try a smaller stamp.',
        ]);

        $file = $request->file('file');
        $hash = hash_file('sha256', $file->getRealPath());

        $attachment = WccAttachment::firstWhere('hash', $hash);

        if (! $attachment) {
            // Trust the sniffed mime, never the client-supplied one.
            $mime = $file->getMimeType();
            $path = "wcc-attachments/{$hash}.".WccAttachment::ALLOWED_MIMES[$mime];

            Storage::disk('local')->put($path, $file->get());

            $attachment = WccAttachment::create([
                'hash' => $hash,
                'path' => $path,
                'mime' => $mime,
                'size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }

        return response()->json([
            'hash' => $attachment->hash,
            'url' => $attachment->url(),
        ], 201);
    }

    /**
     * Stream the image back. Signatures are personal data, so this sits behind
     * the same auth as the rest of the app rather than on a public disk.
     */
    public function show(WccAttachment $attachment): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->response($attachment->path, null, [
            'Content-Type' => $attachment->mime,
            'Content-Disposition' => 'inline',
            // Never let a browser second-guess the type and run it as HTML.
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            // Content-addressed: the bytes at this URL can never change.
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
