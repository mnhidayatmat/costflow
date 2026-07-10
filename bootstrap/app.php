<?php

use App\Http\Middleware\EnforceIdleTimeout;
use App\Http\Middleware\RejectOversizedRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * First in the stack. PHP has already dropped an oversized body, so the
         * CSRF token is gone too — without this, the caller is told their
         * session expired rather than that their upload was too big.
         */
        $middleware->prepend(RejectOversizedRequest::class);

        $middleware->alias([
            'idle' => EnforceIdleTimeout::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The WCC workspace saves over fetch() and needs 422 JSON back on a
        // validation failure, not a redirect it cannot read.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
