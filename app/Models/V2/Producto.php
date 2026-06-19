<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'productos';

    protected $fillable = [
        'codigo',
        'nombre',
        'categoria',
        'subcategoria',
        'proveedor',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function stock(): HasMany
    {
        return $this->hasMany(StockActual::class, 'producto_id');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(VentaHistorica::class, 'producto_id');
    }
}
