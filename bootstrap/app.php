<?php

use App\Mail\Client\MailCredentialsMissingException;
use App\Mail\Exceptions\ProviderException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (MailCredentialsMissingException $e, Request $request) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'mail_credentials_missing'], 409);
        });

        $exceptions->render(function (ProviderException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Mail provider error: '.$e->getMessage(), 'code' => 'provider_error'], 502);
            }
        });

        $exceptions->render(function (AuthFailedException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Mail server rejected the credentials. Please sign in again.', 'code' => 'mail_auth_failed'], 409);
            }
        });

        $exceptions->render(function (ConnectionFailedException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Could not connect to the mail server.', 'code' => 'mail_unreachable'], 503);
            }
        });
    })->create();
