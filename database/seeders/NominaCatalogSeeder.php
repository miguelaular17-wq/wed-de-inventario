<?php

namespace Database\Seeders;

use App\Models\Nomina\NominaCargo;
use Illuminate\Database\Seeder;

class NominaCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(NominaSedesAreasSeeder::class);

        $cargos = [
            ['nombre' => 'Supervisor de sede', 'descripcion' => 'Responsable del equipo de una sede (Doral, Centro, Sambil, etc.)'],
            ['nombre' => 'Supervisor de área', 'descripcion' => 'Responsable de un área sin sede de tienda (Marketing, Call center, Inventario)'],
            ['nombre' => 'Vendedor', 'descripcion' => 'Venta en piso'],
            ['nombre' => 'Cajero', 'descripcion' => 'Caja y cierre'],
            ['nombre' => 'Auxiliar de tienda', 'descripcion' => 'Apoyo operativo de sede'],
            ['nombre' => 'Gerente de tienda', 'descripcion' => 'Responsable de sede'],
            ['nombre' => 'Call center', 'descripcion' => 'Atención telefónica'],
            ['nombre' => 'Marketing', 'descripcion' => 'Publicidad y campañas'],
            ['nombre' => 'Inventario', 'descripcion' => 'Control de existencias'],
            ['nombre' => 'Depósito', 'descripcion' => 'Almacén y despacho'],
            ['nombre' => 'Administración', 'descripcion' => 'Personal administrativo'],
            ['nombre' => 'RRHH', 'descripcion' => 'Recursos humanos'],
            ['nombre' => 'Compras', 'descripcion' => 'Compras y abastecimiento'],
            ['nombre' => 'Contabilidad', 'descripcion' => 'Contabilidad'],
            ['nombre' => 'Tesorería', 'descripcion' => 'Tesorería'],
        ];

        foreach ($cargos as $cargo) {
            NominaCargo::query()->firstOrCreate(
                ['nombre' => $cargo['nombre']],
                $cargo + ['estado' => 'ACTIVO']
            );
        }
    }
}
