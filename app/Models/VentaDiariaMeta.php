<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VentaDiariaMeta extends Model
{
    protected $table = 'venta_diaria_metas';

    protected $fillable = [
        'sede',
        'meta_venta_lv_sab',
        'meta_venta_domingo',
        'meta_prod_lv_sab',
        'meta_prod_domingo',
        'venta_historica',
        'venta_meta_mes',
        'productos_meta_mes',
        'clientes_meta_mes',
        'periodo_label',
    ];

    protected $casts = [
        'meta_venta_lv_sab' => 'decimal:4',
        'meta_venta_domingo' => 'decimal:4',
        'meta_prod_lv_sab' => 'decimal:4',
        'meta_prod_domingo' => 'decimal:4',
        'venta_historica' => 'decimal:2',
        'venta_meta_mes' => 'decimal:2',
        'productos_meta_mes' => 'decimal:2',
        'clientes_meta_mes' => 'decimal:2',
    ];
}
