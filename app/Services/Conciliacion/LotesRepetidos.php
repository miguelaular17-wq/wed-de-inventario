<?php

namespace App\Services\Conciliacion;

use App\Models\TesoreriaIngreso;
use App\Services\BankReconciliationMatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Lotes de punto de venta registrados dos veces en Tesorería (misma cuenta, fecha, número y monto).
 * De cada lote queda uno: el conciliado o, si ninguno lo está, el más antiguo. Las copias no se concilian
 * nunca (el banco abona el lote una vez) y duplican los ingresos de Tesorería.
 */
class LotesRepetidos
{
    public function __construct(private BankReconciliationMatcher $matcher) {}

    /**
     * @param  Collection<int, TesoreriaIngreso>  $lotes
     * @return array<int, int> id de la copia => id del lote que se queda
     */
    public function copias(Collection $lotes): array
    {
        $copias = [];
        $grupos = $lotes->where('tipo', 'punto_venta')->groupBy(fn ($l) => implode('|', [
            $this->matcher->claveCuenta($l->banco, $l->titular),
            substr((string) ($l->fecha instanceof \DateTimeInterface ? $l->fecha->format('Y-m-d') : $l->fecha), 0, 10),
            ltrim(preg_replace('/\D/', '', (string) $l->lote_referencia) ?? '', '0'),
            number_format(abs((float) $l->monto), 2, '.', ''),
        ]));
        foreach ($grupos as $grupo) {
            if ($grupo->count() < 2 || trim((string) $grupo->first()->lote_referencia) === '') {
                continue;
            }
            $queda = $grupo->sortBy(fn ($l) => [$l->es_conciliado ? 0 : 1, $l->id])->first();
            foreach ($grupo as $l) {
                if ($l->id !== $queda->id && ! $l->es_conciliado) {
                    $copias[$l->id] = $queda->id;
                }
            }
        }

        return $copias;
    }

    /**
     * Borra las copias que no estén ligadas a ningún movimiento del banco. Guarda un respaldo en storage.
     *
     * @param  list<int>  $ids
     * @return array{borradas: int, respaldo: ?string}
     */
    public function quitar(array $ids): array
    {
        $lotes = TesoreriaIngreso::query()->whereIn('id', $ids)->get();
        $fechas = $lotes->map(fn ($l) => substr((string) ($l->fecha instanceof \DateTimeInterface ? $l->fecha->format('Y-m-d') : $l->fecha), 0, 10))->unique();
        $validas = $fechas->isEmpty() ? [] : $this->copias(TesoreriaIngreso::query()->where('tipo', 'punto_venta')
            ->whereBetween('fecha', [$fechas->min(), $fechas->max()])->get());
        $ligadas = DB::table('conciliacion_lineas')->whereIn('tesoreria_ingreso_id', $ids)->pluck('tesoreria_ingreso_id')
            ->merge(Schema::hasTable('conciliacion_lotes_parciales')
                ? DB::table('conciliacion_lotes_parciales')->whereIn('tesoreria_ingreso_id', $ids)->pluck('tesoreria_ingreso_id')
                : [])
            ->map(fn ($id) => (int) $id)->flip();
        $borrar = $lotes->filter(fn ($l) => isset($validas[$l->id]) && ! $l->es_conciliado && ! isset($ligadas[$l->id]))->values();
        if ($borrar->isEmpty()) {
            return ['borradas' => 0, 'respaldo' => null];
        }

        $respaldo = 'respaldos/tesoreria_lotes_repetidos_'.date('Ymd_His').'.json';
        Storage::disk('local')->put($respaldo, json_encode($borrar->map(fn ($l) => $l->toArray() + ['copia_de' => $validas[$l->id]])->all(), JSON_UNESCAPED_UNICODE));
        $n = TesoreriaIngreso::query()->whereIn('id', $borrar->pluck('id'))->where('es_conciliado', false)->delete();

        return ['borradas' => $n, 'respaldo' => $respaldo];
    }
}
