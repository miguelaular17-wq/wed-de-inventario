<?php

return [
    /*
    | Filas del extracto que se insertan por transacción.
    */
    'import_chunk' => (int) env('CONCILIACION_IMPORT_CHUNK', 500),

    /*
    | true  → la subida responde enseguida y un worker de cola procesa el archivo.
    |         Requiere QUEUE_CONNECTION=database, la tabla jobs y `php artisan queue:work`.
    | false → se procesa en la misma petición (por bloques e inserción masiva).
    */
    'import_async' => (bool) env('CONCILIACION_IMPORT_ASYNC', false),

    'import_queue' => env('CONCILIACION_IMPORT_QUEUE', 'conciliaciones'),

    /*
    | Tope de filas por archivo para no aceptar algo que no es un extracto.
    */
    'import_max_rows' => (int) env('CONCILIACION_IMPORT_MAX_ROWS', 200000),

    /*
    | Cuántos motivos de rechazo se guardan por importación (el resto solo se cuenta).
    */
    'import_max_errores' => 200,
];
