<?php

namespace App\Http\Middleware;

use App\Services\AccesosService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Corta las rutas que el administrador desactivó para la persona (o para todos), aunque se escriba la dirección. */
class BloquearAccesosDesactivados
{
    public function __construct(private AccesosService $accesos) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Con sesión: lo desactivado para la persona o para todos. Sin sesión: los accesos rápidos de inicio.
        $user = $request->user();
        if ($this->accesos->peticionBloqueada($user, $request)) {
            $mensaje = 'Este acceso fue desactivado por el administrador.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $mensaje], 403);
            }
            abort(403, $mensaje);
        }

        return $next($request);
    }
}
