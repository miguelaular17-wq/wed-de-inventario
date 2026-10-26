<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TABLAS = [
        'stock_actual' => 'ck_stock_actual_sede',
        'ventas_historicas' => 'ck_ventas_historicas_sede',
        'reposicion' => 'ck_reposicion_sede',
        'inventario_derivado' => 'ck_inventario_derivado_sede',
        'config_sede' => 'ck_config_sede',
    ];

    private const SEDES = ['JRZ', 'DORAL', 'VIRTUDES', 'ZAMORA', 'CENTRO', 'SAMBIL', 'NUNES', 'MOVISTAR'];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $this->restricciones([...self::SEDES, 'TECNOHOUSE']);
        DB::statement("INSERT INTO inventario_v2.config_sede (sede, tiempo_pronostico) VALUES ('TECNOHOUSE', 15) ON CONFLICT (sede) DO NOTHING");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("DELETE FROM inventario_v2.config_sede WHERE sede = 'TECNOHOUSE'");
        $this->restricciones(self::SEDES);
    }

    /** @param  list<string>  $sedes */
    private function restricciones(array $sedes): void
    {
        $lista = implode(',', array_map(fn ($s) => "'{$s}'", $sedes));
        foreach (self::TABLAS as $tabla => $restriccion) {
            DB::statement("ALTER TABLE inventario_v2.{$tabla} DROP CONSTRAINT IF EXISTS {$restriccion}");
            DB::statement("ALTER TABLE inventario_v2.{$tabla} ADD CONSTRAINT {$restriccion} CHECK (sede IN ({$lista}))");
        }
    }
};
