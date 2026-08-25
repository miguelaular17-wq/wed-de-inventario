<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaSede;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
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
            ->with(['cliente', 'cargoCatalogo', 'sedeCatalogo', 'supervisor.cliente', 'supervisor.cargoCatalogo', 'supervisor.sedeCatalogo', 'jefes.cliente', 'jefes.cargoCatalogo', 'jefes.sedeCatalogo'])
            ->when($cargoId, fn ($q) => $q->where('cargo_id', $cargoId))
            ->orderBy('id')
            ->get();

        return $sedes->map(function (NominaSede $sede) use ($empleados, $supervisorId) {
            $deSede = $empleados->where('sede_id', $sede->id)->values();
            $localesSup = $deSede
                ->filter(fn (NominaEmpleado $e) => $e->es_supervisor && ! $this->esGerente($e))
                ->values();

            $gerentes = $localesSup
                ->flatMap(fn (NominaEmpleado $e) => $e->jefes->concat([$e->supervisor])->filter())
                ->unique('id')
                ->filter(fn (NominaEmpleado $e) => $this->esGerente($e))
                ->values();

            if ($supervisorId) {
                $enSede = $gerentes->contains('id', $supervisorId) || $localesSup->contains('id', $supervisorId);
                if (! $enSede) {
                    return null;
                }
            }

            $idsLiderazgo = $gerentes->pluck('id')->merge($localesSup->pluck('id'));

            if ($sede->isArea()) {
                $gruposSup = $localesSup;
                if ($supervisorId && $localesSup->contains('id', $supervisorId)) {
                    $gruposSup = $localesSup->where('id', $supervisorId)->values();
                }

                $asignados = $idsLiderazgo->all();
                $grupos = $gruposSup->map(function (NominaEmpleado $supervisor) use ($deSede, $idsLiderazgo, &$asignados) {
                    $equipo = $deSede
                        ->filter(fn (NominaEmpleado $e) => ! $idsLiderazgo->contains($e->id) && $this->reportaA($e, $supervisor->id))
                        ->values();
                    $asignados = array_merge($asignados, $equipo->pluck('id')->all());

                    return [
                        'supervisor' => $supervisor,
                        'empleados' => $equipo,
                    ];
                });

                return [
                    'sede' => $sede,
                    'gerentes' => $gerentes,
                    'supervisores' => $localesSup,
                    'equipo' => collect(),
                    'grupos' => $grupos,
                    'sin_supervisor' => $deSede->reject(fn (NominaEmpleado $e) => in_array($e->id, $asignados, true))->values(),
                ];
            }

            return [
                'sede' => $sede,
                'gerentes' => $gerentes,
                'supervisores' => $localesSup,
                'equipo' => $deSede->reject(fn (NominaEmpleado $e) => $idsLiderazgo->contains($e->id))->values(),
                'grupos' => collect(),
                'sin_supervisor' => collect(),
            ];
        })->filter()->values();
    }

    public function esGerente(NominaEmpleado $empleado): bool
    {
        $cargo = mb_strtoupper($empleado->nombreCargo(), 'UTF-8');
        $sede = mb_strtoupper((string) ($empleado->sedeCatalogo?->codigo ?? $empleado->sede), 'UTF-8');

        return str_contains($cargo, 'GERENTE') || $sede === 'GERENCIA';
    }

    public function reportaA(NominaEmpleado $empleado, int $supervisorId): bool
    {
        if ((int) $empleado->id === $supervisorId) {
            return false;
        }

        if ((int) $empleado->supervisor_id === $supervisorId) {
            return true;
        }

        return $empleado->jefes->contains('id', $supervisorId);
    }

    public function supervisoresDisponibles(?int $exceptoEmpleadoId = null): Collection
    {
        return NominaEmpleado::query()
            ->with(['cliente', 'sedeCatalogo', 'cargoCatalogo'])
            ->where('es_supervisor', true)
            ->where('estado', 'ACTIVO')
            ->when($exceptoEmpleadoId, fn ($q) => $q->where('id', '!=', $exceptoEmpleadoId))
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  list<int>  $supervisorIds
     * @return list<int>
     */
    public function normalizarJefes(array $supervisorIds, ?int $empleadoId = null): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $supervisorIds))));

        if (count($ids) > 2) {
            throw ValidationException::withMessages([
                'supervisor_ids' => 'Una persona puede tener como máximo dos supervisores (sede compartida).',
            ]);
        }

        foreach ($ids as $supervisorId) {
            $this->assertSupervisorValido($supervisorId, $empleadoId);
        }

        return $ids;
    }

    public function syncJefes(NominaEmpleado $empleado, array $supervisorIds): void
    {
        if (! Schema::hasTable('nomina_empleado_supervisores')) {
            $empleado->supervisor_id = $supervisorIds[0] ?? null;
            $empleado->save();

            return;
        }

        $empleado->jefes()->sync($supervisorIds);
        $empleado->supervisor_id = $supervisorIds[0] ?? null;
        $empleado->save();
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
        $actual = NominaEmpleado::query()->with('jefes')->find($supervisorId);
        $vistos = [];

        while ($actual) {
            if (in_array($actual->id, $vistos, true)) {
                return true;
            }
            $vistos[] = $actual->id;

            $siguientes = $actual->jefes->pluck('id')->push($actual->supervisor_id)->filter()->unique();
            if ($siguientes->contains($empleadoId)) {
                return true;
            }

            $siguienteId = $siguientes->first();
            $actual = $siguienteId ? NominaEmpleado::query()->with('jefes')->find($siguienteId) : null;
        }

        return false;
    }
}
