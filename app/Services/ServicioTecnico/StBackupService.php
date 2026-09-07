<?php

namespace App\Services\ServicioTecnico;

use App\Models\StBackup;
use App\Models\StEquipo;
use App\Models\StEquipoEvento;
use App\Models\StOrden;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StBackupService
{
    public function __construct(
        private readonly StEquipoService $equipos,
    ) {}

    /**
     * @param  array{
     *   marca?:?string,modelo?:?string,imei?:?string,serial?:?string,
     *   estado_fisico?:?string,accesorios?:?string,condiciones?:?string,
     *   firma_cliente?:?string,firma_empleado?:?string
     * }  $datos
     */
    public function entregar(StOrden $orden, array $datos, User $user): StBackup
    {
        return DB::transaction(function () use ($orden, $datos, $user) {
            $condiciones = trim((string) ($datos['condiciones'] ?? ''));
            if ($condiciones === '') {
                $condiciones = (string) config('servicio_tecnico.backup_condiciones');
            }

            $backup = StBackup::create([
                'orden_id' => $orden->id,
                'equipo_cliente_id' => $orden->equipo_id,
                'marca' => $datos['marca'] ?? null,
                'modelo' => $datos['modelo'] ?? null,
                'imei' => $this->equipos->normalizarImei($datos['imei'] ?? null),
                'serial' => $this->equipos->normalizarSerial($datos['serial'] ?? null),
                'estado_fisico' => $datos['estado_fisico'] ?? null,
                'accesorios' => $datos['accesorios'] ?? null,
                'condiciones' => $condiciones,
                'firma_cliente' => $datos['firma_cliente'] ?? null,
                'firma_empleado' => $datos['firma_empleado'] ?? ($user->name),
                'estado' => StBackup::ESTADO_ENTREGADO,
                'entregado_at' => now(),
                'created_by' => $user->id,
            ]);

            if ($orden->equipo_id && ($equipo = StEquipo::query()->find($orden->equipo_id))) {
                $this->equipos->registrarEvento(
                    $equipo,
                    $user,
                    StEquipoEvento::TIPO_BACKUP_ENTREGADO,
                    'Celular de backup entregado',
                    trim(($backup->marca.' '.$backup->modelo) ?: 'Equipo de préstamo')
                        .' · IMEI '.($backup->imei ?: '—'),
                    $orden,
                    (string) $orden->sede,
                    [
                        'backup_id' => $backup->id,
                        'imei' => $backup->imei,
                        'serial' => $backup->serial,
                    ],
                    $backup->id
                );
            }

            return $backup;
        });
    }
}
