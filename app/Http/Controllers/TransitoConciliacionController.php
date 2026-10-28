<?php

namespace App\Http\Controllers;

use App\Models\CompraDivisa;
use App\Models\ConciliacionLinea;
use App\Models\FlujoCaja;
use App\Services\BankMovementClassifier;
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

    /** La coma corrida solo se sugiere cerca de la fecha: con montos ×100 aparece mucho ruido. */
    private const DIAS_X100 = 10;

    public function __construct(
        private BankReconciliationMatcher $matcher,
        private ConciliacionAutoMatcher $autoMatcher,
        private BankMovementClassifier $classifier,
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
                $enRango = function ($q, int $dias, array $valores) use ($fecha) {
                    $q->orWhere(function ($q) use ($fecha, $dias, $valores) {
                        $q->where('fecha', '>=', $fecha->copy()->subDays($dias)->toDateString())
                            ->where('fecha', '<=', $fecha->copy()->addDays($dias)->toDateString())
                            ->where(function ($q) use ($valores) {
                                foreach ($valores as $v) {
                                    $q->orWhereBetween('monto', [$v - 0.005, $v + 0.005])
                                        ->orWhereBetween('monto', [-$v - 0.005, -$v + 0.005]);
                                }
                            });
                    });
                };
                if ($montos !== []) {
                    $enRango($q, self::DIAS_MONTO, $montos);
                    $enRango($q, self::DIAS_X100, array_merge(...array_map(fn ($m) => [$m * 100, $m / 100], $montos)));
                }
                if ($cola !== '') {
                    $q->orWhere('referencia', 'like', '%'.$cola.'%');
                }
            })
            ->limit(400);

        $lineas = $q->get()->reject(fn (ConciliacionLinea $l) => $this->esComision($l));
        $flujos = $this->ligados($lineas->pluck('flujo_caja_id')->filter()->unique()->all());

        // Primero lo que coincide en monto y referencia, luego solo monto, luego solo referencia;
        // dentro de cada grupo, lo más cercano a la fecha del egreso. La coma corrida va al final.
        $resultados = $lineas
            ->map(fn (ConciliacionLinea $l) => $this->resultado($l, $mov, $esCompra, $cuenta, $montos, $fecha, $flujos))
            ->filter(fn ($r) => $r['mismo_monto'] || $r['misma_ref'] || ($r['x100'] && abs($r['dias']) <= self::DIAS_X100))
            ->sortBy(fn ($r) => [$r['grupo'], abs($r['dias']), $r['misma_cuenta'] ? 0 : 1])
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
                && ! ($l->devolucion_linea_id ?? null) && ! $this->esComision($l))
            ->map(fn (ConciliacionLinea $l) => $this->resultado($l, $mov, $esCompra, $cuenta, $montos, $fecha, []))
            ->sortBy(fn ($r) => [$r['mismo_monto'] ? 0 : 1, $r['misma_ref'] ? 0 : 1, abs($r['monto'] - ($montos[0] ?? 0)), abs($r['dias'])])
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

    /**
     * El egreso en tránsito es una copia de otro ya registrado: se anula (queda como nulo, con el motivo),
     * sale de En tránsito y de los totales. No se borra.
     */
    public function anularDuplicado(Request $request)
    {
        $data = $request->validate([
            'flujo_id' => ['required', 'integer'],
            'duplicado_de' => ['required', 'integer', 'different:flujo_id'],
        ]);
        if (! Schema::hasColumn('flujo_cajas', 'anulado')) {
            return $this->volver($request)->with('error', 'Falta migrar la columna anulado. Ejecuta: php artisan migrate');
        }
        $flujo = FlujoCaja::findOrFail($data['flujo_id']);
        $original = FlujoCaja::findOrFail($data['duplicado_de']);

        if ($flujo->es_conciliado || $flujo->anulado || ConciliacionLinea::query()->where('flujo_caja_id', $flujo->id)->exists()) {
            return $this->volver($request)->with('error', 'El egreso #'.$flujo->id.' ya está conciliado o anulado: no se puede anular como duplicado.');
        }
        if ($original->anulado || ! $this->matcher->mismosMontos(abs((float) $original->monto_bs), abs((float) $flujo->monto_bs))) {
            return $this->volver($request)->with('error', 'El egreso #'.$original->id.' no tiene el mismo monto o está anulado: no es un duplicado.');
        }

        $motivo = 'Duplicado del egreso #'.$original->id.' ('.$this->dia($original->fecha)->format('d/m/Y')
            .($original->referencia ? ', ref. '.$original->referencia : '').') — '.($request->user()?->name ?? 'usuario');
        DB::table('flujo_cajas')->where('id', $flujo->id)->where('es_conciliado', false)->update([
            'anulado' => true,
            'anulado_motivo' => mb_substr($motivo, 0, 255),
            'anulado_at' => now(),
            'updated_at' => now(),
        ]);
        // Totales del día en el resumen del Flujo de Caja.
        app(FinanzasController::class)->syncTotalesSalidas($this->dia($flujo->fecha)->toDateString());

        return $this->volver($request)->with('success', 'Egreso #'.$flujo->id.' anulado por estar duplicado con el #'.$original->id.'. Ya no aparece en En tránsito.');
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

    /**
     * Monto con el que se busca en los extractos: el de bolívares; el de dólares solo si el egreso
     * no tiene monto en Bs (cuentas en divisas). Mezclarlos hacía que $200 ÷ 100 pareciera Bs 2,00.
     *
     * @return list<float>
     */
    private function montos(object $mov, bool $esCompra): array
    {
        $bs = abs((float) $mov->monto_bs);
        if ($bs >= 0.005 || $esCompra) {
            return $bs >= 0.005 ? [$bs] : [];
        }
        $usd = abs((float) ($mov->monto_usd ?? 0));

        return $usd >= 0.005 ? [$usd] : [];
    }

    private function esComision(ConciliacionLinea $l): bool
    {
        return (bool) ($l->forzar_comision ?? false) || $this->classifier->esComision($l->descripcion, $l->banco);
    }

    private function textoDias(int $dias): string
    {
        return match (true) {
            $dias === 0 => 'mismo día',
            $dias > 0 => $dias.' '.($dias === 1 ? 'día' : 'días').' después',
            default => abs($dias).' '.($dias === -1 ? 'día' : 'días').' antes',
        };
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

        // Mismo pago del banco ya conciliado con otro egreso del mismo monto: este egreso sobra.
        $duplicadoDe = (! $esCompra && $mismoMonto && $l->flujo_caja_id && (int) $l->flujo_caja_id !== (int) $mov->id)
            ? (int) $l->flujo_caja_id : null;

        $pista = match (true) {
            $l->tipo !== 'cargo' => 'Es un abono (entrada), no una salida.',
            $ligado !== null && (int) $l->flujo_caja_id === (int) $mov->id && ! $esCompra => 'Ya está ligada a este mismo egreso.',
            $duplicadoDe !== null => 'Este pago del banco ya está conciliado con el egreso #'.$duplicadoDe.': si es el mismo pago, este egreso está registrado dos veces.',
            $ligado !== null => 'Ya está conciliada con otro movimiento.',
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
            'dias' => $dias = (int) $fecha->diffInDays($l->fecha->copy()->startOfDay(), false),
            'cuando' => $this->textoDias($dias),
            'pista' => $pista,
            'accion' => $accion,
            'duplicado_de' => $duplicadoDe,
            'pide_nota' => ! $mismoMonto,
            'grupo' => match (true) {
                $mismoMonto && $mismaRef => 0,
                $mismoMonto => 1,
                $mismaRef => 2,
                default => 3,
            },
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
            ->map(function (FlujoCaja $f) use ($mov, $monto) {
                $mismoMonto = $this->matcher->mismosMontos(abs((float) $f->monto_bs), $monto);
                $mismaRef = $this->matcher->referenciasCruzan($f->referencia, $mov->referencia);
                $dias = (int) $this->dia($mov->fecha)->diffInDays($this->dia($f->fecha), false);
                $sinRef = trim((string) $mov->referencia) === '' || trim((string) $f->referencia) === '';
                $mismaCuenta = $this->matcher->claveCuenta($f->banco, $f->titular) === $this->matcher->claveCuenta($mov->banco, $mov->titular);

                return [
                    'id' => $f->id,
                    'fecha' => $this->dia($f->fecha)->format('d/m/Y'),
                    'cuando' => $this->textoDias($dias),
                    'dias' => $dias,
                    'cuenta' => $this->nombreCuenta($f->banco, $f->titular),
                    'referencia' => $f->referencia,
                    'motivo' => $f->motivo ?: $f->concepto,
                    'monto' => round((float) $f->monto_bs, 2),
                    'conciliado' => (bool) $f->es_conciliado,
                    'mismo_monto' => $mismoMonto,
                    'misma_ref' => $mismaRef,
                    // Duplicado = misma cuenta y monto, y misma referencia (sin referencia: el mismo día).
                    'duplicado' => $mismaCuenta && $mismoMonto && ($mismaRef || ($sinRef && $dias === 0)),
                ];
            })
            ->filter(fn ($e) => $e['mismo_monto'] || $e['misma_ref'])
            ->sortBy(fn ($e) => [$e['duplicado'] ? 0 : 1, $e['mismo_monto'] && $e['misma_ref'] ? 0 : 1, abs($e['dias'])])
            ->values()->all();
    }

    private function ligados(array $ids): array
    {
        $columnas = array_values(array_filter(['id', 'fecha', 'motivo', 'concepto'], fn ($c) => Schema::hasColumn('flujo_cajas', $c)));

        return $ids === [] ? [] : FlujoCaja::query()->whereIn('id', $ids)->get($columnas)->keyBy('id')->all();
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
