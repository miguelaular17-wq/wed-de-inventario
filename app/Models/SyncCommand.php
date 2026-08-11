<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class SyncCommand extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'inventario_v2.sync_commands';
    protected $guarded    = [];

    protected $casts = [
        'parametros'   => 'array',
        'ejecutado_at' => 'datetime',
    ];

    const LABELS = [
        'sync_stock'     => 'Stock / Inventario',
        'sync_precios'   => 'Precios',
        'sync_cobranzas' => 'Cobranzas',
        'sync_compras'   => 'Compras',
    ];

    const ESTADO_COLORS = [
        'pendiente'  => '#f59e0b',
        'ejecutando' => '#3b82f6',
        'completado' => '#22c55e',
        'error'      => '#ef4444',
    ];

    const ESTADO_ICONS = [
        'pendiente'  => '⏳',
        'ejecutando' => '⚙️',
        'completado' => '✅',
        'error'      => '❌',
    ];

    public function scopePendientes(Builder $q): Builder
    {
        return $q->where('estado', 'pendiente');
    }

    public function scopeRecientes(Builder $q): Builder
    {
        return $q->orderBy('created_at', 'desc')->limit(30);
    }

    public function getLabelAttribute(): string
    {
        return self::LABELS[$this->comando] ?? $this->comando;
    }

    public function getColorAttribute(): string
    {
        return self::ESTADO_COLORS[$this->estado] ?? '#64748b';
    }

    public function getIconAttribute(): string
    {
        return self::ESTADO_ICONS[$this->estado] ?? '•';
    }
}
