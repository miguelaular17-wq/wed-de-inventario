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
        'call_center' => [
            'cargos' => ['call center'],
            'titulo' => 'Call center',
            // Quien tenga este permiso ve solo este módulo; con "incentivos" se ven todos.
            'permiso' => 'incentivos.call_center',
            'resumen' => 'El 100% es lo que vendió todo el equipo de call center en el mes. La nota es 70% efectividad y 30% producto meta.',
            'indicadores' => [
                ['nombre' => 'Efectividad en el equipo', 'peso' => '70%', 'mide' => 'Promedio de la parte de cada trabajador en la venta, los productos y los clientes de todo el call center en el mes.'],
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
            'resumen' => 'Una fila por sede. La nota promedia metas, nivelación, venta cruzada e inventario, y el faltante de caja se resta o se suma.',
            'indicadores' => [
                ['nombre' => 'Venta de la sede', 'peso' => '120%', 'mide' => 'Lo que vendió la sede frente a la meta de la tienda. Se topa en 120%.'],
                ['nombre' => 'Productos', 'peso' => '120%', 'mide' => 'Unidades de la sede frente a la meta de la tienda. Se topa en 120%.'],
                ['nombre' => 'Clientes', 'peso' => '120%', 'mide' => 'Clientes de la sede frente a la meta de la tienda. Se topa en 120%.'],
            ],
        ],
    ],
];
