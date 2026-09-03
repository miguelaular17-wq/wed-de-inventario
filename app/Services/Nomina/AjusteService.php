<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaEmpleadoAjuste;
use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaPeriodo;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AjusteService
{
    public function __construct(private SalaryAdvanceService $quincenas)
    {
    }

    public function disponible(): bool
    {
        return Schema::hasTable('nomina_empleado_ajustes');
    }

    public function create(NominaEmpleado $empleado, array $data, ?int $usuarioId = null): NominaEmpleadoAjuste
    {
        if (! $this->disponible()) {
            throw ValidationException::withMessages([
                'ajustes' => 'Falta migrar la tabla de ajustes de nómina.',
            ]);
        }

        $monto = round((float) $data['monto'], 2);
        if ($monto <= 0) {
            throw ValidationException::withMessages([
                'monto' => 'El monto debe ser mayor a cero.',
            ]);
        }

        $motivo = trim((string) ($data['motivo'] ?? ''));
        if ($motivo === '') {
            throw ValidationException::withMessages([
                'motivo' => 'Indica el motivo.',
            ]);
        }

        $tipo = ($data['tipo'] ?? '') === NominaEmpleadoAjuste::TIPO_BONIFICACION
            ? NominaEmpleadoAjuste::TIPO_BONIFICACION
            : NominaEmpleadoAjuste::TIPO_DEDUCCION;
        $destino = ($data['destino'] ?? '') === NominaEmpleadoAjuste::DESTINO_COMISION
            ? NominaEmpleadoAjuste::DESTINO_COMISION
            : NominaEmpleadoAjuste::DESTINO_NOMINA;
        if ($destino === NominaEmpleadoAjuste::DESTINO_COMISION && ! $empleado->generaComision()) {
            $destino = NominaEmpleadoAjuste::DESTINO_NOMINA;
        }

        $fecha = Carbon::parse($data['fecha'] ?? now())->startOfDay();
        $quincena = $this->quincenas->quincenaDe($fecha);

        return DB::transaction(function () use ($empleado, $motivo, $monto, $tipo, $destino, $fecha, $quincena, $usuarioId) {
            $row = NominaEmpleadoAjuste::create([
                'empleado_id' => $empleado->id,
                'fecha' => $fecha->toDateString(),
                'tipo' => $tipo,
                'destino' => $destino,
                'monto' => $monto,
                'quincena_inicio' => $quincena['inicio']->toDateString(),
                'quincena_fin' => $quincena['fin']->toDateString(),
                'etiqueta' => $quincena['etiqueta'],
                'estado' => NominaEmpleadoAjuste::PENDIENTE,
                'motivo' => $motivo,
                'created_by' => $usuarioId,
            ]);

            NominaAuditLog::registrar('AJUSTE_CREAR', 'ajuste', $row->id, null, [
                'empleado_id' => $empleado->id,
                'tipo' => $tipo,
                'destino' => $destino,
                'monto' => $monto,
                'motivo' => $motivo,
                'quincena' => $quincena['etiqueta'],
            ]);

            return $row;
        });
    }

    public function cancelar(NominaEmpleadoAjuste $ajuste, ?string $motivo = null): NominaEmpleadoAjuste
    {
        if ($ajuste->estado === NominaEmpleadoAjuste::APLICADO) {
            throw ValidationException::withMessages([
                'estado' => 'No se puede cancelar un ajuste ya aplicado en la quincena.',
            ]);
        }

        if ($ajuste->estado === NominaEmpleadoAjuste::CANCELADO) {
            return $ajuste;
        }

        $anterior = $ajuste->estado;
        $ajuste->estado = NominaEmpleadoAjuste::CANCELADO;
        if ($motivo) {
            $ajuste->motivo = trim($ajuste->motivo.' | Cancelado: '.$motivo);
        }
        $ajuste->save();

        NominaAuditLog::registrar('AJUSTE_CANCELAR', 'ajuste', $ajuste->id, [
            'estado' => $anterior,
        ], [
            'estado' => NominaEmpleadoAjuste::CANCELADO,
        ]);

        return $ajuste;
    }

    public function delDia(Carbon|string $fecha): Collection
    {
        if (! $this->disponible()) {
            return collect();
        }

        return NominaEmpleadoAjuste::query()
            ->with(['empleado.cliente', 'empleado.sedeCatalogo', 'creador'])
            ->whereDate('fecha', Carbon::parse($fecha)->toDateString())
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array{deducciones:float,bonos:float,pendiente:float,esta_quincena:float,cantidad:int}
     */
    public function kpis(?Carbon $enFecha = null): array
    {
        if (! $this->disponible()) {
            return [
                'deducciones' => 0.0,
                'bonos' => 0.0,
                'pendiente' => 0.0,
                'esta_quincena' => 0.0,
                'cantidad' => 0,
            ];
        }

        $vivos = fn () => NominaEmpleadoAjuste::query()
            ->whereIn('estado', [NominaEmpleadoAjuste::PENDIENTE, NominaEmpleadoAjuste::APLICADO]);
        $quincena = $this->quincenas->quincenaDe($enFecha ?? now());
        $deQuincena = fn () => $vivos()
            ->whereDate('quincena_inicio', $quincena['inicio']->toDateString())
            ->whereDate('quincena_fin', $quincena['fin']->toDateString());

        return [
            'deducciones' => round((float) $deQuincena()->where('tipo', NominaEmpleadoAjuste::TIPO_DEDUCCION)->sum('monto'), 2),
            'bonos' => round((float) $deQuincena()->where('tipo', NominaEmpleadoAjuste::TIPO_BONIFICACION)->sum('monto'), 2),
            'pendiente' => round((float) $vivos()->where('estado', NominaEmpleadoAjuste::PENDIENTE)->sum('monto'), 2),
            'esta_quincena' => round((float) $deQuincena()->sum('monto'), 2),
            'cantidad' => (int) $deQuincena()->count(),
        ];
    }

    /**
     * @return array{pendiente_nomina:float,pendiente_comision:float,bonos:float,deducciones:float,cantidad:int}
     */
    public function resumenEmpleado(NominaEmpleado $empleado, array $quincena): array
    {
        if (! $this->disponible()) {
            return [
                'pendiente_nomina' => 0.0,
                'pendiente_comision' => 0.0,
                'bonos' => 0.0,
                'deducciones' => 0.0,
                'cantidad' => 0,
            ];
        }

        $vivos = $empleado->ajustes->whereIn('estado', [NominaEmpleadoAjuste::PENDIENTE, NominaEmpleadoAjuste::APLICADO]);
        $esta = $vivos->filter(function (NominaEmpleadoAjuste $a) use ($quincena) {
            return $a->quincena_inicio?->toDateString() === $quincena['inicio']->toDateString()
                && $a->quincena_fin?->toDateString() === $quincena['fin']->toDateString();
        });

        $pendientes = $esta->where('estado', NominaEmpleadoAjuste::PENDIENTE);

        return [
            'pendiente_nomina' => round((float) $pendientes->where('destino', NominaEmpleadoAjuste::DESTINO_NOMINA)->sum('monto'), 2),
            'pendiente_comision' => round((float) $pendientes->where('destino', NominaEmpleadoAjuste::DESTINO_COMISION)->sum('monto'), 2),
            'bonos' => round((float) $esta->where('tipo', NominaEmpleadoAjuste::TIPO_BONIFICACION)->sum('monto'), 2),
            'deducciones' => round((float) $esta->where('tipo', NominaEmpleadoAjuste::TIPO_DEDUCCION)->sum('monto'), 2),
            'cantidad' => $esta->count(),
        ];
    }

    public function aplicarNominaAPeriodo(int $periodoId, Carbon $inicio, Carbon $fin): int
    {
        return $this->marcarPeriodo($periodoId, $inicio, $fin, NominaEmpleadoAjuste::DESTINO_NOMINA);
    }

    public function aplicarComision(NominaPeriodo $periodo, NominaEmpleado $empleado, string $tipo): float
    {
        if (! $this->disponible()) {
            return 0.0;
        }

        $items = NominaEmpleadoAjuste::query()
            ->where('empleado_id', $empleado->id)
            ->where('destino', NominaEmpleadoAjuste::DESTINO_COMISION)
            ->where('tipo', $tipo)
            ->where('estado', NominaEmpleadoAjuste::PENDIENTE)
            ->whereNull('nomina_periodo_id')
            ->whereDate('quincena_inicio', $periodo->fecha_inicio->toDateString())
            ->whereDate('quincena_fin', $periodo->fecha_fin->toDateString())
            ->get();

        foreach ($items as $item) {
            $item->update([
                'estado' => NominaEmpleadoAjuste::APLICADO,
                'nomina_periodo_id' => $periodo->id,
            ]);
        }

        return round((float) $items->sum('monto'), 2);
    }

    public function deshacerComisionPeriodo(int $periodoId): int
    {
        if (! $this->disponible()) {
            return 0;
        }

        return NominaEmpleadoAjuste::query()
            ->where('nomina_periodo_id', $periodoId)
            ->where('destino', NominaEmpleadoAjuste::DESTINO_COMISION)
            ->where('estado', NominaEmpleadoAjuste::APLICADO)
            ->update([
                'estado' => NominaEmpleadoAjuste::PENDIENTE,
                'nomina_periodo_id' => null,
            ]);
    }

    public function deshacerPeriodo(int $periodoId): int
    {
        if (! $this->disponible()) {
            return 0;
        }

        return NominaEmpleadoAjuste::query()
            ->where('nomina_periodo_id', $periodoId)
            ->where('estado', NominaEmpleadoAjuste::APLICADO)
            ->update([
                'estado' => NominaEmpleadoAjuste::PENDIENTE,
                'nomina_periodo_id' => null,
            ]);
    }

    public function totalAplicado(NominaPeriodo $periodo, NominaEmpleado $empleado, string $destino, string $tipo): float
    {
        if (! $this->disponible()) {
            return 0.0;
        }

        return round((float) NominaEmpleadoAjuste::query()
            ->where('empleado_id', $empleado->id)
            ->where('nomina_periodo_id', $periodo->id)
            ->where('destino', $destino)
            ->where('tipo', $tipo)
            ->sum('monto'), 2);
    }

    private function marcarPeriodo(int $periodoId, Carbon $inicio, Carbon $fin, string $destino): int
    {
        if (! $this->disponible()) {
            return 0;
        }

        return (int) DB::transaction(function () use ($periodoId, $inicio, $fin, $destino) {
            $items = NominaEmpleadoAjuste::query()
                ->where('estado', NominaEmpleadoAjuste::PENDIENTE)
                ->where('destino', $destino)
                ->whereNull('nomina_periodo_id')
                ->whereDate('quincena_inicio', '>=', $inicio->toDateString())
                ->whereDate('quincena_fin', '<=', $fin->toDateString())
                ->lockForUpdate()
                ->get();

            foreach ($items as $item) {
                $item->estado = NominaEmpleadoAjuste::APLICADO;
                $item->nomina_periodo_id = $periodoId;
                $item->save();
            }

            return $items->count();
        });
    }
}
