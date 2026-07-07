<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CuentasBancariasSeeder extends Seeder
{
    public function run(): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () {
            $cuentas = [
                // =========================================================
                // BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO (Mostrar en principal: true)
                // =========================================================
                ['banco' => 'BANESCO', 'titular' => 'GRUPO JRZ', 'color_tc' => '#f4b183', 'cat' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO', 'principal' => true],
                ['banco' => 'BANESCO', 'titular' => 'DORAL', 'color_tc' => '#f4b183', 'cat' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO', 'principal' => true],
                ['banco' => 'BANESCO', 'titular' => 'LNACEH', 'color_tc' => '#f4b183', 'cat' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO', 'principal' => true],
                ['banco' => 'BANESCO', 'titular' => 'NUNES', 'color_tc' => '#ff0000', 'cat' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO', 'principal' => true],
                ['banco' => 'BANESCO', 'titular' => 'GRUPO JENU', 'color_tc' => '#0070c0', 'cat' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO', 'principal' => true],
                ['banco' => 'BANESCO', 'titular' => 'JOSE JEREZ', 'color_tc' => '#f4b183', 'cat' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO', 'principal' => true],
                ['banco' => 'BANESCO', 'titular' => 'EURONISSI', 'color_tc' => '#f4b183', 'cat' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO', 'principal' => true],
                ['banco' => 'BNC', 'titular' => 'GRUPO JRZ', 'color_tc' => '#f4b183', 'cat' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO', 'principal' => true],
                ['banco' => 'BNC', 'titular' => 'DORAL', 'color_tc' => '#f4b183', 'cat' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO', 'principal' => true],
                ['banco' => 'BNC', 'titular' => 'LNACEH', 'color_tc' => '#f4b183', 'cat' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO', 'principal' => true],
                ['banco' => 'BNC', 'titular' => 'L.S. CASHEA', 'color_tc' => '#ffff00', 'cat' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO', 'principal' => true],
                ['banco' => 'MERCANTIL', 'titular' => 'GRUPO JENU', 'color_tc' => '#0070c0', 'cat' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO', 'principal' => true],
                ['banco' => 'MERCANTIL', 'titular' => 'JRZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO', 'principal' => true],
                ['banco' => 'BBVA', 'titular' => 'LNACEH', 'color_tc' => null, 'cat' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO', 'principal' => true],
                ['banco' => 'BANCARIBE', 'titular' => 'GRUPO JRZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO', 'principal' => true],
                ['banco' => 'BANCARIBE', 'titular' => 'JOSE JEREZ', 'color_tc' => '#ff0000', 'cat' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO', 'principal' => true],
                ['banco' => 'BANCAMIGA', 'titular' => 'DORAL', 'color_tc' => null, 'cat' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO', 'principal' => true],

                // =========================================================
                // BANCA NACIONAL - BAJO MOVIMIENTO (Mostrar en principal: true)
                // =========================================================
                ['banco' => 'MERCANTIL', 'titular' => 'LNACEH', 'color_tc' => null, 'cat' => 'BANCA NACIONAL - BAJO MOVIMIENTO', 'principal' => true],
                ['banco' => 'MERCANTIL', 'titular' => 'DORAL', 'color_tc' => null, 'cat' => 'BANCA NACIONAL - BAJO MOVIMIENTO', 'principal' => true],
                ['banco' => 'TESORO', 'titular' => 'DORAL', 'color_tc' => null, 'cat' => 'BANCA NACIONAL - BAJO MOVIMIENTO', 'principal' => true],
                ['banco' => 'TESORO', 'titular' => 'LNACEH', 'color_tc' => null, 'cat' => 'BANCA NACIONAL - BAJO MOVIMIENTO', 'principal' => true],
                ['banco' => 'TESORO', 'titular' => 'GRUPO JRZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL - BAJO MOVIMIENTO', 'principal' => true],
                ['banco' => 'TESORO', 'titular' => 'JOSE JEREZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL - BAJO MOVIMIENTO', 'principal' => true],
                ['banco' => 'VENEZUELA', 'titular' => 'GRUPO JRZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL - BAJO MOVIMIENTO', 'principal' => true],
                ['banco' => 'VENEZUELA', 'titular' => 'DORAL', 'color_tc' => null, 'cat' => 'BANCA NACIONAL - BAJO MOVIMIENTO', 'principal' => true],
                ['banco' => 'VENEZUELA', 'titular' => 'LNACEH', 'color_tc' => null, 'cat' => 'BANCA NACIONAL - BAJO MOVIMIENTO', 'principal' => true],
                ['banco' => 'VENEZUELA', 'titular' => 'JOSE JEREZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL - BAJO MOVIMIENTO', 'principal' => true],
                ['banco' => 'BANCARIBE', 'titular' => 'DORAL', 'color_tc' => null, 'cat' => 'BANCA NACIONAL - BAJO MOVIMIENTO', 'principal' => true],
                ['banco' => 'BANCAMIGA', 'titular' => 'JOSE JEREZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL - BAJO MOVIMIENTO', 'principal' => true],
                ['banco' => 'BBVA', 'titular' => 'JOSE JEREZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL - BAJO MOVIMIENTO', 'principal' => true],
                ['banco' => 'BNC', 'titular' => 'DORAL CASHEA', 'color_tc' => '#ffff00', 'cat' => 'BANCA NACIONAL - BAJO MOVIMIENTO', 'principal' => true],
                ['banco' => 'BNC', 'titular' => 'JOSE JEREZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL - BAJO MOVIMIENTO', 'principal' => true],
                ['banco' => 'BNC', 'titular' => 'EURONISSI', 'color_tc' => null, 'cat' => 'BANCA NACIONAL - BAJO MOVIMIENTO', 'principal' => true],
                ['banco' => 'BANCARIBE', 'titular' => 'EURONISSI', 'color_tc' => null, 'cat' => 'BANCA NACIONAL - BAJO MOVIMIENTO', 'principal' => true],

                // =========================================================
                // BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA (Mostrar en principal: false)
                // =========================================================
                ['banco' => 'BANESCO', 'titular' => 'JOSE JEREZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'BANESCO', 'titular' => 'DORAL', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'BANESCO', 'titular' => 'NUNES', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'BANESCO', 'titular' => 'LNACEH', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'BANESCO', 'titular' => 'GRUPO JENU', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'BANESCO', 'titular' => 'GRUPO JRZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'BNC', 'titular' => 'LNACEH', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'BNC', 'titular' => 'GRUPO JRZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'BNC', 'titular' => 'DORAL', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'BNC TIPO B', 'titular' => 'JOSE JEREZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'BNC TIPO A', 'titular' => 'JOSE JEREZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'TRJ BNC', 'titular' => 'JOSE JEREZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'BNC', 'titular' => 'L.S. CASHEA', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'BNC', 'titular' => 'DORAL CASHEA', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'MERCANTIL', 'titular' => 'LNACEH', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'MERCANTIL', 'titular' => 'GRUPO JRZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'MERCANTIL', 'titular' => 'GRUPO JENU', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'BBVA USD', 'titular' => 'JOSE JEREZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'BBVA EUR', 'titular' => 'JOSE JEREZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'BBVA', 'titular' => 'LNACEH', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'BANCARIBE', 'titular' => 'DORAL', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'BANCARIBE', 'titular' => 'GRUPO JRZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'BANCARIBE', 'titular' => 'JOSE JEREZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'TESORO', 'titular' => 'LNACEH', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'TESORO', 'titular' => 'JOSE JEREZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'TESORO', 'titular' => 'JRZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'TESORO', 'titular' => 'DORAL', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'TRJ TESORO', 'titular' => 'JOSE JEREZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'VENEZUELA', 'titular' => 'JOSE JEREZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'VENEZUELA', 'titular' => 'LNACEH', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'BANCAMIGA', 'titular' => 'DORAL', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],
                ['banco' => 'BANCAMIGA', 'titular' => 'JOSE JEREZ', 'color_tc' => null, 'cat' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA', 'principal' => false],

                // =========================================================
                // BANCA INTERNACIONAL / BILLETERAS (Mostrar en principal: false)
                // =========================================================
                ['banco' => 'MER. PAN.', 'titular' => 'JOSE JEREZ', 'color_tc' => null, 'cat' => 'BANCA INTERNACIONAL / BILLETERAS', 'principal' => false],
                ['banco' => 'BINANCE', 'titular' => 'GRUPO JENU', 'color_tc' => null, 'cat' => 'BANCA INTERNACIONAL / BILLETERAS', 'principal' => false],
                ['banco' => 'WELLS FARGO', 'titular' => 'INV. DORAL', 'color_tc' => null, 'cat' => 'BANCA INTERNACIONAL / BILLETERAS', 'principal' => false],
                ['banco' => 'AMERANT', 'titular' => 'INV. DORAL', 'color_tc' => null, 'cat' => 'BANCA INTERNACIONAL / BILLETERAS', 'principal' => false],
                ['banco' => 'CITIZENS MM', 'titular' => 'INV. DORAL', 'color_tc' => null, 'cat' => 'BANCA INTERNACIONAL / BILLETERAS', 'principal' => false],
                ['banco' => 'CITIZENS CH', 'titular' => 'INV. DORAL', 'color_tc' => null, 'cat' => 'BANCA INTERNACIONAL / BILLETERAS', 'principal' => false],

                // =========================================================
                // BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS (Mostrar en principal: false)
                // =========================================================
                ['banco' => 'FACEBANK', 'titular' => 'JOSE JEREZ', 'color_tc' => null, 'cat' => 'BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS', 'principal' => false],
                ['banco' => 'BANCARIBE', 'titular' => 'CURAZAO', 'color_tc' => null, 'cat' => 'BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS', 'principal' => false],
                ['banco' => 'BANCARIBE', 'titular' => 'PUERTO RICO', 'color_tc' => null, 'cat' => 'BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS', 'principal' => false],
                ['banco' => 'REGIONS', 'titular' => 'INV. DORAL', 'color_tc' => null, 'cat' => 'BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS', 'principal' => false],
                ['banco' => 'FIRST HORIZON', 'titular' => 'INV. DORAL', 'color_tc' => null, 'cat' => 'BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS', 'principal' => false],
                ['banco' => 'CITIZENS CH', 'titular' => 'NUNES STORE', 'color_tc' => null, 'cat' => 'BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS', 'principal' => false],
                ['banco' => 'CITIZNES SV', 'titular' => 'NUNES STORE', 'color_tc' => null, 'cat' => 'BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS', 'principal' => false],

                // =========================================================
                // TARJETAS INTERNACIONALES DE TERCEROS (Mostrar en principal: false)
                // =========================================================
                ['banco' => 'VENEZUELA', 'titular' => 'EDWARD MAVO', 'color_tc' => null, 'cat' => 'TARJETAS INTERNACIONALES DE TERCEROS', 'principal' => false],
                ['banco' => 'VENEZUELA', 'titular' => 'MARIA NUÑEZ', 'color_tc' => null, 'cat' => 'TARJETAS INTERNACIONALES DE TERCEROS', 'principal' => false],
                ['banco' => 'VENEZUELA', 'titular' => 'DAYANA LOPEZ', 'color_tc' => null, 'cat' => 'TARJETAS INTERNACIONALES DE TERCEROS', 'principal' => false],
                ['banco' => 'VENEZUELA', 'titular' => 'JOSE SEMECO', 'color_tc' => null, 'cat' => 'TARJETAS INTERNACIONALES DE TERCEROS', 'principal' => false],
            ];

            foreach ($cuentas as $index => $cuenta) {
                \App\Models\CuentaBancaria::updateOrCreate(
                    [
                        'banco' => $cuenta['banco'],
                        'titular' => $cuenta['titular']
                    ],
                    [
                        'color_tc' => $cuenta['color_tc'],
                        'categoria_reporte' => $cuenta['cat'],
                        'mostrar_en_principal' => $cuenta['principal'],
                        'orden' => $index,
                    ]
                );
            }

            // Crear las 7 filas vacias para planificacion (idempotente)
            for($i = 1; $i <= 7; $i++) {
                \App\Models\PlanificacionPago::firstOrCreate([
                    'orden' => $i
                ]);
            }

            \App\Models\FinanzasResumen::firstOrCreate(
                ['fecha' => date('Y-m-d')],
                [
                    'tasa_bcv_usd' => 633.36,
                    'tasa_paralelo' => 738.50,
                    'saldo_inicial' => 0,
                    'queda_dia_anterior' => 0,
                    'porcentaje_total_diferencial' => 0
                ]
            );
        });
    }
}
