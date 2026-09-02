<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StReparacion extends Model
{
    protected $table = 'st_reparaciones';

    protected $fillable = [
        'sede',
        'tipo',
        'cliente_nombre',
        'cliente_telefono',
        'producto',
        'categoria',
        'comprobante_venta',
        'falla',
        'accion',
        'repuestos_texto',
        'costo_interno',
        'estado',
        'observaciones',
        'tecnico_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'costo_interno' => 'decimal:2',
        ];
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function etiquetaTipo(): string
    {
        return config('servicio_tecnico.tipos_reparacion.'.$this->tipo, $this->tipo);
    }

    public function etiquetaEstado(): string
    {
        return config('servicio_tecnico.estados_reparacion.'.$this->estado, $this->estado);
    }

    public function etiquetaAccion(): string
    {
        return config('servicio_tecnico.acciones_reparacion.'.$this->accion, $this->accion);
    }

    public function etiquetaCategoria(): string
    {
        $key = $this->categoria;
        $label = config('servicio_tecnico.categorias_reparacion.'.$key, $key);
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
}
