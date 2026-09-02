<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StRepuesto extends Model
{
    protected $table = 'st_repuestos';

    protected $fillable = [
        'sede',
        'codigo',
        'nombre',
        'categoria',
        'stock',
        'stock_min',
        'costo',
        'precio_venta',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'stock' => 'integer',
            'stock_min' => 'integer',
            'costo' => 'decimal:2',
            'precio_venta' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(StMovimientoRepuesto::class, 'repuesto_id');
    }

    public function bajoStock(): bool
    {
        return $this->stock_min > 0 && $this->stock <= $this->stock_min;
    }

    public function etiquetaCategoria(): string
    {
        $key = $this->categoria;
        if (! $key) {
            return '—';
        }

        $label = config('servicio_tecnico.categorias_reparacion.'.$key);
        if (! $label) {
            return $key;
        }

        $icon = config('servicio_tecnico.categorias_reparacion_iconos.'.$key, '📦');

        return $icon.' '.$label;
    }

    public function scopeVisiblePara(Builder $query, User $user): Builder
    {
        if ($user->scopesServicioToOwnSede()) {
            return $query->where('sede', strtoupper((string) $user->sede));
        }

        return $query;
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
