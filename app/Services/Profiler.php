<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Profiler v2 — Alta precisión con hrtime(true).
 *
 * Mide nanosegundos reales (hrtime) + memoria antes/después.
 * Genera un reporte ordenado por tiempo descendente.
 *
 * Uso:
 *   Profiler::start('Bloque');
 *   // ... código ...
 *   Profiler::stop('Bloque', count($collection));
 *
 *   $result = Profiler::measure('Bloque', fn() => ..., count($items));
 */
class Profiler
{
    /** @var array<string, array{ns: int, memBefore: int}> Timers activos */
    private static array $running = [];

    /** @var array<string, array{ms: float, count: int, calls: int, memDelta: int, items: int}> Timings acumulados */
    private static array $timings = [];

    /** @var bool|null */
    private static ?bool $enabled = null;

    public static function enabled(): bool
    {
        if (self::$enabled === null) {
            self::$enabled = env('APP_PROFILING', false) === true || env('APP_PROFILING') === 'true';
        }
        return self::$enabled;
    }

    /**
     * Inicia un bloque con hrtime(true) para nanosegundos reales.
     */
    public static function start(string $name): void
    {
        if (!self::enabled()) return;
        self::$running[$name] = [
            'ns'        => hrtime(true),
            'memBefore' => memory_get_usage(true),
        ];
    }

    /**
     * Detiene un bloque. $items = cantidad de elementos procesados (opcional).
     */
    public static function stop(string $name, int $items = 0): float
    {
        if (!self::enabled() || !isset(self::$running[$name])) return 0.0;

        $nsElapsed = hrtime(true) - self::$running[$name]['ns'];
        $memDelta  = memory_get_usage(true) - self::$running[$name]['memBefore'];
        $ms        = round($nsElapsed / 1_000_000, 3); // nanosegundos → ms con 3 decimales

        unset(self::$running[$name]);

        if (!isset(self::$timings[$name])) {
            self::$timings[$name] = ['ms' => 0.0, 'calls' => 0, 'memDelta' => 0, 'items' => 0];
        }
        self::$timings[$name]['ms']       += $ms;
        self::$timings[$name]['calls']++;
        self::$timings[$name]['memDelta'] += $memDelta;
        self::$timings[$name]['items']    += $items;

        return $ms;
    }

    /**
     * Mide un callable. Retorna su resultado.
     */
    public static function measure(string $name, callable $fn, int $items = 0): mixed
    {
        if (!self::enabled()) return $fn();

        self::start($name);
        $result = $fn();
        self::stop($name, $items);
        return $result;
    }

    /**
     * Registra un valor instantáneo sin start/stop (para eventos puntuales).
     */
    public static function record(string $name, float $ms, int $items = 0, int $memDelta = 0): void
    {
        if (!self::enabled()) return;
        if (!isset(self::$timings[$name])) {
            self::$timings[$name] = ['ms' => 0.0, 'calls' => 0, 'memDelta' => 0, 'items' => 0];
        }
        self::$timings[$name]['ms']       += $ms;
        self::$timings[$name]['calls']++;
        self::$timings[$name]['memDelta'] += $memDelta;
        self::$timings[$name]['items']    += $items;
    }

    /**
     * Todos los timings ordenados de mayor a menor.
     */
    public static function all(): array
    {
        $sorted = self::$timings;
        uasort($sorted, fn($a, $b) => $b['ms'] <=> $a['ms']);
        return $sorted;
    }

    public static function totalMs(): float
    {
        return round(array_sum(array_column(self::$timings, 'ms')), 2);
    }

    /**
     * Vuelca el reporte al log con formato tabular de alta resolución.
     */
    public static function flush(): void
    {
        if (!self::enabled() || empty(self::$timings)) {
            self::$timings = [];
            self::$running = [];
            return;
        }

        $peakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 1);
        $total  = self::totalMs();

        $lines   = [];
        $lines[] = '';
        $lines[] = '╔══════════════════════════════════════════════════════════════════════════════════╗';
        $lines[] = '║  PROFILER v2 — DESGLOSE DE ALTA PRECISIÓN (hrtime)                             ║';
        $lines[] = '╚══════════════════════════════════════════════════════════════════════════════════╝';
        $lines[] = sprintf('  Peak RAM: %s MB   │   Σ Tiempo instrumentado: %.2f ms', $peakMb, $total);
        $lines[] = '  ┌────────────────────────────────────────────────┬─────────┬───────┬──────────────────┐';
        $lines[] = '  │ Bloque                                         │  ms     │ calls │ items / Δ RAM    │';
        $lines[] = '  ├────────────────────────────────────────────────┼─────────┼───────┼──────────────────┤';

        foreach (self::all() as $name => $d) {
            $flag  = $d['ms'] >= 500 ? ' 🔴' : ($d['ms'] >= 100 ? ' ⚠️ ' : '    ');
            $memKb = $d['memDelta'] > 0
                ? sprintf('+%.0f KB', $d['memDelta'] / 1024)
                : ($d['memDelta'] < 0 ? sprintf('%.0f KB', $d['memDelta'] / 1024) : '—');
            $itemsStr = $d['items'] > 0 ? number_format($d['items']) . ' items' : '—';

            $lines[] = sprintf(
                '  │ %-46s │ %7.2f │ %5d │ %-7s / %-7s │%s',
                substr($name, 0, 46),
                $d['ms'],
                $d['calls'],
                $itemsStr,
                $memKb,
                $flag
            );
        }

        $lines[] = '  └────────────────────────────────────────────────┴─────────┴───────┴──────────────────┘';

        Log::channel('daily')->info(implode("\n", $lines));

        self::$timings = [];
        self::$running = [];
    }
}
