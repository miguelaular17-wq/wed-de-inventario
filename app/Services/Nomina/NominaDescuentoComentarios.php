<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaAbonoSueldo;
use App\Models\Nomina\NominaComisionDescuento;
use App\Models\Nomina\NominaDeduccion;
use App\Models\Nomina\NominaDescuentoMercancia;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaEmpleadoAjuste;
use App\Models\Nomina\NominaInasistencia;
use App\Models\Nomina\NominaLiquidacionComision;
use App\Models\Nomina\NominaPeriodo;
use App\Models\Nomina\NominaPrestamoAbono;
use App\Models\Nomina\NominaPrestamoCuota;
use App\Models\Nomina\NominaRegistro;
use Illuminate\Support\Facades\Schema;

class NominaDescuentoComentarios
{
    /**
     * @return list<array{tipo: string, comentario: string, monto: float, grupo: string}>
     */
    public function lineasNomina(NominaRegistro $registro): array
    {
        $desglose = $registro->desglose();
        $periodo = $registro->periodo;
        $empleado = $registro->empleado;
        if (! $periodo || ! $empleado) {
            return [];
        }

        if (! empty($desglose['descuentos_lineas']) && is_array($desglose['descuentos_lineas'])) {
            $lineas = $this->normalizar($desglose['descuentos_lineas']);
            $tieneAdelanto = collect($lineas)->contains(fn (array $l) => $l['grupo'] === 'adelanto');
            $montoAdelanto = (float) ($desglose['abonos_sueldo'] ?? 0);
            // Snapshot viejo o incompleto: reconstruir adelantos desde la tabla real.
            if ($montoAdelanto > 0.009 && ! $tieneAdelanto) {
                $vivos = array_values(array_filter(
                    $this->lineasNominaDesdePeriodo($periodo, $empleado),
                    fn (array $l) => $l['grupo'] === 'adelanto'
                ));
                $lineas = array_values(array_merge($lineas, $vivos));
            }

            return $lineas;
        }

        return $this->lineasNominaDesdePeriodo($periodo, $empleado);
    }

    /**
     * @return list<array{tipo: string, comentario: string, monto: float, grupo: string}>
     */
    public function lineasNominaColumnaDeducciones(NominaRegistro $registro): array
    {
        return array_values(array_filter(
            $this->lineasNomina($registro),
            fn (array $l) => in_array($l['grupo'], ['deduccion', 'mercancia', 'faltante_caja'], true)
        ));
    }

    /**
     * @return list<array{tipo: string, comentario: string, monto: float, grupo: string}>
     */
    public function lineasComision(NominaLiquidacionComision $liq): array
    {
        $snap = $liq->snapshot ?? [];
        if (! empty($snap['descuentos_lineas']) && is_array($snap['descuentos_lineas'])) {
            return $this->normalizar($snap['descuentos_lineas']);
        }

        $periodo = $liq->periodo;
        $empleado = $liq->empleado;
        if (! $periodo || ! $empleado) {
            return [];
        }

        return $this->lineasComisionDesdePeriodo($periodo, $empleado);
    }

    /**
     * @return list<array{tipo: string, comentario: string, monto: float, grupo: string}>
     */
    public function lineasNominaDesdePeriodo(NominaPeriodo $periodo, NominaEmpleado $empleado): array
    {
        $lineas = [];

        foreach ($this->filas(NominaAbonoSueldo::class, $empleado->id, $periodo->id, 'nomina_periodo_id') as $row) {
            $lineas[] = $this->linea('Adelanto', $row->motivo ?? null, (float) $row->monto, 'adelanto');
        }
        foreach ($this->filas(NominaInasistencia::class, $empleado->id, $periodo->id, 'nomina_periodo_id') as $row) {
            $lineas[] = $this->linea('Inasistencia', $row->motivo ?? null, (float) $row->monto, 'inasistencia');
        }
        if (Schema::hasTable('nomina_descuentos_mercancia')) {
            foreach (NominaDescuentoMercancia::query()
                ->where('empleado_id', $empleado->id)
                ->where('nomina_periodo_id', $periodo->id)
                ->where(function ($q) {
                    $q->where('destino', NominaDescuentoMercancia::DESTINO_NOMINA)
                        ->orWhereNull('destino');
                })
                ->orderBy('id')
                ->get() as $row) {
                $lineas[] = $this->linea('Mercancía', $row->motivo ?? null, (float) $row->monto, 'mercancia');
            }
        }
        if (Schema::hasTable('nomina_comision_descuentos')) {
            $faltantesNomina = NominaComisionDescuento::query()
                ->where('empleado_id', $empleado->id)
                ->where('periodo_id', $periodo->id)
                ->where('tipo', 'FALTANTE')
                ->where(function ($q) use ($empleado) {
                    if (Schema::hasColumn('nomina_comision_descuentos', 'destino')) {
                        $q->where('destino', NominaComisionDescuento::DESTINO_NOMINA);
                        if (! $empleado->generaComision()) {
                            $q->orWhereNull('destino');
                        }
                    } else {
                        $q->whereRaw('1 = ?', [$empleado->generaComision() ? 0 : 1]);
                    }
                })
                ->orderBy('id')
                ->get();
            foreach ($faltantesNomina as $row) {
                $lineas[] = $this->linea('Faltante de caja', $row->motivo ?? null, (float) $row->monto, 'faltante_caja');
            }
        }
        if (Schema::hasTable('nomina_deducciones')) {
            foreach ($this->filas(NominaDeduccion::class, $empleado->id, $periodo->id, 'nomina_periodo_id') as $row) {
                $lineas[] = $this->linea('Deducción', $row->motivo ?? null, (float) $row->monto, 'deduccion');
            }
        }
        if (Schema::hasTable('nomina_empleado_ajustes')) {
            foreach (NominaEmpleadoAjuste::query()
                ->where('empleado_id', $empleado->id)
                ->where('nomina_periodo_id', $periodo->id)
                ->where('destino', NominaEmpleadoAjuste::DESTINO_NOMINA)
                ->where('tipo', NominaEmpleadoAjuste::TIPO_DEDUCCION)
                ->get() as $row) {
                $lineas[] = $this->linea('Deducción', $row->motivo ?? null, (float) $row->monto, 'deduccion');
            }
        }
        foreach ($this->prestamosNomina($periodo, $empleado) as $linea) {
            $lineas[] = $linea;
        }

        return $this->normalizar($lineas);
    }

    /**
     * @return list<array{tipo: string, comentario: string, monto: float, grupo: string}>
     */
    public function lineasComisionDesdePeriodo(NominaPeriodo $periodo, NominaEmpleado $empleado): array
    {
        $lineas = [];

        if (Schema::hasTable('nomina_comision_descuentos')) {
            foreach (NominaComisionDescuento::query()
                ->where('empleado_id', $empleado->id)
                ->where('periodo_id', $periodo->id)
                ->where(function ($q) {
                    $q->where('tipo', '!=', 'FALTANTE')
                        ->orWhere(function ($f) {
                            $f->where('tipo', 'FALTANTE');
                            if (Schema::hasColumn('nomina_comision_descuentos', 'destino')) {
                                $f->where(function ($d) {
                                    $d->where('destino', NominaComisionDescuento::DESTINO_COMISION)
                                        ->orWhereNull('destino');
                                });
                            }
                        });
                })
                ->get() as $row) {
                $tipoRaw = strtoupper((string) $row->tipo);
                $tipo = match ($tipoRaw) {
                    'PRESTAMO' => 'Préstamo',
                    'FALTANTE' => 'Faltante de caja',
                    default => 'Descuento',
                };
                $grupo = $tipoRaw === 'PRESTAMO' ? 'prestamo' : 'descuento';
                $lineas[] = $this->linea($tipo, $row->motivo ?? null, (float) $row->monto, $grupo);
            }
        }

        if (Schema::hasTable('nomina_empleado_ajustes')) {
            foreach (NominaEmpleadoAjuste::query()
                ->where('empleado_id', $empleado->id)
                ->where('nomina_periodo_id', $periodo->id)
                ->where('destino', NominaEmpleadoAjuste::DESTINO_COMISION)
                ->where('tipo', NominaEmpleadoAjuste::TIPO_DEDUCCION)
                ->get() as $row) {
                $lineas[] = $this->linea('Deducción', $row->motivo ?? null, (float) $row->monto, 'descuento');
            }
        }

        if (Schema::hasTable('nomina_descuentos_mercancia')) {
            foreach (NominaDescuentoMercancia::query()
                ->where('empleado_id', $empleado->id)
                ->where('nomina_periodo_id', $periodo->id)
                ->where('destino', NominaDescuentoMercancia::DESTINO_COMISION)
                ->orderBy('id')
                ->get() as $row) {
                $lineas[] = $this->linea('Mercancía', $row->motivo ?? null, (float) $row->monto, 'descuento');
            }
        }

        return $this->normalizar($lineas);
    }

    /**
     * @param  class-string  $modelo
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    private function filas(string $modelo, int $empleadoId, int $periodoId, string $periodoCol)
    {
        if (! Schema::hasTable((new $modelo)->getTable())) {
            return collect();
        }

        return $modelo::query()
            ->where('empleado_id', $empleadoId)
            ->where($periodoCol, $periodoId)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<array{tipo: string, comentario: string, monto: float, grupo: string}>
     */
    private function prestamosNomina(NominaPeriodo $periodo, NominaEmpleado $empleado): array
    {
        $lineas = [];
        if (Schema::hasTable('nomina_prestamo_cuotas')) {
            $cuotas = NominaPrestamoCuota::query()
                ->where('nomina_periodo_id', $periodo->id)
                ->whereHas('prestamo', fn ($q) => $q->where('empleado_id', $empleado->id))
                ->with(['prestamo', 'abono'])
                ->get();
            foreach ($cuotas as $cuota) {
                $monto = (float) ($cuota->abono?->monto ?? $cuota->monto_pagado ?? 0);
                if ($monto <= 0) {
                    continue;
                }
                $comentario = $cuota->abono?->observacion
                    ?: $cuota->prestamo?->motivo
                    ?: 'Cuota de préstamo';
                $lineas[] = $this->linea('Préstamo', $comentario, $monto, 'prestamo');
            }
        }

        if (Schema::hasTable('nomina_prestamo_abonos')) {
            $abonos = NominaPrestamoAbono::query()
                ->where('tipo', NominaPrestamoAbono::TIPO_NOMINA)
                ->whereDate('fecha', '>=', $periodo->fecha_inicio->toDateString())
                ->whereDate('fecha', '<=', $periodo->fecha_fin->toDateString())
                ->whereHas('prestamo', fn ($q) => $q->where('empleado_id', $empleado->id))
                ->with('prestamo')
                ->get();
            foreach ($abonos as $abono) {
                if ($abono->cuota_id) {
                    continue;
                }
                $lineas[] = $this->linea(
                    'Préstamo',
                    $abono->observacion ?: $abono->prestamo?->motivo,
                    (float) $abono->monto,
                    'prestamo'
                );
            }
        }

        return $lineas;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array{tipo: string, comentario: string, monto: float, grupo: string}>
     */
    private function normalizar(array $lineas): array
    {
        $out = [];
        foreach ($lineas as $linea) {
            $monto = round((float) ($linea['monto'] ?? 0), 2);
            if ($monto == 0.0) {
                continue;
            }
            $out[] = $this->linea(
                (string) ($linea['tipo'] ?? 'Descuento'),
                $linea['comentario'] ?? null,
                $monto,
                (string) ($linea['grupo'] ?? 'descuento')
            );
        }

        return $out;
    }

    /**
     * @return array{tipo: string, comentario: string, monto: float, grupo: string}
     */
    private function linea(string $tipo, ?string $comentario, float $monto, string $grupo): array
    {
        $comentario = trim((string) $comentario);

        return [
            'tipo' => $tipo,
            'comentario' => $comentario !== '' ? $comentario : 'Sin comentario',
            'monto' => round($monto, 2),
            'grupo' => $grupo,
        ];
    }
}
