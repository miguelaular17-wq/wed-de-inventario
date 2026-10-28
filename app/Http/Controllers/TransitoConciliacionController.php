<?php

namespace App\Http\Controllers;

use App\Models\CompraDivisa;
use App\Models\ConciliacionLinea;
use App\Models\FlujoCaja;
use App\Services\BankReconciliationMatcher;
use App\Services\Conciliacion\ConciliacionAutoMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Movimientos "En tránsito" de Conciliaciones: buscar dónde quedó el pago en los bancos
 * y conciliarlo con una línea elegida o con una justificación.
 */
class TransitoConciliacionController extends Controller
{
    private const DIAS_MONTO = 45;

    private const DIAS_REFERENCIA = 120;

    public function __construct(
        private BankReconciliationMatcher $matcher,
        private ConciliacionAutoMatcher $autoMatcher,
    ) {}

    public function buscar(Request $request): JsonResponse
    {
        [$mov, $esCompra] = $this->movimiento($request);
        $fecha = $this->dia($mov->fecha);
        $cuenta = $this->matcher->claveCuenta($mov->banco, $mov->titular);
        $montos = $this->montos($mov, $esCompra);
        $ref = $this->digitos($mov->referencia);
        $cola = strlen($ref) >= 6 ? substr($ref, -6) : '';

        $q = ConciliacionLinea::query()
            ->where('fecha', '>=', $fecha->copy()->subDays(self::DIAS_REFERENCIA)->toDateString())
            ->where('fecha', '<=', $fecha->copy()->addDays(self::DIAS_REFERENCIA)->toDateString())
            ->where(function ($q) use ($montos, $cola, $fecha) {
                $q->where(function ($q) use ($montos, $fecha) {
                    $q->where('fecha', '>=', $fecha->copy()->subDays(self::DIAS_MONTO)->toDateString())
                        ->where('fecha', '<=', $fecha->copy()->addDays(self::DIAS_MONTO)->toDateString())
                        ->where(function ($q) use ($montos) {
                            foreach ($montos as $m) {
                                foreach ([$m, $m * 100, $m / 100] as $v) {
                                    $q->orWhereBetween('monto', [$v - 0.005, $v + 0.005])
                                        ->orWhereBetween('monto', [-$v - 0.005, -$v + 0.005]);
                                }
                            }
                        });
                });
                if ($cola !== '') {
                    $q->orWhere('referencia', 'like', '%'.$cola.'%');
                }
            })
            ->limit(400);

        $lineas = $q->get();
        $flujos = $this->ligados($lineas->pluck('flujo_caja_id')->filter()->unique()->all());

        $resultados = $lineas
            ->map(fn (ConciliacionLinea $l) => $this->resultado($l, $mov, $esCompra, $cuenta, $montos, $fecha, $flujos))
            ->filter(fn ($r) => $r['mismo_monto'] || $r['misma_ref'] || $r['x100'])
            ->sortBy(fn ($r) => [$r['puntos'], $r['dias']])
            ->take(60)
            ->values();

        $pendientesCuenta = ConciliacionLinea::query()
            ->where('estado', 'pendiente')->whereNull('flujo_caja_id')->whereNull('tesoreria_ingreso_id')
            ->when(Schema::hasColumn('conciliacion_lineas', 'compra_divisa_id'), fn ($q) => $q->whereNull('compra_divisa_id'))
            ->where('tipo', 'cargo')
            ->where(function ($q) use ($mov) {
                foreach ($this->matcher->variantesBanco($mov->banco) as $v) {
                    $q->orWhereRaw('UPPER(TRIM(banco)) = ?', [strtoupper($v)]);
                }
            })
            ->where('fecha', '>=', $fecha->copy()->subDays(30)->toDateString())
            ->where('fecha', '<=', $fecha->copy()->addDays(30)->toDateString())
            ->limit(3000)->get()
            ->filter(fn (ConciliacionLinea $l) => $this->matcher->claveCuenta($l->banco, $l->titular) === $cuenta
                && ! ($l->devolucion_linea_id ?? null))
            ->map(fn (ConciliacionLinea $l) => $this->resultado($l, $mov, $esCompra, $cuenta, $montos, $fecha, []))
            ->sortBy(fn ($r) => [$r['mismo_monto'] ? 0 : 1, $r['misma_ref'] ? 0 : 1, abs($r['monto'] - ($montos[0] ?? 0)), $r['dias']])
            ->take(80)
            ->values();

        return response()->json([
            'movimiento' => [
                'fecha' => $this->dia($mov->fecha)->format('d/m/Y'),
                'referencia' => $mov->referencia,
                'concepto' => $mov->concepto ?: $mov->motivo,
                'monto' => round((float) $mov->monto_bs, 2),
                'cuenta' => $this->nombreCuenta($mov->banco, $mov->titular),
            ],
            'resultados' => $resultados,
            'pendientes_cuenta' => $pendientesCuenta,
            'duplicados' => $esCompra ? [] : $this->egresosParecidos($mov),
        ]);
    }

    public function justificar(Request $request)
    {
        $data = $request->validate([
            'linea_id' => ['nullable', 'integer'],
            'justificacion' => ['nullable', 'string', 'max:500'],
            'mover_cuenta' => ['nullable', 'boolean'],
        ]);
        [$mov, $esCompra] = $this->movimiento($request);
        $nota = trim((string) ($data['justificacion'] ?? ''));
        $usuario = $request->user()?->name ?? 'usuario';
        $firma = fn (string $texto) => $texto.' — '.$usuario.', '.now()->format('d/m/Y H:i');

        if ($mov->es_conciliado) {
            return $this->volver($request)->with('error', 'Ese movimiento ya estaba conciliado.');
        }

        if (empty($data['linea_id'])) {
            if (mb_strlen($nota) < 5) {
                return $this->volver($request)->with('error', 'Escribe qué es el movimiento para darlo por conciliado sin línea del banco.');
            }
            $cambios = ['es_conciliado' => true, 'updated_at' => now()];
            if ($this->tieneNota($mov)) {
                $cambios['conciliacion_nota'] = $firma('Sin movimiento en el banco: '.$nota);
            }
            DB::table($mov->getTable())->where('id', $mov->id)->update($cambios);

            return $this->volver($request)->with('success', 'Movimiento en tránsito dado por conciliado con la justificación: «'.$nota.'».');
        }

        $linea = ConciliacionLinea::findOrFail($data['linea_id']);
        $mismaCuenta = $this->matcher->claveCuenta($linea->banco, $linea->titular) === $this->matcher->claveCuenta($mov->banco, $mov->titular);
        $mismoMonto = collect($this->montos($mov, $esCompra))->contains(fn ($m) => $this->matcher->mismosMontos(abs((float) $linea->monto), $m));
        $partes = [];

        if (! $mismaCuenta) {
            if (! $request->boolean('mover_cuenta')) {
                return $this->volver($request)->with('error', 'Esa línea es de otra cuenta. Usa «Cambiar a esa cuenta y conciliar».');
            }
            $partes[] = 'Cuenta cambiada de '.$this->nombreCuenta($mov->banco, $mov->titular).' a '.$this->nombreCuenta($linea->banco, $linea->titular);
        }
        if (! $mismoMonto) {
            if (mb_strlen($nota) < 5) {
                return $this->volver($request)->with('error', 'El monto del banco (Bs '.number_format(abs((float) $linea->monto), 2, ',', '.').') no coincide: escribe por qué se concilia igual.');
            }
            $partes[] = 'Monto del banco Bs '.number_format(abs((float) $linea->monto), 2, ',', '.').' vs registrado Bs '.number_format((float) $mov->monto_bs, 2, ',', '.');
        }
        if ($nota !== '') {
            $partes[] = $nota;
        }

        try {
            DB::transaction(function () use ($mov, $linea, $esCompra, $mismaCuenta, $partes, $firma) {
                if (! $mismaCuenta) {
                    DB::table($mov->getTable())->where('id', $mov->id)->update(['banco' => $linea->banco, 'titular' => $linea->titular, 'updated_at' => now()]);
                }
                if (! $this->autoMatcher->vincularElegida($linea, $mov, $esCompra)) {
                    throw new \RuntimeException('ocupada');
                }
                if ($partes !== [] && $this->tieneNota($mov)) {
                    DB::table($mov->getTable())->where('id', $mov->id)->update(['conciliacion_nota' => $firma(implode('. ', $partes))]);
                }
            });
        } catch (\RuntimeException) {
            return $this->volver($request)->with('error', 'Esa línea del banco ya la tomó otro movimiento. Recarga y vuelve a buscar.');
        }

        return $this->volver($request)->with('success', 'Conciliado con el movimiento del banco del '.$linea->fecha->format('d/m/Y')
            .($linea->referencia ? ' (ref. '.$linea->referencia.')' : '')
            .($mismaCuenta ? '' : ' y el egreso pasó a la cuenta '.$this->nombreCuenta($linea->banco, $linea->titular)).'.');
    }

    /** Movimiento del banco sin egreso/ingreso en el sistema: se concilia dejando escrito qué es. */
    public function justificarLinea(Request $request)
    {
        $data = $request->validate([
            'linea_id' => ['required', 'integer'],
            'justificacion' => ['nullable', 'string', 'max:500'],
        ]);
        $nota = trim((string) ($data['justificacion'] ?? ''));
        if (mb_strlen($nota) < 5) {
            return $this->volver($request)->with('error', 'Escribe qué es el movimiento (mínimo 5 caracteres) para conciliarlo.');
        }
        if (! Schema::hasColumn('conciliacion_lineas', 'conciliacion_nota')) {
            return $this->volver($request)->with('error', 'Falta migrar la columna conciliacion_nota. Ejecuta: php artisan migrate');
        }

        $firmada = $nota.' — '.($request->user()?->name ?? 'usuario').', '.now()->format('d/m/Y H:i');
        $hecho = DB::table('conciliacion_lineas')
            ->where('id', $data['linea_id'])
            ->where('estado', 'pendiente')
            ->whereNull('flujo_caja_id')->whereNull('tesoreria_ingreso_id')
            ->when(Schema::hasColumn('conciliacion_lineas', 'compra_divisa_id'), fn ($q) => $q->whereNull('compra_divisa_id'))
            ->when(Schema::hasColumn('conciliacion_lineas', 'devolucion_linea_id'), fn ($q) => $q->whereNull('devolucion_linea_id'))
            ->update(['estado' => 'conciliado', 'conciliacion_nota' => $firmada, 'updated_at' => now()]);

        return $hecho === 1
            ? $this->volver($request)->with('success', 'Movimiento del banco conciliado con la justificación: «'.$nota.'».')
            : $this->volver($request)->with('error', 'Ese movimiento ya no está pendiente (lo concilió otro proceso). Recarga la página.');
    }

    /** @return array{0: FlujoCaja|CompraDivisa, 1: bool} */
    private function movimiento(Request $request): array
    {
        if ($request->filled('compra_id') && Schema::hasTable('compra_divisas')) {
            return [CompraDivisa::findOrFail((int) $request->input('compra_id')), true];
        }
        abort_unless($request->filled('flujo_id'), 422, 'No se indicó el movimiento.');

        return [FlujoCaja::findOrFail((int) $request->input('flujo_id')), false];
    }

    /** @return list<float> */
    private function montos(object $mov, bool $esCompra): array
    {
        return array_values(array_filter(
            [abs((float) $mov->monto_bs), $esCompra ? 0.0 : abs((float) ($mov->monto_usd ?? 0))],
            fn ($m) => $m >= 0.005
        ));
    }

    private function resultado(ConciliacionLinea $l, object $mov, bool $esCompra, string $cuenta, array $montos, $fecha, array $flujos): array
    {
        $monto = abs((float) $l->monto);
        $mismoMonto = collect($montos)->contains(fn ($m) => $this->matcher->mismosMontos($monto, $m));
        $x100 = ! $mismoMonto && collect($montos)->contains(fn ($m) => $this->matcher->mismosMontos($monto, $m * 100) || $this->matcher->mismosMontos($monto, $m / 100));
        $mismaRef = $this->matcher->referenciasCruzan($l->referencia, $mov->referencia);
        $mismaCuenta = $this->matcher->claveCuenta($l->banco, $l->titular) === $cuenta;
        $libre = $l->estado === 'pendiente' && ! $l->flujo_caja_id && ! $l->tesoreria_ingreso_id
            && ! ($l->compra_divisa_id ?? null) && ! ($l->devolucion_linea_id ?? null);

        $ligado = null;
        if ($l->flujo_caja_id) {
            $f = $flujos[$l->flujo_caja_id] ?? null;
            $ligado = 'Egreso #'.$l->flujo_caja_id.($f ? ' · '.$this->dia($f->fecha)->format('d/m/Y').' · '.($f->motivo ?: $f->concepto) : '');
        } elseif ($l->tesoreria_ingreso_id) {
            $ligado = 'Ingreso de Tesorería #'.$l->tesoreria_ingreso_id;
        } elseif ($l->compra_divisa_id ?? null) {
            $ligado = 'Compra de divisas #'.$l->compra_divisa_id;
        } elseif ($l->devolucion_linea_id ?? null) {
            $ligado = 'Devolución';
        } elseif ($l->estado !== 'pendiente') {
            $ligado = ucfirst((string) $l->estado);
        }

        $pista = match (true) {
            $l->tipo !== 'cargo' => 'Es un abono (entrada), no una salida.',
            $ligado !== null && (int) $l->flujo_caja_id === (int) $mov->id && ! $esCompra => 'Ya está ligada a este mismo egreso.',
            $ligado !== null => 'Ya está conciliada con otro movimiento: puede ser un egreso registrado dos veces.',
            ! $mismaCuenta => 'El pago salió por otra cuenta: el egreso puede estar registrado en la cuenta equivocada.',
            $x100 => 'El monto es 100 veces mayor o menor: posible error de coma al registrar.',
            ! $mismoMonto => 'Misma referencia con otro monto: revisa el monto registrado.',
            default => 'Está pendiente en esta cuenta: se puede conciliar.',
        };

        $accion = null;
        if ($libre && $l->tipo === 'cargo') {
            $accion = $mismaCuenta ? 'conciliar' : (! $esCompra ? 'mover' : null);
        }

        return [
            'id' => $l->id,
            'fecha' => $l->fecha->format('d/m/Y'),
            'cuenta' => $this->nombreCuenta($l->banco, $l->titular),
            'referencia' => $l->referencia,
            'descripcion' => $l->descripcion,
            'monto' => round($monto, 2),
            'tipo' => $l->tipo,
            'estado' => $l->estado,
            'ligado' => $ligado,
            'misma_cuenta' => $mismaCuenta,
            'mismo_monto' => $mismoMonto,
            'misma_ref' => $mismaRef,
            'x100' => $x100,
            'dias' => (int) abs($l->fecha->copy()->startOfDay()->diffInDays($fecha, false)),
            'pista' => $pista,
            'accion' => $accion,
            'pide_nota' => ! $mismoMonto,
            'puntos' => ($mismoMonto && $mismaRef ? 0 : ($mismaRef ? 1 : ($mismoMonto ? 2 : 3))) + ($libre ? 0 : 4),
        ];
    }

    /** Otros egresos con la misma referencia, o mismo monto y fecha cercana: posible registro duplicado. */
    private function egresosParecidos(FlujoCaja $mov): array
    {
        $ref = $this->digitos($mov->referencia);
        $monto = abs((float) $mov->monto_bs);

        return FlujoCaja::query()
            ->where('id', '!=', $mov->id)
            ->where('tipo', 'egreso')
            ->when(Schema::hasColumn('flujo_cajas', 'anulado'), fn ($q) => $q->where('anulado', false))
            ->where(function ($q) use ($mov, $ref, $monto) {
                if (strlen($ref) >= 6) {
                    $q->orWhere('referencia', 'like', '%'.substr($ref, -6).'%');
                }
                $q->orWhere(function ($q) use ($mov, $monto) {
                    $q->whereBetween('monto_bs', [$monto - 0.005, $monto + 0.005])
                        ->whereBetween('fecha', [$this->dia($mov->fecha)->subDays(7)->toDateString(), $this->dia($mov->fecha)->addDays(7)->toDateString()]);
                });
            })
            ->orderBy('fecha')->limit(20)->get()
            ->filter(fn (FlujoCaja $f) => $this->matcher->referenciasCruzan($f->referencia, $mov->referencia)
                || $this->matcher->mismosMontos(abs((float) $f->monto_bs), $monto))
            ->map(fn (FlujoCaja $f) => [
                'id' => $f->id,
                'fecha' => $this->dia($f->fecha)->format('d/m/Y'),
                'cuenta' => $this->nombreCuenta($f->banco, $f->titular),
                'referencia' => $f->referencia,
                'motivo' => $f->motivo ?: $f->concepto,
                'monto' => round((float) $f->monto_bs, 2),
                'conciliado' => (bool) $f->es_conciliado,
            ])->values()->all();
    }

    private function ligados(array $ids): array
    {
        return $ids === [] ? [] : FlujoCaja::query()->whereIn('id', $ids)->get(['id', 'fecha', 'motivo', 'concepto'])->keyBy('id')->all();
    }

    private function nombreCuenta(?string $banco, ?string $titular): string
    {
        return trim(strtoupper(trim((string) $banco)).' · '.strtoupper(trim((string) $titular)), ' ·');
    }

    private function dia($fecha): \Carbon\Carbon
    {
        return \Carbon\Carbon::parse($fecha instanceof \DateTimeInterface ? $fecha->format('Y-m-d') : substr((string) $fecha, 0, 10))->startOfDay();
    }

    private function digitos(?string $ref): string
    {
        return ltrim(preg_replace('/\D+/', '', (string) $ref) ?? '', '0');
    }

    private function tieneNota(object $mov): bool
    {
        return Schema::hasColumn($mov->getTable(), 'conciliacion_nota');
    }

    private function volver(Request $request)
    {
        $filtros = $request->session()->get('conciliaciones.filtros', []);

        return redirect()->route('finanzas.conciliaciones', array_filter([
            'fecha_desde' => $request->input('fecha_desde') ?: ($filtros['fecha_desde'] ?? null),
            'fecha_hasta' => $request->input('fecha_hasta') ?: ($filtros['fecha_hasta'] ?? null),
            'banco_filtro' => $request->input('banco_filtro') ?: ($filtros['banco_filtro'] ?? null),
            'titular_filtro' => $request->input('titular_filtro') ?: ($filtros['titular_filtro'] ?? null),
        ], fn ($v) => $v !== null && $v !== ''));
    }
}
