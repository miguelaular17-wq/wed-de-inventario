<?php
namespace App\Models\Patrimonial;

use Illuminate\Database\Eloquent\Model;

class Llave extends Model
{
    protected $table = 'pat_llaves';

    protected $fillable = [
        'propiedad_id', 'descripcion', 'ubicacion_actual',
        'responsable', 'fecha_entrega', 'fecha_devolucion', 'observaciones',
    ];

    protected $casts = [
        'fecha_entrega'    => 'date',
        'fecha_devolucion' => 'date',
    ];

    public function propiedad()
    {
        return $this->belongsTo(Propiedad::class, 'propiedad_id');
    }
}
