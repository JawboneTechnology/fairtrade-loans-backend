<?php

use App\Models\ErrorLog;
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
        $middleware->alias([
            'Image' => Intervention\Image\Facades\Image::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        $middleware->api(prepend: [
            \App\Http\Middleware\ApiForceJsonResponse::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })

    ->withSchedule(function (Schedule $schedule) {
        // Schedule the loan deductions command to run every night at midnight
        $schedule->command('loans:deduct')->dailyAt('00:00');
    })

    ->withExceptions(function (Exceptions $exceptions) {
        // Handle the Authorization Exception
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'data' => null
                ], 401);
            }
        });

        // Handle the Unauthorized Exception
        $exceptions->render(function (UnauthorizedException $exception, Request $request) {
            if ($exception instanceof UnauthorizedException) {
                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                    'data' => null,
                ], 403); // You can set the appropriate status code (403 or 401)
            }

            return parent::render($request, $exception);
        });

//        $exceptions->report(function (Throwable $e) {
//            $user = Auth::user();
//
//            ErrorLog::create([
//                'title' => $e->getMessage(),
//                'filename' => $e->getFile(),
//                'description' => $e->getTraceAsString(),
//                'user_id' => $user?->id,
//                'request_data' => json_encode(request()->all()),
//            ]);
//        });
    })->create();
