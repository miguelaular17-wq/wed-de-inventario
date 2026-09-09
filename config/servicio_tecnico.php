<?php

return [
    'categorias_reparacion' => [
        'telefonia' => 'Telefonía',
        'pantalla' => 'Pantalla',
        'bateria' => 'Batería',
        'pin_carga' => 'Pin de carga',
        'flex_carga' => 'Flex de carga',
        'camara' => 'Cámara',
        'tapa' => 'Tapa',
        'carcasa' => 'Carcasa',
        'boton' => 'Botón',
        'relojes' => 'Relojes',
        'computacion' => 'Computación',
        'electrodomesticos' => 'Electrodomésticos',
        'electronicos' => 'Electrónicos',
        'otro' => 'Otro',
    ],

    'categorias_reparacion_iconos' => [
        'telefonia' => '📱',
        'pantalla' => '🖥️',
        'bateria' => '🔋',
        'pin_carga' => '🔌',
        'flex_carga' => '➿',
        'camara' => '📷',
        'tapa' => '🪟',
        'carcasa' => '🧰',
        'boton' => '🔘',
        'relojes' => '⌚',
        'computacion' => '💻',
        'electrodomesticos' => '🏠',
        'electronicos' => '🔌',
        'otro' => '📦',
    ],

    'tipos_reparacion' => [
        'garantia' => 'Garantía de venta',
        'interno' => 'Servicio interno',
    ],

    'acciones_reparacion' => [
        'reparado' => 'Reparado',
        'reemplazado' => 'Reemplazado',
        'sin_cobertura' => 'Sin cobertura',
        'pendiente' => 'Pendiente evaluación',
    ],

    'estados_reparacion' => [
        'en_proceso' => 'En proceso',
        'enviado_proveedor' => 'Enviado a proveedor',
        'soporte_pd' => 'Soporte técnico PD',
        'completada' => 'Resuelto',
        'no_reparable' => 'No reparable',
    ],

    'estados_pago_factura' => [
        'pendiente' => 'Pendiente',
        'pagado' => 'Pagado',
        'anulado' => 'Anulado',
    ],

    'tipos_dispositivo' => [
        'celular' => 'Celular',
        'audifonos' => 'Audífonos',
        'impresora' => 'Impresora',
        'camara' => 'Cámara',
        'corneta' => 'Corneta / Altavoz',
    ],

    'tipos_impresora' => [
        'laser' => 'Láser',
        'inyeccion' => 'Inyección de tinta',
        'termica' => 'Térmica',
    ],

    'accesorios_por_tipo' => [
        'celular' => [
            'cargador' => 'Cargador',
            'funda' => 'Funda',
            'sim' => 'Tarjeta SIM',
            'sd' => 'Tarjeta SD',
        ],
        'impresora' => [
            'poder' => 'Cable de poder',
            'usb' => 'Cable USB',
            'bandejas' => 'Bandejas de papel',
            'toner' => 'Cartuchos / tóner instalados',
        ],
        'camara' => [
            'tapa_lente' => 'Tapa de lente',
            'correa' => 'Correa',
            'bateria' => 'Batería',
            'memoria' => 'Tarjeta de memoria',
            'lente' => 'Lente específico',
        ],
        'audifonos' => [
            'estuche_carga' => 'Estuche de carga',
            'cables' => 'Cables auxiliar o de carga',
            'estuche' => 'Estuche de transporte',
        ],
        'corneta' => [
            'estuche_carga' => 'Estuche de carga',
            'cables' => 'Cables auxiliar o de carga',
            'estuche' => 'Estuche de transporte',
        ],
    ],

    'checklist_recepcion_por_tipo' => [
        'celular' => [
            'encendido' => 'Enciende y se apaga',
            'pantalla' => 'Pantalla (táctil, manchas)',
            'carcasa' => 'Carcasa y golpes',
            'camara' => 'Cámaras frontal y trasera',
            'audio' => 'Altavoz y micrófono',
            'carga' => 'Pin de carga',
            'humedad' => 'Sin indicios de humedad',
            'sim' => 'Bandeja SIM',
            'biometria' => 'Biometría (Huella / Face ID)',
            'bateria' => 'Batería',
            'imei_coincide' => 'Verificación de IMEI',
        ],
        'impresora' => [
            'imp_encendido' => 'Enciende correctamente',
            'imp_panel' => 'Panel de control y botones',
            'imp_rodillos' => 'Rodillos de tracción de papel',
            'imp_carro' => 'Carro de impresión',
            'imp_toner' => 'Estado del tóner o cartuchos',
            'imp_usb' => 'Puerto USB',
            'imp_ethernet' => 'Ethernet',
            'imp_wifi' => 'Wi-Fi',
            'imp_estetica' => 'Daños estéticos o bandeja rota',
        ],
        'camara' => [
            'cam_menu' => 'Encendido y funcionamiento del menú',
            'cam_sensor' => 'Estado del sensor (limpieza / rayones)',
            'cam_lcd' => 'Pantalla LCD',
            'cam_visor' => 'Visor',
            'cam_montura' => 'Montura del lente',
            'cam_diales' => 'Botones y diales',
            'cam_tapa_bateria' => 'Tapa de compartimiento de batería',
            'cam_bateria' => 'Estado de la batería',
        ],
        'corneta' => [
            'cor_bluetooth' => 'Encendido y parpadeo Bluetooth',
            'cor_malla' => 'Integridad de la malla protectora',
            'cor_puertos' => 'Conectividad de puertos (Auxiliar, USB, MicroSD)',
            'cor_audio' => 'Distorsión de audio al reproducir',
            'cor_volumen' => 'Control de volumen',
            'cor_bateria' => 'Estado de la batería o puerto de carga',
        ],
        'audifonos' => [
            'aud_lados' => 'Funcionamiento en ambos lados (izquierdo y derecho)',
            'aud_gomas' => 'Estado de las almohadillas o gomas',
            'aud_inalambrico' => 'Conectividad inalámbrica',
            'aud_estuche' => 'Estuche de carga (pines y bisagra; N/A si no aplica)',
            'aud_microfono' => 'Micrófono incorporado',
        ],
    ],

    'checklist_recepcion' => [
        'encendido' => 'Enciende y se apaga',
        'pantalla' => 'Pantalla (táctil, manchas)',
        'carcasa' => 'Carcasa y golpes',
        'camara' => 'Cámaras frontal y trasera',
        'audio' => 'Altavoz y micrófono',
        'carga' => 'Pin de carga',
        'humedad' => 'Sin indicios de humedad',
        'sim' => 'Bandeja SIM',
        'biometria' => 'Biometría (Huella / Face ID)',
        'bateria' => 'Batería',
        'imei_coincide' => 'Verificación de IMEI',
    ],

    'backup_condiciones' => "El equipo de backup es propiedad de Palacio de los Detalles y se entrega en calidad de préstamo temporal mientras se realiza el servicio o garantía del equipo del cliente.\n"
        ."El cliente se compromete a devolverlo en las mismas condiciones físicas y de funcionamiento en que lo recibió, salvo el desgaste normal de uso.\n"
        ."En caso de pérdida, robo o daño atribuible al cliente, este deberá responder por el valor del equipo según inventario de la empresa.\n"
        .'El préstamo no implica transferencia de propiedad ni garantía adicional sobre el equipo de backup.',
];
