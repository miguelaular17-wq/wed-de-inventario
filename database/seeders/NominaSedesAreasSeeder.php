<?php

namespace Database\Seeders;

use App\Models\Nomina\NominaSede;
use Illuminate\Database\Seeder;

class NominaSedesAreasSeeder extends Seeder
{
    public function run(): void
    {
        $unidades = [
            ['codigo' => 'CENTRO', 'nombre' => 'Centro', 'tipo' => 'SEDE'],
            ['codigo' => 'DORAL', 'nombre' => 'Doral', 'tipo' => 'SEDE'],
            ['codigo' => 'VIRTUDES', 'nombre' => 'Virtudes', 'tipo' => 'SEDE'],
            ['codigo' => 'ZAMORA', 'nombre' => 'Zamora', 'tipo' => 'SEDE'],
            ['codigo' => 'SAMBIL', 'nombre' => 'Sambil', 'tipo' => 'SEDE'],
            ['codigo' => 'NUNES', 'nombre' => 'Nunes', 'tipo' => 'SEDE'],
            ['codigo' => 'EGRESOS', 'nombre' => 'Egresos', 'tipo' => 'AREA'],
            ['codigo' => 'INVENTARIO', 'nombre' => 'Inventario', 'tipo' => 'AREA'],
            ['codigo' => 'MOVISTAR', 'nombre' => 'Movistar', 'tipo' => 'AREA'],
            ['codigo' => 'DEPOSITO', 'nombre' => 'Depósito', 'tipo' => 'AREA'],
            ['codigo' => 'CALL_CENTER', 'nombre' => 'Call Center', 'tipo' => 'AREA'],
            ['codigo' => 'MARKETING', 'nombre' => 'Marketing', 'tipo' => 'AREA'],
            ['codigo' => 'COMPRAS', 'nombre' => 'Compras', 'tipo' => 'AREA'],
            ['codigo' => 'CONTABILIDAD', 'nombre' => 'Contabilidad', 'tipo' => 'AREA'],
            ['codigo' => 'ADMINISTRACION', 'nombre' => 'Administración', 'tipo' => 'AREA'],
            ['codigo' => 'TESORERIA', 'nombre' => 'Tesorería', 'tipo' => 'AREA'],
            ['codigo' => 'FINANZAS', 'nombre' => 'Finanzas', 'tipo' => 'AREA'],
            ['codigo' => 'CUENTAS_COBRAR', 'nombre' => 'Cuentas por cobrar', 'tipo' => 'AREA'],
            ['codigo' => 'RRHH', 'nombre' => 'RRHH', 'tipo' => 'AREA'],
            ['codigo' => 'SOPORTE_TECNICO', 'nombre' => 'Soporte técnico', 'tipo' => 'AREA'],
            ['codigo' => 'FLOTA', 'nombre' => 'Flota', 'tipo' => 'AREA'],
            ['codigo' => 'MANTENIMIENTO', 'nombre' => 'Mantenimiento', 'tipo' => 'AREA'],
            ['codigo' => 'GERENCIA', 'nombre' => 'Gerencia', 'tipo' => 'AREA'],
            ['codigo' => 'SIN_ASIGNAR', 'nombre' => 'Sin asignar', 'tipo' => 'AREA'],
        ];

        foreach ($unidades as $unidad) {
            NominaSede::query()->updateOrCreate(
                ['codigo' => $unidad['codigo']],
                $unidad + ['estado' => 'ACTIVO']
            );
        }
    }
}
