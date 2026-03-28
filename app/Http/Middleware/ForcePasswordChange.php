<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::guard('web')->user();

        if ($user && $user->must_change_password) {
            $allowed = ['account.force-change-password', 'account.force-change-password.update', 'logout'];

            if (!$request->routeIs(...$allowed)) {
                return redirect()->route('account.force-change-password');
            }
        }

        return $next($request);
    }
}
