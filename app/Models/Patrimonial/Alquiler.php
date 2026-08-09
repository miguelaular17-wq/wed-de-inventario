<?php
namespace App\Models\Patrimonial;

use Illuminate\Database\Eloquent\Model;

class Alquiler extends Model
{
    protected $table = 'pat_alquileres';

    protected $fillable = [
        'propiedad_id', 'inquilino_nombre', 'inquilino_contacto',
        'contrato_nro', 'fecha_inicio', 'fecha_fin', 'tipo_canon',
        'canon_mensual', 'canon_quincenal', 'dia_pago', 'forma_pago',
        'estado', 'observaciones',
    ];

    protected $casts = [
        'fecha_inicio'     => 'date',
        'fecha_fin'        => 'date',
        'canon_mensual'    => 'decimal:2',
        'canon_quincenal'  => 'decimal:2',
    ];

    public function propiedad()
    {
        return $this->belongsTo(Propiedad::class, 'propiedad_id');
    }

    public function pagos()
    {
        return $this->hasMany(AlquilerPago::class, 'alquiler_id');
    }

    public function canonActual(): float
    {
        return $this->tipo_canon === 'quincenal'
            ? (float)($this->canon_quincenal ?? 0)
            : (float)($this->canon_mensual ?? 0);
    }
}
