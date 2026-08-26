<?php

namespace App\Support;

class ProfitMotivos
{
    /** @var array<string, array<string, string>> */
    public const CATALOGO = [
        'DEV' => [
            '01' => 'AUTORIZADO GERENCIA',
            '02' => 'PRODUCTO DEFECTUOSO',
            '03' => 'PRODUCTO VENCIDO',
            '04' => 'CLIENTE NO LLEVA COMPRA',
            '05' => 'GARANTÍA',
            '06' => 'ERROR EN PRECIO',
            '07' => 'CAMBIO DE PRODUCTO',
        ],
        'INV-AJU' => [
            '01' => 'MERCANCÍA DAÑADA PROVEEDOR',
            '02' => 'HURTO INTERNO',
            '03' => 'HURTO EXTERNO',
            '04' => 'VENCIDO',
            '05' => 'ERROR DE CONTEO',
            '06' => 'MERCANCIA DAÑADA TIENDA',
            '07' => 'ROBO O ATRACO',
            '08' => 'USO INTERNO',
            '09' => 'ERROR EN VENTA',
            '10' => 'SIN DIFERENCIA',
            '11' => 'DEGUSTACION/TESTER',
            '12' => 'LIQUIDACION',
            '13' => 'ERROR DE AJUSTE',
            '14' => 'SIN REPARACION SERVICIO TECNICO',
            '16' => 'AUDITORIA',
            '17' => 'ERROR EN DESPACHO',
            '18' => 'ERROR DE SISTEMA',
            '19' => 'DIFERENCIAS POR VALIDAR',
        ],
        'INV-TRA' => [
            '001' => 'TRASLADO ALMACENES',
        ],
        'INV-CAR' => [
            '01' => 'MERCANCIA REPARADA SERVICIO TECNICO MOVISTAR',
            '02' => 'SOBRANTE MERCANCIA SIN IDENTIFICAR',
            '03' => 'REGALO PROVEEDORES',
            '04' => 'MERCANCIA REPARADA SERVICIO TECNICO CENTRO',
            '05' => 'INSUMOS',
            '06' => 'INGRESO DE IPHONE POR METODO DE PAGO',
        ],
        'INV-DES' => [
            '01' => 'ERROR EN CARGO',
        ],
        'INV-CARDES' => [
            '01' => 'DIFERENTE BARRA',
            '02' => 'DESGLOSE DE UNIDADES',
            '03' => 'SEGMENTACION DE COGIDOS',
        ],
    ];

    public static function devolucion(?string $raw): string
    {
        return self::etiqueta($raw, 'DEV');
    }

    public static function ajuste(?string $raw, ?string $tipoMovimiento = null): string
    {
        $modulos = [];
        $tipo = strtoupper(trim((string) $tipoMovimiento));
        if ($tipo !== '') {
            $modulos[] = 'INV-'.$tipo;
        }
        $modulos = array_merge($modulos, ['INV-AJU', 'INV-TRA', 'INV-CAR', 'INV-DES', 'INV-CARDES']);

        return self::etiqueta($raw, $modulos);
    }

    /**
     * @param  string|list<string>  $modulos
     */
    public static function etiqueta(?string $raw, string|array $modulos): string
    {
        $valor = self::limpiar($raw);
        if ($valor === '' || self::esPlaceholder($valor)) {
            return 'Sin motivo';
        }
        if (! self::pareceCodigo($valor)) {
            return $valor;
        }

        foreach ((array) $modulos as $modulo) {
            $catalogo = self::CATALOGO[$modulo] ?? [];
            if (isset($catalogo[$valor])) {
                return $catalogo[$valor];
            }
            $pad = str_pad($valor, 2, '0', STR_PAD_LEFT);
            if (isset($catalogo[$pad])) {
                return $catalogo[$pad];
            }
        }

        return $valor;
    }

    public static function limpiar(?string $raw): string
    {
        $valor = trim(preg_replace('/\s+/u', ' ', str_replace(["\r", "\n"], ' ', (string) $raw)) ?? '');

        return $valor;
    }

    public static function esPlaceholder(string $valor): bool
    {
        $norm = mb_strtolower($valor, 'UTF-8');

        return in_array($norm, [
            'sin motivo',
            'sin descripción',
            'sin descripcion',
            'seleccione un motivo...',
            'seleccione un motivo',
        ], true);
    }

    public static function pareceCodigo(string $valor): bool
    {
        return (bool) preg_match('/^\d{1,3}$/', $valor);
    }
}
