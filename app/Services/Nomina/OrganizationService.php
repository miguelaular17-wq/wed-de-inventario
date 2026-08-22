<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaSede;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OrganizationService
{
    public function tree(?int $sedeId = null, ?int $supervisorId = null, ?int $cargoId = null): Collection
    {
        $sedes = NominaSede::query()
            ->when($sedeId, fn ($q) => $q->where('id', $sedeId))
            ->orderByRaw("CASE WHEN tipo = 'SEDE' THEN 0 ELSE 1 END")
            ->orderBy('nombre')
            ->get();

        $empleados = NominaEmpleado::query()
            ->with(['cliente', 'cargoCatalogo', 'supervisor.cliente'])
            ->when($sedeId, fn ($q) => $q->where('sede_id', $sedeId))
            ->when($supervisorId, fn ($q) => $q->where('supervisor_id', $supervisorId))
            ->when($cargoId, fn ($q) => $q->where('cargo_id', $cargoId))
            ->orderBy('id')
            ->get();

        return $sedes->map(function (NominaSede $sede) use ($empleados, $supervisorId) {
            $deSede = $empleados->where('sede_id', $sede->id);
            $supervisores = $deSede
                ->filter(fn (NominaEmpleado $e) => $e->es_supervisor || $deSede->contains('supervisor_id', $e->id))
                ->unique('id')
                ->values();

            if ($supervisorId) {
                $supervisores = $supervisores->where('id', $supervisorId)->values();
            }

            $asignados = collect();

            $nodos = $supervisores->map(function (NominaEmpleado $supervisor) use ($deSede, &$asignados) {
                $equipo = $deSede->where('supervisor_id', $supervisor->id)->values();
                $asignados = $asignados->merge($equipo->pluck('id'))->push($supervisor->id);

                return [
                    'supervisor' => $supervisor,
                    'empleados' => $equipo,
                ];
            });

            $sinSupervisor = $deSede
                ->reject(fn (NominaEmpleado $e) => $asignados->contains($e->id))
                ->values();

            return [
                'sede' => $sede,
                'supervisores' => $nodos,
                'sin_supervisor' => $sinSupervisor,
            ];
        });
    }

    public function supervisoresDisponibles(?int $exceptoEmpleadoId = null): Collection
    {
        return NominaEmpleado::query()
            ->with('cliente')
            ->where('es_supervisor', true)
            ->where('estado', 'ACTIVO')
            ->when($exceptoEmpleadoId, fn ($q) => $q->where('id', '!=', $exceptoEmpleadoId))
            ->orderBy('id')
            ->get();
    }

    public function assertSupervisorValido(int $supervisorId, ?int $empleadoId = null): void
    {
        if ($empleadoId && (int) $supervisorId === (int) $empleadoId) {
            throw ValidationException::withMessages([
                'supervisor_id' => 'Un empleado no puede ser su propio supervisor.',
            ]);
        }

        $supervisor = NominaEmpleado::query()->find($supervisorId);

        if (! $supervisor || ! $supervisor->es_supervisor) {
            throw ValidationException::withMessages([
                'supervisor_id' => 'El supervisor seleccionado no está habilitado para tener personal a cargo.',
            ]);
        }

        if ($empleadoId && $this->formariaCiclo($empleadoId, $supervisorId)) {
            throw ValidationException::withMessages([
                'supervisor_id' => 'Esa asignación formaría un ciclo (el supervisor reporta a este empleado).',
            ]);
        }
    }

    public function formariaCiclo(int $empleadoId, int $supervisorId): bool
    {
        $actual = NominaEmpleado::query()->find($supervisorId);
        $vistos = [];

        while ($actual && $actual->supervisor_id) {
            if (in_array($actual->id, $vistos, true)) {
                return true;
            }
            if ((int) $actual->supervisor_id === $empleadoId) {
                return true;
            }
            $vistos[] = $actual->id;
            $actual = $actual->supervisor;
        }

        return false;
    }
}
