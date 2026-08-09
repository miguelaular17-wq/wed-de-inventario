<?php
namespace App\Models\Patrimonial;

use Illuminate\Database\Eloquent\Model;

class PatTransaccion extends Model
{
    protected $table = 'pat_transacciones';

    protected $fillable = [
        'propiedad_id', 'tipo', 'categoria', 'descripcion',
        'monto', 'moneda', 'mes', 'anio', 'fecha', 'observaciones',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:2',
    ];

    public function propiedad()
    {
        return $this->belongsTo(Propiedad::class, 'propiedad_id');
    }

    public static function categorias(): array
    {
        return [
            'ingreso'   => ['Alquiler', 'Reserva temporal', 'Depósito', 'Otro ingreso'],
            'gasto'     => ['Reparación', 'Mantenimiento', 'Electricidad', 'Agua', 'Condominio',
                            'Limpieza', 'Remodelación', 'Servicios', 'Impuestos', 'Otro gasto'],
            'comision'  => ['Comisión plataforma', 'Comisión administración', 'Otro descuento'],
        ];
    }
}
