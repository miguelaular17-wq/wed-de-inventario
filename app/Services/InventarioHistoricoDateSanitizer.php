<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventarioHistoricoDateSanitizer
{
    /**
     * @return array{ventas_corregidas: int, compras_corregidas: int, ventas_nulas: int, compras_nulas: int}
     */
    public function run(): array
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('ventas_historicas')) {
            return [
                'ventas_corregidas' => 0,
                'compras_corregidas' => 0,
                'ventas_nulas' => 0,
                'compras_nulas' => 0,
            ];
        }

        $ventasCorregidas = 0;
        if (Schema::hasTable('ventas_detalle')) {
            $ventasCorregidas = (int) DB::connection('pgsql')->update("
                UPDATE inventario_v2.ventas_historicas vh
                SET ultima_venta = src.max_fecha,
                    updated_at = NOW()
                FROM (
                    SELECT producto_id, sede, MAX(fecha)::date AS max_fecha
                    FROM inventario_v2.ventas_detalle
                    WHERE tipo_documento = 'FAC'
                      AND producto_id IS NOT NULL
                      AND fecha >= DATE '1990-01-01'
                      AND fecha <= CURRENT_DATE + 1
                    GROUP BY producto_id, sede
                ) src
                WHERE vh.producto_id = src.producto_id
                  AND vh.sede = src.sede
                  AND (
                    vh.ultima_venta IS NULL
                    OR vh.ultima_venta < DATE '1990-01-01'
                    OR vh.ultima_venta > CURRENT_DATE + 1
                  )
            ");
        }

        $ventasNulas = (int) DB::connection('pgsql')->update("
            UPDATE inventario_v2.ventas_historicas
            SET ultima_venta = NULL, updated_at = NOW()
            WHERE ultima_venta IS NOT NULL
              AND (ultima_venta < DATE '1990-01-01' OR ultima_venta > CURRENT_DATE + 1)
        ");

        $comprasNulas = 0;
        if (Schema::hasColumn('ventas_historicas', 'ultima_compra')) {
            $comprasNulas = (int) DB::connection('pgsql')->update("
                UPDATE inventario_v2.ventas_historicas
                SET ultima_compra = NULL, updated_at = NOW()
                WHERE ultima_compra IS NOT NULL
                  AND (ultima_compra < DATE '1990-01-01' OR ultima_compra > CURRENT_DATE + 1)
            ");
        }

        return [
            'ventas_corregidas' => $ventasCorregidas,
            'compras_corregidas' => 0,
            'ventas_nulas' => $ventasNulas,
            'compras_nulas' => $comprasNulas,
        ];
    }
}
