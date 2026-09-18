<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NfcRecompensa extends Model
{
    protected $table = 'nfc_recompensas';

    protected $fillable = [
        'nombre',
        'descripcion',
        'puntos_costo',
        'activa',
        'orden',
    ];

    protected $casts = [
        'puntos_costo' => 'integer',
        'activa' => 'boolean',
        'orden' => 'integer',
    ];

    public function movimientos(): HasMany
    {
        return $this->hasMany(NfcMovimiento::class, 'nfc_recompensa_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('activa', true)->orderBy('orden')->orderBy('nombre');
    }
}
