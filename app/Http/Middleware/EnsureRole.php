<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            if ($request->expectsJson()) {
                abort(403, 'Acceso denegado. Permisos insuficientes.');
            }

            if ($user) {
                return redirect('/');
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Acceso denegado. Su rol no tiene permisos para esta sección.',
            ]);
        }

        return $next($request);
    }
}
