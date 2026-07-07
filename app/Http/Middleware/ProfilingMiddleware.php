<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de Profiling Completo
 *
 * Registra en los logs, por cada request HTTP:
 *  - Ruta y método
 *  - Cada query SQL con tiempo individual
 *  - Cantidad total de queries
 *  - Tiempo SQL acumulado
 *  - Tiempo de PHP (procesamiento puro)
 *  - Tiempo total de respuesta
 *  - Memoria peak utilizada
 *  - Tamaño de la respuesta
 *
 * Solo activo cuando APP_PROFILING=true en el .env.
 * No depende de APP_DEBUG para evitar activarlo en producción accidentalmente.
 */
class ProfilingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Solo activo si APP_PROFILING=true en el .env
        if (env('APP_PROFILING') !== 'true') {
            return $next($request);
        }

        $requestStart = microtime(true);
        $queries      = [];

        // Capturar CADA query SQL con su tiempo
        DB::listen(function ($query) use (&$queries) {
            $queries[] = [
                'sql'      => $query->sql,
                'bindings' => $query->bindings,
                'ms'       => round($query->time, 2),
            ];
        });

        // Ejecutar el request
        $response = $next($request);

        // Calcular tiempos
        $totalMs   = round((microtime(true) - $requestStart) * 1000, 2);
        $sqlTotalMs = round(array_sum(array_column($queries, 'ms')), 2);
        $phpMs     = round($totalMs - $sqlTotalMs, 2);
        $memoryMb  = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $queryCount = count($queries);
        $responseSize = strlen($response->getContent());

        // Identificar el controlador/acción actual
        $routeName   = $request->route()?->getName() ?? 'sin-nombre';
        $routeAction = $request->route()?->getActionName() ?? 'sin-acción';

        // ─── Construir el bloque de log ───────────────────────────────────────
        $lines = [];
        $lines[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
        $lines[] = sprintf('🌐 REQUEST  %s %s', $request->method(), $request->path());
        $lines[] = sprintf('   Ruta:    %s', $routeName);
        $lines[] = sprintf('   Acción:  %s', $routeAction);
        $lines[] = '──────────────────────────────────────────────────';

        // Listar cada query SQL
        foreach ($queries as $i => $q) {
            $num = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
            $sql = preg_replace('/\s+/', ' ', trim($q['sql']));
            $sql = strlen($sql) > 120 ? substr($sql, 0, 120) . '…' : $sql;
            // Inyectar bindings de forma legible (solo para el log, no para ejecución)
            $preview = $this->interpolate($sql, $q['bindings']);
            $lines[] = sprintf('   SQL #%s  [%6.2f ms]  %s', $num, $q['ms'], $preview);
        }

        if ($queryCount > 0) {
            $lines[] = '──────────────────────────────────────────────────';
        }

        // Resumen
        $lines[] = sprintf('   ■ Queries SQL   : %d', $queryCount);
        $lines[] = sprintf('   ■ Tiempo SQL    : %s ms  (%s%%)',
            $sqlTotalMs,
            $totalMs > 0 ? round($sqlTotalMs / $totalMs * 100) : 0
        );
        $lines[] = sprintf('   ■ Tiempo PHP    : %s ms  (%s%%)',
            $phpMs,
            $totalMs > 0 ? round($phpMs / $totalMs * 100) : 0
        );
        $lines[] = sprintf('   ■ TOTAL REQUEST : %s ms', $totalMs);
        $lines[] = sprintf('   ■ Memoria peak  : %s MB', $memoryMb);
        $lines[] = sprintf('   ■ Respuesta     : %s KB', round($responseSize / 1024, 1));
        $lines[] = sprintf('   ■ HTTP Status   : %s', $response->getStatusCode());
        $lines[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';

        Log::channel('daily')->info(implode("\n", $lines));

        return $response;
    }

    /**
     * Reemplaza los "?" en el SQL por sus valores reales para lectura humana.
     * Solo para display en logs, no para ejecución.
     */
    private function interpolate(string $sql, array $bindings): string
    {
        foreach ($bindings as $binding) {
            $value = is_string($binding)
                ? '"' . substr($binding, 0, 30) . '"'
                : (is_null($binding) ? 'NULL' : $binding);
            $sql = preg_replace('/\?/', (string) $value, $sql, 1);
        }

        return $sql;
    }
}
