<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaConfig;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaHoraExtra;
use App\Models\Nomina\NominaInasistencia;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(private SalaryAdvanceService $quincenas)
    {
    }

    public function valorHoraEmpresa(): float
    {
        return NominaConfig::getDecimal('valor_hora_extra');
    }

    public function valorDia(NominaEmpleado $empleado): float
    {
        return round(((float) $empleado->salario_base) / 30, 2);
    }

    public function valorHora(): float
    {
        return $this->valorHoraEmpresa();
    }

    public function guardarTarifasEmpresa(array $data): void
    {
        NominaConfig::put('valor_hora_extra', round((float) $data['valor_hora_extra'], 2));

        NominaAuditLog::registrar('TARIFAS_EMPRESA', 'nomina_config', null, null, [
            'valor_hora_extra' => round((float) $data['valor_hora_extra'], 2),
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
        $valor = $this->valorHora();

        if ($horas <= 0) {
            throw ValidationException::withMessages([
                'horas' => 'Las horas extras deben ser mayores a cero.',
            ]);
        }

        if ($valor <= 0) {
            throw ValidationException::withMessages([
                'valor_hora_extra' => 'Define el valor por hora extra en Configuración de nómina antes de registrarlas.',
            ]);
        }

        $quincena = $this->quincenas->quincenaDe($fecha);
        $monto = round($horas * $valor, 2);

        return DB::transaction(function () use ($empleado, $data, $fecha, $horas, $valor, $monto, $quincena, $usuarioId) {
            $row = NominaHoraExtra::create([
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
            ]);

            NominaAuditLog::registrar('HORA_EXTRA_CREAR', 'hora_extra', $row->id, null, [
                'empleado_id' => $empleado->id,
                'fecha' => $fecha->toDateString(),
                'horas' => $horas,
                'monto' => $monto,
            ]);

            return $row;
        });
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
            'valor_hora' => $this->valorHora(),
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
