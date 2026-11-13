<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Persona que responde por varios contratos (intermediario o responsable): tiene un estado de cuenta agrupado. */
class ContratoIntermediario extends Model
{
    protected $table = 'contrato_intermediarios';

    protected $fillable = ['nombre', 'documento', 'telefono', 'observaciones'];

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class, 'intermediario_id');
    }
}
