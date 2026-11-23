<?php

/*
 * Encabezado del recibo de pago del flujo de Cobranza.
 * - Cobranza → Doral (membrete fijo).
 * - Contratos y Patrimonial → Je Nu (membrete fijo).
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
        'JENU' => [
            'rif' => 'J-50255135-2',
            'razon_social' => 'Grupo Inmobiliario y de Transporte Je Nu & Asociados, C.A.',
            'direccion' => [
                'Calle Girardot, con Av. Santa Irene, Punto Fijo - Edo. Falcon',
                'Zona Postal 4102',
            ],
            'telefono' => '0412-6937658',
            'leyenda' => null,
        ],
    ],

    /** Solo Cobranza usa Doral. */
    'cobranza' => 'DORAL',

    /** Contratos y Patrimonial usan Je Nu. */
    'otros' => 'JENU',

    'por_defecto' => 'JENU',
];
