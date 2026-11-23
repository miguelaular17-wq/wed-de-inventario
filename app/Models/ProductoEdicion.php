<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoEdicion extends Model
{
    public const CAMPOS = [
        'nombre',
        'categoria',
        'subcategoria',
        'proveedor',
        'precio_unidad',
        'precio_mayor',
        'lote_inicial',
        'url_imagen',
        'descripcion_ampliada',
    ];

    protected $table = 'producto_ediciones';

    protected $fillable = [
        'producto_id',
        'codigo',
        'nombre',
        'categoria',
        'subcategoria',
        'proveedor',
        'precio_unidad',
        'precio_mayor',
        'lote_inicial',
        'url_imagen',
        'descripcion_ampliada',
        'editado_by',
    ];

    protected $casts = [
        'precio_unidad' => 'decimal:2',
        'precio_mayor' => 'decimal:2',
        'lote_inicial' => 'integer',
    ];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editado_by');
    }

    public function tieneAlgunaEdicion(): bool
    {
        foreach (self::CAMPOS as $campo) {
            if ($this->$campo !== null && $this->$campo !== '') {
                return true;
            }
        }

        return false;
    }

    /** Campos con override activo. */
    public function camposEditados(): array
    {
        $out = [];
        foreach (self::CAMPOS as $campo) {
            if ($this->$campo !== null && $this->$campo !== '') {
                $out[] = $campo;
            }
        }

        return $out;
    }
}
