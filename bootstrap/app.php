<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use App\Http\Middleware\AuditTrailMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function ($middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'audit.trail' => AuditTrailMiddleware::class,
        ]);

        // API STATELESS + CORS
        $middleware->api([
            HandleCors::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function ($exceptions) {

        // 401 — Unauthenticated (expired/missing token)
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        });

        // 403 — Authorization failures from FormRequest::authorize() or Gate
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
                'errors'  => $e->getMessage(),
            ], 403);
        });

        // 422 — Validation errors
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        });

        // 404 — Model not found
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data not found',
            ], 404);
        });

        // 429 — Too Many Requests
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
            ], 429);
        });

        // 404 — Route Not Found
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Route not found',
            ], 404);
        });

        // 500 — Catch-all (only show details in local)
        $exceptions->render(function (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error (DEBUG)',
                'error' => $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine(),
            ], 500);
        });
    })
    ->create();
