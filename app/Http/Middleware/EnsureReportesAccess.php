<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureReportesAccess
{
    /**
     * @param  string  ...$tambien  Permisos que también dan acceso (p. ej. "inventario" en análisis de categorías).
     */
    public function handle(Request $request, Closure $next, string ...$tambien): Response
    {
        $user = $request->user();

        if (! $user || (! $user->puedeVerReportes() && ! $user->canAccessAny($tambien))) {
            if ($request->expectsJson()) {
                abort(403, 'Acceso denegado. Permisos insuficientes.');
            }

            return $user
                ? redirect('/')
                : redirect()->route('login');
        }

        return $next($request);
    }
}
