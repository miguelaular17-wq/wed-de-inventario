<?php

namespace App\Models\Mayor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MayorFacturaItem extends Model
{
    protected $table = 'mayor_factura_items';

    protected $fillable = ['factura_id', 'producto_id', 'codigo', 'descripcion', 'cantidad', 'presentacion', 'unidades_por', 'unidades', 'precio_usd', 'total_usd'];

    protected $casts = [
        'cantidad' => 'integer',
        'unidades_por' => 'integer',
        'unidades' => 'integer',
        'precio_usd' => 'float',
        'total_usd' => 'float',
    ];

    /** «3 cajas (x12)» o «5 und.». */
    public function cantidadTexto(): string
    {
        $presentacion = (string) ($this->presentacion ?: 'unidad');
        $texto = MayorCatalogo::cantidadTexto((int) $this->cantidad, $presentacion);

        return $presentacion !== 'unidad' && (int) $this->unidades_por > 1 ? $texto.' (x'.$this->unidades_por.')' : $texto;
    }

    public function unidadesTotales(): int
    {
        return (int) ($this->unidades ?? $this->cantidad);
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(MayorFactura::class, 'factura_id');
    }
}
