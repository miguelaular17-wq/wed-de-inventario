<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Persona que intermedia la factura (id_documento) de otro cliente. */
class CobranzaIntermediario extends Model
{
    protected $connection = 'pgsql';

    protected $table = 'cobranza_intermediarios';

    protected $fillable = ['id_documento', 'codigo_cliente', 'codigo_intermediario', 'nombre_intermediario', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
