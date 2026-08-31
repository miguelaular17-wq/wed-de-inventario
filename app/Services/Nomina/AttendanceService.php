<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaConfig;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaHoraExtra;
use App\Models\Nomina\NominaInasistencia;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(private SalaryAdvanceService $quincenas)
    {
    }

    public function valorHoraEmpresa(): float
    {
        return $this->valorHoraTrabajador();
    }

    public function valorHoraTrabajador(): float
    {
        if (NominaConfig::query()->find('valor_hora_extra_trabajador')) {
            return NominaConfig::getDecimal('valor_hora_extra_trabajador', 1);
        }

        $legado = NominaConfig::getDecimal('valor_hora_extra', 1);

        return $legado > 0 ? $legado : 1.0;
    }

    public function valorHoraSupervisor(): float
    {
        return NominaConfig::getDecimal('valor_hora_extra_supervisor', 1.5);
    }

    public function usaTarifaHoraSupervisor(NominaEmpleado $empleado): bool
    {
        return (bool) $empleado->es_supervisor
            || in_array((string) $empleado->modo_comision, [
                NominaEmpleado::COMISION_SUPERVISOR_SEDE,
                NominaEmpleado::COMISION_SUPERVISOR_EQUIPO,
            ], true);
    }

    public function valorDia(NominaEmpleado $empleado): float
    {
        return round(((float) $empleado->salario_base) / 30, 2);
    }

    public function valorHora(?NominaEmpleado $empleado = null): float
    {
        if ($empleado && $this->usaTarifaHoraSupervisor($empleado)) {
            return $this->valorHoraSupervisor();
        }

        return $this->valorHoraTrabajador();
    }

    public function guardarTarifasEmpresa(array $data): void
    {
        $trabajador = array_key_exists('valor_hora_extra_trabajador', $data)
            ? round((float) $data['valor_hora_extra_trabajador'], 2)
            : round((float) ($data['valor_hora_extra'] ?? $this->valorHoraTrabajador()), 2);
        $supervisor = array_key_exists('valor_hora_extra_supervisor', $data)
            ? round((float) $data['valor_hora_extra_supervisor'], 2)
            : $this->valorHoraSupervisor();

        NominaConfig::put('valor_hora_extra', $trabajador);
        NominaConfig::put('valor_hora_extra_trabajador', $trabajador);
        NominaConfig::put('valor_hora_extra_supervisor', $supervisor);

        NominaAuditLog::registrar('TARIFAS_EMPRESA', 'nomina_config', null, null, [
            'valor_hora_extra_trabajador' => $trabajador,
            'valor_hora_extra_supervisor' => $supervisor,
        ]);
    }

    public function registrarInasistencia(NominaEmpleado $empleado, array $data, ?int $usuarioId = null): NominaInasistencia
    {
        $fecha = Carbon::parse($data['fecha'] ?? now())->startOfDay();
        $cantidad = round((float) ($data['cantidad'] ?? 1), 2);
        $valor = $this->valorDia($empleado);

        if ($cantidad <= 0) {
            throw ValidationException::withMessages([
                'cantidad' => 'La cantidad de inasistencias debe ser mayor a cero.',
            ]);
        }

        if ($valor <= 0) {
            throw ValidationException::withMessages([
                'valor_dia' => 'El empleado debe tener un salario base mayor a cero para calcular su valor diario.',
            ]);
        }

        if ($this->inasistenciaDelDia($empleado, $fecha)) {
            throw ValidationException::withMessages([
                'fecha' => 'Ya hay una inasistencia registrada para esa fecha.',
            ]);
        }

        $quincena = $this->quincenas->quincenaDe($fecha);
        $monto = round($cantidad * $valor, 2);

        return DB::transaction(function () use ($empleado, $data, $fecha, $cantidad, $valor, $monto, $quincena, $usuarioId) {
            $row = NominaInasistencia::create([
                'empleado_id' => $empleado->id,
                'fecha' => $fecha->toDateString(),
                'cantidad' => $cantidad,
                'valor_unitario' => $valor,
                'monto' => $monto,
                'quincena_inicio' => $quincena['inicio']->toDateString(),
                'quincena_fin' => $quincena['fin']->toDateString(),
                'etiqueta' => $quincena['etiqueta'],
                'estado' => 'PENDIENTE',
                'motivo' => $data['motivo'] ?? null,
                'created_by' => $usuarioId,
            ]);

            NominaAuditLog::registrar('INASISTENCIA_CREAR', 'inasistencia', $row->id, null, [
                'empleado_id' => $empleado->id,
                'fecha' => $fecha->toDateString(),
                'cantidad' => $cantidad,
                'monto' => $monto,
            ]);

            return $row;
        });
    }

    public function marcarFaltoHoy(NominaEmpleado $empleado, ?int $usuarioId = null): NominaInasistencia
    {
        return $this->registrarInasistencia($empleado, [
            'fecha' => now()->toDateString(),
            'cantidad' => 1,
            'motivo' => 'Faltó hoy',
        ], $usuarioId);
    }

    public function registrarHorasExtras(NominaEmpleado $empleado, array $data, ?int $usuarioId = null): NominaHoraExtra
    {
        $fecha = Carbon::parse($data['fecha'] ?? now())->startOfDay();
        $horas = round((float) ($data['horas'] ?? 0), 2);
        $unidad = (($data['unidad'] ?? 'HORAS') === 'DIAS') ? 'DIAS' : 'HORAS';
        $valor = $unidad === 'DIAS' ? $this->valorDia($empleado) : $this->valorHora($empleado);

        if ($horas <= 0) {
            throw ValidationException::withMessages([
                'horas' => $unidad === 'DIAS'
                    ? 'Los días extras deben ser mayores a cero.'
                    : 'Las horas extras deben ser mayores a cero.',
            ]);
        }

        if ($valor <= 0) {
            throw ValidationException::withMessages([
                $unidad === 'DIAS' ? 'valor_dia' : 'valor_hora_extra' => $unidad === 'DIAS'
                    ? 'El empleado debe tener un salario base mayor a cero para calcular el valor del día.'
                    : 'Define el valor por hora extra en Configuración de nómina antes de registrarlas.',
            ]);
        }

        $quincena = $this->quincenas->quincenaDe($fecha);
        $monto = round($horas * $valor, 2);

        return DB::transaction(function () use ($empleado, $data, $fecha, $horas, $unidad, $valor, $monto, $quincena, $usuarioId) {
            $payload = [
                'empleado_id' => $empleado->id,
                'fecha' => $fecha->toDateString(),
                'horas' => $horas,
                'valor_unitario' => $valor,
                'monto' => $monto,
                'quincena_inicio' => $quincena['inicio']->toDateString(),
                'quincena_fin' => $quincena['fin']->toDateString(),
                'etiqueta' => $quincena['etiqueta'],
                'estado' => 'PENDIENTE',
                'motivo' => $data['motivo'] ?? null,
                'created_by' => $usuarioId,
            ];
            if (Schema::hasColumn('nomina_horas_extras', 'unidad')) {
                $payload['unidad'] = $unidad;
            }

            $row = NominaHoraExtra::create($payload);

            NominaAuditLog::registrar('HORA_EXTRA_CREAR', 'hora_extra', $row->id, null, [
                'empleado_id' => $empleado->id,
                'fecha' => $fecha->toDateString(),
                'unidad' => $unidad,
                'horas' => $horas,
                'monto' => $monto,
            ]);

            return $row;
        });
    }

    public function empleadosDeSede(int $sedeId, string $alcance = 'TODOS'): Collection
    {
        $empleados = NominaEmpleado::query()
            ->activos()
            ->where('sede_id', $sedeId)
            ->with(['cliente', 'sedeCatalogo', 'cargoCatalogo'])
            ->join('clientes', 'clientes.id', '=', 'nomina_empleados.cliente_id')
            ->select('nomina_empleados.*')
            ->orderBy('clientes.nombre')
            ->get();

        if ($alcance === 'SUPERVISORES') {
            return $empleados->filter(fn (NominaEmpleado $empleado) => $this->usaTarifaHoraSupervisor($empleado))->values();
        }

        if ($alcance === 'TRABAJADORES') {
            return $empleados->filter(fn (NominaEmpleado $empleado) => ! $this->usaTarifaHoraSupervisor($empleado))->values();
        }

        return $empleados->values();
    }

    public function registrarHorasExtrasMasivas(array $empleadoIds, array $data, ?int $usuarioId = null): Collection
    {
        $sedeId = (int) ($data['sede_id'] ?? 0);
        $alcance = (string) ($data['alcance'] ?? 'TODOS');
        $ids = collect($empleadoIds)->map(fn ($id) => (int) $id)->unique()->filter();

        if ($sedeId <= 0) {
            throw ValidationException::withMessages([
                'sede_id' => 'Elige la sede o área.',
            ]);
        }

        $permitidos = $this->empleadosDeSede($sedeId, $alcance)->keyBy('id');
        if ($ids->isEmpty()) {
            $ids = $permitidos->keys();
        }

        $seleccionados = $ids
            ->map(fn (int $id) => $permitidos->get($id))
            ->filter();

        if ($seleccionados->isEmpty()) {
            throw ValidationException::withMessages([
                'empleado_ids' => 'Selecciona al menos una persona activa de esa sede.',
            ]);
        }

        return DB::transaction(function () use ($seleccionados, $data, $usuarioId) {
            return $seleccionados
                ->map(fn (NominaEmpleado $empleado) => $this->registrarHorasExtras($empleado, $data, $usuarioId))
                ->values();
        });
    }

    public function extrasDelDia(Carbon|string $fecha, ?int $sedeId = null): Collection
    {
        $dia = Carbon::parse($fecha)->toDateString();

        return NominaHoraExtra::query()
            ->with(['empleado.cliente', 'empleado.sedeCatalogo', 'creador'])
            ->whereDate('fecha', $dia)
            ->when($sedeId, function ($query) use ($sedeId) {
                $query->whereHas('empleado', fn ($empleado) => $empleado->where('sede_id', $sedeId));
            })
            ->orderByDesc('id')
            ->get();
    }

    public function cancelarInasistencia(NominaInasistencia $row, ?string $motivo = null): NominaInasistencia
    {
        return $this->cancelarMovimiento($row, 'inasistencia', $motivo);
    }

    public function cancelarHorasExtras(NominaHoraExtra $row, ?string $motivo = null): NominaHoraExtra
    {
        return $this->cancelarMovimiento($row, 'hora_extra', $motivo);
    }

    /**
     * @return array{
     *     valor_dia:float,
     *     valor_hora:float,
     *     dias:float,
     *     monto_ausencias:float,
     *     horas:float,
     *     monto_horas:float,
     *     ya_falto_hoy:bool
     * }
     */
    public function resumenQuincena(NominaEmpleado $empleado, Carbon|string|null $enFecha = null): array
    {
        $fecha = Carbon::parse($enFecha ?? now());
        $q = $this->quincenas->quincenaDe($fecha);

        $ausencias = $empleado->inasistencias()
            ->where('estado', 'PENDIENTE')
            ->whereDate('quincena_inicio', $q['inicio']->toDateString())
            ->whereDate('quincena_fin', $q['fin']->toDateString())
            ->get();

        $horas = $empleado->horasExtras()
            ->where('estado', 'PENDIENTE')
            ->whereDate('quincena_inicio', $q['inicio']->toDateString())
            ->whereDate('quincena_fin', $q['fin']->toDateString())
            ->get();

        return [
            'valor_dia' => $this->valorDia($empleado),
            'valor_hora' => $this->valorHora($empleado),
            'hora_supervisor' => $this->usaTarifaHoraSupervisor($empleado),
            'dias' => round((float) $ausencias->sum('cantidad'), 2),
            'monto_ausencias' => round((float) $ausencias->sum('monto'), 2),
            'horas' => round((float) $horas->sum('horas'), 2),
            'monto_horas' => round((float) $horas->sum('monto'), 2),
            'ya_falto_hoy' => (bool) $this->inasistenciaDelDia($empleado, now()),
        ];
    }

    public function aplicarAPeriodo(int $periodoId, Carbon $inicio, Carbon $fin): int
    {
        return $this->marcarPeriodo(NominaInasistencia::query(), $periodoId, $inicio, $fin)
            + $this->marcarPeriodo(NominaHoraExtra::query(), $periodoId, $inicio, $fin);
    }

    private function marcarPeriodo($query, int $periodoId, Carbon $inicio, Carbon $fin): int
    {
        return (int) DB::transaction(function () use ($query, $periodoId, $inicio, $fin) {
            $rows = $query
                ->where('estado', 'PENDIENTE')
                ->whereNull('nomina_periodo_id')
                ->whereDate('quincena_inicio', '>=', $inicio->toDateString())
                ->whereDate('quincena_fin', '<=', $fin->toDateString())
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                $row->estado = 'APLICADO';
                $row->nomina_periodo_id = $periodoId;
                $row->save();
            }

            return $rows->count();
        });
    }

    public function deshacerPeriodo(int $periodoId): int
    {
        $inasistencias = NominaInasistencia::query()
            ->where('nomina_periodo_id', $periodoId)
            ->where('estado', 'APLICADO')
            ->update([
                'estado' => 'PENDIENTE',
                'nomina_periodo_id' => null,
            ]);

        $horas = NominaHoraExtra::query()
            ->where('nomina_periodo_id', $periodoId)
            ->where('estado', 'APLICADO')
            ->update([
                'estado' => 'PENDIENTE',
                'nomina_periodo_id' => null,
            ]);

        return $inasistencias + $horas;
    }

    private function cancelarMovimiento(Model $row, string $entidad, ?string $motivo = null): Model
    {
        if ($row->estado === 'APLICADO') {
            throw ValidationException::withMessages([
                'estado' => 'No se puede cancelar un registro ya aplicado en nómina.',
            ]);
        }

        if ($row->estado === 'CANCELADO') {
            return $row;
        }

        $anterior = $row->estado;
        $row->estado = 'CANCELADO';
        if ($motivo) {
            $row->motivo = trim(($row->motivo ? $row->motivo.' | ' : '').'Cancelado: '.$motivo);
        }
        $row->save();

        NominaAuditLog::registrar(strtoupper($entidad).'_CANCELAR', $entidad, $row->id, [
            'estado' => $anterior,
        ], [
            'estado' => 'CANCELADO',
        ]);

        return $row;
    }

    private function inasistenciaDelDia(NominaEmpleado $empleado, Carbon $fecha): ?NominaInasistencia
    {
        return $empleado->inasistencias()
            ->whereDate('fecha', $fecha->toDateString())
            ->whereIn('estado', ['PENDIENTE', 'APLICADO'])
            ->first();
    }
}
