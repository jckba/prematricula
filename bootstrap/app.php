<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\RequireStudentProfile;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'student.profile' => RequireStudentProfile::class,
            'user.active' => EnsureActiveUser::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => $request->is('api', 'api/*') || $request->expectsJson());
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if ($request->is('api', 'api/*') && $response->getStatusCode() >= 500) {
                return response()->json(['message' => 'No se pudo completar la operación.'], $response->getStatusCode());
            }

            return $response;
        });
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'token', 'access_token']);

        $exceptions->report(function (QueryException $exception): bool {
            Log::error('Error de base de datos.', [
                'connection' => $exception->connectionName,
                'sqlstate' => $exception->errorInfo[0] ?? null,
            ]);

            return false;
        });

        $exceptions->render(function (QueryException $exception, Request $request): ?JsonResponse {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'No se pudo completar la operación.'], 500);
            }

            return null;
        });

        $exceptions->render(
            function (
                DomainException $exception,
                Request $request
            ) {
                if ($request->is('api/*')) {
                    return response()->json([
                        'message' => $exception->getMessage(),
                    ], 422);
                }

                return null;
            }
        );

        $exceptions->render(
            function (
                ModelNotFoundException $exception,
                Request $request
            ) {
                if ($request->is('api/*')) {
                    return response()->json([
                        'message' => 'El recurso solicitado no existe.',
                    ], 404);
                }

                return null;
            }
        );

        $exceptions->render(
            function (
                NotFoundHttpException $exception,
                Request $request
            ) {
                if ($request->is('api/*')) {
                    return response()->json([
                        'message' => 'Ruta o recurso no encontrado.',
                    ], 404);
                }

                return null;
            }
        );

        $exceptions->render(
            function (
                ValidationException $exception,
                Request $request
            ) {
                if ($request->is('api/*')) {
                    return response()->json([
                        'message' => 'Los datos enviados no son válidos.',
                        'errors' => $exception->errors(),
                    ], 422);
                }

                return null;
            }
        );

    })
    ->create();
