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
            ->whereRaw(self::SOLO_CARGA_MAS_RECIENTE);
    }

    /**
     * En cada carga la cabecera (monto/saldo) es la primera fila de su factura y le siguen sus
     * renglones y abonos. Si dos sincronizaciones se cruzaron y la factura quedó dos veces en el
     * corte, se descarta todo lo anterior a la cabecera más reciente.
     */
    private const SOLO_CARGA_MAS_RECIENTE = "NOT EXISTS (
        SELECT 1 FROM historial_cobranzas h2
        WHERE h2.fecha_registro = historial_cobranzas.fecha_registro
          AND h2.sede_nombre = historial_cobranzas.sede_nombre
          AND h2.codigo_cliente = historial_cobranzas.codigo_cliente
          AND h2.id_documento IS NOT DISTINCT FROM historial_cobranzas.id_documento
          AND (h2.monto_neto > 0 OR h2.saldo > 0)
          AND h2.id > historial_cobranzas.id
          AND (COALESCE(historial_cobranzas.tipo_fila, 1) = 2 OR h2.tipo_cxc IS NOT DISTINCT FROM historial_cobranzas.tipo_cxc)
    )";

    public function scopeCabeceras($query)
    {
        return $query->where('monto_neto', '>', 0);
    }
}
