<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StOrdenEvento extends Model
{
    public const TIPO_CREADA = 'creada';
    public const TIPO_ESTADO = 'estado';
    public const TIPO_TRANSFERENCIA = 'transferencia';
    public const TIPO_REPUESTO = 'repuesto';
    public const TIPO_NOTA = 'nota';

    public $timestamps = false;

    protected $table = 'st_orden_eventos';

    protected $fillable = [
        'orden_id',
        'user_id',
        'tipo',
        'descripcion',
        'meta',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(StOrden::class, 'orden_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
