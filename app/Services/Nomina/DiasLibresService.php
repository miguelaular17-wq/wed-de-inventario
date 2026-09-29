<?php

namespace App\Services\Nomina;

use App\Models\Nomina\NominaDiaLibre;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaSede;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiasLibresService
{
    public function __construct(private OrganizationService $organization) {}

    public function esRrhh(User $user): bool
    {
        return $user->canAccess('nomina');
    }

    /**
     * @return list<int>
     */
    public function idsEmpleadosVisibles(User $user, ?int $sedeId = null): array
    {
        if ($this->esRrhh($user)) {
            $q = NominaEmpleado::query()->activos();
            if ($sedeId) {
                $q->where('sede_id', $sedeId);
            }

            return $q->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $this->organization->idsPersonalACargo($user, false);
    }

    /**
     * @return Collection<int, NominaEmpleado>
     */
    public function empleadosVisibles(User $user, ?int $sedeId = null): Collection
    {
        $ids = $this->idsEmpleadosVisibles($user, $sedeId);
        if ($ids === []) {
            return collect();
        }

        return NominaEmpleado::query()
            ->with(['cliente', 'sedeCatalogo', 'cargoCatalogo'])
            ->activos()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (NominaEmpleado $e) => mb_strtoupper($e->nombre(), 'UTF-8'))
            ->values();
    }

    /**
     * @return Collection<int, NominaDiaLibre> keyed by "empleadoId|Y-m-d"
     */
    public function mapaDias(array $empleadoIds, Carbon $desde, Carbon $hasta): Collection
    {
        if ($empleadoIds === []) {
            return collect();
        }

        return NominaDiaLibre::query()
            ->whereIn('empleado_id', $empleadoIds)
            ->whereDate('fecha', '>=', $desde->toDateString())
            ->whereDate('fecha', '<=', $hasta->toDateString())
            ->get()
            ->keyBy(fn (NominaDiaLibre $d) => $d->empleado_id.'|'.$d->fecha->toDateString());
    }

    /**
     * @return list<Carbon>
     */
    public function fechasDelRango(Carbon $desde, Carbon $hasta): array
    {
        if ($hasta->lt($desde)) {
            throw ValidationException::withMessages(['hasta' => 'La fecha hasta debe ser mayor o igual a desde.']);
        }
        if ($desde->diffInDays($hasta) > 45) {
            throw ValidationException::withMessages(['hasta' => 'El rango máximo es de 45 días.']);
        }

        $out = [];
        $cursor = $desde->copy()->startOfDay();
        $end = $hasta->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $out[] = $cursor->copy();
            $cursor->addDay();
        }

        return $out;
    }

    public function quincenaActual(): array
    {
        $hoy = now()->startOfDay();
        if ($hoy->day <= 15) {
            return [$hoy->copy()->day(1), $hoy->copy()->day(15)];
        }

        return [$hoy->copy()->day(16), $hoy->copy()->endOfMonth()->startOfDay()];
    }

    public function puedeEditarEmpleado(User $user, int $empleadoId): bool
    {
        return in_array($empleadoId, $this->idsEmpleadosVisibles($user), true)
            || ($this->esRrhh($user) && NominaEmpleado::query()->whereKey($empleadoId)->exists());
    }

    public function toggle(User $user, int $empleadoId, string $fecha): NominaDiaLibre|array
    {
        if (! $this->puedeEditarEmpleado($user, $empleadoId)) {
            throw ValidationException::withMessages(['empleado_id' => 'No puedes editar este trabajador.']);
        }

        $dia = NominaDiaLibre::query()
            ->where('empleado_id', $empleadoId)
            ->whereDate('fecha', $fecha)
            ->first();

        if ($dia) {
            if (! $this->esRrhh($user) && $dia->esAprobado()) {
                throw ValidationException::withMessages(['fecha' => 'Ese día ya está aprobado por RRHH.']);
            }
            $dia->delete();

            return ['removed' => true, 'empleado_id' => $empleadoId, 'fecha' => $fecha];
        }

        $estado = $this->esRrhh($user) ? NominaDiaLibre::APROBADO : NominaDiaLibre::PENDIENTE;
        $row = NominaDiaLibre::create([
            'empleado_id' => $empleadoId,
            'fecha' => $fecha,
            'estado' => $estado,
            'creado_por' => $user->id,
            'aprobado_por' => $estado === NominaDiaLibre::APROBADO ? $user->id : null,
            'aprobado_at' => $estado === NominaDiaLibre::APROBADO ? now() : null,
        ]);

        return $row;
    }

    /**
     * @param  list<array{empleado_id:int,fecha:string,libre:bool}>  $celdas
     */
    public function syncCeldas(User $user, array $celdas): array
    {
        $added = 0;
        $removed = 0;

        DB::transaction(function () use ($user, $celdas, &$added, &$removed) {
            foreach ($celdas as $celda) {
                $empleadoId = (int) ($celda['empleado_id'] ?? 0);
                $fecha = (string) ($celda['fecha'] ?? '');
                $libre = (bool) ($celda['libre'] ?? false);
                if ($empleadoId < 1 || $fecha === '') {
                    continue;
                }
                if (! $this->puedeEditarEmpleado($user, $empleadoId)) {
                    continue;
                }

                $existente = NominaDiaLibre::query()
                    ->where('empleado_id', $empleadoId)
                    ->whereDate('fecha', $fecha)
                    ->first();

                if ($libre) {
                    if ($existente) {
                        continue;
                    }
                    $estado = $this->esRrhh($user) ? NominaDiaLibre::APROBADO : NominaDiaLibre::PENDIENTE;
                    NominaDiaLibre::create([
                        'empleado_id' => $empleadoId,
                        'fecha' => $fecha,
                        'estado' => $estado,
                        'creado_por' => $user->id,
                        'aprobado_por' => $estado === NominaDiaLibre::APROBADO ? $user->id : null,
                        'aprobado_at' => $estado === NominaDiaLibre::APROBADO ? now() : null,
                    ]);
                    $added++;
                } elseif ($existente) {
                    if (! $this->esRrhh($user) && $existente->esAprobado()) {
                        continue;
                    }
                    $existente->delete();
                    $removed++;
                }
            }
        });

        return compact('added', 'removed');
    }

    public function aprobarRango(User $user, Carbon $desde, Carbon $hasta, ?int $sedeId = null): int
    {
        if (! $this->esRrhh($user)) {
            throw ValidationException::withMessages(['autorizacion' => 'Solo RRHH puede aprobar.']);
        }

        $ids = $this->idsEmpleadosVisibles($user, $sedeId);
        if ($ids === []) {
            return 0;
        }

        return NominaDiaLibre::query()
            ->whereIn('empleado_id', $ids)
            ->whereDate('fecha', '>=', $desde->toDateString())
            ->whereDate('fecha', '<=', $hasta->toDateString())
            ->where('estado', NominaDiaLibre::PENDIENTE)
            ->update([
                'estado' => NominaDiaLibre::APROBADO,
                'aprobado_por' => $user->id,
                'aprobado_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function sedes(): Collection
    {
        return NominaSede::query()->orderBy('nombre')->get();
    }
}
