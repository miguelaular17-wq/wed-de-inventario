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
        'finanzas.eliminar' => 'Eliminar egresos, traslados, divisas y avances',
        'conciliaciones' => 'Conciliaciones',
        'tesoreria' => 'Tesorería',
        'cobranza' => 'Cobranza',
        'contratos' => 'Contratos',
        'patrimonial' => 'Patrimonial',
        'nomina' => 'Nómina',
        'nomina.equipo' => 'Nómina y comisiones del equipo (tras calcular)',
        'marketing.publicidad_equipo' => 'Ver publicidad del equipo',
        'meta' => 'Metas de quincena (marcar productos)',
        'meta.ver' => 'Metas de quincena (solo ver / supervisor)',
        'gerencial' => 'Dashboard gerencial',
        'gerencial.devoluciones' => 'Devoluciones en ventas',
        'gerencial.valorizados' => 'Valorizados de inventarios',
        'gerencial.ajustes' => 'Consolidados de ajustes de inventarios',
        'gerencial.rentabilidad' => 'Rentabilidad gerencial',
        'servicio' => 'Servicio técnico',
        'servicio.inventario' => 'Inventario de repuestos (servicio técnico)',
    ],

    /*
     * Secciones usadas para presentar los permisos en la administración.
     * Las claves deben existir en "assignable".
     */
    'groups' => [
        'Operaciones' => ['operacion'],
        'Compras y Marketing' => ['compras', 'marketing.publicidad_equipo', 'meta', 'meta.ver'],
        'Reportes' => ['compras.reporte_sede'],
        'Finanzas' => ['finanzas.ver', 'finanzas.editar', 'finanzas.eliminar'],
        'Conciliaciones bancarias' => ['conciliaciones'],
        'Tesorería' => ['tesoreria'],
        'Cobranza' => ['cobranza'],
        'Contratos' => ['contratos'],
        'Patrimonial' => ['patrimonial'],
        'Nómina' => ['nomina', 'nomina.equipo'],
        'Gerencial' => ['gerencial', 'gerencial.devoluciones', 'gerencial.valorizados', 'gerencial.ajustes', 'gerencial.rentabilidad'],
        'Servicio técnico' => ['servicio', 'servicio.inventario'],
    ],

    'roles' => [
        'admin' => ['*'],
        'gerente' => ['*'],
        'supervisor' => ['operacion', 'compras.reporte_sede'],
        'telefonia' => ['operacion'],
        'sede' => ['operacion'],
        'comprador' => ['operacion', 'compras'],
        'marketing' => ['compras'],
        'finanzas' => ['finanzas.ver', 'finanzas.editar', 'finanzas.eliminar'],
        'auditor' => ['finanzas.ver'],
        'contabilidad' => ['conciliaciones'],
        'cobranza' => ['cobranza', 'contratos', 'patrimonial'],
        'tesoreria' => ['tesoreria'],
        'rrhh' => ['nomina'],
        'tecnico' => ['servicio'],
        'vendedor' => [],
    ],
];
