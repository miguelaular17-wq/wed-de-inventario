<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StEquipoEvento extends Model
{
    public const TIPO_REGISTRO = 'registro';
    public const TIPO_ENVIO = 'envio';
    public const TIPO_RECEPCION = 'recepcion';
    public const TIPO_DIAGNOSTICO = 'diagnostico';
    public const TIPO_REPARACION = 'reparacion';
    public const TIPO_ENTREGA = 'entrega';
    public const TIPO_BACKUP_ENTREGADO = 'backup_entregado';
    public const TIPO_BACKUP_DEVUELTO = 'backup_devuelto';
    public const TIPO_NOTA = 'nota';
    public const TIPO_ESTADO = 'estado';

    public const TIPOS = [
        self::TIPO_REGISTRO => 'Ingreso / registro',
        self::TIPO_ENVIO => 'Envío entre sedes',
        self::TIPO_RECEPCION => 'Recepción en ST',
        self::TIPO_DIAGNOSTICO => 'Diagnóstico',
        self::TIPO_REPARACION => 'Reparación',
        self::TIPO_ENTREGA => 'Entrega',
        self::TIPO_BACKUP_ENTREGADO => 'Backup entregado',
        self::TIPO_BACKUP_DEVUELTO => 'Backup devuelto',
        self::TIPO_NOTA => 'Nota',
        self::TIPO_ESTADO => 'Cambio de estado',
    ];

    public const ICONOS = [
        self::TIPO_REGISTRO => '📥',
        self::TIPO_ENVIO => '🚚',
        self::TIPO_RECEPCION => '📦',
        self::TIPO_DIAGNOSTICO => '🔎',
        self::TIPO_REPARACION => '🔧',
        self::TIPO_ENTREGA => '✅',
        self::TIPO_BACKUP_ENTREGADO => '📱',
        self::TIPO_BACKUP_DEVUELTO => '↩️',
        self::TIPO_NOTA => '📝',
        self::TIPO_ESTADO => '🔄',
    ];

    public $timestamps = false;

    protected $table = 'st_equipo_eventos';

    protected $fillable = [
        'equipo_id',
        'orden_id',
        'backup_id',
        'user_id',
        'sede',
        'tipo',
        'titulo',
        'descripcion',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(StEquipo::class, 'equipo_id');
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(StOrden::class, 'orden_id');
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(StBackup::class, 'backup_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function icono(): string
    {
        return self::ICONOS[$this->tipo] ?? '•';
    }

    public function etiquetaTipo(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }
}
