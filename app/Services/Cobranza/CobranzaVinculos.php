<?php

namespace App\Services\Cobranza;

use App\Models\CobranzaFactura;
use App\Models\CobranzaMovimiento;
use App\Models\Contrato;
use App\Models\ContratoCuota;
use App\Models\HistorialCobranza;
use App\Models\Patrimonial\AlquilerPago;
use App\Models\Patrimonial\PatTransaccion;
use App\Models\Patrimonial\Propiedad;
use App\Models\Patrimonial\Reserva;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Qué se está pagando en cada movimiento, y lo que se puede elegir para pagar. */
class CobranzaVinculos
{
    public function __construct(private readonly CobranzaFacturaService $facturas) {}

    /**
     * Descripción de lo que paga cada movimiento, cargada en bloque.
     *
     * @param  Collection<int, CobranzaMovimiento>  $movimientos
     * @return array<int, array{apartado: string, titulo: string, detalle: string}>
     */
    public function describir(Collection $movimientos): array
    {
        $ids = fn (string $tipo) => $movimientos->where('vinculo_tipo', $tipo)->pluck('vinculo_id')->filter()->unique()->values();
        $cargar = fn (string $tipo, callable $consulta) => ($l = $ids($tipo))->isEmpty() ? collect() : $consulta($l)->keyBy('id');

        $facturas = $cargar(CobranzaFactura::VINCULO, fn ($l) => CobranzaFactura::query()->whereIn('id', $l)->get());
        $cuotas = $cargar(CobranzaMovimiento::VINCULO_CUOTA, fn ($l) => ContratoCuota::query()->with('contrato')->whereIn('id', $l)->get());
        $alquileres = $cargar(CobranzaMovimiento::VINCULO_ALQUILER, fn ($l) => AlquilerPago::query()->with('alquiler.propiedad')->whereIn('id', $l)->get());
        $reservas = $cargar(CobranzaMovimiento::VINCULO_RESERVA, fn ($l) => Reserva::query()->with('propiedad')->whereIn('id', $l)->get());
        $transacciones = $cargar(CobranzaMovimiento::VINCULO_PAT_TRANSACCION, fn ($l) => PatTransaccion::query()->with('propiedad')->whereIn('id', $l)->get());
        $idsReparto = $movimientos->where('vinculo_tipo', CobranzaMovimiento::VINCULO_PAT_REPARTO)->pluck('id');
        $repartos = $idsReparto->isEmpty() || ! Schema::hasColumn('pat_transacciones', 'cobranza_movimiento_id')
            ? collect()
            : PatTransaccion::query()->with('propiedad')->whereIn('cobranza_movimiento_id', $idsReparto)->orderBy('id')->get()->groupBy('cobranza_movimiento_id');

        $out = [];
        foreach ($movimientos as $m) {
            $id = (int) $m->vinculo_id;
            $d = match ($m->vinculo_tipo) {
                CobranzaFactura::VINCULO => ($f = $facturas->get($id)) ? [
                    'apartado' => 'Cobranza',
                    'titulo' => $f->nombre_cliente.($f->codigo_cliente ? ' ('.$f->codigo_cliente.')' : ''),
                    'detalle' => trim(($f->tipo_cxc ?: 'FAC').' '.($f->numero_documento ?: $f->id_documento)).' · '.$f->sede_nombre
                        .($f->fecha_emision ? ' · emitida '.$f->fecha_emision->format('d/m/Y') : ''),
                ] : null,
                CobranzaMovimiento::VINCULO_CUOTA => ($c = $cuotas->get($id)) ? [
                    'apartado' => 'Contratos',
                    'titulo' => 'Contrato '.($c->contrato?->numero_contrato ?? '—').' · '.($c->contrato?->cliente ?? ''),
                    'detalle' => 'Cuota #'.$c->numero_cuota.($c->fecha_vencimiento ? ' · vence '.$c->fecha_vencimiento->format('d/m/Y') : '')
                        .' · cuota $'.number_format((float) $c->monto, 2),
                ] : null,
                CobranzaMovimiento::VINCULO_ALQUILER => ($p = $alquileres->get($id)) ? [
                    'apartado' => 'Patrimonial',
                    'titulo' => ($p->alquiler?->propiedad?->nombre ?? 'Propiedad').' · Alquiler',
                    'detalle' => 'Período '.$p->periodo.' · inquilino '.($p->alquiler?->inquilino_nombre ?? '—')
                        .' · canon $'.number_format((float) $p->monto, 2),
                ] : null,
                CobranzaMovimiento::VINCULO_RESERVA => ($r = $reservas->get($id)) ? [
                    'apartado' => 'Patrimonial',
                    'titulo' => ($r->propiedad?->nombre ?? 'Propiedad').' · Reserva',
                    'detalle' => $r->cliente_nombre.' · '.optional($r->fecha_entrada)->format('d/m/Y').' al '.optional($r->fecha_salida)->format('d/m/Y'),
                ] : null,
                CobranzaMovimiento::VINCULO_PAT_TRANSACCION => ($t = $transacciones->get($id)) ? [
                    'apartado' => 'Patrimonial',
                    'titulo' => ($t->propiedad?->nombre ?? 'Propiedad').' · '.($t->tipo === 'gasto' ? 'Gasto' : 'Ingreso'),
                    'detalle' => (string) $t->categoria,
                ] : null,
                CobranzaMovimiento::VINCULO_PAT_REPARTO => ($partes = $repartos->get($m->id)) ? [
                    'apartado' => 'Patrimonial',
                    'titulo' => ($m->esIngreso() ? 'Ingreso' : 'Gasto').' repartido entre '.$partes->count().' propiedad'.($partes->count() === 1 ? '' : 'es'),
                    'detalle' => $partes->map(fn ($p) => ($p->propiedad?->nombre ?? 'Propiedad').' $'.number_format((float) $p->monto, 2).' ('.$p->categoria.')')->implode(' · '),
                ] : null,
                default => null,
            };
            if ($d) {
                $out[$m->id] = $d;
            }
        }

        return $out;
    }

    /**
     * Contratos activos con sus cuotas abiertas, para elegir qué cuota se paga.
     *
     * @return list<array<string, mixed>>
     */
    public function contratosConCuotas(): array
    {
        if (! Schema::hasTable('contrato_cuotas')) {
            return [];
        }
        $cuotas = ContratoCuota::query()
            ->whereIn('estatus', ['pendiente', 'parcial', 'vencido'])
            ->whereHas('contrato', fn ($q) => $q->where('activo', true))
            ->orderBy('fecha_vencimiento')
            ->get()
            ->groupBy('contrato_id');
        if ($cuotas->isEmpty()) {
            return [];
        }

        return Contrato::query()->whereIn('id', $cuotas->keys())->orderBy('cliente')->get()
            ->map(fn (Contrato $c) => [
                'id' => $c->id,
                'numero' => $c->numero_contrato,
                'cliente' => $c->cliente,
                'capital' => round((float) $c->total_a_pagar, 2),
                'cuotas' => $cuotas->get($c->id)->map(fn (ContratoCuota $q) => [
                    'valor' => CobranzaMovimiento::VINCULO_CUOTA.':'.$q->id,
                    'numero' => $q->numero_cuota,
                    'vence' => optional($q->fecha_vencimiento)->format('d/m/Y'),
                    'monto' => round((float) $q->monto, 2),
                    'pagado' => round((float) $q->monto_pagado, 2),
                    'debe' => round((float) $q->saldo > 0 ? (float) $q->saldo : max(0, (float) $q->monto - (float) $q->monto_pagado), 2),
                    'estatus' => $q->estatus,
                ])->values()->all(),
            ])->values()->all();
    }

    /**
     * Propiedades con alquileres por cobrar y reservas con saldo.
     *
     * @return list<array<string, mixed>>
     */
    public function propiedades(): array
    {
        if (! Schema::hasTable('pat_propiedades')) {
            return [];
        }
        $alquileres = Schema::hasTable('pat_alquiler_pagos')
            ? AlquilerPago::query()->with('alquiler')->where('estado', '!=', 'pagado')->orderBy('fecha_vencimiento')->get()
                ->groupBy(fn ($p) => $p->alquiler?->propiedad_id)
            : collect();
        $reservas = Schema::hasTable('pat_reservas')
            ? Reserva::query()->withSum('pagos', 'monto_pagado')->whereNotIn('estado', ['cancelada'])->orderByDesc('fecha_entrada')->limit(300)->get()
                ->filter(fn ($r) => $r->getTotal() - (float) ($r->pagos_sum_monto_pagado ?? 0) > 0.009)
                ->groupBy('propiedad_id')
            : collect();

        return Propiedad::query()->orderBy('nombre')->get()
            ->map(fn (Propiedad $p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'codigo' => $p->codigo,
                'alquileres' => ($alquileres->get($p->id) ?? collect())->map(fn (AlquilerPago $a) => [
                    'valor' => CobranzaMovimiento::VINCULO_ALQUILER.':'.$a->id,
                    'periodo' => $a->periodo,
                    'inquilino' => $a->alquiler?->inquilino_nombre,
                    'vence' => optional($a->fecha_vencimiento)->format('d/m/Y'),
                    'debe' => round((float) $a->getSaldo(), 2),
                    'estado' => $a->estado,
                ])->values()->all(),
                'reservas' => ($reservas->get($p->id) ?? collect())->map(fn (Reserva $r) => [
                    'valor' => CobranzaMovimiento::VINCULO_RESERVA.':'.$r->id,
                    'cliente' => $r->cliente_nombre,
                    'fechas' => optional($r->fecha_entrada)->format('d/m').' al '.optional($r->fecha_salida)->format('d/m/Y'),
                    'debe' => round($r->getTotal() - (float) ($r->pagos_sum_monto_pagado ?? 0), 2),
                ])->values()->all(),
            ])->values()->all();
    }

    /**
     * Facturas de Cobranza con saldo (última carga de Profit, sin las pagadas y con los abonos de la web).
     *
     * @return list<array{sede: string, doc: string, numero: string, cliente: string, codigo: string, emision: ?string, monto: float, saldo: float}>
     */
    public function buscarFacturas(string $texto, int $limite = 25): array
    {
        $ultima = HistorialCobranza::query()->max('fecha_registro');
        if (! $ultima) {
            return [];
        }
        $q = HistorialCobranza::query()->cuentasOperativas()
            ->where('fecha_registro', $ultima)
            ->where('saldo', '>', 0)
            ->whereNotExists(function ($w) {
                $w->select(DB::raw(1))->from('cobranzas_pagadas_manualmente as p')
                    ->where(fn ($x) => $x->whereColumn('p.id_documento', 'historial_cobranzas.numero_documento')
                        ->orWhereColumn('p.id_documento', 'historial_cobranzas.id_documento')
                        ->orWhereColumn('p.id_documento', 'historial_cobranzas.factura_padre'));
            });
        $texto = trim($texto);
        if ($texto !== '') {
            $like = '%'.mb_strtolower($texto, 'UTF-8').'%';
            $q->where(fn ($w) => $w->whereRaw('LOWER(nombre_cliente) LIKE ?', [$like])
                ->orWhereRaw('LOWER(codigo_cliente) LIKE ?', [$like])
                ->orWhereRaw('LOWER(numero_documento) LIKE ?', [$like])
                ->orWhereRaw('LOWER(id_documento) LIKE ?', [$like]));
        }
        $filas = $q->orderBy('nombre_cliente')->orderBy('fecha_emision')->limit($limite)->get();
        $this->facturas->aplicar($filas);

        return $filas->filter(fn ($f) => (float) $f->saldo > 0.009)->map(fn ($f) => [
            'sede' => (string) $f->sede_nombre,
            'doc' => (string) $f->id_documento,
            'numero' => trim(($f->tipo_cxc ?: 'FAC').' '.($f->numero_documento ?: $f->id_documento)),
            'cliente' => (string) $f->nombre_cliente,
            'codigo' => (string) $f->codigo_cliente,
            'emision' => $f->fecha_emision ? \Carbon\Carbon::parse($f->fecha_emision)->format('d/m/Y') : null,
            'monto' => round((float) $f->monto_neto, 2),
            'saldo' => round((float) $f->saldo, 2),
        ])->values()->all();
    }
}
