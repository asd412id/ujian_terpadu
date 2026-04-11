<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;

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
        // Trust proxies (Docker internal networks + Cloudflare IP ranges)
        // NEVER use '*' — it allows IP spoofing and bypasses rate limiting.
        // These CIDR ranges cover Docker bridge networks and all Cloudflare edge IPs.
        $middleware->trustProxies(
            at: array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES',
                // Docker internal networks
                '172.16.0.0/12,10.0.0.0/8,192.168.0.0/16,'
                // Cloudflare IPv4 (https://www.cloudflare.com/ips-v4/)
                .'173.245.48.0/20,103.21.244.0/22,103.22.200.0/22,103.31.4.0/22,'
                .'104.16.0.0/13,104.24.0.0/14,108.162.192.0/18,131.0.72.0/22,'
                .'141.101.64.0/18,162.158.0.0/15,172.64.0.0/13,188.114.96.0/20,'
                .'190.93.240.0/20,197.234.240.0/22,198.41.128.0/17'
            )))),
            headers: Request::HEADER_X_FORWARDED_FOR
                   | Request::HEADER_X_FORWARDED_HOST
                   | Request::HEADER_X_FORWARDED_PORT
                   | Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->alias([
            'role'                => \App\Http\Middleware\RoleMiddleware::class,
            'peserta'             => \App\Http\Middleware\AuthPeserta::class,
            'no.active.exam'      => \App\Http\Middleware\EnsureNoActiveExam::class,
            'verify.ujian.token'  => \App\Http\Middleware\VerifyUjianToken::class,
            'force.password.change' => \App\Http\Middleware\ForcePasswordChange::class,
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
