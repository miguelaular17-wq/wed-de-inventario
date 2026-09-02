<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StMovimientoRepuesto extends Model
{
    public const TIPO_ENTRADA = 'entrada';
    public const TIPO_SALIDA = 'salida';
    public const TIPO_AJUSTE = 'ajuste';

    public $timestamps = false;

    protected $table = 'st_movimientos_repuesto';

    protected $fillable = [
        'repuesto_id',
        'orden_id',
        'tipo',
        'cantidad',
        'stock_antes',
        'stock_despues',
        'motivo',
        'user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'stock_antes' => 'integer',
            'stock_despues' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function repuesto(): BelongsTo
    {
        return $this->belongsTo(StRepuesto::class, 'repuesto_id');
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
