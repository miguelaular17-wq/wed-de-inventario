<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CobranzaLegacySeeder extends Seeder
{
    public function run()
    {
        DB::table('cobranzas')->truncate();
        DB::table('cobranza_resumenes')->truncate();

        $sedes = ['ZONA OESTE', 'ZONA ESTE', 'ZONA SUR'];
        
        foreach ($sedes as $sede) {
            $total_clientes = 0;
            $total_saldo = 0;
            $critico_clientes = 0;
            $critico_saldo = 0;
            $moroso_clientes = 0;
            $moroso_saldo = 0;
            $reciente_clientes = 0;
            $reciente_saldo = 0;

            $insertData = [];
            
            for ($i = 1; $i <= 50; $i++) {
                $saldo = rand(100, 5000);
                $meses = rand(1, 15);
                
                $estatus = 'RECIENTE';
                if ($meses > 9.3) {
                    $estatus = 'CRITICO';
                    $critico_clientes++;
                    $critico_saldo += $saldo;
                } elseif ($meses > 2 && $meses <= 9.3) {
                    $estatus = 'MOROSO';
                    $moroso_clientes++;
                    $moroso_saldo += $saldo;
                } else {
                    $reciente_clientes++;
                    $reciente_saldo += $saldo;
                }
                
                $total_clientes++;
                $total_saldo += $saldo;

                $insertData[] = [
                    'sede_nombre' => $sede,
                    'codigo' => 'CUST' . str_pad(rand(1,100), 4, '0', STR_PAD_LEFT),
                    'cliente' => 'Cliente Prueba ' . rand(1,100),
                    'saldo_bs' => $saldo * 36,
                    'saldo_usd' => $saldo,
                    'fecha_emision' => Carbon::now()->subMonths($meses)->format('Y-m-d'),
                    'meses_antiguedad' => $meses,
                    'estatus' => $estatus,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }
            
            DB::table('cobranza_resumenes')->insert([
                'sede_nombre' => $sede,
                'total_clientes' => $total_clientes,
                'total_saldo' => $total_saldo,
                'critico_clientes' => $critico_clientes,
                'critico_saldo' => $critico_saldo,
                'moroso_clientes' => $moroso_clientes,
                'moroso_saldo' => $moroso_saldo,
                'reciente_clientes' => $reciente_clientes,
                'reciente_saldo' => $reciente_saldo,
                'apartado_clientes' => 0,
                'apartado_saldo' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            DB::table('cobranzas')->insert($insertData);
        }
        
        \Illuminate\Support\Facades\Cache::forget('cobranza_resumenes');
    }
}
