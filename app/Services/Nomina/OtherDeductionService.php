<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaDeduccion;
use App\Models\Nomina\NominaEmpleado;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OtherDeductionService
{
    public function __construct(private SalaryAdvanceService $quincenas)
    {
    }

    public function create(NominaEmpleado $empleado, array $data, ?int $usuarioId = null): NominaDeduccion
    {
        $monto = round((float) $data['monto'], 2);
        if ($monto <= 0) {
            throw ValidationException::withMessages([
                'monto' => 'El monto del descuento debe ser mayor a cero.',
            ]);
        }

        $motivo = trim((string) ($data['motivo'] ?? ''));
        if ($motivo === '') {
            throw ValidationException::withMessages([
                'motivo' => 'Indica el motivo del descuento.',
            ]);
        }

        $fecha = Carbon::parse($data['fecha'] ?? now())->startOfDay();
        $quincena = $this->quincenas->quincenaDe($fecha);

        return DB::transaction(function () use ($empleado, $motivo, $monto, $fecha, $quincena, $usuarioId) {
            $row = NominaDeduccion::create([
                'empleado_id' => $empleado->id,
                'fecha' => $fecha->toDateString(),
                'monto' => $monto,
                'quincena_inicio' => $quincena['inicio']->toDateString(),
                'quincena_fin' => $quincena['fin']->toDateString(),
                'etiqueta' => $quincena['etiqueta'],
                'estado' => 'PENDIENTE',
                'motivo' => $motivo,
                'created_by' => $usuarioId,
            ]);

            NominaAuditLog::registrar('DEDUCCION_CREAR', 'deduccion', $row->id, null, [
                'empleado_id' => $empleado->id,
                'monto' => $monto,
                'motivo' => $motivo,
                'quincena' => $quincena['etiqueta'],
            ]);

            return $row;
        });
    }

    public function cancelar(NominaDeduccion $deduccion, ?string $motivo = null): NominaDeduccion
    {
        if ($deduccion->estado === 'DESCONTADO') {
            throw ValidationException::withMessages([
                'estado' => 'No se puede cancelar un descuento ya aplicado en nómina.',
            ]);
        }

        if ($deduccion->estado === 'CANCELADO') {
            return $deduccion;
        }

        $anterior = $deduccion->estado;
        $deduccion->estado = 'CANCELADO';
        if ($motivo) {
            $deduccion->motivo = trim($deduccion->motivo.' | Cancelado: '.$motivo);
        }
        $deduccion->save();

        NominaAuditLog::registrar('DEDUCCION_CANCELAR', 'deduccion', $deduccion->id, [
            'estado' => $anterior,
        ], [
            'estado' => 'CANCELADO',
        ]);

        return $deduccion;
    }

    /**
     * @return array{pendiente:float,descontado:float,acumulado:float,cantidad:int}
     */
    public function resumenEmpleado(NominaEmpleado $empleado): array
    {
        $items = $empleado->deducciones->whereIn('estado', ['PENDIENTE', 'DESCONTADO']);

        return [
            'pendiente' => round((float) $items->where('estado', 'PENDIENTE')->sum('monto'), 2),
            'descontado' => round((float) $items->where('estado', 'DESCONTADO')->sum('monto'), 2),
            'acumulado' => round((float) $items->sum('monto'), 2),
            'cantidad' => $items->count(),
        ];
    }

    public function pendientesDe(NominaEmpleado $empleado, ?Carbon $enFecha = null): float
    {
        if (! Schema::hasTable('nomina_deducciones')) {
            return 0.0;
        }

        $query = $empleado->deducciones()->where('estado', 'PENDIENTE');

        if ($enFecha) {
            $q = $this->quincenas->quincenaDe($enFecha);
            $query->whereDate('quincena_inicio', $q['inicio']->toDateString())
                ->whereDate('quincena_fin', $q['fin']->toDateString());
        }

        return round((float) $query->sum('monto'), 2);
    }

    public function aplicarAPeriodo(int $periodoId, Carbon $inicio, Carbon $fin): int
    {
        if (! Schema::hasTable('nomina_deducciones')) {
            return 0;
        }

        return (int) DB::transaction(function () use ($periodoId, $inicio, $fin) {
            $items = NominaDeduccion::query()
                ->where('estado', 'PENDIENTE')
                ->whereNull('nomina_periodo_id')
                ->whereDate('quincena_inicio', '>=', $inicio->toDateString())
                ->whereDate('quincena_fin', '<=', $fin->toDateString())
                ->lockForUpdate()
                ->get();

            foreach ($items as $item) {
                $item->estado = 'DESCONTADO';
                $item->nomina_periodo_id = $periodoId;
                $item->save();
            }

            return $items->count();
        });
    }

    public function deshacerPeriodo(int $periodoId): int
    {
        if (! Schema::hasTable('nomina_deducciones')) {
            return 0;
        }

        return NominaDeduccion::query()
            ->where('nomina_periodo_id', $periodoId)
            ->where('estado', 'DESCONTADO')
            ->update([
                'estado' => 'PENDIENTE',
                'nomina_periodo_id' => null,
            ]);
    }
}
