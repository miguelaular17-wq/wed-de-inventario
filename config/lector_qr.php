<?php

return [
    /*
    | Código que solo conoce el admin para salir del modo kiosco del lector QR.
    | Cámbialo en .env (LECTOR_QR_PIN).
    */
    'pin' => (string) env('LECTOR_QR_PIN', '2580'),

    /*
    | Segundos que se muestra la ficha del producto antes de volver a la espera.
    */
    'auto_cerrar_segundos' => (int) env('LECTOR_QR_AUTO_CERRAR', 30),
];
