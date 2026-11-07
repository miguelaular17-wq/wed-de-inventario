<?php

return [
    /*
     * Bancos que Tesorería elige para un lote de punto de venta (los mismos del registro de lotes POS).
     */
    'bancos_punto_venta' => ['BANESCO', 'MERCANTIL', 'VENEZUELA', 'TESORO', 'BNC', 'BBVA', 'PROVINCIAL', 'BANCAMIGA', 'BANCARIBE'],

    /*
     * Titulares que Tesorería elige para un lote de punto de venta (los mismos del registro de lotes POS).
     */
    'titulares_punto_venta' => ['JRZ', 'DORAL', 'LNACEH', 'NUNES', 'EURONISSI'],

    /*
     * Motivos para cerrar una caja (cierre definitivo). La clave se guarda en cuadres_caja.motivo_cierre:
     * no cambiar una clave que ya se usó, solo su texto.
     */
    'motivos_cierre' => [
        'cuadrada' => 'Caja cuadrada, sin diferencia',
        'faltante' => 'Faltante de dinero',
        'sobrante' => 'Sobrante de dinero',
        'error_declaracion' => 'Error de la cajera al declarar en Suite',
        'pago_no_declarado' => 'Forma de pago no declarada',
        'diferencia_tasa' => 'Diferencia por tasa de cambio',
        'comision_bancaria' => 'Comisión o recargo bancario',
        'pago_por_confirmar' => 'Pago por confirmar en el banco',
        'otro' => 'Otro (explicar en la observación)',
    ],
];
