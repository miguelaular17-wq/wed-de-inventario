<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaDescuentoMercancia;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaPeriodo;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MerchandiseDeductionService
{
    public function __construct(private SalaryAdvanceService $quincenas)
    {
    }

    public function disponible(): bool
    {
        return Schema::hasTable('nomina_descuentos_mercancia');
    }

    public function create(NominaEmpleado $empleado, array $data, ?int $usuarioId = null): NominaDescuentoMercancia
    {
        $monto = round((float) $data['monto'], 2);
        if ($monto <= 0) {
            throw ValidationException::withMessages([
                'monto' => 'El monto de mercancía debe ser mayor a cero.',
            ]);
        }

        $motivo = trim((string) ($data['motivo'] ?? ''));
        if ($motivo === '') {
            throw ValidationException::withMessages([
                'motivo' => 'El motivo de mercancía es obligatorio.',
            ]);
        }

        $destino = ($data['destino'] ?? '') === NominaDescuentoMercancia::DESTINO_COMISION
            ? NominaDescuentoMercancia::DESTINO_COMISION
            : NominaDescuentoMercancia::DESTINO_NOMINA;
        if ($destino === NominaDescuentoMercancia::DESTINO_COMISION && ! $empleado->generaComision()) {
            $destino = NominaDescuentoMercancia::DESTINO_NOMINA;
        }

        $fecha = Carbon::parse($data['fecha'] ?? now())->startOfDay();
        $quincena = $this->quincenas->quincenaDe($fecha);

        return DB::transaction(function () use ($empleado, $motivo, $monto, $destino, $fecha, $quincena, $usuarioId) {
            $payload = [
                'empleado_id' => $empleado->id,
                'fecha' => $fecha->toDateString(),
                'monto' => $monto,
                'quincena_inicio' => $quincena['inicio']->toDateString(),
                'quincena_fin' => $quincena['fin']->toDateString(),
                'etiqueta' => $quincena['etiqueta'],
                'estado' => 'PENDIENTE',
                'motivo' => $motivo,
                'created_by' => $usuarioId,
            ];
            if (Schema::hasColumn('nomina_descuentos_mercancia', 'destino')) {
                $payload['destino'] = $destino;
            }

            $row = NominaDescuentoMercancia::create($payload);

            NominaAuditLog::registrar('MERCANCIA_CREAR', 'mercancia', $row->id, null, [
                'empleado_id' => $empleado->id,
                'monto' => $monto,
                'destino' => $destino,
                'quincena' => $quincena['etiqueta'],
            ]);

            return $row;
        });
    }

    public function cancelar(NominaDescuentoMercancia $descuento, ?string $motivo = null): NominaDescuentoMercancia
    {
        if ($descuento->estado === 'DESCONTADO') {
            throw ValidationException::withMessages([
                'estado' => 'No se puede cancelar un descuento de mercancía ya aplicado.',
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

    public function delDia(Carbon|string $fecha): Collection
    {
        if (! $this->disponible()) {
            return collect();
        }

        return NominaDescuentoMercancia::query()
            ->with(['empleado.cliente', 'empleado.sedeCatalogo', 'creador'])
            ->whereDate('fecha', Carbon::parse($fecha)->toDateString())
            ->where('estado', '!=', 'CANCELADO')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array{pendiente:float,esta_quincena:float,del_dia:float,personas_hoy:int,cantidad:int}
     */
    public function kpis(?Carbon $enFecha = null): array
    {
        if (! $this->disponible()) {
            return [
                'pendiente' => 0.0,
                'esta_quincena' => 0.0,
                'del_dia' => 0.0,
                'personas_hoy' => 0,
                'cantidad' => 0,
            ];
        }

        $fecha = $enFecha ?? now();
        $quincena = $this->quincenas->quincenaDe($fecha);
        $delDia = $this->delDia($fecha);
        $vivos = fn () => NominaDescuentoMercancia::query()->whereIn('estado', ['PENDIENTE', 'DESCONTADO']);

        return [
            'pendiente' => round((float) $vivos()->where('estado', 'PENDIENTE')->sum('monto'), 2),
            'esta_quincena' => round((float) $vivos()
                ->whereDate('quincena_inicio', $quincena['inicio']->toDateString())
                ->whereDate('quincena_fin', $quincena['fin']->toDateString())
                ->sum('monto'), 2),
            'del_dia' => round((float) $delDia->sum('monto'), 2),
            'personas_hoy' => $delDia->pluck('empleado_id')->unique()->count(),
            'cantidad' => (int) $vivos()
                ->whereDate('quincena_inicio', $quincena['inicio']->toDateString())
                ->whereDate('quincena_fin', $quincena['fin']->toDateString())
                ->count(),
        ];
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

    public function pendientesDe(NominaEmpleado $empleado, ?Carbon $enFecha = null, ?string $destino = null): float
    {
        $query = $empleado->descuentosMercancia()->where('estado', 'PENDIENTE');

        if ($destino) {
            $query->where('destino', $destino);
        }

        if ($enFecha) {
            $q = $this->quincenas->quincenaDe($enFecha);
            $query->whereDate('quincena_inicio', $q['inicio']->toDateString())
                ->whereDate('quincena_fin', $q['fin']->toDateString());
        }

        return round((float) $query->sum('monto'), 2);
    }

    public function aplicarAPeriodo(int $periodoId, Carbon $inicio, Carbon $fin): int
    {
        if (! $this->disponible()) {
            return 0;
        }

        return (int) DB::transaction(function () use ($periodoId, $inicio, $fin) {
            $items = NominaDescuentoMercancia::query()
                ->where('estado', 'PENDIENTE')
                ->where(function ($q) {
                    $q->where('destino', NominaDescuentoMercancia::DESTINO_NOMINA)
                        ->orWhereNull('destino');
                })
                ->whereNull('nomina_periodo_id')
                ->whereDate('quincena_inicio', '>=', $inicio->toDateString())
                ->whereDate('quincena_fin', '<=', $fin->toDateString())
                ->lockForUpdate()
                ->get();

            foreach ($items as $item) {
                $item->estado = 'DESCONTADO';
                $item->nomina_periodo_id = $periodoId;
                if (! $item->destino) {
                    $item->destino = NominaDescuentoMercancia::DESTINO_NOMINA;
                }
                $item->save();
            }

            return $items->count();
        });
    }

    public function aplicarComision(NominaPeriodo $periodo, NominaEmpleado $empleado): float
    {
        if (! $this->disponible()) {
            return 0.0;
        }

        $items = NominaDescuentoMercancia::query()
            ->where('empleado_id', $empleado->id)
            ->where('destino', NominaDescuentoMercancia::DESTINO_COMISION)
            ->where('estado', 'PENDIENTE')
            ->whereNull('nomina_periodo_id')
            ->whereDate('quincena_inicio', $periodo->fecha_inicio->toDateString())
            ->whereDate('quincena_fin', $periodo->fecha_fin->toDateString())
            ->get();

        foreach ($items as $item) {
            $item->update([
                'estado' => 'DESCONTADO',
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

        return NominaDescuentoMercancia::query()
            ->where('nomina_periodo_id', $periodoId)
            ->where('destino', NominaDescuentoMercancia::DESTINO_COMISION)
            ->where('estado', 'DESCONTADO')
            ->update([
                'estado' => 'PENDIENTE',
                'nomina_periodo_id' => null,
            ]);
    }

    public function deshacerPeriodo(int $periodoId): int
    {
        if (! $this->disponible()) {
            return 0;
        }

        return NominaDescuentoMercancia::query()
            ->where('nomina_periodo_id', $periodoId)
            ->where('estado', 'DESCONTADO')
            ->update([
                'estado' => 'PENDIENTE',
                'nomina_periodo_id' => null,
            ]);
    }
}
