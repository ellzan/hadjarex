<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! ($user instanceof User) || ! $user->must_change_password) {
            return $next($request);
        }

        if (
            $request->routeIs('filament.panel.pages.change-password') ||
            $request->routeIs('filament.panel.auth.logout') ||
            $request->is('panel/change-password') ||
            $request->is('panel/logout') ||
            $request->is('livewire/*')
        ) {
            return $next($request);
        }

        return redirect()->route('filament.panel.pages.change-password');
    }
}
