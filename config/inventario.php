<?php

return [
    /*
    | Sedes con interfaz Ventas + Inventario (sin JRZ local ni pestaña Compra).
    | JRZ sigue en BD como almacén origen de requisiciones.
    */
    'sedes_locales' => ['DORAL', 'VIRTUDES', 'ZAMORA', 'CENTRO', 'SAMBIL'],

    'sedes_stock' => ['JRZ', 'DORAL', 'VIRTUDES', 'ZAMORA', 'CENTRO', 'SAMBIL'],

    'sede_central' => 'JRZ',

    'display' => [
        'JRZ' => 'JRZ',
        'DORAL' => 'DORAL',
        'VIRTUDES' => 'Virtude',
        'ZAMORA' => 'Zamora',
        'CENTRO' => 'Centro',
        'SAMBIL' => 'Sambil',
    ],

    /** Etiquetas de columnas en Excel multisede → clave sede */
    'excel_sede_labels' => [
        'JRZ' => 'JRZ',
        'DORAL' => 'DORAL',
        'VIRTUDES' => 'Virtude',
        'ZAMORA' => 'Zamora',
        'CENTRO' => 'Centro',
        'SAMBIL' => 'Sambil',
    ],

    'tiempo_venta_sede' => 15,
    'tiempo_venta_jrz' => 60,
    'tiempo_pronostico_default' => 15,
    'minimo_sugerido_ventas' => 3,
    'minimo_inv_solicitar' => 6,

    'req_colores' => ['Todos', 'Verde', 'Amarillo', 'Rojo'],

    'tipos_movimiento' => ['REQUISICION', 'PEDIDO', 'SURTIDO', 'AJUSTE', 'IMPORT'],

    'acciones' => [
        'HACER REQUISICION',
        'TIENE EXISTENCIA',
        'NO TIENE EXISTENCIA',
        'SIN VENTA',
    ],

    /** Tutorial guiado para usuarios nuevos (desactivado) */
    'tutorial_enabled' => false,

    'per_page' => (int) env('PER_PAGE', 75),
    'sync_interval_ms' => (int) env('SYNC_INTERVAL_MS', 60000),
    'last_stock_update_cache_seconds' => (int) env('LAST_STOCK_UPDATE_CACHE_SECONDS', 30),
    'load_for_sede_cache_seconds' => (int) env('LOAD_FOR_SEDE_CACHE_SECONDS', 1800),
];
