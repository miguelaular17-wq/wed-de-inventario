<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            if ($request->expectsJson()) {
                abort(403, 'Acceso de administrador requerido.');
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Debe iniciar sesión como administrador.',
            ]);
        }

        return $next($request);
    }
}
