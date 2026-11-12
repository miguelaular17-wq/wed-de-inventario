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
     * Orden de las formas de pago en el cuadre, igual que la pantalla de cierre de Suite: códigos de metodo_pago
     * y, dentro de cada uno, las cuentas en el orden de metodo_pago_cuentas_banco. Lo que no esté aquí va al final.
     */
    'orden_metodos' => ['01', '02', 'TDD', 'TDC', 'TRFBS', 'TRF', '10', '11', '12', 'CASHEA', 'GIFTCARD', '03', 'NDC', 'NDC1', 'IGTF'],
    'orden_cuentas' => ['4459', 'VZLA01', '4025', 'BAN01', 'BAN02', 'BAN03', 'BAN05', 'BNC01', 'BNC02', 'TESOROPM', 'BINANCE', 'CITYB01', 'MPAN'],

    /*
     * Formas de pago que muestra el cuadre, en el orden de la pantalla de cierre de Suite de cada sede.
     * clave «metodo|cuenta» => [forma de pago, cuenta, en dólares]. Una forma que no esté aquí solo sale
     * si la caja tiene algo declarado o contado en ella.
     * Las sedes sin lista muestran las formas de «metodos_cuadre» que ya se usaron en sus cajas.
     */
    'formas_cuadre' => [
        'DORAL' => [
            '01|' => ['EFECTIVO BS', null, false],
            '02|' => ['EFECTIVO $', null, true],
            'TDD|4025' => ['TARJ. DÉBITO', 'BANESCO INV. DORAL (4025)', false],
            'TDD|BAN01' => ['TARJ. DÉBITO', 'BANESCO P.V. JRZ', false],
            'TDD|BAN02' => ['TARJ. DÉBITO', 'BANESCO P.V. DORAL', false],
            'TDD|BNC02' => ['TARJ. DÉBITO', 'BNC P.V. DORAL', false],
            'TRFBS|4025' => ['TRANSFERENCIA BS', 'BANESCO INV. DORAL (4025)', false],
            'TRFBS|BAN01' => ['TRANSFERENCIA BS', 'BANESCO P.V. JRZ', false],
            'TRFBS|BAN05' => ['TRANSFERENCIA BS', 'BANESCO DORAL', false],
            'TRFBS|TESOROPM' => ['TRANSFERENCIA BS', 'TESORO (PAGO MÓVIL)', false],
            'TRF|BINANCE' => ['TRANSF. $', 'BINANCE', true],
            'TRF|CITYB01' => ['TRANSF. $', 'CITY BANK', true],
            'TRF|MPAN' => ['TRANSF. $', 'MERCANTIL PANAMÁ', true],
            '10|' => ['IPHONE', null, true],
            '11|' => ['PREVENTA', null, true],
            'CASHEA|' => ['CASHEA', null, true],
            'GIFTCARD|' => ['GIFT CARD', null, true],
            'NDC|' => ['NOTA DE CRÉDITO', null, false],
            'NDC1|' => ['EFECTIVO DEVOLUCION', null, false],
            'IGTF|' => ['RECARGO FACTURA', null, false],
        ],
    ],
    'metodos_cuadre' => ['01', '02', 'TDD', 'TRFBS', 'TRF', '10', '11', 'CASHEA', 'GIFTCARD', 'NDC', 'NDC1', 'IGTF'],

    /*
     * Como en la configuración de las formas de pago de Suite:
     * - «No tomar en cuenta para totalizar» (Nota de crédito): se ve, pero no suma al cuadre ni a la diferencia.
     * - «Procesar en negativo (diferencia)» (Efectivo devolución): el efectivo que se le devolvió al cliente; Suite
     *   no lo manda como cobro, sale de las devoluciones de la cajera ese día menos lo que se pagó con nota de crédito,
     *   y se carga solo en lo contado: compensa el efectivo que falta en la caja.
     */
    'metodos_no_suman' => ['NDC'],
    'metodo_devolucion_efectivo' => 'NDC1',

    /*
     * Cuentas que también son punto de venta aunque su nombre no diga «P.V.» (solo para tarjetas;
     * una transferencia a esa cuenta no es un lote del punto).
     */
    'cuentas_punto_venta' => ['BANESCO INV. DORAL (4025)'],

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
