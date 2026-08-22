<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user || ! $user->canAccessAny($permissions)) {
            if ($request->expectsJson()) {
                abort(403, 'Acceso denegado. Permisos insuficientes.');
            }

            if ($user) {
                return redirect('/');
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Acceso denegado. Su usuario no tiene permisos para esta sección.',
            ]);
        }

        return $next($request);
    }
}
