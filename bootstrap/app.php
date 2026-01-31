<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        \App\Providers\BroadcastServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => null);

        // Enable CORS for API routes
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Check work session status for digit code guests
        $middleware->api(append: [
            \App\Http\Middleware\CheckWorkSessionStatus::class,
        ]);

        // Apply role-specific navigation for Filament admin
        $middleware->web(append: [
            \App\Http\Middleware\ApplyRoleNavigationMiddleware::class,
        ]);

        // Register middleware aliases for route-level usage
        $middleware->alias([
            'account.lockout' => \App\Http\Middleware\CheckAccountLockout::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'partner.feature' => \App\Http\Middleware\CheckPartnerFeature::class,
            'auth.client' => \App\Http\Middleware\AuthenticateClient::class,
        ]);
    })
    ->withSchedule(function ($schedule) {
        // SMTP health check - DISABLED (was causing bounce emails)
        // $schedule->command('smtp:health-check')->everyFifteenMinutes();

        // ZIP cleanup daily at 2 AM (delete ZIPs older than 24h)
        $schedule->command('zips:cleanup --hours=24')->dailyAt('02:00');

        // Conversion job cleanup daily at 3 AM (delete jobs older than 48h)
        $schedule->command('cleanup:conversion-jobs --hours=48 --force')
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/cleanup.log'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });
    })->create();
