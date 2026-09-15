<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaComisionDescuento;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaPeriodo;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class FaltanteCajaService
{
    public function disponible(): bool
    {
        return Schema::hasTable('nomina_comision_descuentos');
    }

    /**
     * Cajeras/cajeros activos (cargo "Cajero"/"Cajera" o texto legacy con "cajer").
     */
    public function cajeras(?string $q = null): Collection
    {
        $query = NominaEmpleado::query()
            ->activos()
            ->with(['cliente', 'sedeCatalogo', 'empresa', 'cargoCatalogo'])
            ->where(function ($builder) {
                $builder
                    ->whereHas('cargoCatalogo', function ($cargo) {
                        $cargo->whereRaw('LOWER(nombre) LIKE ?', ['%cajer%']);
                    })
                    ->orWhereRaw('LOWER(COALESCE(cargo, \'\')) LIKE ?', ['%cajer%']);
            })
            ->join('clientes', 'clientes.id', '=', 'nomina_empleados.cliente_id')
            ->select('nomina_empleados.*')
            ->orderBy('clientes.nombre');

        if ($q !== null && trim($q) !== '') {
            $query->buscar(trim($q));
        }

        return $query->get();
    }

    public function create(NominaEmpleado $empleado, array $data, ?int $usuarioId = null): NominaComisionDescuento
    {
        if (! $this->disponible()) {
            throw ValidationException::withMessages([
                'faltante' => 'Falta migrar la tabla de descuentos de comisión.',
            ]);
        }

        if (! $this->esCajera($empleado)) {
            throw ValidationException::withMessages([
                'empleado_id' => 'Solo se puede cargar faltante de caja a cajeros/cajeras.',
            ]);
        }

        $monto = round((float) ($data['monto'] ?? 0), 2);
        if ($monto <= 0) {
            throw ValidationException::withMessages([
                'monto' => 'El monto debe ser mayor a cero.',
            ]);
        }

        $fecha = Carbon::parse($data['fecha'] ?? now())->startOfDay();
        $motivo = trim((string) ($data['motivo'] ?? ''));
        if ($motivo === '') {
            $motivo = 'Faltante de caja';
        } elseif (! str_contains(mb_strtolower($motivo), 'faltante')) {
            $motivo = 'Faltante de caja: '.$motivo;
        }

        $row = NominaComisionDescuento::create([
            'empleado_id' => $empleado->id,
            'fecha' => $fecha->toDateString(),
            'tipo' => 'FALTANTE',
            'monto' => $monto,
            'motivo' => $motivo,
            'estado' => 'PENDIENTE',
            'created_by' => $usuarioId,
        ]);

        NominaAuditLog::registrar('FALTANTE_CAJA_CREAR', 'faltante_caja', $row->id, null, [
            'empleado_id' => $empleado->id,
            'monto' => $monto,
            'motivo' => $motivo,
            'fecha' => $fecha->toDateString(),
        ]);

        return $row;
    }

    public function cancelar(NominaComisionDescuento $descuento, ?string $motivo = null): NominaComisionDescuento
    {
        if ($descuento->tipo !== 'FALTANTE') {
            throw ValidationException::withMessages([
                'tipo' => 'Solo se pueden cancelar faltantes de caja.',
            ]);
        }

        if ($descuento->estado === 'APLICADO') {
            throw ValidationException::withMessages([
                'estado' => 'No se puede cancelar un faltante ya aplicado en comisiones.',
            ]);
        }

        if ($descuento->estado === 'CANCELADO') {
            return $descuento;
        }

        $anterior = $descuento->estado;
        $descuento->estado = 'CANCELADO';
        if ($motivo) {
            $descuento->motivo = trim(($descuento->motivo ?: '').' | Cancelado: '.$motivo);
        }
        $descuento->save();

        NominaAuditLog::registrar('FALTANTE_CAJA_CANCELAR', 'faltante_caja', $descuento->id, [
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

        $fecha = Carbon::parse($fecha)->toDateString();

        return NominaComisionDescuento::query()
            ->with(['empleado.cliente', 'empleado.cargoCatalogo', 'creador'])
            ->where('tipo', 'FALTANTE')
            ->whereDate('fecha', $fecha)
            ->where('estado', '!=', 'CANCELADO')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array{pendiente:float,del_dia:float,cajeras:int,personas_hoy:int}
     */
    public function kpis(Carbon|string $fecha): array
    {
        $fecha = Carbon::parse($fecha);
        $delDia = $this->delDia($fecha);

        $pendiente = 0.0;
        if ($this->disponible()) {
            $pendiente = (float) NominaComisionDescuento::query()
                ->where('tipo', 'FALTANTE')
                ->where('estado', 'PENDIENTE')
                ->sum('monto');
        }

        return [
            'pendiente' => round($pendiente, 2),
            'del_dia' => round((float) $delDia->sum('monto'), 2),
            'cajeras' => $this->cajeras()->count(),
            'personas_hoy' => $delDia->pluck('empleado_id')->unique()->count(),
        ];
    }

    public function pendienteDe(NominaEmpleado $empleado): float
    {
        if (! $this->disponible()) {
            return 0.0;
        }

        return round((float) NominaComisionDescuento::query()
            ->where('empleado_id', $empleado->id)
            ->where('tipo', 'FALTANTE')
            ->where('estado', 'PENDIENTE')
            ->sum('monto'), 2);
    }

    /**
     * Para cajeros sin comisión: descuenta faltantes pendientes del sueldo al calcular nómina.
     */
    public function aplicarANominaSinComision(NominaPeriodo $periodo): void
    {
        if (! $this->disponible()) {
            return;
        }

        $desde = $periodo->fecha_inicio->toDateString();
        $hasta = $periodo->fecha_fin->toDateString();

        $items = NominaComisionDescuento::query()
            ->with('empleado')
            ->where('tipo', 'FALTANTE')
            ->where('estado', 'PENDIENTE')
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->whereHas('empleado', fn ($q) => $q->where('modo_comision', NominaEmpleado::COMISION_NINGUNA))
            ->get();

        foreach ($items as $item) {
            $item->update([
                'estado' => 'APLICADO',
                'periodo_id' => $periodo->id,
            ]);
        }
    }

    public function deshacerPeriodoNomina(int $periodoId): void
    {
        if (! $this->disponible()) {
            return;
        }

        NominaComisionDescuento::query()
            ->where('tipo', 'FALTANTE')
            ->where('periodo_id', $periodoId)
            ->where('estado', 'APLICADO')
            ->whereHas('empleado', fn ($q) => $q->where('modo_comision', NominaEmpleado::COMISION_NINGUNA))
            ->update([
                'estado' => 'PENDIENTE',
                'periodo_id' => null,
            ]);
    }

    public function totalAplicadoNomina(NominaPeriodo $periodo, NominaEmpleado $empleado): float
    {
        if (! $this->disponible() || $empleado->generaComision()) {
            return 0.0;
        }

        return round((float) NominaComisionDescuento::query()
            ->where('empleado_id', $empleado->id)
            ->where('tipo', 'FALTANTE')
            ->where('periodo_id', $periodo->id)
            ->where('estado', 'APLICADO')
            ->sum('monto'), 2);
    }

    public function esCajera(NominaEmpleado $empleado): bool
    {
        $empleado->loadMissing('cargoCatalogo');
        $nombre = mb_strtolower((string) ($empleado->cargoCatalogo?->nombre ?: $empleado->cargo ?: ''));

        return str_contains($nombre, 'cajer');
    }
}
