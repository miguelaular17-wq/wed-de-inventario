<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Builder;
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
        'oculto',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'oculto' => 'boolean',
    ];

    /** Activos y no ocultos: lo que debe verse en catálogos, stock y búsquedas. */
    public function scopeVisibles(Builder $query): Builder
    {
        return $query->where('activo', true)
            ->where(fn (Builder $q) => $q->where('oculto', false)->orWhereNull('oculto'));
    }

    /** Fragmento SQL para consultas raw: excluye productos ocultos. */
    public static function sqlNoOculto(string $alias = 'p'): string
    {
        return "({$alias}.oculto = false OR {$alias}.oculto IS NULL)";
    }

    public function stock(): HasMany
    {
        return $this->hasMany(StockActual::class, 'producto_id');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(VentaHistorica::class, 'producto_id');
    }
}
