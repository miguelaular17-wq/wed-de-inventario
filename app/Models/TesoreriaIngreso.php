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
        'fecha',
        'monto',
        'lote_referencia',
        'descripcion',
        'comprobante_path',
        'user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
