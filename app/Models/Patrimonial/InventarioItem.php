<?php
namespace App\Models\Patrimonial;

use Illuminate\Database\Eloquent\Model;

class InventarioItem extends Model
{
    protected $table = 'pat_inventario_items';

    protected $fillable = [
        'propiedad_id', 'articulo', 'cantidad',
        'estado_articulo', 'observacion', 'fotos',
    ];

    protected $casts = [
        'fotos' => 'array',
    ];

    public function propiedad()
    {
        return $this->belongsTo(Propiedad::class, 'propiedad_id');
    }
}
