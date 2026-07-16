<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HistorialCobranzasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::connection('pgsql')->table('historial_cobranzas')->truncate();

        $sedes = ['ZONA OESTE', 'ZONA ESTE', 'ZONA SUR'];
        $estatus = ['CRITICO', 'MOROSO', 'RECIENTE'];
        
        $fechas = [
            Carbon::now(), // Today
            Carbon::now()->startOfWeek(), // Monday of this week
            Carbon::now()->subWeek()->startOfWeek(), // Last week's monday
            Carbon::now()->subWeeks(2)->startOfWeek(), // 2 weeks ago monday
            Carbon::now()->subWeeks(3)->startOfWeek(), // 3 weeks ago monday
            Carbon::now()->subWeeks(4)->startOfWeek(), // 4 weeks ago monday
        ];

        $data = [];
        
        foreach ($fechas as $fecha) {
            for ($i = 1; $i <= 50; $i++) {
                $saldo = rand(100, 5000);
                
                // Deterministic customer for some continuity across weeks
                $cliente_id = rand(1, 30); 
                
                $estado = $estatus[array_rand($estatus)];
                
                // Let's make older dates have higher probability of being MOROSO or CRITICO
                if ($fecha < Carbon::now()->subWeeks(2)) {
                    $estado = rand(0, 1) ? 'CRITICO' : 'MOROSO';
                }

                $data[] = [
                    'codigo_cliente' => 'CUST' . str_pad($cliente_id, 4, '0', STR_PAD_LEFT),
                    'nombre_cliente' => 'Cliente Prueba ' . $cliente_id,
                    'id_documento' => 'DOC-' . rand(1000, 9999),
                    'fecha_emision' => Carbon::now()->subDays(rand(10, 400))->format('Y-m-d'),
                    'tipo_cxc' => 'FAC',
                    'numero_documento' => 'F-' . rand(10000, 99999),
                    'monto_neto' => $saldo * 1.16,
                    'saldo' => $saldo,
                    'dias_deuda' => rand(10, 400),
                    'estatus' => $estado,
                    'usuario' => 'TEST',
                    'estacion' => 'EST-1',
                    'codigo_caja' => 'CAJA-1',
                    'sede_nombre' => $sedes[array_rand($sedes)],
                    'fecha_registro' => $fecha->format('Y-m-d 05:00:00'),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }
        }
        
        foreach(array_chunk($data, 100) as $chunk) {
            DB::connection('pgsql')->table('historial_cobranzas')->insert($chunk);
        }
    }
}
