<?php

return [
    /*
     * Permisos asignables a un usuario además de su rol.
     * admin y gerente tienen todos (se resuelve en User::canAccess).
     */
    'assignable' => [
        'operacion' => 'Operación (ventas, inventario, exportar)',
        'compras' => 'Compras / Marketing',
        'compras.reporte_sede' => 'Reporte diario de pedidos por sede',
        'finanzas.ver' => 'Flujo de caja y gastos fijos (ver)',
        'finanzas.editar' => 'Flujo de caja y gastos fijos (editar)',
        'conciliaciones' => 'Conciliaciones',
        'tesoreria' => 'Tesorería',
        'cobranza' => 'Cobranza',
        'contratos' => 'Contratos',
        'patrimonial' => 'Patrimonial',
        'nomina' => 'Nómina',
        'marketing.publicidad_equipo' => 'Ver publicidad del equipo',
    ],

    'roles' => [
        'admin' => ['*'],
        'gerente' => ['*'],
        'supervisor' => ['operacion', 'compras.reporte_sede'],
        'telefonia' => ['operacion'],
        'sede' => ['operacion'],
        'comprador' => ['operacion', 'compras'],
        'marketing' => ['compras'],
        'finanzas' => ['finanzas.ver', 'finanzas.editar', 'contratos'],
        'auditor' => ['finanzas.ver'],
        'contabilidad' => ['conciliaciones'],
        'cobranza' => ['cobranza', 'contratos', 'patrimonial'],
        'tesoreria' => ['tesoreria'],
        'rrhh' => ['nomina'],
        'vendedor' => [],
    ],
];
