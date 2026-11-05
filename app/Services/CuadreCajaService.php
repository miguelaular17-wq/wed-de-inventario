<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\CuadreCaja;
use App\Models\CuadreCajaConteo;
use App\Models\CuadreCajaPago;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuadre de caja: los ingresos que la cajera declaró en el cierre de Suite contra lo que
 * Tesorería contó a mano de cada forma de pago. Diferencia = Tesorería − Suite (en USD).
 */
class CuadreCajaService
{
    public const ESTADOS = ['pendiente' => 'Pendiente', 'conciliado' => 'Conciliado'];

    /**
     * @return array{filas: list<array<string, mixed>>, totales: array<string, float|int>, ultimaCarga: array<string, string>}
     */
    public function consolidado(Carbon $desde, Carbon $hasta, ?string $sede = null, ?string $cajera = null, ?string $estado = null): array
    {
        if (! Schema::hasTable('cuadres_caja')) {
            return ['filas' => [], 'totales' => $this->totales([]), 'ultimaCarga' => []];
        }

        $query = CuadreCaja::query()
            ->with(['pagos', 'conteos', 'conciliador'])
            ->whereBetween('fecha_cierre', [$desde->toDateString(), $hasta->toDateString().' 23:59:59']);
        if ($sede) {
            $query->where('sede', strtoupper(trim($sede)));
        }
        $cajera = trim((string) $cajera);
        if ($cajera !== '') {
            $like = '%'.mb_strtoupper($cajera, 'UTF-8').'%';
            $cedulas = Cliente::query()->whereRaw('UPPER(nombre) LIKE ?', [$like])->pluck('cedula')
                ->flatMap(fn ($c) => [$c, 'V'.$c, 'E'.$c])->all();
            $query->where(fn ($q) => $q->whereRaw('UPPER(usuario_nombre) LIKE ?', [$like])
                ->orWhereRaw('UPPER(usuario) LIKE ?', [$like])
                ->orWhereIn('usuario', $cedulas));
        }
        if ($estado === 'pendiente') {
            $query->whereNull('conciliado_at');
        } elseif ($estado === 'conciliado') {
            $query->whereNotNull('conciliado_at');
        }

        $cierres = $query->orderBy('fecha_cierre')->orderBy('sede')->orderBy('codigo_caja')->orderBy('id_cierre')->get();
        $this->completarNombres($cierres);
        $filas = $cierres
            ->map(fn (CuadreCaja $c) => ['cierre' => $c] + $this->resumen($c))
            ->all();

        return [
            'filas' => $filas,
            'totales' => $this->totales($filas),
            'ultimaCarga' => $this->ultimaCarga(),
        ];
    }

    /**
     * Suite no siempre manda el nombre de la cajera, solo su cédula (a veces con "V"):
     * se toma el de su ficha de empleado. No se guarda; el sincronizador puede traerlo después.
     *
     * @param  iterable<CuadreCaja>  $cierres
     */
    public function completarNombres(iterable $cierres): void
    {
        $sinNombre = collect($cierres)->filter(fn (CuadreCaja $c) => ! $c->usuario_nombre && $c->usuario);
        if ($sinNombre->isEmpty()) {
            return;
        }

        $nombres = Cliente::query()
            ->whereIn('cedula', $sinNombre->map(fn (CuadreCaja $c) => $this->cedula($c->usuario))->unique()->values())
            ->pluck('nombre', 'cedula');

        foreach ($sinNombre as $cierre) {
            $nombre = $nombres[$this->cedula($cierre->usuario)] ?? null;
            if ($nombre) {
                $cierre->usuario_nombre = $nombre;
            }
        }
    }

    /**
     * Una línea por forma de pago y cuenta: lo de Suite y lo que contó Tesorería.
     *
     * @return list<array<string, mixed>>
     */
    public function lineas(CuadreCaja $cuadre): array
    {
        $factor = $cuadre->factor_cambio > 0 ? $cuadre->factor_cambio : null;
        $lineas = [];

        foreach ($cuadre->pagos as $pago) {
            /** @var CuadreCajaPago $pago */
            $clave = $this->clave($pago->codigo_metodo, $pago->codigo_cuenta);
            $lineas[$clave] ??= $this->lineaVacia($clave, $pago->codigo_metodo, $pago->codigo_cuenta, $pago->forma_pago, $pago->cuenta, $pago->en_dolares);
            $lineas[$clave]['suite'] += $pago->ingresos;
            $lineas[$clave]['suite_usd'] += $pago->ingresos_usd;
        }

        foreach ($cuadre->conteos as $conteo) {
            /** @var CuadreCajaConteo $conteo */
            $clave = $this->clave($conteo->codigo_metodo, $conteo->codigo_cuenta);
            $lineas[$clave] ??= $this->lineaVacia($clave, $conteo->codigo_metodo, $conteo->codigo_cuenta, $conteo->forma_pago, $conteo->cuenta, $conteo->en_dolares);
            $lineas[$clave]['tesoreria'] = $conteo->monto;
        }

        foreach ($lineas as &$linea) {
            $linea['suite'] = round($linea['suite'], 2);
            $linea['suite_usd'] = round($linea['suite_usd'], 2);
            if ($linea['tesoreria'] !== null) {
                $usd = $linea['en_dolares'] ? $linea['tesoreria'] : ($factor ? $linea['tesoreria'] / $factor : 0.0);
                $linea['tesoreria_usd'] = round($usd, 2);
            } elseif ($cuadre->conciliado()) {
                $linea['tesoreria_usd'] = 0.0;
            }
            if ($linea['tesoreria_usd'] !== null) {
                $linea['diferencia_usd'] = round($linea['tesoreria_usd'] - $linea['suite_usd'], 2);
            }
        }
        unset($linea);

        return array_values($lineas);
    }

    /** @return array{ingresos_usd: float, tesoreria_usd: float|null, diferencia_usd: float|null, lineas: list<array<string, mixed>>} */
    public function resumen(CuadreCaja $cuadre): array
    {
        $lineas = $this->lineas($cuadre);
        $ingresos = round(array_sum(array_column($lineas, 'suite_usd')), 2);
        if (! $cuadre->conciliado()) {
            return ['lineas' => $lineas, 'ingresos_usd' => $ingresos, 'tesoreria_usd' => null, 'diferencia_usd' => null];
        }
        $tesoreria = round(array_sum(array_map(fn ($l) => $l['tesoreria_usd'] ?? 0.0, $lineas)), 2);

        return [
            'lineas' => $lineas,
            'ingresos_usd' => $ingresos,
            'tesoreria_usd' => $tesoreria,
            'diferencia_usd' => round($tesoreria - $ingresos, 2),
        ];
    }

    /**
     * Formas de pago con cuenta que ya aparecieron en los cierres de la sede, para que Tesorería
     * pueda anotar una que la cajera no declaró.
     *
     * @return list<array{clave:string, codigo_metodo:string, codigo_cuenta:string, forma_pago:?string, cuenta:?string, en_dolares:bool}>
     */
    public function formasDePago(string $sede): array
    {
        return CuadreCajaPago::query()
            ->join('cuadres_caja as c', 'c.id', '=', 'cuadre_caja_pagos.cuadre_caja_id')
            ->where('c.sede', $sede)
            ->whereNotNull('cuadre_caja_pagos.codigo_metodo')
            ->select('cuadre_caja_pagos.codigo_metodo', 'cuadre_caja_pagos.codigo_cuenta', 'cuadre_caja_pagos.en_dolares')
            ->selectRaw('MAX(cuadre_caja_pagos.forma_pago) as forma_pago, MAX(cuadre_caja_pagos.cuenta) as cuenta')
            ->groupBy('cuadre_caja_pagos.codigo_metodo', 'cuadre_caja_pagos.codigo_cuenta', 'cuadre_caja_pagos.en_dolares')
            ->orderByRaw('MAX(cuadre_caja_pagos.forma_pago)')
            ->get()
            ->map(fn ($r) => [
                'clave' => $this->clave($r->codigo_metodo, $r->codigo_cuenta),
                'codigo_metodo' => (string) $r->codigo_metodo,
                'codigo_cuenta' => (string) ($r->codigo_cuenta ?? ''),
                'forma_pago' => $r->forma_pago,
                'cuenta' => $r->cuenta,
                'en_dolares' => (bool) $r->en_dolares,
            ])
            ->unique('clave')
            ->values()
            ->all();
    }

    /**
     * Guarda lo que contó Tesorería. Un monto vacío borra el conteo de esa forma de pago.
     *
     * @param  array<string, string|float|int|null>  $montos  clave (metodo|cuenta) => monto en la moneda de la forma de pago
     */
    public function conciliar(CuadreCaja $cuadre, array $montos, ?string $observacion, User $user): void
    {
        $conocidas = collect($this->lineas($cuadre))->keyBy('clave')
            ->union(collect($this->formasDePago($cuadre->sede))->keyBy('clave'));

        DB::transaction(function () use ($cuadre, $montos, $observacion, $user, $conocidas) {
            foreach ($montos as $clave => $monto) {
                $linea = $conocidas->get($clave);
                if (! $linea) {
                    continue;
                }
                $buscar = [
                    'cuadre_caja_id' => $cuadre->id,
                    'codigo_metodo' => (string) $linea['codigo_metodo'],
                    'codigo_cuenta' => (string) ($linea['codigo_cuenta'] ?? ''),
                ];
                if ($monto === null || trim((string) $monto) === '') {
                    CuadreCajaConteo::query()->where($buscar)->delete();

                    continue;
                }
                CuadreCajaConteo::query()->updateOrCreate($buscar, [
                    'forma_pago' => $linea['forma_pago'],
                    'cuenta' => $linea['cuenta'],
                    'en_dolares' => (bool) $linea['en_dolares'],
                    'monto' => round((float) $monto, 2),
                    'user_id' => $user->id,
                ]);
            }

            $cuadre->forceFill([
                'observacion_tesoreria' => trim((string) $observacion) ?: null,
                'conciliado_at' => now(),
                'conciliado_por' => $user->id,
            ])->save();
        });

        $cuadre->unsetRelation('conteos');
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, float|int>
     */
    public function totales(array $filas): array
    {
        $conciliadas = array_filter($filas, fn (array $f) => $f['diferencia_usd'] !== null);
        $diferencias = array_column($conciliadas, 'diferencia_usd');

        return [
            'cierres' => count($filas),
            'pendientes' => count($filas) - count($conciliadas),
            'ingresos_usd' => round(array_sum(array_column($filas, 'ingresos_usd')), 2),
            'tesoreria_usd' => round(array_sum(array_map(fn ($f) => $f['tesoreria_usd'] ?? 0.0, $conciliadas)), 2),
            'diferencia_usd' => round(array_sum($diferencias), 2),
            'faltantes_usd' => round(array_sum(array_filter($diferencias, fn ($d) => $d < 0)), 2),
            'sobrantes_usd' => round(array_sum(array_filter($diferencias, fn ($d) => $d > 0)), 2),
        ];
    }

    public function clave(?string $metodo, ?string $cuenta): string
    {
        return strtoupper(trim((string) $metodo)).'|'.strtoupper(trim((string) $cuenta));
    }

    private function cedula(string $usuario): string
    {
        return (string) preg_replace('/\D/', '', $usuario);
    }

    /** @return array<string, mixed> */
    private function lineaVacia(string $clave, ?string $metodo, ?string $cuenta, ?string $forma, ?string $nombreCuenta, bool $enDolares): array
    {
        return [
            'clave' => $clave,
            'codigo_metodo' => (string) $metodo,
            'codigo_cuenta' => (string) ($cuenta ?? ''),
            'forma_pago' => $forma ?: $metodo,
            'cuenta' => $nombreCuenta,
            'en_dolares' => $enDolares,
            'suite' => 0.0,
            'suite_usd' => 0.0,
            'tesoreria' => null,
            'tesoreria_usd' => null,
            'diferencia_usd' => null,
        ];
    }

    /** @return array<string, string> sede => fecha y hora de la última subida */
    private function ultimaCarga(): array
    {
        return CuadreCaja::query()
            ->selectRaw('sede, MAX(updated_at) as ultima')
            ->groupBy('sede')
            ->pluck('ultima', 'sede')
            ->map(fn ($v) => Carbon::parse($v)->format('d/m/Y H:i'))
            ->all();
    }
}
