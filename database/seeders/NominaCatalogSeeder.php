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
            ['nombre' => 'Gerente', 'descripcion' => 'Dirección general. Puede supervisar sedes y áreas de oficina.'],
            ['nombre' => 'Supervisor de sede', 'descripcion' => 'Responsable del equipo de una sede. Puede haber dos por tienda.'],
            ['nombre' => 'Supervisor de área', 'descripcion' => 'Responsable de un área sin sede de tienda (Marketing, Call center, Inventario)'],
            ['nombre' => 'Supervisora área call center', 'descripcion' => 'Responsable del call center'],
            ['nombre' => 'Supervisor de inventario', 'descripcion' => 'Responsable de inventario'],
            ['nombre' => 'Supervisor de flota', 'descripcion' => 'Responsable de flota'],
            ['nombre' => 'Coordinador de tienda', 'descripcion' => 'Coordinación operativa de tiendas'],
            ['nombre' => 'Coordinador logístico', 'descripcion' => 'Depósito y logística'],
            ['nombre' => 'Coordinadora de RRHH', 'descripcion' => 'Recursos humanos'],
            ['nombre' => 'Líder de tienda senior', 'descripcion' => 'Liderazgo de piso de venta'],
            ['nombre' => 'Asesor de venta', 'descripcion' => 'Venta en piso'],
            ['nombre' => 'Asesor de venta telefonía', 'descripcion' => 'Venta de telefonía'],
            ['nombre' => 'PCP', 'descripcion' => 'Punto de control de piso'],
            ['nombre' => 'Servicio técnico', 'descripcion' => 'Reparaciones y garantías'],
            ['nombre' => 'Community manager', 'descripcion' => 'Redes y contenido'],
            ['nombre' => 'Analista de inventario', 'descripcion' => 'Control de existencias'],
            ['nombre' => 'Analista administrativo', 'descripcion' => 'Apoyo administrativo'],
            ['nombre' => 'Analista contable tributario', 'descripcion' => 'Contabilidad tributaria'],
            ['nombre' => 'Receptor de mercancía', 'descripcion' => 'Recepción en depósito'],
            ['nombre' => 'Chofer', 'descripcion' => 'Flota'],
            ['nombre' => 'Operador de mantenimiento', 'descripcion' => 'Mantenimiento'],
            ['nombre' => 'Cajero', 'descripcion' => 'Caja y cierre'],
            ['nombre' => 'Auxiliar de tienda', 'descripcion' => 'Apoyo operativo de sede'],
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
