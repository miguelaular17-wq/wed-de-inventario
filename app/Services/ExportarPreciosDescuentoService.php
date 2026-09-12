<?php

namespace App\Services;

use App\Models\V2\Producto;
use App\Support\SimpleXlsxWriter;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExportarPreciosDescuentoService
{
    public const CATEGORIAS_EXCLUIDAS = [
        'ACCESORIOS DE BEBE',
        'ACCESORIOS DE NIÑO',
        'BEBE',
        'BIOSEGURIDAD',
        'BISUTERIA',
        'BODYS',
        'CARTERAS Y BOLSOS',
        'CONJUNTOS',
        'DECORACION',
        'DECORACION Y ARREGLOS',
        'LENCERIA',
        'LIQUIDACION',
        'MOVISTAR',
        'PANTALONES',
        'PRODUCTOS DE CONSUMO',
        'SIN CATEGORÍA',
        'TEXTILES',
        'UNIFORMES',
        '',
    ];

    /**
     * @return array{0: string, 1: string} binary content and download filename
     */
    public function generarXlsx(float $descuento = 25.0): array
    {
        if ($descuento < 0 || $descuento > 100) {
            throw new RuntimeException('El descuento debe estar entre 0 y 100.');
        }

        $query = Producto::query()
            ->select(['id', 'nombre', 'precio_unidad'])
            ->where('activo', true)
            ->where(function ($query) {
                $query->whereNull('oculto')->orWhere('oculto', false);
            })
            ->whereHas('stock', function ($query) {
                $query->where('existencia', '>', 0);
            })
            ->whereNotIn(
                DB::raw("UPPER(TRIM(COALESCE(categoria, '')))"),
                self::CATEGORIAS_EXCLUIDAS
            )
            ->orderBy('nombre');

        $descuentoTxt = rtrim(rtrim(number_format($descuento, 2, '.', ''), '0'), '.');
        $rows = [[
            'Nombre del Producto',
            'Precio 1 (Costo por Unidad)',
            'Precio 3 (Descuento '.$descuentoTxt.'%)',
        ]];

        foreach ($query->cursor() as $producto) {
            $precio = round((float) $producto->precio_unidad, 2);
            $rows[] = [
                (string) $producto->nombre,
                $precio,
                round($precio * (1 - $descuento / 100), 2),
            ];
        }

        if (count($rows) === 1) {
            throw new RuntimeException('No se encontraron productos con existencia bajo esos filtros.');
        }

        $xlsx = SimpleXlsxWriter::toString(['Productos' => $rows]);
        $filename = 'Productos_Con_Existencia_Descuento_'.$descuentoTxt.'_'.now()->format('Ymd_His').'.xlsx';

        return [$xlsx, $filename];
    }
}
