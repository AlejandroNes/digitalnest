<?php

use App\Http\Middleware\ApplyRedirects;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend([SecurityHeaders::class, ApplyRedirects::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['smtp_username', 'smtp_password', 'submission_token']);
        $exceptions->report(function (QueryException $exception): bool {
            Log::error('database.operation_failed', ['exception' => $exception::class]);

            return false;
        });
        $exceptions->report(function (TransportExceptionInterface $exception): bool {
            Log::error('mail.transport_failed', ['exception' => $exception::class]);

            return false;
        });
        $exceptions->respond(fn (Response $response): Response => SecurityHeaders::apply(request(), $response));
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
