<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockActual extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'stock_actual';

    public $timestamps = false;

    const UPDATED_AT = 'updated_at';

    protected $fillable = ['producto_id', 'sede', 'existencia'];

    protected $casts = [
        'existencia' => 'integer',
        'updated_at' => 'datetime',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
