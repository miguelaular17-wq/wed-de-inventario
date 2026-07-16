<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSedeSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        \App\Services\Profiler::start('Middleware::EnsureSedeSelected');

        if ($request->user() && $request->user()->sede && ! $request->session()->has('sede_local')) {
            $request->session()->put('sede_local', strtoupper($request->user()->sede));
        }

        if (! $request->session()->has('sede_local')) {
            if ($request->routeIs('sede.*')) {
                \App\Services\Profiler::stop('Middleware::EnsureSedeSelected');
                return $next($request);
            }

            \App\Services\Profiler::stop('Middleware::EnsureSedeSelected');
            return redirect()->route('sede.select');
        }

        \App\Services\Profiler::stop('Middleware::EnsureSedeSelected');
        return $next($request);
    }
}
