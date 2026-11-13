<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Fotos adicionales de un producto (la principal sigue siendo imagenes/{codigo}.jpg). */
class ProductoImagen extends Model
{
    public const MAXIMO_EXTRA = 5;

    protected $table = 'producto_imagenes';

    protected $fillable = ['codigo', 'archivo', 'url', 'orden', 'user_id'];

    protected $casts = ['orden' => 'integer'];
}
