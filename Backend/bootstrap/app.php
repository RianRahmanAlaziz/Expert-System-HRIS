<?php

use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->appendToGroup('api', [
            \Illuminate\Session\Middleware\StartSession::class,
        ]);
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        /*
         * Validation error: 422.
         */
        $exceptions->render(
            function (ValidationException $exception, Request $request) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::error(
                    message: 'Validation failed',
                    status: 422,
                    errors: $exception->errors(),
                );
            }
        );
        /*
         * User belum login: 401.
         */
        $exceptions->render(
            function (AuthenticationException $exception, Request $request) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::error(
                    message: 'Unauthenticated',
                    status: 401,
                );
            }
        );

        /*
         * User login tetapi tidak memiliki permission: 403.
         */
        $exceptions->render(
            function (AuthorizationException $exception, Request $request) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::error(
                    message: 'You are not authorized to perform this action',
                    status: 403,
                );
            }
        );

        /*
         * Model tidak ditemukan: 404.
         */
        $exceptions->render(
            function (ModelNotFoundException $exception, Request $request) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return ApiResponse::error(
                    message: 'Resource not found',
                    status: 404,
                );
            }
        );

        /*
         * HTTP error seperti endpoint tidak ditemukan,
         * method salah, dan rate limit.
         */
        $exceptions->render(
            function (HttpExceptionInterface $exception, Request $request) {
                if (! $request->is('api/*')) {
                    return null;
                }

                $status = $exception->getStatusCode();

                $message = match ($status) {
                    404 => 'Endpoint not found',
                    405 => 'HTTP method not allowed',
                    419 => 'Page expired',
                    429 => 'Too many requests',
                    default => 'Request failed',
                };

                return ApiResponse::error(
                    message: $message,
                    status: $status,
                );
            }
        );

        /*
         * Error internal yang tidak dikenali: 500.
         */
        $exceptions->render(
            function (Throwable $exception, Request $request) {
                if (! $request->is('api/*')) {
                    return null;
                }

                $errors = config('app.debug')
                    ? [
                        'exception' => $exception::class,
                        'detail' => $exception->getMessage(),
                    ]
                    : null;

                return ApiResponse::error(
                    message: 'Internal server error',
                    status: 500,
                    errors: $errors,
                );
            }
        );
    })->create();
