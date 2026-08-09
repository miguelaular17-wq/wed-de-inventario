<?php
namespace App\Models\Patrimonial;

use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    protected $table = 'pat_reservas';

    protected $fillable = [
        'propiedad_id', 'cliente_nombre', 'cliente_contacto',
        'fecha_entrada', 'fecha_salida', 'precio_noche',
        'estado', 'moneda', 'observaciones',
    ];

    protected $casts = [
        'fecha_entrada' => 'date',
        'fecha_salida'  => 'date',
        'precio_noche'  => 'decimal:2',
    ];

    public function propiedad()
    {
        return $this->belongsTo(Propiedad::class, 'propiedad_id');
    }

    public function getNoches(): int
    {
        if (!$this->fecha_entrada || !$this->fecha_salida) return 0;
        return (int) $this->fecha_entrada->diffInDays($this->fecha_salida);
    }

    public function getTotal(): float
    {
        return $this->getNoches() * (float)($this->precio_noche ?? 0);
    }
}
