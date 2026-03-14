<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('horizon:snapshot')->everyFiveMinutes();
        $schedule->command('ujian:auto-submit')->everyMinute();
        $schedule->command('soal:cleanup-orphan-images')->dailyAt('03:00');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies (Cloudflare Tunnel forwards requests via HTTP internally)
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role'            => \App\Http\Middleware\RoleMiddleware::class,
            'peserta'         => \App\Http\Middleware\AuthPeserta::class,
            'no.active.exam'  => \App\Http\Middleware\EnsureNoActiveExam::class,
            'verify.ujian.token' => \App\Http\Middleware\VerifyUjianToken::class,
        ]);

        // Where to redirect authenticated users who hit "guest" routes (e.g. /login)
        $middleware->redirectUsersTo(function (\Illuminate\Http\Request $request) {
            if (\Illuminate\Support\Facades\Auth::guard('web')->check()) {
                /** @var \App\Models\User $user */
                $user = \Illuminate\Support\Facades\Auth::guard('web')->user();
                return route($user->getDashboardRoute());
            }
            if (\Illuminate\Support\Facades\Auth::guard('peserta')->check()) {
                return route('ujian.lobby');
            }
            return '/dinas/dashboard';
        });

        // Livewire CSRF exception
        $middleware->validateCsrfTokens(except: [
            'api/ujian/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
