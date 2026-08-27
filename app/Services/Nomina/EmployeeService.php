<?php

namespace App\Services\Nomina;

use App\Models\Cliente;
use App\Models\Nomina\NominaAuditLog;
use App\Models\Nomina\NominaCargo;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaSede;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeService
{
    public function __construct(private OrganizationService $organization)
    {
    }

    public function syncFromClientes(): int
    {
        if (Cliente::query()->count() <= NominaEmpleado::query()->count()) {
            return 0;
        }

        $yaCargados = NominaEmpleado::query()->pluck('cliente_id');
        $faltantes = Cliente::query()
            ->whereNotIn('id', $yaCargados)
            ->orderBy('id')
            ->pluck('id');

        if ($faltantes->isEmpty()) {
            return 0;
        }

        $now = now();
        foreach ($faltantes->chunk(100) as $lote) {
            NominaEmpleado::query()->insert(
                $lote->map(fn ($clienteId) => [
                    'cliente_id' => $clienteId,
                    'salario_base' => 0,
                    'tipo_salario' => 'QUINCENAL',
                    'estado' => 'ACTIVO',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all()
            );
        }

        return $faltantes->count();
    }

    public function create(array $data): NominaEmpleado
    {
        return DB::transaction(function () use ($data) {
            $empleado = NominaEmpleado::create($this->payload($data));
            $this->organization->syncJefes(
                $empleado,
                $this->idsJefes($data, $empleado)
            );
            $this->syncDenormalized($empleado);

            NominaAuditLog::registrar('CREAR', 'empleado', $empleado->id, null, $empleado->toArray());

            return $empleado->fresh([
                'cliente', 'sedeCatalogo', 'cargoCatalogo', 'supervisor.cliente', 'jefes.cliente',
            ]);
        });
    }

    public function update(NominaEmpleado $empleado, array $data): NominaEmpleado
    {
        return DB::transaction(function () use ($empleado, $data) {
            $anterior = $empleado->toArray();
            $empleado->fill($this->payload($data, $empleado));
            $empleado->save();
            $this->organization->syncJefes(
                $empleado,
                $this->idsJefes($data, $empleado)
            );
            $this->syncDenormalized($empleado);

            NominaAuditLog::registrar('ACTUALIZAR', 'empleado', $empleado->id, $anterior, $empleado->fresh()->toArray());

            return $empleado->fresh([
                'cliente', 'sedeCatalogo', 'cargoCatalogo', 'supervisor.cliente', 'jefes.cliente',
            ]);
        });
    }

    private function payload(array $data, ?NominaEmpleado $empleado = null): array
    {
        $cliente = $this->resolveCliente($data, $empleado);
        $supervisorIds = $data['supervisor_ids'] ?? [];
        if ($supervisorIds === [] && ! empty($data['supervisor_id'])) {
            $supervisorIds = [$data['supervisor_id']];
        }
        $supervisorIds = $this->organization->normalizarJefes(
            is_array($supervisorIds) ? $supervisorIds : [$supervisorIds],
            $empleado?->id
        );

        $codigoVendedor = NominaEmpleado::normalizarVendedor($data['codigo_vendedor'] ?? null);
        if ($codigoVendedor) {
            $tomado = NominaEmpleado::query()
                ->whereRaw('UPPER(TRIM(codigo_vendedor)) = ?', [$codigoVendedor])
                ->when($empleado, fn ($q) => $q->where('id', '!=', $empleado->id))
                ->exists();

            if ($tomado) {
                throw ValidationException::withMessages([
                    'codigo_vendedor' => 'Ese código de vendedor ya está asignado a otro empleado.',
                ]);
            }
        }

        return [
            'cliente_id' => $cliente->id,
            'user_id' => ($data['user_id'] ?? null) ?: null,
            'email' => ($data['email'] ?? null) ?: null,
            'telefono' => ($data['telefono'] ?? null) ?: null,
            'fecha_ingreso' => ($data['fecha_ingreso'] ?? null) ?: null,
            'salario_base' => $data['salario_base'] ?? 0,
            'tipo_salario' => $data['tipo_salario'] ?? 'QUINCENAL',
            'estado' => $data['estado'] ?? 'ACTIVO',
            'sede_id' => ($data['sede_id'] ?? null) ?: null,
            'cargo_id' => ($data['cargo_id'] ?? null) ?: null,
            'supervisor_id' => $supervisorIds[0] ?? null,
            'es_supervisor' => (bool) ($data['es_supervisor'] ?? false),
            'es_servicio_tecnico' => (bool) ($data['es_servicio_tecnico'] ?? false),
            'modo_comision' => $data['modo_comision'] ?? $empleado?->modo_comision ?? NominaEmpleado::COMISION_NINGUNA,
            'codigo_vendedor' => $codigoVendedor,
        ];
    }

    private function resolveCliente(array $data, ?NominaEmpleado $empleado = null): Cliente
    {
        if (! empty($data['cliente_id'])) {
            $cliente = Cliente::query()->find($data['cliente_id']);
            if (! $cliente) {
                throw ValidationException::withMessages([
                    'cliente_id' => 'La persona no existe en clientes.',
                ]);
            }

            $this->assertClienteLibre($cliente, $empleado);

            return $cliente;
        }

        $cedula = trim((string) ($data['cedula'] ?? ''));
        $nombre = trim((string) ($data['nombre'] ?? ''));

        if ($cedula === '' || $nombre === '') {
            throw ValidationException::withMessages([
                'cedula' => 'Seleccione una persona de clientes, o indique cédula y nombre.',
            ]);
        }

        $cliente = Cliente::query()->where('cedula', $cedula)->first();

        if ($cliente) {
            $this->assertClienteLibre($cliente, $empleado);
            $cliente->update(['nombre' => $nombre]);

            return $cliente;
        }

        return Cliente::create([
            'cedula' => $cedula,
            'nombre' => $nombre,
        ]);
    }

    private function assertClienteLibre(Cliente $cliente, ?NominaEmpleado $empleado = null): void
    {
        $tomado = NominaEmpleado::query()
            ->where('cliente_id', $cliente->id)
            ->when($empleado, fn ($q) => $q->where('id', '!=', $empleado->id))
            ->exists();

        if ($tomado) {
            throw ValidationException::withMessages([
                'cliente_id' => 'Esa persona ya tiene ficha de empleado.',
                'cedula' => 'Ya existe un empleado con esa cédula.',
            ]);
        }
    }

    private function syncDenormalized(NominaEmpleado $empleado): void
    {
        $sede = $empleado->sede_id ? NominaSede::find($empleado->sede_id) : null;
        $cargo = $empleado->cargo_id ? NominaCargo::find($empleado->cargo_id) : null;

        $empleado->sede = $sede?->codigo;
        $empleado->cargo = $cargo?->nombre;
        $empleado->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function idsJefes(array $data, NominaEmpleado $empleado): array
    {
        $ids = $data['supervisor_ids'] ?? [];
        if ($ids === [] && ! empty($data['supervisor_id'])) {
            $ids = [$data['supervisor_id']];
        }

        return $this->organization->normalizarJefes(
            is_array($ids) ? $ids : [$ids],
            $empleado->id
        );
    }
}
