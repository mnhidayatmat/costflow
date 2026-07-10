<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs a user out after a stretch of inactivity, mirroring the original
 * client-side watchdog. Enforced here so a stale tab cannot keep a session
 * alive by simply not being closed.
 */
class EnforceIdleTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $limit = (int) config('costflow.idle_minutes') * 60;
        $last = $request->session()->get('last_activity');

        if ($last !== null && (Carbon::now()->timestamp - $last) > $limit) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with(
                'status',
                'Signed out after '.config('costflow.idle_minutes').' minutes of inactivity.'
            );
        }

        $request->session()->put('last_activity', Carbon::now()->timestamp);

        return $next($request);
    }
}
