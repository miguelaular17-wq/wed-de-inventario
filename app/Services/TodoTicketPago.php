<?php

namespace App\Services;

class TodoTicketPago
{
    /**
     * Total Real = recarga + comisión + IVA − retenciones (valores absolutos).
     */
    public static function totalReal(array $detalle): float
    {
        $recarga = (float) ($detalle['recarga'] ?? 0);
        $comision = (float) ($detalle['comision'] ?? 0);
        $iva = (float) ($detalle['iva'] ?? 0);
        $retenciones = abs((float) ($detalle['ret_islr'] ?? 0))
            + abs((float) ($detalle['ret_iva'] ?? 0))
            + abs((float) ($detalle['ret_1x1000'] ?? 0))
            + abs((float) ($detalle['ret_resp_social'] ?? 0))
            + abs((float) ($detalle['ret_isae'] ?? 0));

        return round($recarga + $comision + $iva - $retenciones, 2);
    }

    public static function normalizarDetalle(array $detalle): array
    {
        $keys = ['recarga', 'comision', 'iva', 'ret_islr', 'ret_iva', 'ret_1x1000', 'ret_resp_social', 'ret_isae'];
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = round((float) ($detalle[$key] ?? 0), 2);
        }
        $out['total_real'] = self::totalReal($out);

        return $out;
    }
}
