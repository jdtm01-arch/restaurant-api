<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Exceptions\BusinessException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'set.restaurant' => \App\Http\Middleware\SetRestaurantContext::class,
            'financial.initialized' => \App\Http\Middleware\EnsureFinancialInitialized::class,
        ]);

        $middleware->api(prepend: [
            \App\Http\Middleware\NormalizeApiResponse::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->render(function (NotFoundHttpException $e, $request) {
            return response()->json([
                'message' => 'Recurso no encontrado'
            ], 404);
        });

        $exceptions->render(function (BusinessException $e, $request) {
            return $e->render();
        });

    })->create();
