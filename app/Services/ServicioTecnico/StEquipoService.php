<?php

namespace App\Services\ServicioTecnico;

use App\Models\StEquipo;
use App\Models\StEquipoEvento;
use App\Models\StOrden;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StEquipoService
{
    public function normalizarImei(?string $imei): ?string
    {
        if ($imei === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $imei) ?? '';

        return $digits !== '' ? $digits : null;
    }

    public function normalizarSerial(?string $serial): ?string
    {
        $serial = strtoupper(trim((string) $serial));

        return $serial !== '' ? $serial : null;
    }

    public function encontrarPorImei(?string $imei): ?StEquipo
    {
        $imei = $this->normalizarImei($imei);
        if (! $imei) {
            return null;
        }

        return StEquipo::query()
            ->where(function ($q) use ($imei) {
                $q->where('imei', $imei)->orWhere('imei2', $imei);
            })
            ->first();
    }

    /**
     * @param  array{
     *   imei?:?string,imei2?:?string,serial?:?string,marca?:?string,modelo?:?string,
     *   color?:?string,telefono_asociado?:?string,sede_actual?:?string,estado_actual?:?string,
     *   usar_existente?:bool
     * }  $datos
     * @return array{equipo:StEquipo,creado:bool,existia:bool}
     */
    public function resolverOCrear(array $datos, bool $usarExistente = false): array
    {
        $imei = $this->normalizarImei($datos['imei'] ?? null);
        $serial = $this->normalizarSerial($datos['serial'] ?? null);

        if (! $imei && ! $serial) {
            throw ValidationException::withMessages([
                'serial' => 'Indica el identificador del equipo (IMEI, serial o código de lote).',
            ]);
        }

        $existente = $imei ? $this->encontrarPorImei($imei) : null;

        if ($existente && ! $usarExistente) {
            throw ValidationException::withMessages([
                'imei' => 'Este equipo ya existe en el sistema. ¿Desea utilizar el equipo existente y crear una nueva orden?',
                'equipo_existente_id' => (string) $existente->id,
            ]);
        }

        if ($existente && $usarExistente) {
            $existente->fill(array_filter([
                'imei2' => $this->normalizarImei($datos['imei2'] ?? null),
                'serial' => $serial ?: $existente->serial,
                'marca' => $datos['marca'] ?? $existente->marca,
                'modelo' => $datos['modelo'] ?? $existente->modelo,
                'color' => $datos['color'] ?? $existente->color,
                'telefono_asociado' => $datos['telefono_asociado'] ?? $existente->telefono_asociado,
                'sede_actual' => isset($datos['sede_actual']) ? strtoupper((string) $datos['sede_actual']) : $existente->sede_actual,
                'estado_actual' => $datos['estado_actual'] ?? $existente->estado_actual,
                'tipo_dispositivo' => $datos['tipo_dispositivo'] ?? $existente->tipo_dispositivo,
                'atributos' => $datos['atributos'] ?? $existente->atributos,
            ], fn ($v) => $v !== null && $v !== ''));
            $existente->save();

            return ['equipo' => $existente->fresh(), 'creado' => false, 'existia' => true];
        }

        $payload = [
            'imei' => $imei,
            'imei2' => $this->normalizarImei($datos['imei2'] ?? null),
            'serial' => $serial,
            'marca' => $datos['marca'] ?? null,
            'modelo' => $datos['modelo'] ?? null,
            'color' => $datos['color'] ?? null,
            'telefono_asociado' => $datos['telefono_asociado'] ?? null,
            'estado_actual' => $datos['estado_actual'] ?? StEquipo::ESTADO_EN_TALLER,
            'sede_actual' => isset($datos['sede_actual']) ? strtoupper((string) $datos['sede_actual']) : null,
        ];
        if (Schema::hasColumn('st_equipos', 'tipo_dispositivo')) {
            $payload['tipo_dispositivo'] = $datos['tipo_dispositivo'] ?? 'celular';
        }
        if (Schema::hasColumn('st_equipos', 'atributos') && isset($datos['atributos'])) {
            $payload['atributos'] = $datos['atributos'];
        }
        $equipo = StEquipo::create($payload);

        return ['equipo' => $equipo, 'creado' => true, 'existia' => false];
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function registrarEvento(
        StEquipo $equipo,
        User $user,
        string $tipo,
        ?string $titulo = null,
        ?string $descripcion = null,
        ?StOrden $orden = null,
        ?string $sede = null,
        array $payload = [],
        ?int $backupId = null,
    ): StEquipoEvento {
        return StEquipoEvento::create([
            'equipo_id' => $equipo->id,
            'orden_id' => $orden?->id,
            'backup_id' => $backupId,
            'user_id' => $user->id,
            'sede' => $sede ? strtoupper($sede) : ($equipo->sede_actual ? strtoupper($equipo->sede_actual) : null),
            'tipo' => $tipo,
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'payload' => $payload !== [] ? $payload : null,
            'created_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, StEquipo>
     */
    public function buscar(string $term, int $limit = 25): Collection
    {
        $term = trim($term);
        if ($term === '') {
            return collect();
        }

        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $needle = '%'.$term.'%';

        $porEquipo = StEquipo::query()
            ->buscar($term)
            ->limit($limit)
            ->get();

        $ids = $porEquipo->pluck('id');

        $porOrden = StOrden::query()
            ->whereNotNull('equipo_id')
            ->where(function ($q) use ($term, $like, $needle) {
                $q->where('cliente_nombre', $like, $needle)
                    ->orWhere('cliente_telefono', $like, $needle)
                    ->orWhere('cliente_cedula', $like, $needle)
                    ->orWhere('imei', $like, $needle)
                    ->orWhere('serial', $like, $needle);

                if (ctype_digit($term)) {
                    $q->orWhere('numero', (int) $term);
                }
            })
            ->limit($limit)
            ->pluck('equipo_id');

        $extra = $porOrden->diff($ids)->filter()->values();
        if ($extra->isNotEmpty()) {
            $porEquipo = $porEquipo->concat(
                StEquipo::query()->whereIn('id', $extra)->get()
            )->unique('id')->values();
        }

        return $porEquipo->take($limit);
    }

    public function etiquetaEquipoDesdeOrden(StOrden $orden): string
    {
        if ($orden->relationLoaded('equipoCelular') && $orden->equipoCelular) {
            return $orden->equipoCelular->etiqueta();
        }

        return (string) ($orden->equipo ?: 'Equipo');
    }
}
