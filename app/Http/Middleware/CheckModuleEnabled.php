<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = auth()->user();

        // Se non autenticato, lascia passare (ci pensa Filament auth dopo)
        if (!$user) {
            return $next($request);
        }

        if ($user->hasRole('superadmin')) {
            return $next($request);
        }

        if (!$user->can("module.{$module}")) {
            abort(403, 'Modulo non abilitato');
        }

        return $next($request);
    }
}
