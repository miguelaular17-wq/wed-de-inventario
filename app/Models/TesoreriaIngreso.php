<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TesoreriaIngreso extends Model
{
    use HasFactory;

    protected $fillable = [
        'tipo',
        'banco',
        'titular',
        'fecha',
        'monto',
        'lote_referencia',
        'descripcion',
        'comprobante_path',
        'user_id',
        'es_conciliado',
        'conciliar_en',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Movimiento del extracto bancario con el que se concilió. */
    public function lineaBanco()
    {
        return $this->hasOne(ConciliacionLinea::class, 'tesoreria_ingreso_id');
    }
}
