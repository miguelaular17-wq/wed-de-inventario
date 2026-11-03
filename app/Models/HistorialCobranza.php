<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistorialCobranza extends Model
{
    use HasFactory;

    protected $table = 'historial_cobranzas';
    
    protected $connection = 'pgsql';

    protected $guarded = [];

    public function scopeCuentasOperativas($query)
    {
        return $query
            ->whereRaw("UPPER(BTRIM(COALESCE(historial_cobranzas.codigo_cliente, ''))) NOT LIKE 'EXP%'")
            ->whereRaw(self::SIN_CABECERA_REPETIDA);
    }

    /**
     * Cada factura lleva monto/saldo en una sola fila por sede y corte; si dos sincronizaciones
     * se cruzan y la cabecera queda dos veces, solo cuenta la primera.
     */
    private const SIN_CABECERA_REPETIDA = "NOT (
        (historial_cobranzas.monto_neto > 0 OR historial_cobranzas.saldo > 0)
        AND EXISTS (
            SELECT 1 FROM historial_cobranzas h2
            WHERE h2.fecha_registro = historial_cobranzas.fecha_registro
              AND h2.sede_nombre = historial_cobranzas.sede_nombre
              AND h2.codigo_cliente = historial_cobranzas.codigo_cliente
              AND h2.tipo_cxc IS NOT DISTINCT FROM historial_cobranzas.tipo_cxc
              AND h2.numero_documento IS NOT DISTINCT FROM historial_cobranzas.numero_documento
              AND (h2.monto_neto > 0 OR h2.saldo > 0)
              AND h2.id < historial_cobranzas.id
        )
    )";

    public function scopeCabeceras($query)
    {
        return $query->where('monto_neto', '>', 0);
    }
}
