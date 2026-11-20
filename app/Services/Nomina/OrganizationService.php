<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaSede;
use App\Models\User;
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
            $equipo = $deSede->reject(fn (NominaEmpleado $e) => $idsLiderazgo->contains($e->id))->values();

            // En sede y área el personal de la unidad queda a cargo de sus supervisores
            // (equipo compartido). Los grupos del área muestran el mismo piso bajo cada uno.
            $gruposSup = $localesSup;
            if ($supervisorId && $localesSup->contains('id', $supervisorId)) {
                $gruposSup = $localesSup->where('id', $supervisorId)->values();
            }

            $grupos = $sede->isArea()
                ? $gruposSup->map(fn (NominaEmpleado $supervisor) => [
                    'supervisor' => $supervisor,
                    'empleados' => $equipo,
                ])
                : collect();

            return [
                'sede' => $sede,
                'gerentes' => $gerentes,
                'supervisores' => $localesSup,
                'equipo' => $equipo,
                'grupos' => $grupos,
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

    /**
     * Cargos de supervisor (sede, área, flota, inventario, call center, etc.).
     */
    public function esCargoConPersonalACargo(?string $nombreCargo): bool
    {
        $cargo = mb_strtoupper(trim((string) $nombreCargo), 'UTF-8');
        if ($cargo === '') {
            return false;
        }

        return str_starts_with($cargo, 'SUPERVISOR') || str_starts_with($cargo, 'SUPERVISORA');
    }

    public function esAdministracion(NominaEmpleado $empleado): bool
    {
        $codigo = mb_strtoupper((string) ($empleado->sedeCatalogo?->codigo ?? $empleado->sede), 'UTF-8');

        return $codigo === 'ADMINISTRACION' || $codigo === 'ADMINISTRACIÓN';
    }

    /**
     * Gerentes activos por cargo (no basta con sede Gerencia).
     *
     * @return list<int>
     */
    public function idsGerentesActivos(?int $exceptoEmpleadoId = null): array
    {
        return NominaEmpleado::query()
            ->with(['cargoCatalogo', 'sedeCatalogo'])
            ->where('estado', 'ACTIVO')
            ->when($exceptoEmpleadoId, fn ($q) => $q->where('id', '!=', $exceptoEmpleadoId))
            ->orderBy('id')
            ->get()
            ->filter(function (NominaEmpleado $e) {
                $cargo = mb_strtoupper($e->nombreCargo(), 'UTF-8');

                return str_contains($cargo, 'GERENTE');
            })
            ->take(2)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
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
     * Supervisores activos de una sede/área (máx. 2 para el pivot).
     *
     * @return list<int>
     */
    public function idsSupervisoresDeUnidad(?int $sedeId, ?int $exceptoEmpleadoId = null): array
    {
        if (! $sedeId) {
            return [];
        }

        return NominaEmpleado::query()
            ->with(['sedeCatalogo', 'cargoCatalogo'])
            ->where('sede_id', $sedeId)
            ->where('es_supervisor', true)
            ->where('estado', 'ACTIVO')
            ->when($exceptoEmpleadoId, fn ($q) => $q->where('id', '!=', $exceptoEmpleadoId))
            ->orderBy('id')
            ->get()
            ->reject(fn (NominaEmpleado $e) => $this->esGerente($e))
            ->take(2)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
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

    /**
     * Empleado de piso: jefes = supervisores de su sede/área.
     * Administración: mando directo de la gerente.
     */
    public function sincronizarJefesPorUnidad(NominaEmpleado $empleado): void
    {
        if ($empleado->es_supervisor || $this->esGerente($empleado) || ! $empleado->sede_id) {
            return;
        }

        if ($this->esAdministracion($empleado)) {
            $this->syncJefes($empleado, $this->idsGerentesActivos((int) $empleado->id));

            return;
        }

        $this->syncJefes(
            $empleado,
            $this->idsSupervisoresDeUnidad((int) $empleado->sede_id, (int) $empleado->id)
        );
    }

    /**
     * Supervisores reportan a la gerente.
     */
    public function sincronizarJefeGerente(NominaEmpleado $supervisor): void
    {
        if (! $supervisor->es_supervisor || $this->esGerente($supervisor)) {
            return;
        }

        $this->syncJefes($supervisor, $this->idsGerentesActivos((int) $supervisor->id));
    }

    /**
     * Si la persona es supervisora de sede o área, el personal activo de esa
     * unidad (que no es liderazgo) queda a su cargo.
     * En Administración el mando es de la gerente: no se reasigna el piso.
     *
     * @return int Cantidad de fichas actualizadas
     */
    public function asignarEquipoPorUnidad(NominaEmpleado $supervisor): int
    {
        if (! $supervisor->es_supervisor || ! $supervisor->sede_id || $this->esGerente($supervisor)) {
            return 0;
        }

        $sede = $supervisor->sedeCatalogo ?? NominaSede::query()->find($supervisor->sede_id);
        if (! $sede) {
            return 0;
        }

        if ($this->esAdministracion($supervisor)) {
            return 0;
        }

        $jefesUnidad = $this->idsSupervisoresDeUnidad((int) $supervisor->sede_id);
        if ($jefesUnidad === []) {
            return 0;
        }

        $equipo = NominaEmpleado::query()
            ->with(['jefes', 'sedeCatalogo', 'cargoCatalogo'])
            ->where('sede_id', $supervisor->sede_id)
            ->where('id', '!=', $supervisor->id)
            ->where('estado', 'ACTIVO')
            ->where('es_supervisor', false)
            ->orderBy('id')
            ->get()
            ->reject(fn (NominaEmpleado $e) => $this->esGerente($e));

        $asignados = 0;
        foreach ($equipo as $empleado) {
            $actuales = $empleado->jefes->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($actuales === [] && $empleado->supervisor_id) {
                $actuales = [(int) $empleado->supervisor_id];
            }

            sort($actuales);
            $esperados = $jefesUnidad;
            sort($esperados);
            if ($actuales === $esperados) {
                continue;
            }

            $this->syncJefes($empleado, $jefesUnidad);
            $asignados++;
        }

        return $asignados;
    }

    /** @deprecated Use asignarEquipoPorUnidad */
    public function asignarComoJefeDeSede(NominaEmpleado $supervisor): int
    {
        return $this->asignarEquipoPorUnidad($supervisor);
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

    public function empleadoDelUsuario(User $user): ?NominaEmpleado
    {
        return NominaEmpleado::query()
            ->with(['cliente', 'sedeCatalogo', 'cargoCatalogo'])
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * IDs del personal a cargo del usuario.
     * Con $incluirse=true también incluye la propia ficha (reporte de nómina del equipo).
     *
     * Un supervisor de sede/área solo ve su unidad: no arrastra gente de otra sede
     * aunque queden vínculos viejos en supervisor_id / pivot.
     *
     * @return list<int>
     */
    public function idsPersonalACargo(User $user, bool $incluirse = false): array
    {
        $yo = $this->empleadoDelUsuario($user);
        if (! $yo) {
            return [];
        }

        // Supervisor de sede/área: únicamente personal de su misma unidad.
        if ($yo->es_supervisor && $yo->sede_id && ! $this->esGerente($yo)) {
            $query = NominaEmpleado::query()
                ->where('sede_id', $yo->sede_id)
                ->where('estado', 'ACTIVO');

            if ($incluirse) {
                $ids = $query->pluck('id');
            } else {
                $ids = $query
                    ->where('id', '!=', $yo->id)
                    ->where('es_supervisor', false)
                    ->pluck('id');
            }

            return $ids
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        $ids = collect();

        // Enlaces explícitos (legado / casos especiales / sin sede).
        $ids = $ids->merge(
            NominaEmpleado::query()
                ->where('supervisor_id', $yo->id)
                ->pluck('id')
        );

        if (Schema::hasTable('nomina_empleado_supervisores')) {
            $ids = $ids->merge($yo->equipo()->pluck('nomina_empleados.id'));
        }

        foreach ($this->tree() as $nodo) {
            $esGerente = $nodo['gerentes']->contains('id', $yo->id);
            $esSupervisor = $nodo['supervisores']->contains('id', $yo->id);

            if ($esGerente) {
                $ids = $ids
                    ->merge($nodo['supervisores']->pluck('id'))
                    ->merge($nodo['equipo']->pluck('id'))
                    ->merge($nodo['grupos']->flatMap(fn (array $grupo) => $grupo['empleados']->pluck('id')))
                    ->merge($nodo['sin_supervisor']->pluck('id'));
            } elseif ($esSupervisor) {
                $ids = $ids->merge($nodo['equipo']->pluck('id'));

                if ($incluirse) {
                    $ids = $ids->merge($nodo['supervisores']->pluck('id'));
                }
            }
        }

        $ids = $ids
            ->map(fn ($id) => (int) $id)
            ->unique();

        if ($incluirse) {
            $ids = $ids->push((int) $yo->id)->unique();
        } else {
            $ids = $ids->reject(fn (int $id) => $id === (int) $yo->id);
        }

        return $ids->values()->all();
    }
}
