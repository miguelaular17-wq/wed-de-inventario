<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Factura de Profit que se cobra desde la web. Su saldo = saldo_inicial (Profit el día del primer abono)
 * menos los ingresos del flujo de caja de Cobranza vinculados a ella.
 */
class CobranzaFactura extends Model
{
    public const VINCULO = 'cobranza_factura';

    protected $table = 'cobranza_facturas';

    protected $fillable = [
        'sede_nombre', 'id_documento', 'numero_documento', 'tipo_cxc', 'codigo_cliente', 'nombre_cliente',
        'fecha_emision', 'monto_neto', 'saldo_inicial', 'pagada_web',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'monto_neto' => 'decimal:2',
        'saldo_inicial' => 'decimal:2',
        'pagada_web' => 'boolean',
    ];

    /** Lo que guarda «Pagado» de Cobranza (cobranzas_pagadas_manualmente) para esta factura. */
    public function claveDocumento(): string
    {
        return trim((string) ($this->numero_documento ?: $this->id_documento));
    }

    public function etiqueta(): string
    {
        return trim(($this->tipo_cxc ?: 'FAC').' '.($this->numero_documento ?: $this->id_documento)).' · '.$this->nombre_cliente;
    }
}
