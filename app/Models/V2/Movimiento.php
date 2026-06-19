<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Movimiento extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'movimientos';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'producto_id',
        'origen',
        'destino',
        'tipo',
        'cantidad',
        'usuario',
        'metadata',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
