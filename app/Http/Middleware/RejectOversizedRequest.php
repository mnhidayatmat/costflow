<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turn PHP's silent truncation of oversized POST bodies into an honest 413.
 *
 * When a request exceeds `post_max_size`, PHP discards the body during startup
 * — before any application code runs. Laravel then sees a POST with no fields,
 * and the caller gets a confusing validation error or, with display_errors off,
 * a bare 200. A save that quietly did nothing is the worst possible outcome for
 * a costing sheet, so we detect the condition from Content-Length and say so.
 */
class RejectOversizedRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $limit = $this->postMaxSizeInBytes();
        $length = (int) $request->server('CONTENT_LENGTH', 0);

        if ($limit > 0 && $length > $limit) {
            $message = sprintf(
                'That upload is %s, over this server\'s %s limit.',
                $this->humanize($length),
                $this->humanize($limit),
            );

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
            }

            return response($message, Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        return $next($request);
    }

    /**
     * `post_max_size` is written as "8M" / "512K" / "1G", or "0" for no limit.
     */
    private function postMaxSizeInBytes(): int
    {
        $setting = trim((string) ini_get('post_max_size'));

        if ($setting === '' || $setting === '0') {
            return 0;
        }

        $value = (int) $setting;

        return match (strtolower(substr($setting, -1))) {
            'g' => $value * 1024 ** 3,
            'm' => $value * 1024 ** 2,
            'k' => $value * 1024,
            default => $value,
        };
    }

    private function humanize(int $bytes): string
    {
        return $bytes >= 1024 ** 2
            ? round($bytes / 1024 ** 2, 1).' MB'
            : round($bytes / 1024).' KB';
    }
}
