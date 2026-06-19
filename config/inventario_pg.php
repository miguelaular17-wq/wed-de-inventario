<?php

return [
    /** Ruta al JSON de conexión (relativa a la raíz del proyecto CALL CENTER). */
    'config_path' => env(
        'INVENTARIO_PG_CONFIG',
        base_path('../inventario_pg_config copy.json')
    ),

    'schema' => 'inventario_v2',

    'sedes' => ['JRZ', 'DORAL', 'VIRTUDES', 'ZAMORA', 'CENTRO', 'SAMBIL'],
];
