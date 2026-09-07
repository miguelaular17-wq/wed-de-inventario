<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StBackup extends Model
{
    public const ESTADO_ENTREGADO = 'entregado';
    public const ESTADO_DEVUELTO = 'devuelto';

    protected $table = 'st_backups';

    protected $fillable = [
        'orden_id',
        'equipo_cliente_id',
        'marca',
        'modelo',
        'imei',
        'serial',
        'estado_fisico',
        'accesorios',
        'condiciones',
        'firma_cliente',
        'firma_empleado',
        'estado',
        'entregado_at',
        'devuelto_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entregado_at' => 'datetime',
            'devuelto_at' => 'datetime',
        ];
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(StOrden::class, 'orden_id');
    }

    public function equipoCliente(): BelongsTo
    {
        return $this->belongsTo(StEquipo::class, 'equipo_cliente_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
