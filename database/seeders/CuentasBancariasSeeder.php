<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CuentasBancariasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \Illuminate\Support\Facades\DB::statement('TRUNCATE TABLE cuentas_bancarias RESTART IDENTITY CASCADE');
        
        $cuentas = [
            ['banco' => 'BANESCO', 'titular' => 'GRUPO JRZ', 'color_tc' => '#f4b183'], // Naranja
            ['banco' => 'BANESCO', 'titular' => 'DORAL', 'color_tc' => '#f4b183'], // Naranja
            ['banco' => 'BANESCO', 'titular' => 'LNACEH', 'color_tc' => '#f4b183'], // Naranja
            ['banco' => 'BANESCO', 'titular' => 'NUNES', 'color_tc' => '#ff0000'], // Rojo
            ['banco' => 'BANESCO', 'titular' => 'GRUPO JENU', 'color_tc' => '#0070c0'], // Azul
            ['banco' => 'BANESCO', 'titular' => 'JOSE JEREZ', 'color_tc' => '#f4b183'], // Naranja
            ['banco' => 'BANESCO', 'titular' => 'EURONISSI', 'color_tc' => '#f4b183'], // Naranja
            ['banco' => 'BNC', 'titular' => 'GRUPO JRZ', 'color_tc' => '#f4b183'], // Naranja
            ['banco' => 'BNC', 'titular' => 'DORAL', 'color_tc' => '#f4b183'], // Naranja
            ['banco' => 'BNC', 'titular' => 'LNACEH', 'color_tc' => '#f4b183'], // Naranja
            ['banco' => 'BNC', 'titular' => 'L.S. CASHEA', 'color_tc' => '#ffff00'], // Amarillo
            ['banco' => 'MERCANTIL', 'titular' => 'GRUPO JENU', 'color_tc' => '#0070c0'], // Azul
            ['banco' => 'MERCANTIL', 'titular' => 'GRUPO JRZ', 'color_tc' => '#f4b183'], // Naranja
            ['banco' => 'BBVA', 'titular' => 'LNACEH', 'color_tc' => null],
            ['banco' => 'BANCARIBE', 'titular' => 'GRUPO JRZ', 'color_tc' => null],
            ['banco' => 'BANCARIBE', 'titular' => 'JOSE JEREZ', 'color_tc' => '#ff0000'], // Rojo
            ['banco' => 'BANCAMIGA', 'titular' => 'DORAL', 'color_tc' => null],
            ['banco' => 'MERCANTIL', 'titular' => 'LNACEH', 'color_tc' => null],
            ['banco' => 'MERCANTIL', 'titular' => 'DORAL', 'color_tc' => null],
            ['banco' => 'TESORO', 'titular' => 'DORAL', 'color_tc' => null],
            ['banco' => 'TESORO', 'titular' => 'LNACEH', 'color_tc' => null],
            ['banco' => 'TESORO', 'titular' => 'GRUPO JRZ', 'color_tc' => null],
            ['banco' => 'TESORO', 'titular' => 'JOSE JEREZ', 'color_tc' => null],
            ['banco' => 'VENEZUELA', 'titular' => 'GRUPO JRZ', 'color_tc' => null],
            ['banco' => 'VENEZUELA', 'titular' => 'DORAL', 'color_tc' => null],
            ['banco' => 'VENEZUELA', 'titular' => 'LNACEH', 'color_tc' => null],
            ['banco' => 'VENEZUELA', 'titular' => 'JOSE JEREZ', 'color_tc' => null],
            ['banco' => 'BANCARIBE', 'titular' => 'DORAL', 'color_tc' => null],
            ['banco' => 'BANCAMIGA', 'titular' => 'JOSE JEREZ', 'color_tc' => null],
            ['banco' => 'BBVA', 'titular' => 'JOSE JEREZ', 'color_tc' => null],
            ['banco' => 'BNC', 'titular' => 'DORAL CASHEA', 'color_tc' => '#ffff00'], // Amarillo
            ['banco' => 'BNC', 'titular' => 'JOSE JEREZ', 'color_tc' => null],
            ['banco' => 'BNC', 'titular' => 'EURONISSI', 'color_tc' => null],
            ['banco' => 'BANCARIBE', 'titular' => 'EURONISSI', 'color_tc' => null],
        ];

        foreach ($cuentas as $index => $cuenta) {
            \App\Models\CuentaBancaria::create([
                'banco' => $cuenta['banco'],
                'titular' => $cuenta['titular'],
                'color_tc' => $cuenta['color_tc'],
                'orden' => $index,
            ]);
        }

        \App\Models\FinanzasResumen::firstOrCreate(
            ['fecha' => date('Y-m-d')],
            [
                'tasa_bcv_usd' => 652.97,
                'saldo_inicial' => 0,
                'queda_dia_anterior' => 0,
                'porcentaje_total_diferencial' => 0
            ]
        );
    }
}
