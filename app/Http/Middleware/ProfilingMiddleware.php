<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * ProfilingMiddleware — Descomposición completa del request
 *
 * Por cada request registra en el log diario:
 *   - Tiempo total (desde $inicioGlobal en index.php)
 *   - Tiempo antes del controlador (overhead de middleware + setup)
 *   - Tiempo dentro del controlador (dispatch → response object)
 *   - Tiempo de render Blade (getContent() separado del controller)
 *   - Cada query SQL individual con su tiempo
 *   - Si alguna query supera 100 ms → SQL completo interpolado
 *   - Si SQL > 50% del request → marca como cuello de botella
 *   - Tiempo de conexión inicial a PostgreSQL (getPdo())
 *   - Tiempo de sesión (getId())
 *   - Operaciones de caché (HIT / MISS / WRITE con su clave)
 *   - Tiempo sin clasificar (diferencia = posibles llamadas HTTP externas)
 *   - Memoria peak y tamaño de respuesta
 *
 * Solo activo cuando APP_PROFILING=true en .env
 */
class ProfilingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // ── Guard: solo cuando APP_PROFILING=true ─────────────────────
        $isProfiling = env('APP_PROFILING', false) === true || env('APP_PROFILING') === 'true';
        if (!$isProfiling) {
            return $next($request);
        }

        // ── Punto de referencia global ─────────────────────────────────
        // index.php define $inicioGlobal = microtime(true) como primera línea.
        // Usamos esa constante como T0 real del proceso PHP completo.
        // Si no existe (Artisan, tests), usamos el inicio de este middleware.
        $globalStart     = isset($GLOBALS['inicioGlobal']) ? $GLOBALS['inicioGlobal'] : microtime(true);
        $middlewareStart = microtime(true);

        // ── 1. Tiempo de conexión inicial a PostgreSQL ─────────────────
        // getPdo() abre la conexión TCP+SSL+auth si no está ya abierta.
        // Esta es la latencia "cold start" del pooler de Supabase.
        $dbConnMs  = 0.0;
        $dbConnErr = null;
        try {
            $t0       = microtime(true);
            DB::connection()->getPdo();
            $dbConnMs = round((microtime(true) - $t0) * 1000, 2);
        } catch (\Throwable $e) {
            $dbConnErr = $e->getMessage();
        }

        // ── 2. Tiempo de sesión ────────────────────────────────────────
        // session()->getId() fuerza la lectura del archivo de sesión del disco.
        $sessionMs = 0.0;
        $sessionId = null;
        try {
            $t0        = microtime(true);
            $sessionId = $request->session()->getId();
            $sessionMs = round((microtime(true) - $t0) * 1000, 2);
        } catch (\Throwable) {}

        // ── 3. Colector de queries SQL ─────────────────────────────────
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = [
                'sql'      => $query->sql,
                'bindings' => $query->bindings,
                'ms'       => round($query->time, 2),
                'conn'     => $query->connectionName,
            ];
        });

        // ── 4. Colector de operaciones de caché ────────────────────────
        // Captura HIT / MISS / WRITE del driver de caché activo (file/redis/etc.)
        $cacheOps  = [];
        $cacheStart = microtime(true);
        Event::listen(CacheHit::class, function (CacheHit $e) use (&$cacheOps) {
            $cacheOps[] = ['op' => 'HIT',   'key' => substr($e->key, 0, 70)];
        });
        Event::listen(CacheMissed::class, function (CacheMissed $e) use (&$cacheOps) {
            $cacheOps[] = ['op' => 'MISS',  'key' => substr($e->key, 0, 70)];
        });
        Event::listen(KeyWritten::class, function (KeyWritten $e) use (&$cacheOps) {
            $cacheOps[] = ['op' => 'WRITE', 'key' => substr($e->key, 0, 70), 'ttl' => $e->seconds ?? 0];
        });

        // ── 5. Tiempo "antes del controlador" ─────────────────────────
        // Todo lo ejecutado hasta aquí: DB conn + session + listener setup
        $setupMs = round((microtime(true) - $middlewareStart) * 1000, 2);

        // ── 6. Ejecutar el resto de la cadena (controlador + middleware) ──
        $controllerStart = microtime(true);
        $response        = $next($request);
        $controllerMs    = round((microtime(true) - $controllerStart) * 1000, 2);

        // ── 7. Tiempo de render Blade ──────────────────────────────────
        // En Laravel, el controlador devuelve un View object no renderizado.
        // getContent() convierte ese objeto en HTML (compila Blade + ejecuta PHP).
        // Llamarlo aquí nos permite medir el tiempo de render por separado.
        // Es idempotente: si la respuesta es JSON/Redirect, devuelve su contenido
        // sin impacto. El contenido queda cacheado en el objeto Response,
        // así que no se renderiza dos veces cuando Laravel lo envía.
        $bladeMs      = 0.0;
        $responseSize = 0;
        try {
            $bladeStart   = microtime(true);
            $content      = $response->getContent();
            $bladeMs      = round((microtime(true) - $bladeStart) * 1000, 2);
            $responseSize = strlen((string) $content);
        } catch (\Throwable) {}

        // ── 8. Totales ─────────────────────────────────────────────────
        $totalMs    = round((microtime(true) - $globalStart) * 1000, 2);
        $sqlTotalMs = round(array_sum(array_column($queries, 'ms')), 2);
        $queryCount = count($queries);
        $memoryMb   = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $cacheMs    = round((microtime(true) - $cacheStart) * 1000, 2);

        // Tiempo "sin clasificar" = total - (sql + blade + setup + db_conn + session)
        // Si es alto, puede indicar llamadas HTTP externas bloqueantes (ej. BcvRateService)
        $accountedMs  = $sqlTotalMs + $bladeMs + $setupMs + $dbConnMs + $sessionMs;
        $unknownMs    = round(max(0.0, $totalMs - $accountedMs), 2);

        // ── 9. Clasificar bottleneck ───────────────────────────────────
        $sqlPct     = $totalMs > 0 ? round($sqlTotalMs / $totalMs * 100) : 0;
        $bottleneck = null;
        if ($sqlPct > 50 && $sqlTotalMs > 50) {
            $bottleneck = "🔴 SQL > 50% DEL REQUEST ({$sqlPct}%)";
        } elseif ($unknownMs > 200) {
            $bottleneck = "🟠 TIEMPO SIN CLASIFICAR > 200 ms — posible HTTP externo bloqueante";
        } elseif ($totalMs > 1000) {
            $bottleneck = "🟡 REQUEST LENTO (>{$totalMs} ms) — revisar queries y red";
        }

        // ── 10. Identificar ruta y acción ──────────────────────────────
        $routeName   = $request->route()?->getName()      ?? '—';
        $routeAction = $request->route()?->getActionName() ?? '—';
        $httpStatus  = $response->getStatusCode();

        // ── 11. Identificar queries lentas (>100 ms) ──────────────────
        $slowQueries = array_filter($queries, fn($q) => $q['ms'] >= 100);

        // ── 12. Construir bloque de log ────────────────────────────────
        $sep  = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
        $dash = '──────────────────────────────────────────────────────────────────';

        $lines   = [];
        $lines[] = $sep;
        $lines[] = sprintf('🌐 %s /%s  [HTTP %d]  |  %s ms total',
            $request->method(), $request->path(), $httpStatus, $totalMs);
        $lines[] = sprintf('   Ruta   : %s', $routeName);
        $lines[] = sprintf('   Acción : %s', $routeAction);

        // ── Descomposición de tiempos ──────────────────────────────────
        $lines[] = $dash;
        $lines[] = '   ── DESCOMPOSICIÓN DEL REQUEST ──────────────────────────────';
        $lines[] = sprintf('   ⏱  TOTAL (PHP process)    : %8.2f ms', $totalMs);
        $lines[] = sprintf('   ⏱  DB conexión inicial    : %8.2f ms  (TCP+SSL+auth Supabase)',   $dbConnMs);
        $lines[] = sprintf('   ⏱  Sesión (getId)         : %8.2f ms  (lectura archivo session)',  $sessionMs);
        $lines[] = sprintf('   ⏱  Pre-controlador setup  : %8.2f ms  (listeners, guards, etc.)', $setupMs);
        $lines[] = sprintf('   ⏱  Controlador+middleware : %8.2f ms  (dispatch → response obj)', $controllerMs);
        $lines[] = sprintf('   ⏱  Blade render           : %8.2f ms  (compilación plantilla)',   $bladeMs);
        $lines[] = sprintf('   ⏱  SQL total              : %8.2f ms  (%d queries, %d%%)',
            $sqlTotalMs, $queryCount, $sqlPct);
        $lines[] = sprintf('   ⏱  Sin clasificar         : %8.2f ms  %s',
            $unknownMs,
            $unknownMs > 100 ? '← ⚠️  posible HTTP externo' : '');

        // ── Queries SQL individuales ───────────────────────────────────
        if ($queryCount > 0) {
            $lines[] = $dash;
            $lines[] = sprintf('   ── SQL (%d queries) ─────────────────────────────────────────', $queryCount);
            foreach ($queries as $i => $q) {
                $num   = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
                $flag  = $q['ms'] >= 100 ? ' ⚠️ LENTO' : ($q['ms'] >= 50 ? ' 🟡' : '');
                $sql   = preg_replace('/\s+/', ' ', trim($q['sql']));
                $short = strlen($sql) > 115 ? substr($sql, 0, 115) . '…' : $sql;
                $short = $this->interpolate($short, $q['bindings']);
                $lines[] = sprintf('   SQL #%s [%8.2f ms]%s  %s', $num, $q['ms'], $flag, $short);
            }
        }

        // ── Queries lentas: SQL completo ───────────────────────────────
        if (!empty($slowQueries)) {
            $lines[] = $dash;
            $lines[] = '   ── ⚠️  QUERIES > 100 ms — SQL COMPLETO ─────────────────────';
            foreach ($slowQueries as $q) {
                $full = preg_replace('/\s+/', ' ', trim($q['sql']));
                $full = $this->interpolate($full, $q['bindings']);
                $lines[] = sprintf('   [%8.2f ms | conn: %s]', $q['ms'], $q['conn']);
                // Partir en bloques de 120 chars para legibilidad
                foreach (str_split($full, 120) as $chunk) {
                    $lines[] = '      ' . $chunk;
                }
            }
        }

        // ── Caché ──────────────────────────────────────────────────────
        if (!empty($cacheOps)) {
            $hits   = count(array_filter($cacheOps, fn($c) => $c['op'] === 'HIT'));
            $misses = count(array_filter($cacheOps, fn($c) => $c['op'] === 'MISS'));
            $writes = count(array_filter($cacheOps, fn($c) => $c['op'] === 'WRITE'));
            $lines[] = $dash;
            $lines[] = sprintf('   ── CACHÉ  HIT:%d  MISS:%d  WRITE:%d ──────────────────────────',
                $hits, $misses, $writes);
            foreach ($cacheOps as $op) {
                $extra   = isset($op['ttl']) && $op['ttl'] ? " (TTL:{$op['ttl']}s)" : '';
                $lines[] = sprintf('   [%5s] %s%s', $op['op'], $op['key'], $extra);
            }
        }

        // ── Bottleneck ─────────────────────────────────────────────────
        if ($bottleneck) {
            $lines[] = $dash;
            $lines[] = "   $bottleneck";
        }

        // ── Error de conexión (si hubo) ────────────────────────────────
        if ($dbConnErr) {
            $lines[] = $dash;
            $lines[] = "   ❌ DB CONN ERROR: $dbConnErr";
        }

        // ── Resumen final ──────────────────────────────────────────────
        $lines[] = $dash;
        $lines[] = sprintf('   ■ Memoria peak   : %s MB', $memoryMb);
        $lines[] = sprintf('   ■ Respuesta      : %s KB', round($responseSize / 1024, 1));
        $lines[] = $sep;

        Log::channel('daily')->info(implode("\n", $lines));

        \App\Services\Profiler::flush();

        return $response;
    }

    /**
     * Interpola los bindings en el SQL para que el log sea legible.
     * Solo para display. Nunca se ejecuta como SQL.
     */
    private function interpolate(string $sql, array $bindings): string
    {
        foreach ($bindings as $binding) {
            if (is_string($binding)) {
                $val = '"' . (strlen($binding) > 40 ? substr($binding, 0, 40) . '…' : $binding) . '"';
            } elseif (is_null($binding)) {
                $val = 'NULL';
            } elseif (is_bool($binding)) {
                $val = $binding ? 'true' : 'false';
            } else {
                $val = (string) $binding;
            }
            $sql = preg_replace('/\?/', $val, $sql, 1);
        }
        return $sql;
    }
}
