<?php

namespace App\Services\Conciliacion;

use App\Models\CompraDivisa;
use App\Models\ConciliacionLinea;
use App\Models\FlujoCaja;
use App\Services\BankMovementClassifier;
use App\Services\BankReconciliationMatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Emparejamiento automático extracto ↔ egresos / traslados / compras de divisas.
 *
 * Las reglas (monto, fechas, referencias, traslados) son las mismas que tenía
 * FinanzasController::conciliaciones; solo cambia cómo se escribe: cada vínculo se guarda
 * con UPDATE condicionado, así dos procesos no pueden conciliar el mismo movimiento.
 */
class ConciliacionAutoMatcher
{
    public function __construct(
        private BankReconciliationMatcher $matcher,
        private BankMovementClassifier $classifier,
    ) {}

    /**
     * @param  Collection<int, ConciliacionLinea>  $lineas  Líneas visibles de la cuenta y período.
     * @return int Líneas conciliadas en esta pasada.
     */
    public function conciliar(Collection $lineas, string $fecha_desde, string $fecha_hasta, ?string $banco_filtro, ?string $titular_filtro, int $limite = 500): int
    {
        $tieneCompraDivisa = Schema::hasColumn('conciliacion_lineas', 'compra_divisa_id');
        $lineas_pendientes = $lineas->where('estado', 'pendiente')
            ->whereNull('flujo_caja_id')
            ->whereNull('tesoreria_ingreso_id');
        if ($tieneCompraDivisa) {
            $lineas_pendientes = $lineas_pendientes->whereNull('compra_divisa_id');
        }
        $lineas_pendientes = $lineas_pendientes->values();
        if ($lineas_pendientes->count() === 0) {
            return 0;
        }

        $fecha_minima = $fecha_desde;
        $fecha_maxima = $fecha_hasta;

        // Los traslados siguen disponibles tras marcar salida (para poder
        // emparejar también el abono en el banco receptor).
        $flujos_posibles = FlujoCaja::where(function ($query) {
            $query->where('es_conciliado', false)
                ->orWhere('categoria_egreso', 'traslados');
        })
            ->where('tipo', 'egreso')
            ->where('fecha', '>=', $fecha_minima)
            ->where('fecha', '<=', $fecha_maxima);
        if (Schema::hasColumn('flujo_cajas', 'anulado')) {
            $flujos_posibles->where('anulado', false);
        }
        if ($banco_filtro) {
            $variantesMatch = array_map('strtolower', $this->matcher->variantesBanco($banco_filtro));
            $flujos_posibles->where(function ($q) use ($variantesMatch) {
                $q->where(function ($banco) use ($variantesMatch) {
                    foreach ($variantesMatch as $v) {
                        $banco->orWhereRaw('LOWER(TRIM(banco)) = ?', [$v]);
                    }
                })->orWhere(function ($receptor) use ($variantesMatch) {
                    foreach ($variantesMatch as $v) {
                        $receptor->orWhereRaw('LOWER(TRIM(banco_receptor)) = ?', [$v]);
                    }
                });
            });
        }
        $this->filtrarPorTitular($flujos_posibles, $titular_filtro);
        $flujos_posibles = $flujos_posibles->limit(5000)->get();

        $clavesEgreso = [];
        $referenciasEgreso = [];
        foreach ($flujos_posibles as $flujoPend) {
            $diaFlujo = $flujoPend->fecha instanceof \DateTimeInterface
                ? $flujoPend->fecha->format('Y-m-d')
                : substr((string) $flujoPend->fecha, 0, 10);
            foreach ([$flujoPend->monto_bs ?? null, $flujoPend->monto ?? null] as $montoFlujo) {
                if ($montoFlujo === null || abs((float) $montoFlujo) < 0.004) {
                    continue;
                }
                $clavesEgreso[$diaFlujo.'|'.(int) round(abs((float) $montoFlujo) * 100)] = true;
            }
            $ref = $this->digitosReferencia($flujoPend->referencia ?? null);
            if (strlen($ref) >= 6) {
                $referenciasEgreso[$ref] = true;
            }
        }
        // Primero las líneas con la referencia exacta de un egreso: si el banco tiene dos cargos
        // del mismo monto y día (intento devuelto + reenvío), el egreso va con el de su referencia.
        $lineas_pendientes = $lineas_pendientes
            ->sortBy(function ($linea) use ($clavesEgreso, $referenciasEgreso) {
                $dia = $linea->fecha instanceof \DateTimeInterface
                    ? $linea->fecha->format('Y-m-d')
                    : substr((string) $linea->fecha, 0, 10);
                $clave = $dia.'|'.(int) round(abs((float) $linea->monto) * 100);
                $prio = (isset($clavesEgreso[$clave]) && $linea->esCargo()) ? 0 : 1;
                $prioRef = isset($referenciasEgreso[$this->digitosReferencia($linea->referencia)]) ? 0 : 1;

                return [$prio, $prioRef, -((int) $linea->id)];
            })
            ->take($limite)
            ->values();

        $comprasDivisasPosibles = Schema::hasTable('compra_divisas')
            ? CompraDivisa::where('es_conciliado', false)
                ->where('fecha', '>=', $fecha_minima)
                ->where('fecha', '<=', $fecha_maxima)
                ->tap(fn ($q) => $this->filtrarPorBanco($q, $banco_filtro))
                ->tap(fn ($q) => $this->filtrarPorTitular($q, $titular_filtro))
                ->get()
            : collect();

        $matcher = $this->matcher;
        $classifier = $this->classifier;
        $traslados = $flujos_posibles->filter(fn ($flujo) => $matcher->esTraslado($flujo));
        $matcher->indexarTraslados($traslados);
        $trasladosPorId = $traslados->keyBy('id');
        $ladosTrasladosVinculados = [];
        if ($traslados->isNotEmpty()) {
            $lineasYaVinculadas = ConciliacionLinea::query()
                ->whereIn('flujo_caja_id', $traslados->pluck('id'))
                ->where('estado', 'conciliado')
                ->get(['id', 'flujo_caja_id', 'banco', 'titular', 'tipo', 'monto', 'fecha', 'descripcion', 'referencia']);

            foreach ($lineasYaVinculadas as $lineaVinculada) {
                $traslado = $trasladosPorId->get($lineaVinculada->flujo_caja_id);
                if (! $traslado) {
                    continue;
                }
                $lado = $matcher->ladoTraslado($lineaVinculada, $traslado);
                if ($lado !== null) {
                    $ladosTrasladosVinculados[$traslado->id][$lado] = true;
                }
            }
        }

        $conciliadas = 0;
        $flujosNormales = $flujos_posibles->reject(fn ($flujo) => $matcher->esTraslado($flujo))->values();
        $matcher->indexarEgresos($flujosNormales);
        foreach ($lineas_pendientes as $linea) {
            if ($matcher->esAbonoLotePuntoVenta($linea->descripcion, $linea->referencia)) {
                continue;
            }

            $match = null;
            $isCompraDivisaMatch = false;
            $trasladosDisponibles = [];
            foreach ($matcher->trasladosPorMonto($linea) as $flujo) {
                $lado = $matcher->ladoTraslado($linea, $flujo);
                if ($lado !== null && empty($ladosTrasladosVinculados[$flujo->id][$lado])) {
                    $trasladosDisponibles[(int) $flujo->id] = $flujo;
                }
            }

            if ($linea->esAbono()) {
                $match = $matcher->mejorEgreso($linea, $trasladosDisponibles);
            } else {
                if ($classifier->esCompraDivisas($linea->descripcion)) {
                    $match = $matcher->mejorCompraDivisa($linea, $comprasDivisasPosibles);
                    if ($match) {
                        $isCompraDivisaMatch = true;
                    }
                }
                if (! $match) {
                    $candidatos = $matcher->candidatosEgreso($linea);
                    foreach ($trasladosDisponibles as $flujo) {
                        $candidatos[(int) $flujo->id] = $flujo;
                    }
                    $match = $matcher->mejorEgreso($linea, $candidatos);
                }
            }

            if (! $match) {
                continue;
            }

            // Una comisión del banco no se queda con la transferencia por cruce de referencia.
            if (! $isCompraDivisaMatch && ! $matcher->esTraslado($match)
                && $matcher->comisionNoCorrespondeAlEgreso($linea, $match, $classifier->esComision($linea->descripcion, $linea->banco))) {
                continue;
            }

            $esTraslado = ! $isCompraDivisaMatch && $matcher->esTraslado($match);
            $ladoNuevo = $esTraslado ? $matcher->ladoTraslado($linea, $match) : null;
            $marcarConciliado = true;
            if ($esTraslado) {
                // Sale de "En tránsito" al conciliar la salida (banco emisor).
                // La entrada en el banco receptor puede seguir emparejándose
                // porque los traslados permanecen en flujos_posibles.
                $lados = $ladosTrasladosVinculados[$match->id] ?? [];
                if ($ladoNuevo !== null) {
                    $lados[$ladoNuevo] = true;
                }
                $marcarConciliado = ! empty($lados['salida']);
            }

            if (! $this->vincular($linea, $match, $isCompraDivisaMatch, $esTraslado, $marcarConciliado, $tieneCompraDivisa)) {
                // Otro proceso tomó la línea o el egreso: no se fuerza el vínculo.
                if (! $isCompraDivisaMatch && ! $esTraslado) {
                    $matcher->retirarEgreso($match);
                }

                continue;
            }

            $conciliadas++;
            $linea->estado = 'conciliado';
            if ($isCompraDivisaMatch) {
                $linea->compra_divisa_id = $match->id;
                $match->es_conciliado = true;
                $comprasDivisasPosibles = $comprasDivisasPosibles->reject(fn ($c) => $c->id == $match->id);
            } else {
                $linea->flujo_caja_id = $match->id;
                $match->es_conciliado = $marcarConciliado;
                if ($esTraslado) {
                    if ($ladoNuevo !== null) {
                        $ladosTrasladosVinculados[$match->id][$ladoNuevo] = true;
                    }
                } else {
                    $flujos_posibles = $flujos_posibles->reject(fn ($f) => $f->id == $match->id);
                    $flujosNormales = $flujosNormales->reject(fn ($f) => $f->id == $match->id);
                    $matcher->retirarEgreso($match);
                }
            }
            $linea->syncOriginal();
            $match->syncOriginal();
        }

        return $conciliadas;
    }

    /**
     * Conciliación manual desde "En tránsito": vincula el egreso / compra con la línea pendiente
     * del extracto de su cuenta que tenga el mismo monto (cargo), para que salga también de
     * "Movimientos en el Banco". Prioriza referencia que cruce y luego la fecha más cercana.
     *
     * @return ConciliacionLinea|null  null si el extracto no tiene una línea con ese monto.
     */
    public function conciliarManual(object $movimiento, bool $esCompraDivisa, ?string $fecha_desde = null, ?string $fecha_hasta = null): ?ConciliacionLinea
    {
        $tieneCompraDivisa = Schema::hasColumn('conciliacion_lineas', 'compra_divisa_id');
        $esTraslado = ! $esCompraDivisa && $this->matcher->esTraslado($movimiento);

        foreach ($this->lineasParaConciliarManual($movimiento, $esCompraDivisa, $fecha_desde, $fecha_hasta, $tieneCompraDivisa) as $linea) {
            if ($this->vincular($linea, $movimiento, $esCompraDivisa, $esTraslado, true, $tieneCompraDivisa)) {
                return $linea;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, ConciliacionLinea>
     */
    private function lineasParaConciliarManual(object $movimiento, bool $esCompraDivisa, ?string $fecha_desde, ?string $fecha_hasta, bool $tieneCompraDivisa): Collection
    {
        $montos = array_values(array_filter(
            [$movimiento->monto_bs ?? null, $esCompraDivisa ? null : ($movimiento->monto_usd ?? null)],
            fn ($m) => $m !== null && abs((float) $m) >= 0.005
        ));
        $fechaMov = $movimiento->fecha instanceof \DateTimeInterface
            ? $movimiento->fecha->format('Y-m-d')
            : substr((string) ($movimiento->fecha ?? ''), 0, 10);
        if ($montos === [] || $fechaMov === '') {
            return collect();
        }

        $dia = \Carbon\Carbon::parse($fechaMov);
        $desde = min(array_filter([$fecha_desde, $dia->copy()->subDays(15)->toDateString()]));
        $hasta = max(array_filter([$fecha_hasta, $dia->copy()->addDays(15)->toDateString()]));

        $q = ConciliacionLinea::query()
            ->where('estado', 'pendiente')
            ->whereNull('flujo_caja_id')
            ->whereNull('tesoreria_ingreso_id')
            ->where('fecha', '>=', $desde)
            ->where('fecha', '<=', $hasta)
            ->where(function ($q) use ($montos) {
                foreach ($montos as $m) {
                    $abs = abs((float) $m);
                    $q->orWhereBetween('monto', [$abs - 0.005, $abs + 0.005])
                        ->orWhereBetween('monto', [-$abs - 0.005, -$abs + 0.005]);
                }
            });
        if ($tieneCompraDivisa) {
            $q->whereNull('compra_divisa_id');
        }
        $this->filtrarPorBanco($q, $movimiento->banco ?? null);
        $this->filtrarPorTitular($q, $movimiento->titular ?? null);

        $matcher = $this->matcher;
        $classifier = $this->classifier;
        $diaMov = $dia->startOfDay();

        return $q->limit(200)->get()
            ->filter(function (ConciliacionLinea $linea) use ($movimiento, $montos, $matcher, $classifier) {
                if (! $linea->esCargo() || $linea->forzar_comision) {
                    return false;
                }
                if ($classifier->esComision($linea->descripcion, $linea->banco)) {
                    return false;
                }
                if (! $matcher->mismoTitular($linea->titular, $movimiento->titular ?? null, $linea->banco, $movimiento->banco ?? null)) {
                    return false;
                }
                foreach ($montos as $m) {
                    if ($matcher->mismosMontos(abs((float) $linea->monto), abs((float) $m))) {
                        return true;
                    }
                }

                return false;
            })
            ->sortBy(fn (ConciliacionLinea $linea) => [
                $matcher->referenciasCruzan($linea->referencia, $movimiento->referencia ?? null) ? 0 : 1,
                abs($linea->fecha->copy()->startOfDay()->diffInDays($diaMov, false)),
                (int) $linea->id,
            ])
            ->values();
    }

    /**
     * Escribe el vínculo solo si la línea sigue pendiente y el egreso / compra sigue libre.
     */
    private function vincular(ConciliacionLinea $linea, object $match, bool $esCompraDivisa, bool $esTraslado, bool $marcarConciliado, bool $tieneCompraDivisa): bool
    {
        try {
            DB::transaction(function () use ($linea, $match, $esCompraDivisa, $esTraslado, $marcarConciliado, $tieneCompraDivisa) {
                $ahora = now();
                $cambios = ['estado' => 'conciliado', 'updated_at' => $ahora];
                $cambios[$esCompraDivisa ? 'compra_divisa_id' : 'flujo_caja_id'] = $match->id;

                $q = DB::table('conciliacion_lineas')
                    ->where('id', $linea->id)
                    ->where('estado', 'pendiente')
                    ->whereNull('flujo_caja_id')
                    ->whereNull('tesoreria_ingreso_id');
                if ($tieneCompraDivisa) {
                    $q->whereNull('compra_divisa_id');
                }
                if ($q->update($cambios) !== 1) {
                    throw new VinculoOcupadoException;
                }

                if ($esCompraDivisa) {
                    $ok = DB::table('compra_divisas')->where('id', $match->id)->where('es_conciliado', false)
                        ->update(['es_conciliado' => true, 'updated_at' => $ahora]);
                } elseif ($esTraslado) {
                    DB::table('flujo_cajas')->where('id', $match->id)->where('es_conciliado', '!=', $marcarConciliado)
                        ->update(['es_conciliado' => $marcarConciliado, 'updated_at' => $ahora]);
                    $ok = 1;
                } else {
                    $ok = DB::table('flujo_cajas')->where('id', $match->id)->where('es_conciliado', false)
                        ->update(['es_conciliado' => true, 'updated_at' => $ahora]);
                }

                if ($ok !== 1) {
                    // El egreso ya lo concilió otro proceso: se deshace también la línea.
                    throw new VinculoOcupadoException;
                }
            });
        } catch (VinculoOcupadoException) {
            return false;
        }

        return true;
    }

    private function digitosReferencia(?string $referencia): string
    {
        return ltrim(preg_replace('/\D+/', '', (string) $referencia) ?? '', '0');
    }

    private function filtrarPorBanco($query, ?string $bancoFiltro, string $columna = 'banco')
    {
        if (! $bancoFiltro) {
            return $query;
        }
        $variantes = array_map('strtolower', $this->matcher->variantesBanco($bancoFiltro));

        return $query->where(function ($q) use ($variantes, $columna) {
            foreach ($variantes as $v) {
                $q->orWhereRaw("LOWER(TRIM({$columna})) = ?", [$v]);
            }
        });
    }

    private function filtrarPorTitular($query, ?string $titular, string $columna = 'titular')
    {
        $titular = strtoupper(trim((string) $titular));
        if ($titular === '') {
            return $query;
        }
        $clave = $this->matcher->titularClave($titular);
        $variantes = array_values(array_unique(array_filter([
            $titular,
            $clave,
            $clave !== '' ? 'GRUPO '.$clave : '',
            $clave !== '' ? 'L.S. '.$clave : '',
        ])));

        return $query->where(function ($q) use ($variantes, $columna) {
            foreach ($variantes as $variante) {
                $q->orWhereRaw("UPPER(TRIM(COALESCE({$columna}, ''))) = ?", [$variante]);
            }
        });
    }
}
