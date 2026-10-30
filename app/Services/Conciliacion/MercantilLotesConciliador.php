<?php

namespace App\Services\Conciliacion;

use App\Models\ConciliacionLinea;
use App\Models\TesoreriaIngreso;
use App\Services\BankReconciliationMatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lotes de punto de venta de Mercantil.
 *
 * Mercantil abona cada cierre en un solo movimiento "CANCELACION RESUMEN DE VENTAS Y RETENCIO" por el
 * importe BRUTO de todos los lotes de la sesión (uno por terminal), al día hábil siguiente; el lunes llegan
 * los del viernes, sábado y domingo por separado. El descuento sale aparte, el mismo día, como
 * "DESCUENTO TARJETA" con la referencia emparejada (145020012 44 ↔ 954020012 44).
 *
 * Sin el balance: se cruza el abono con los lotes de Tesorería de un mismo día (1 a 4 días antes) cuya suma
 * bruta sea exacta. Con el balance del portal ("Balance de Transacciones en el período", leído de la imagen):
 * su total bruto identifica el abono y dice qué lotes lo forman. Si falta alguno en Tesorería no se crea:
 * el abono queda sin lote hasta que se registre.
 */
class MercantilLotesConciliador
{
    public const DIAS_SESION = 4;

    public const TOLERANCIA = 0.05;

    public function __construct(private BankReconciliationMatcher $matcher) {}

    /**
     * @return array{conciliados: int, descuentos: int, sin_cuadrar: int}
     */
    public function conciliarAutomatico(string $desde, string $hasta, ?string $titularFiltro = null): array
    {
        $abonos = $this->abonosPendientes($desde, $hasta, $titularFiltro);
        $descuentos = $this->marcarDescuentos($abonos);
        // Cierres aplicados a medias (por su abono o su «DESCUENTO TARJETA»): se completan con los lotes que se registren.
        $conciliados = $this->completarParciales($this->lineasConParcial($desde, $hasta));
        // Los que ya tienen un balance aplicado a medias solo se completan con sus lotes faltantes.
        $conParcial = $this->parciales($abonos->pluck('id')->all())->keys()->all();
        $abonos = $abonos->reject(fn ($l) => in_array($l->id, $conParcial, true))->values();
        if ($abonos->isEmpty()) {
            return ['conciliados' => $conciliados, 'descuentos' => $descuentos, 'sin_cuadrar' => 0];
        }
        $lotes = $this->lotesPendientes($this->sumarDias($desde, -self::DIAS_SESION), $hasta);

        $sinCuadrar = 0;
        foreach ($abonos->sortBy(fn ($l) => [$this->dia($l->fecha), $l->referencia]) as $abono) {
            $grupo = $this->lotesDelDia($abono, $lotes);
            if ($grupo === null) {
                $sinCuadrar++;

                continue;
            }
            if ($this->vincular($abono, $grupo)) {
                $conciliados++;
                $ids = $grupo->pluck('id')->all();
                $lotes = $lotes->reject(fn ($i) => in_array($i->id, $ids, true));
            }
        }

        return ['conciliados' => $conciliados, 'descuentos' => $descuentos, 'sin_cuadrar' => $sinCuadrar];
    }

    /**
     * Qué haría el balance leído de la imagen, sin escribir nada.
     *
     * @param  list<array{lote: string, bruto: float, descuento: float, retencion: float, abonado: float}>  $balance
     * @return array{error: ?string, abono: ?ConciliacionLinea, descuento: ?ConciliacionLinea, total: float,
     *     filas: list<array{lote: string, bruto: float, descuento: float, abonado: float, tesoreria: ?TesoreriaIngreso, conflicto: ?TesoreriaIngreso, crear: bool}>}
     *     crear = el lote no está en Tesorería (hay que registrarlo).
     */
    public function planBalance(array $balance, string $titular): array
    {
        $total = round(array_sum(array_column($balance, 'bruto')), 2);
        $totalDescuento = round(array_sum(array_column($balance, 'descuento')), 2);
        $vacio = ['abono' => null, 'descuento' => null, 'via' => null, 'total' => $total, 'total_descuento' => $totalDescuento, 'filas' => []];
        if ($balance === [] || $total < 0.01) {
            return $vacio + ['error' => 'El archivo no trae lotes con importe bruto.'];
        }

        // El movimiento del banco del cierre: el abono «CANCELACION RESUMEN DE VENTAS» por el bruto (si quedó cargado) o,
        // como ese abono ya no se importa, el «DESCUENTO TARJETA» del mismo cierre por el descuento total.
        $desde = now()->subYear()->toDateString();
        $hasta = now()->toDateString();
        $abonos = $this->abonosPendientes($desde, $hasta, $titular)
            ->filter(fn ($l) => abs(abs((float) $l->monto) - $total) <= self::TOLERANCIA)->values();
        $via = 'abono';
        if ($abonos->isEmpty() && $totalDescuento >= 0.01) {
            $abonos = $this->descuentosPendientes($desde, $hasta, $titular)
                ->filter(fn ($l) => abs(abs((float) $l->monto) - $totalDescuento) <= self::TOLERANCIA)->values();
            $via = 'descuento';
        }
        if ($abonos->count() !== 1) {
            return $vacio + ['error' => $abonos->isEmpty()
                ? 'No hay en el extracto de Mercantil · '.$titular.' un «DESCUENTO TARJETA» pendiente por Bs '.number_format($totalDescuento, 2, ',', '.')
                    .' (el descuento total del balance) ni un abono «CANCELACION RESUMEN DE VENTAS» por Bs '.number_format($total, 2, ',', '.')
                    .'. ¿Ya está cargado el extracto de esa fecha, o ya se concilió?'
                : 'Hay '.$abonos->count().' movimientos pendientes por ese monto en el extracto: no sé a cuál corresponde este balance.'];
        }
        $abono = $abonos->first();
        $previo = $this->parciales([$abono->id])->get($abono->id);
        if ($previo) {
            return ['abono' => $abono, 'error' => 'Este abono ya tiene su balance aplicado: falta '.$this->textoFaltan($previo->whereNull('tesoreria_ingreso_id'))
                .'. Se completa solo cuando lo registres en Tesorería.'] + $vacio;
        }
        $diaAbono = $this->dia($abono->fecha);
        $lotes = $this->lotesPendientes($this->sumarDias($abono->fecha->format('Y-m-d'), -self::DIAS_SESION), $abono->fecha->format('Y-m-d'))
            ->filter(fn ($i) => $this->matcher->mismoTitular($i->titular, $abono->titular, $i->banco, $abono->banco)
                && $this->dia($i->fecha) < $diaAbono)
            ->values();

        $filas = [];
        $usados = [];
        foreach ($balance as $b) {
            $numero = $this->numero($b['lote']);
            $t = $lotes->first(fn ($i) => ! isset($usados[$i->id]) && $this->numero($i->lote_referencia) === $numero
                && abs(abs((float) $i->monto) - $b['bruto']) <= self::TOLERANCIA);
            // Mismo lote en Tesorería con otro monto: no se crea otro encima, hay que corregir el registrado.
            $conflicto = $t ? null : $lotes->first(fn ($i) => ! isset($usados[$i->id]) && $this->numero($i->lote_referencia) === $numero);
            if ($t) {
                $usados[$t->id] = true;
                // Las copias del mismo lote (registrado dos veces) no se concilian: quedan a la vista para depurarlas.
                $lotes = $lotes->reject(fn ($i) => $i->id === $t->id);
            }
            $filas[] = [
                'lote' => $numero, 'bruto' => $b['bruto'], 'descuento' => $b['descuento'], 'abonado' => $b['abonado'],
                'tesoreria' => $t, 'conflicto' => $conflicto, 'crear' => $t === null && $conflicto === null,
            ];
        }
        $conflictos = array_values(array_filter($filas, fn ($f) => $f['conflicto'] !== null));
        $faltan = array_values(array_filter($filas, fn ($f) => $f['crear']));
        $existentes = collect($filas)->pluck('tesoreria')->filter();
        $fechaSesion = $existentes->isNotEmpty()
            ? \Carbon\Carbon::parse($existentes->first()->fecha)->format('d/m/Y')
            : $abono->fecha->copy()->subDay()->format('d/m/Y');
        $error = $conflictos === [] ? null : 'En Tesorería '.implode('; ', array_map(
            fn ($f) => 'el lote '.$f['lote'].' está por Bs '.number_format((float) $f['conflicto']->monto, 2, ',', '.').' y el balance dice Bs '.number_format($f['bruto'], 2, ',', '.'),
            $conflictos,
        )).': corrige el monto del lote y vuelve a subir el archivo.';
        if ($error === null && $existentes->isEmpty()) {
            $error = 'Ninguno de los lotes del balance está en Tesorería: regístralos y vuelve a subir el archivo.';
        }
        if ($error === null && $faltan !== [] && ! Schema::hasTable('conciliacion_lotes_parciales')) {
            $error = 'Para conciliar en parte falta la tabla conciliacion_lotes_parciales: ejecuta php artisan migrate.';
        }
        // Los que faltan no se crean: se concilian los presentes y el cierre se completa solo cuando se registren.
        $aviso = $faltan === [] ? null : 'Se conciliarán los lotes que están en Tesorería. Falta registrar '
            .$this->textoFaltan(collect($faltan)->map(fn ($f) => (object) ['lote' => $f['lote'], 'monto' => $f['bruto']]))
            .' (cierre del '.$fechaSesion.'): se concilia solo cuando lo registres en Tesorería.';

        return [
            'error' => $error, 'aviso' => $aviso, 'abono' => $abono, 'via' => $via,
            'descuento' => $via === 'abono' ? $this->descuentoDe($abono) : $abono,
            'total' => $total, 'total_descuento' => $totalDescuento, 'filas' => $filas,
        ];
    }

    /**
     * Aplica el plan del balance. Con todos los lotes en Tesorería, el abono queda conciliado; si falta alguno,
     * se concilian los presentes y el abono sigue pendiente por el monto de los que faltan.
     *
     * @return 'conciliado'|'parcial'|null
     */
    public function aplicarBalance(array $plan, ?int $userId = null): ?string
    {
        if ($plan['error'] !== null || $plan['abono'] === null) {
            return null;
        }
        $filas = collect($plan['filas']);
        $abono = $plan['abono'];
        if ($filas->every(fn ($f) => $f['tesoreria'] !== null)) {
            if (! $this->vincular($abono, $filas->pluck('tesoreria'))) {
                return null;
            }
            $this->marcarDescuentos(collect([$abono]));

            return 'conciliado';
        }
        if (! Schema::hasTable('conciliacion_lotes_parciales')) {
            return null;
        }

        try {
            DB::transaction(function () use ($filas, $abono, $userId) {
                $ahora = now();
                $sigue = DB::table('conciliacion_lineas')->where('id', $abono->id)->where('estado', 'pendiente')
                    ->whereNull('tesoreria_ingreso_id')->lockForUpdate()->exists();
                if (! $sigue || DB::table('conciliacion_lotes_parciales')->where('conciliacion_linea_id', $abono->id)->exists()) {
                    throw new VinculoOcupadoException;
                }
                $presentes = $filas->pluck('tesoreria')->filter();
                if (DB::table('tesoreria_ingresos')->whereIn('id', $presentes->pluck('id'))->where('es_conciliado', false)
                    ->update(['es_conciliado' => true, 'updated_at' => $ahora]) !== $presentes->count()) {
                    throw new VinculoOcupadoException;
                }
                DB::table('conciliacion_lotes_parciales')->insert($filas->map(fn ($f) => [
                    'conciliacion_linea_id' => $abono->id,
                    'tesoreria_ingreso_id' => $f['tesoreria']?->id,
                    'lote' => $f['lote'],
                    'monto' => $f['bruto'],
                    'user_id' => $userId,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ])->all());
            });
        } catch (VinculoOcupadoException) {
            return null;
        }
        $this->marcarDescuentos(collect([$abono]));

        return 'parcial';
    }

    /**
     * Abonos conciliados a medias: cada lote que faltaba se completa con el de Tesorería del mismo número y monto
     * (1 a 4 días antes del abono). Sin faltantes, el abono queda conciliado.
     *
     * @param  Collection<int, ConciliacionLinea>  $abonos
     */
    private function completarParciales(Collection $abonos): int
    {
        $parciales = $this->parciales($abonos->pluck('id')->all());
        if ($parciales->isEmpty()) {
            return 0;
        }
        $completos = 0;
        foreach ($abonos->whereIn('id', $parciales->keys()) as $abono) {
            $dia = $this->dia($abono->fecha);
            $candidatos = $this->lotesPendientes($this->sumarDias($abono->fecha->format('Y-m-d'), -self::DIAS_SESION), $abono->fecha->format('Y-m-d'))
                ->filter(fn ($i) => $this->dia($i->fecha) < $dia && $this->matcher->mismoTitular($i->titular, $abono->titular, $i->banco, $abono->banco));
            foreach ($parciales[$abono->id]->whereNull('tesoreria_ingreso_id') as $falta) {
                $lote = $candidatos->first(fn ($i) => $this->numero($i->lote_referencia) === $this->numero($falta->lote)
                    && abs(abs((float) $i->monto) - (float) $falta->monto) <= self::TOLERANCIA);
                if ($lote && $this->completarFaltante($abono, $falta->id, $lote)) {
                    $candidatos = $candidatos->reject(fn ($i) => $i->id === $lote->id);
                }
            }
            if ($this->cerrarSiCompleto($abono)) {
                $completos++;
            }
        }

        return $completos;
    }

    /** @return Collection<int, ConciliacionLinea> */
    private function lineasConParcial(string $desde, string $hasta): Collection
    {
        if (! Schema::hasTable('conciliacion_lotes_parciales')) {
            return collect();
        }
        $ids = DB::table('conciliacion_lotes_parciales')->whereNull('tesoreria_ingreso_id')->distinct()->pluck('conciliacion_linea_id');

        return $ids->isEmpty() ? collect() : ConciliacionLinea::query()->whereIn('id', $ids)
            ->where('estado', 'pendiente')->whereBetween('fecha', [$desde, $hasta])->get();
    }

    /**
     * «Conciliar manualmente» con un abono a medias: el lote elegido cubre lo que falta (mismo monto, ±0,05).
     */
    public function completarConLote(ConciliacionLinea $abono, TesoreriaIngreso $lote): bool
    {
        $faltan = $this->parciales([$abono->id])->get($abono->id)?->whereNull('tesoreria_ingreso_id') ?? collect();
        if ($faltan->isEmpty() || $lote->es_conciliado) {
            return false;
        }
        $falta = $faltan->first(fn ($f) => abs((float) $f->monto - abs((float) $lote->monto)) <= self::TOLERANCIA);
        if (! $falta || ! $this->completarFaltante($abono, $falta->id, $lote)) {
            return false;
        }
        $this->cerrarSiCompleto($abono);

        return true;
    }

    private function completarFaltante(ConciliacionLinea $abono, int $parcialId, TesoreriaIngreso $lote): bool
    {
        try {
            DB::transaction(function () use ($parcialId, $lote) {
                $ahora = now();
                $ok = DB::table('conciliacion_lotes_parciales')->where('id', $parcialId)->whereNull('tesoreria_ingreso_id')
                    ->update(['tesoreria_ingreso_id' => $lote->id, 'updated_at' => $ahora]) === 1
                    && DB::table('tesoreria_ingresos')->where('id', $lote->id)->where('es_conciliado', false)
                        ->update(['es_conciliado' => true, 'updated_at' => $ahora]) === 1;
                if (! $ok) {
                    throw new VinculoOcupadoException;
                }
            });
        } catch (VinculoOcupadoException) {
            return false;
        }

        return true;
    }

    private function cerrarSiCompleto(ConciliacionLinea $abono): bool
    {
        $filas = DB::table('conciliacion_lotes_parciales')->where('conciliacion_linea_id', $abono->id)->get();
        if ($filas->isEmpty() || $filas->contains(fn ($f) => $f->tesoreria_ingreso_id === null)) {
            return false;
        }
        $mayor = $filas->sortByDesc(fn ($f) => (float) $f->monto)->first();

        return DB::table('conciliacion_lineas')->where('id', $abono->id)->where('estado', 'pendiente')->whereNull('tesoreria_ingreso_id')
            ->update(['estado' => 'conciliado', 'tesoreria_ingreso_id' => $mayor->tesoreria_ingreso_id, 'updated_at' => now()]) === 1;
    }

    /**
     * Lotes de los balances aplicados a medias, por abono.
     *
     * @param  list<int>  $lineaIds
     * @return Collection<int, Collection<int, object>>
     */
    public function parciales(array $lineaIds): Collection
    {
        if ($lineaIds === [] || ! Schema::hasTable('conciliacion_lotes_parciales')) {
            return collect();
        }

        return DB::table('conciliacion_lotes_parciales')->whereIn('conciliacion_linea_id', $lineaIds)->orderBy('id')->get()
            ->groupBy('conciliacion_linea_id');
    }

    /** @param  Collection<int, object>  $faltan  con lote y monto */
    private function textoFaltan(Collection $faltan): string
    {
        return ($faltan->count() === 1 ? 'el lote ' : 'los lotes ').$faltan->map(fn ($f) => $f->lote.' por Bs '.number_format((float) $f->monto, 2, ',', '.'))->implode(', ');
    }

    /** @return Collection<int, ConciliacionLinea> */
    public function abonosPendientes(string $desde, string $hasta, ?string $titular = null): Collection
    {
        return ConciliacionLinea::query()
            ->whereRaw('UPPER(banco) LIKE ?', ['%MERCANTIL%'])
            ->where('tipo', 'abono')
            ->where('estado', 'pendiente')
            ->whereNull('tesoreria_ingreso_id')->whereNull('flujo_caja_id')
            ->when(Schema::hasColumn('conciliacion_lineas', 'compra_divisa_id'), fn ($q) => $q->whereNull('compra_divisa_id'))
            ->whereRaw('UPPER(descripcion) LIKE ?', ['CANCELACION RESUMEN DE VENTAS%'])
            ->whereBetween('fecha', [$desde, $hasta])
            ->get()
            ->filter(fn ($l) => ! $titular || $this->matcher->mismoTitular($l->titular, $titular, $l->banco, 'MERCANTIL'))
            ->values();
    }

    /** Cargos «DESCUENTO TARJETA» de Mercantil sin ligar (la comisión de cada cierre de punto de venta). */
    public function descuentosPendientes(string $desde, string $hasta, ?string $titular = null): Collection
    {
        return ConciliacionLinea::query()
            ->whereRaw('UPPER(banco) LIKE ?', ['%MERCANTIL%'])
            ->where('tipo', 'cargo')
            ->where('estado', 'pendiente')
            ->whereNull('tesoreria_ingreso_id')->whereNull('flujo_caja_id')
            ->whereRaw('UPPER(descripcion) LIKE ?', ['DESCUENTO TARJETA%'])
            ->whereBetween('fecha', [$desde, $hasta])
            ->get()
            ->filter(fn ($l) => ! $titular || $this->matcher->mismoTitular($l->titular, $titular, $l->banco, 'MERCANTIL'))
            ->values();
    }

    /** "DESCUENTO TARJETA" del mismo día y con la misma cola de referencia que el abono. */
    public function descuentoDe(ConciliacionLinea $abono): ?ConciliacionLinea
    {
        $cola = substr(preg_replace('/\D/', '', (string) $abono->referencia) ?? '', -8);
        if (strlen($cola) < 6) {
            return null;
        }

        return ConciliacionLinea::query()
            ->where('banco', $abono->banco)->where('titular', $abono->titular)
            ->whereDate('fecha', $abono->fecha->format('Y-m-d'))
            ->where('tipo', 'cargo')
            ->whereRaw('UPPER(descripcion) LIKE ?', ['DESCUENTO TARJETA%'])
            ->get()
            ->first(fn ($c) => str_ends_with(preg_replace('/\D/', '', (string) $c->referencia) ?? '', $cola));
    }

    /** @param  Collection<int, ConciliacionLinea>  $abonos */
    private function marcarDescuentos(Collection $abonos): int
    {
        if (! Schema::hasColumn('conciliacion_lineas', 'forzar_comision')) {
            return 0;
        }
        $n = 0;
        foreach ($abonos as $abono) {
            $d = $this->descuentoDe($abono);
            if ($d && ! $d->forzar_comision && $d->estado === 'pendiente') {
                $n += DB::table('conciliacion_lineas')->where('id', $d->id)->update(['forzar_comision' => true, 'updated_at' => now()]);
            }
        }

        return $n;
    }

    /** @return Collection<int, TesoreriaIngreso> */
    private function lotesPendientes(string $desde, string $hasta): Collection
    {
        return TesoreriaIngreso::query()
            ->where('tipo', 'punto_venta')
            ->whereRaw('UPPER(banco) LIKE ?', ['%MERCANTIL%'])
            ->where('es_conciliado', false)
            ->whereBetween('fecha', [$desde, $hasta])
            ->orderBy('id')
            ->get();
    }

    /**
     * Lotes de un mismo día (el más cercano antes del abono) cuya suma bruta es el abono. Una copia del mismo
     * lote (misma fecha, número y monto) se cuenta una vez. Null si ningún día cuadra o si cuadra más de uno.
     *
     * @param  Collection<int, TesoreriaIngreso>  $lotes
     * @return Collection<int, TesoreriaIngreso>|null
     */
    private function lotesDelDia(ConciliacionLinea $abono, Collection $lotes): ?Collection
    {
        $diaAbono = $this->dia($abono->fecha);
        $monto = abs((float) $abono->monto);
        $candidatos = $lotes
            ->filter(fn ($i) => $this->matcher->mismoTitular($i->titular, $abono->titular, $i->banco, $abono->banco)
                && $this->dia($i->fecha) < $diaAbono && $this->dia($i->fecha) >= $diaAbono - self::DIAS_SESION)
            ->unique(fn ($i) => $this->dia($i->fecha).'|'.$this->numero($i->lote_referencia).'|'.round((float) $i->monto, 2));

        $cuadran = $candidatos->groupBy(fn ($i) => $this->dia($i->fecha))
            ->filter(fn ($g) => abs(round($g->sum(fn ($i) => abs((float) $i->monto)), 2) - $monto) <= self::TOLERANCIA);

        return $cuadran->count() === 1 ? $cuadran->first()->values() : null;
    }

    /** Igual que los otros lotes: el abono queda ligado al lote mayor y todos los lotes del cierre, conciliados. */
    private function vincular(ConciliacionLinea $abono, Collection $grupo): bool
    {
        $mayor = $grupo->sortByDesc(fn ($i) => abs((float) $i->monto))->first();
        try {
            DB::transaction(function () use ($abono, $grupo, $mayor) {
                $ahora = now();
                $ok = DB::table('conciliacion_lineas')->where('id', $abono->id)
                    ->where('estado', 'pendiente')->whereNull('tesoreria_ingreso_id')->whereNull('flujo_caja_id')
                    ->update(['estado' => 'conciliado', 'tesoreria_ingreso_id' => $mayor->id, 'updated_at' => $ahora]);
                $lotes = DB::table('tesoreria_ingresos')->whereIn('id', $grupo->pluck('id'))->where('es_conciliado', false)
                    ->update(['es_conciliado' => true, 'updated_at' => $ahora]);
                if ($ok !== 1 || $lotes !== $grupo->count()) {
                    throw new VinculoOcupadoException;
                }
            });
        } catch (VinculoOcupadoException) {
            return false;
        }

        return true;
    }

    private function numero(?string $lote): string
    {
        return ltrim(preg_replace('/\D/', '', (string) $lote) ?? '', '0');
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
