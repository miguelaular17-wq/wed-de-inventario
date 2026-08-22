<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaPrestamo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanService
{
    public function create(NominaEmpleado $empleado, array $data, ?int $usuarioId = null): NominaPrestamo
    {
        $monto = round((float) $data['monto_original'], 2);
        $cuotas = (int) $data['numero_cuotas'];
        $frecuencia = $data['frecuencia'];
        $inicio = Carbon::parse($data['fecha_inicio'])->startOfDay();

        if ($monto <= 0 || $cuotas < 1) {
            throw ValidationException::withMessages([
                'monto_original' => 'El monto y el número de cuotas deben ser mayores a cero.',
            ]);
        }

        $valorCuota = round($monto / $cuotas, 2);
        $calendario = $this->generarCalendario($monto, $cuotas, $valorCuota, $frecuencia, $inicio);

        return DB::transaction(function () use ($empleado, $data, $monto, $cuotas, $valorCuota, $frecuencia, $inicio, $calendario, $usuarioId) {
            $prestamo = NominaPrestamo::create([
                'empleado_id' => $empleado->id,
                'fecha' => $data['fecha'] ?? now()->toDateString(),
                'monto_original' => $monto,
                'numero_cuotas' => $cuotas,
                'valor_cuota' => $valorCuota,
                'frecuencia' => $frecuencia,
                'fecha_inicio' => $inicio->toDateString(),
                'fecha_fin_estimada' => $calendario[count($calendario) - 1]['fecha_programada'],
                'saldo_pendiente' => $monto,
                'estado' => $inicio->lte(now()->startOfDay()) ? 'ACTIVO' : 'PENDIENTE',
                'motivo' => $data['motivo'] ?? null,
                'created_by' => $usuarioId,
            ]);

            foreach ($calendario as $fila) {
                $prestamo->cuotas()->create($fila);
            }

            NominaAuditLog::registrar('PRESTAMO_CREAR', 'prestamo', $prestamo->id, null, [
                'empleado_id' => $empleado->id,
                'monto' => $monto,
                'cuotas' => $cuotas,
                'frecuencia' => $frecuencia,
            ]);

            return $prestamo->fresh(['cuotas', 'abonos']);
        });
    }

    public function cancelar(NominaPrestamo $prestamo, ?string $observacion = null): NominaPrestamo
    {
        if ($prestamo->estado === 'PAGADO') {
            throw ValidationException::withMessages([
                'estado' => 'No se puede cancelar un préstamo ya pagado.',
            ]);
        }

        $anterior = $prestamo->estado;
        $prestamo->estado = 'CANCELADO';
        $prestamo->save();

        NominaAuditLog::registrar('PRESTAMO_CANCELAR', 'prestamo', $prestamo->id, [
            'estado' => $anterior,
        ], [
            'estado' => 'CANCELADO',
            'observacion' => $observacion,
        ]);

        return $prestamo;
    }

    public function resumenEmpleado(NominaEmpleado $empleado): array
    {
        $prestamos = $empleado->prestamos()->with('cuotas')->get();
        $activos = $prestamos->whereIn('estado', ['PENDIENTE', 'ACTIVO']);

        return [
            'cantidad' => $activos->count(),
            'saldo' => round((float) $activos->sum('saldo_pendiente'), 2),
            'proxima_cuota' => $activos
                ->map(fn (NominaPrestamo $p) => $p->proximaCuota())
                ->filter()
                ->sortBy('fecha_programada')
                ->first(),
        ];
    }

    public function kpis(): array
    {
        $prestamos = NominaPrestamo::query()->with('cuotas')->get();
        $activos = $prestamos->whereIn('estado', ['PENDIENTE', 'ACTIVO']);
        $hoy = now()->startOfDay();

        return [
            'total_prestado' => round((float) $prestamos->sum('monto_original'), 2),
            'total_pendiente' => round((float) $activos->sum('saldo_pendiente'), 2),
            'activos' => $activos->count(),
            'vencidos' => $activos->filter(function (NominaPrestamo $p) use ($hoy) {
                return $p->cuotas->contains(function ($c) use ($hoy) {
                    return in_array($c->estado, ['PENDIENTE', 'VENCIDA', 'PARCIAL'], true)
                        && $c->fecha_programada->lt($hoy);
                });
            })->count(),
            'cobrado_mes' => round((float) \App\Models\Nomina\NominaPrestamoAbono::query()
                ->whereMonth('fecha', now()->month)
                ->whereYear('fecha', now()->year)
                ->sum('monto'), 2),
        ];
    }

    /**
     * @return list<array{numero:int,fecha_programada:string,monto:float,monto_pagado:float,estado:string}>
     */
    public function generarCalendario(float $monto, int $cuotas, float $valorCuota, string $frecuencia, Carbon $inicio): array
    {
        $filas = [];
        $acumulado = 0.0;
        $fecha = $inicio->copy();

        for ($n = 1; $n <= $cuotas; $n++) {
            $montoCuota = $n === $cuotas
                ? round($monto - $acumulado, 2)
                : $valorCuota;
            $acumulado = round($acumulado + $montoCuota, 2);

            $filas[] = [
                'numero' => $n,
                'fecha_programada' => $fecha->toDateString(),
                'monto' => $montoCuota,
                'monto_pagado' => 0,
                'estado' => 'PENDIENTE',
            ];

            $fecha = $this->siguienteFecha($fecha, $frecuencia);
        }

        return $filas;
    }

    private function siguienteFecha(Carbon $fecha, string $frecuencia): Carbon
    {
        return match ($frecuencia) {
            'SEMANAL' => $fecha->copy()->addWeek(),
            'MENSUAL' => $fecha->copy()->addMonth(),
            default => $fecha->copy()->addDays(15),
        };
    }
}
