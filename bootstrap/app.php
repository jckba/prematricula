<?php

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {

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
    })
    ->create();
