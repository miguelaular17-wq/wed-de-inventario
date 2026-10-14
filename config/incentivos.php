<?php

return [
    'modulos' => [
        'telefonia' => [
            'cargos' => ['asesor de venta telefonia'],
            'titulo' => 'Asesor de telefonía',
            'resumen' => 'El 100% es lo que vendió la tienda en el mes. La nota mezcla piso, venta cruzada y producto meta.',
            'indicadores' => [
                ['nombre' => 'Efectividad de piso', 'peso' => '30%', 'mide' => 'Promedio de la parte de cada trabajador en la venta, los productos y los clientes de su tienda en el mes.'],
                ['nombre' => 'Efectividad de venta cruzada', 'peso' => '50%', 'mide' => 'Unidades del cruce activo y la parte que representan sobre el cruce de la tienda.'],
                ['nombre' => 'Producto meta', 'peso' => '20%', 'mide' => '100% si cumplió el producto que se le asignó.'],
            ],
        ],
        'ventas' => [
            'cargos' => ['asesor de venta'],
            'titulo' => 'Asesor de ventas',
            'resumen' => 'El 100% es lo que vendió la tienda en el mes. La nota es 70% efectividad de piso y 30% producto meta.',
            'indicadores' => [
                ['nombre' => 'Efectividad en tienda', 'peso' => '70%', 'mide' => 'Promedio de la parte de cada trabajador en la venta, los productos y los clientes de su tienda en el mes.'],
                ['nombre' => 'Producto meta', 'peso' => '30%', 'mide' => '100% si cumplió el producto que se le asignó.'],
            ],
        ],
        'arreglos' => [
            'cargos' => ['arreglos', 'servicio tecnico'],
            'titulo' => 'Arreglo',
            'resumen' => 'Solo cuentan las categorías o productos activos. El 100% es lo que vendió la tienda de esa selección. La nota es 70% piso y 30% producto meta.',
            'indicadores' => [
                ['nombre' => 'Efectividad en tienda', 'peso' => '70%', 'mide' => 'Promedio de la parte de cada trabajador en la venta, los productos y los clientes de la selección, sobre el total de la tienda.'],
                ['nombre' => 'Producto meta', 'peso' => '30%', 'mide' => '100% si cumplió el producto que se le asignó dentro de la selección.'],
            ],
        ],
        'supervisor' => [
            'cargos' => [
                'supervisor de sede',
                'supervisor de area',
                'supervisora area call center',
                'coordinador de tienda',
            ],
            'titulo' => 'Supervisor',
            'resumen' => 'Se mide por el equipo. Las metas son las de tienda asignadas a los vendedores que supervisa.',
            'indicadores' => [
                ['nombre' => 'Cumplimiento de metas', 'peso' => '35%', 'mide' => 'Logro de las metas de tienda del equipo.'],
                ['nombre' => 'Monto total', 'peso' => '25%', 'mide' => 'Venta neta del equipo.'],
                ['nombre' => 'Clientes', 'peso' => '20%', 'mide' => 'Clientes del equipo.'],
                ['nombre' => 'Productos', 'peso' => '20%', 'mide' => 'Unidades del equipo.'],
            ],
        ],
    ],
];
