<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionNotExpired
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession() && Filament::auth()->check()) {
            $lifetimeSeconds = (int) config('session.lifetime', 120) * 60;
            $lastActivity = $request->session()->get('panel_last_activity_at');

            if ($lastActivity !== null && (now()->timestamp - (int) $lastActivity) > $lifetimeSeconds) {
                Filament::auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect(Filament::getLoginUrl());
            }

            $request->session()->put('panel_last_activity_at', now()->timestamp);
        }

        return $next($request);
    }
}
