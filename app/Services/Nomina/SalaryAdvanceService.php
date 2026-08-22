<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaAbonoSueldo;
use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaEmpleado;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryAdvanceService
{
    /**
     * @return array{inicio:Carbon,fin:Carbon,etiqueta:string}
     */
    public function quincenaDe(Carbon|string $fecha): array
    {
        $dia = Carbon::parse($fecha)->startOfDay();
        $inicio = $dia->copy()->startOfMonth();
        $fin = $dia->copy()->startOfMonth()->addDays(14);

        if ($dia->day > 15) {
            $inicio = $dia->copy()->startOfMonth()->addDays(15);
            $fin = $dia->copy()->endOfMonth()->startOfDay();
        }

        return [
            'inicio' => $inicio,
            'fin' => $fin,
            'etiqueta' => $inicio->format('d/m/Y').' al '.$fin->format('d/m/Y'),
        ];
    }

    public function create(NominaEmpleado $empleado, array $data, ?int $usuarioId = null): NominaAbonoSueldo
    {
        $monto = round((float) $data['monto'], 2);
        if ($monto <= 0) {
            throw ValidationException::withMessages([
                'monto' => 'El monto del abono debe ser mayor a cero.',
            ]);
        }

        $fecha = Carbon::parse($data['fecha'] ?? now())->startOfDay();
        $quincena = $this->quincenaDe($fecha);

        return DB::transaction(function () use ($empleado, $data, $monto, $fecha, $quincena, $usuarioId) {
            $abono = NominaAbonoSueldo::create([
                'empleado_id' => $empleado->id,
                'fecha' => $fecha->toDateString(),
                'monto' => $monto,
                'quincena_inicio' => $quincena['inicio']->toDateString(),
                'quincena_fin' => $quincena['fin']->toDateString(),
                'etiqueta' => $quincena['etiqueta'],
                'estado' => 'PENDIENTE',
                'motivo' => $data['motivo'] ?? null,
                'created_by' => $usuarioId,
            ]);

            NominaAuditLog::registrar('ABONO_SUELDO_CREAR', 'abono_sueldo', $abono->id, null, [
                'empleado_id' => $empleado->id,
                'monto' => $monto,
                'quincena' => $quincena['etiqueta'],
            ]);

            return $abono;
        });
    }

    public function cancelar(NominaAbonoSueldo $abono, ?string $motivo = null): NominaAbonoSueldo
    {
        if ($abono->estado === 'DESCONTADO') {
            throw ValidationException::withMessages([
                'estado' => 'No se puede cancelar un abono ya descontado en nómina.',
            ]);
        }

        if ($abono->estado === 'CANCELADO') {
            return $abono;
        }

        $anterior = $abono->estado;
        $abono->estado = 'CANCELADO';
        if ($motivo) {
            $abono->motivo = trim(($abono->motivo ? $abono->motivo.' | ' : '').'Cancelado: '.$motivo);
        }
        $abono->save();

        NominaAuditLog::registrar('ABONO_SUELDO_CANCELAR', 'abono_sueldo', $abono->id, [
            'estado' => $anterior,
        ], [
            'estado' => 'CANCELADO',
        ]);

        return $abono;
    }

    public function pendientesDe(NominaEmpleado $empleado, ?Carbon $enFecha = null): float
    {
        $query = $empleado->abonosSueldo()->where('estado', 'PENDIENTE');

        if ($enFecha) {
            $q = $this->quincenaDe($enFecha);
            $query->whereDate('quincena_inicio', $q['inicio']->toDateString())
                ->whereDate('quincena_fin', $q['fin']->toDateString());
        }

        return round((float) $query->sum('monto'), 2);
    }

    /**
     * Marca abonos pendientes del rango como descontados. No vuelve a descontar el mismo.
     */
    public function aplicarAPeriodo(int $periodoId, Carbon $inicio, Carbon $fin): int
    {
        return (int) DB::transaction(function () use ($periodoId, $inicio, $fin) {
            $abonos = NominaAbonoSueldo::query()
                ->where('estado', 'PENDIENTE')
                ->whereNull('nomina_periodo_id')
                ->whereDate('quincena_inicio', '>=', $inicio->toDateString())
                ->whereDate('quincena_fin', '<=', $fin->toDateString())
                ->lockForUpdate()
                ->get();

            foreach ($abonos as $abono) {
                $abono->estado = 'DESCONTADO';
                $abono->nomina_periodo_id = $periodoId;
                $abono->save();
            }

            return $abonos->count();
        });
    }
}
