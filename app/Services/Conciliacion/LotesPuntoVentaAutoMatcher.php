<?php

namespace App\Services\Conciliacion;

use App\Models\ConciliacionLinea;
use App\Models\TesoreriaIngreso;
use App\Services\BankReconciliationMatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cruce automático de lotes de punto de venta Banesco con sus liquidaciones del extracto.
 *
 * Banesco abona cada lote en uno o dos movimientos con el número de lote en la descripción
 * ("TMD 0037638504 L683 20260801 683" débito, "TDB CAPIT 0037638504 L000683 30687" crédito).
 * El abono llega 1 a 3 días después y descontada la comisión (0 % a 2 %), así que se exige:
 * misma cuenta, mismo número de lote, fecha cercana y suma del banco entre el neto del lote
 * menos COMISION_MAX y el neto del lote. Si hay más de un lote posible no se cruza nada.
 */
class LotesPuntoVentaAutoMatcher
{
    public const COMISION_MAX = 0.03;

    public const DIAS_ANTES = 1;

    public const DIAS_DESPUES = 7;

    public function __construct(private BankReconciliationMatcher $matcher) {}

    /**
     * @return int Lotes conciliados en esta pasada.
     */
    public function conciliar(string $desde, string $hasta, ?string $bancoFiltro = null, ?string $titularFiltro = null): int
    {
        $conciliados = 0;
        foreach ($this->propuestas($desde, $hasta, $bancoFiltro, $titularFiltro) as [$grupo, $filas]) {
            if ($this->vincular($grupo, $filas)) {
                $conciliados++;
            }
        }

        return $conciliados;
    }

    /**
     * Cruces que se harían, sin escribir nada.
     *
     * @return list<array{0: Collection<int, ConciliacionLinea>, 1: Collection<int, TesoreriaIngreso>}>
     */
    public function propuestas(string $desde, string $hasta, ?string $bancoFiltro = null, ?string $titularFiltro = null): array
    {
        if ($bancoFiltro && ! $this->matcher->mismoBanco($bancoFiltro, 'BANESCO')) {
            return [];
        }

        $lineas = ConciliacionLinea::query()
            ->select(['id', 'banco', 'titular', 'fecha', 'descripcion', 'referencia', 'monto'])
            ->whereRaw('LOWER(banco) LIKE ?', ['%banesco%'])
            ->where('estado', 'pendiente')
            ->whereNull('tesoreria_ingreso_id')
            ->whereNull('flujo_caja_id')
            ->where('monto', '>', 0)
            ->where('fecha', '>=', $desde)
            ->where('fecha', '<=', $this->sumarDias($hasta, self::DIAS_DESPUES))
            ->get()
            ->filter(fn ($l) => $this->numeroLote($l->descripcion) !== null && ! $this->matcher->esPagoMovil($l->descripcion))
            ->filter(fn ($l) => ! $titularFiltro || $this->matcher->mismoTitular($l->titular, $titularFiltro, $l->banco, $l->banco));
        if ($lineas->isEmpty()) {
            return [];
        }

        $lotes = TesoreriaIngreso::query()
            ->where('tipo', 'punto_venta')
            ->where('es_conciliado', false)
            ->whereRaw('LOWER(banco) LIKE ?', ['%banesco%'])
            ->where('fecha', '>=', $this->sumarDias($desde, -self::DIAS_DESPUES))
            ->where('fecha', '<=', $this->sumarDias($hasta, self::DIAS_ANTES))
            ->get();
        if ($lotes->isEmpty()) {
            return [];
        }

        $grupos = $lineas->groupBy(fn ($l) => $this->matcher->claveCuenta($l->banco, $l->titular).'|'.$this->numeroLote($l->descripcion));

        $propuestas = [];
        foreach ($grupos as $grupo) {
            $filas = $this->lotesDelGrupo($grupo, $lotes);
            if ($filas === null || ! $this->montoCuadra($grupo, $filas)) {
                continue;
            }
            $propuestas[] = [$grupo->values(), $filas];
            $ids = $filas->pluck('id')->all();
            $lotes = $lotes->reject(fn ($i) => in_array($i->id, $ids, true));
        }

        return $propuestas;
    }

    /**
     * Número de lote Banesco en la descripción: "L683", "L000685", "L.58".
     */
    public function numeroLote(?string $descripcion): ?string
    {
        if (preg_match('/(?:^|[^0-9A-Z])L\.?\s*0*(\d{1,})(?![0-9])/i', (string) $descripcion, $m) !== 1) {
            return null;
        }

        return $m[1] === '' ? null : $m[1];
    }

    /**
     * Filas de tesorería del mismo lote y cuenta, todas del mismo cierre; null si no hay o es ambiguo.
     *
     * @param  Collection<int, ConciliacionLinea>  $grupo
     * @param  Collection<int, TesoreriaIngreso>  $lotes
     * @return Collection<int, TesoreriaIngreso>|null
     */
    private function lotesDelGrupo(Collection $grupo, Collection $lotes): ?Collection
    {
        $primera = $grupo->first();
        $numero = $this->numeroLote($primera->descripcion);
        $diasLineas = $grupo->map(fn ($l) => $this->dia($l->fecha));

        $filas = $lotes->filter(function ($i) use ($primera, $numero, $diasLineas) {
            if (ltrim((string) preg_replace('/\D+/', '', (string) $i->lote_referencia), '0') !== $numero) {
                return false;
            }
            if (! $this->matcher->mismoBanco($i->banco, $primera->banco)
                || ! $this->matcher->mismoTitular($i->titular, $primera->titular, $i->banco, $primera->banco)) {
                return false;
            }
            $diaLote = $this->dia($i->fecha);

            return $diasLineas->every(fn ($d) => $d >= $diaLote - self::DIAS_ANTES && $d <= $diaLote + self::DIAS_DESPUES);
        })->values();

        if ($filas->isEmpty()) {
            return null;
        }
        $dias = $filas->map(fn ($i) => $this->dia($i->fecha));
        if ($dias->max() - $dias->min() > 1) {
            return null;
        }

        return $filas;
    }

    private function montoCuadra(Collection $grupo, Collection $filas): bool
    {
        $banco = round((float) $grupo->sum(fn ($l) => abs((float) $l->monto)), 2);
        $lote = round((float) $filas->sum(fn ($i) => abs((float) $i->monto)), 2);
        if ($lote < 0.01) {
            return false;
        }

        return $banco <= $lote + 0.05 && $banco >= round($lote * (1 - self::COMISION_MAX), 2);
    }

    /**
     * Con igual cantidad de abonos y filas se emparejan por monto (débito con débito, crédito con crédito);
     * si no, todas las líneas quedan ligadas a la fila mayor. Todas las filas del lote quedan conciliadas.
     */
    private function vincular(Collection $grupo, Collection $filas): bool
    {
        $lineas = $grupo->sortBy(fn ($l) => abs((float) $l->monto))->values();
        $filas = $filas->sortBy(fn ($i) => abs((float) $i->monto))->values();
        $mayor = $filas->last();
        $pares = $lineas->map(fn ($l, $k) => [$l, $lineas->count() === $filas->count() ? $filas[$k] : $mayor]);

        try {
            DB::transaction(function () use ($pares, $filas) {
                $ahora = now();
                foreach ($pares as [$linea, $fila]) {
                    $ok = DB::table('conciliacion_lineas')
                        ->where('id', $linea->id)
                        ->where('estado', 'pendiente')
                        ->whereNull('tesoreria_ingreso_id')
                        ->whereNull('flujo_caja_id')
                        ->update([
                            'estado' => 'conciliado',
                            'tesoreria_ingreso_id' => $fila->id,
                            'updated_at' => $ahora,
                        ]);
                    if ($ok !== 1) {
                        throw new VinculoOcupadoException;
                    }
                }
                $ok = DB::table('tesoreria_ingresos')
                    ->whereIn('id', $filas->pluck('id'))
                    ->where('es_conciliado', false)
                    ->update(['es_conciliado' => true, 'updated_at' => $ahora]);
                if ($ok !== $filas->count()) {
                    throw new VinculoOcupadoException;
                }
            });
        } catch (VinculoOcupadoException) {
            return false;
        }

        return true;
    }

    private function dia($fecha): int
    {
        $texto = $fecha instanceof \DateTimeInterface ? $fecha->format('Y-m-d') : substr((string) $fecha, 0, 10);

        return intdiv((int) strtotime($texto.' 00:00:00 UTC'), 86400);
    }

    private function sumarDias(string $fecha, int $dias): string
    {
        return gmdate('Y-m-d', strtotime(substr($fecha, 0, 10).' 00:00:00 UTC') + $dias * 86400);
    }
}
