<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // On Vercel every request reaches PHP from the runtime's local proxy (127.0.0.1); the client's IP
        // and https scheme arrive in X-Forwarded-* headers, which Vercel's edge sets and overwrites
        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: [
            'broadcasting/auth',
            'api/*',
        ]);

        // Expires idle rooms and deletes unused data piggybacking on API traffic (no scheduler process needed)
        $middleware->api(append: [
            \App\Http\Middleware\CleanupInactiveRooms::class,
        ]);

        $middleware->alias([
            'room.member' => \App\Http\Middleware\EnsureRoomMember::class,
            'room.host' => \App\Http\Middleware\EnsureRoomHost::class,
            'room.playback_control' => \App\Http\Middleware\EnsurePlaybackControl::class,
            'room.queue_reorder' => \App\Http\Middleware\EnsureQueueReorder::class,
            'app.version' => \App\Http\Middleware\EnforceAppVersion::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Every API error uses one envelope: {"success": false, "message", "code", "errors"}
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 'VALIDATION_FAILED',
                'errors' => $e->errors(),
            ], $e->status);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            $codes = [
                400 => 'BAD_REQUEST',
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                405 => 'METHOD_NOT_ALLOWED',
                422 => 'UNPROCESSABLE',
                426 => 'UPDATE_REQUIRED',
                429 => 'TOO_MANY_REQUESTS',
            ];
            $status = $e->getStatusCode();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: (Response::$statusTexts[$status] ?? 'Error'),
                'code' => $codes[$status] ?? 'HTTP_ERROR',
                'errors' => (object) [],
            ], $status, $e->getHeaders());
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            // These already carry their own response; let the framework render them
            if (!$request->is('api/*') || $e instanceof HttpResponseException || $e instanceof AuthenticationException) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Server Error',
                'code' => 'SERVER_ERROR',
                'errors' => (object) [],
            ], 500);
        });
    })->create();
