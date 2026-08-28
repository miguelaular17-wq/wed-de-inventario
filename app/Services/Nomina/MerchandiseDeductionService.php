<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaDescuentoMercancia;
use App\Models\Nomina\NominaEmpleado;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MerchandiseDeductionService
{
    public function __construct(private SalaryAdvanceService $quincenas)
    {
    }

    public function create(NominaEmpleado $empleado, array $data, ?int $usuarioId = null): NominaDescuentoMercancia
    {
        $monto = round((float) $data['monto'], 2);
        if ($monto <= 0) {
            throw ValidationException::withMessages([
                'monto' => 'El monto de mercancía debe ser mayor a cero.',
            ]);
        }

        $fecha = Carbon::parse($data['fecha'] ?? now())->startOfDay();
        $quincena = $this->quincenas->quincenaDe($fecha);

        return DB::transaction(function () use ($empleado, $data, $monto, $fecha, $quincena, $usuarioId) {
            $row = NominaDescuentoMercancia::create([
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

            NominaAuditLog::registrar('MERCANCIA_CREAR', 'mercancia', $row->id, null, [
                'empleado_id' => $empleado->id,
                'monto' => $monto,
                'quincena' => $quincena['etiqueta'],
            ]);

            return $row;
        });
    }

    public function cancelar(NominaDescuentoMercancia $descuento, ?string $motivo = null): NominaDescuentoMercancia
    {
        if ($descuento->estado === 'DESCONTADO') {
            throw ValidationException::withMessages([
                'estado' => 'No se puede cancelar un descuento de mercancía ya aplicado en nómina.',
            ]);
        }

        if ($descuento->estado === 'CANCELADO') {
            return $descuento;
        }

        $anterior = $descuento->estado;
        $descuento->estado = 'CANCELADO';
        if ($motivo) {
            $descuento->motivo = trim(($descuento->motivo ? $descuento->motivo.' | ' : '').'Cancelado: '.$motivo);
        }
        $descuento->save();

        NominaAuditLog::registrar('MERCANCIA_CANCELAR', 'mercancia', $descuento->id, [
            'estado' => $anterior,
        ], [
            'estado' => 'CANCELADO',
        ]);

        return $descuento;
    }

    /**
     * @return array{pendiente:float,descontado:float,acumulado:float,cantidad:int}
     */
    public function resumenEmpleado(NominaEmpleado $empleado): array
    {
        $items = $empleado->descuentosMercancia->whereIn('estado', ['PENDIENTE', 'DESCONTADO']);

        return [
            'pendiente' => round((float) $items->where('estado', 'PENDIENTE')->sum('monto'), 2),
            'descontado' => round((float) $items->where('estado', 'DESCONTADO')->sum('monto'), 2),
            'acumulado' => round((float) $items->sum('monto'), 2),
            'cantidad' => $items->count(),
        ];
    }

    public function pendientesDe(NominaEmpleado $empleado, ?Carbon $enFecha = null): float
    {
        $query = $empleado->descuentosMercancia()->where('estado', 'PENDIENTE');

        if ($enFecha) {
            $q = $this->quincenas->quincenaDe($enFecha);
            $query->whereDate('quincena_inicio', $q['inicio']->toDateString())
                ->whereDate('quincena_fin', $q['fin']->toDateString());
        }

        return round((float) $query->sum('monto'), 2);
    }

    public function aplicarAPeriodo(int $periodoId, Carbon $inicio, Carbon $fin): int
    {
        return (int) DB::transaction(function () use ($periodoId, $inicio, $fin) {
            $items = NominaDescuentoMercancia::query()
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
        return NominaDescuentoMercancia::query()
            ->where('nomina_periodo_id', $periodoId)
            ->where('estado', 'DESCONTADO')
            ->update([
                'estado' => 'PENDIENTE',
                'nomina_periodo_id' => null,
            ]);
    }
}
