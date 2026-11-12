<?php

/*
 * Encabezado del recibo de pago de Cobranza: datos fiscales de la empresa de cada sede.
 * La sede sale de la factura de Profit que se abona; Contratos y Patrimonial usan «por_defecto».
 * Una sede sin datos muestra solo su nombre.
 */
return [
    'empresas' => [
        'DORAL' => [
            'rif' => 'J-40172229-6',
            'razon_social' => 'INVERSIONES DORAL PARAGUANA, C.A.',
            'direccion' => [
                'AV GIRARDOT CC EMPRESARIAL DORAL NIVEL',
                'PB LOCAL N° 09 URB SANTA IRENE',
                'PUNTO FIJO FALCON ZONA POSTAL 4102',
            ],
            'leyenda' => 'CONTRIBUYENTE FORMAL ESPECIAL',
        ],
    ],

    'por_defecto' => null,
];
