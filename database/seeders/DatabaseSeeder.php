<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Orquestador PRINCIPAL y SEGURO.
     * Este archivo puede correrse en Producción sin riesgo de pérdida de datos.
     * Todos los seeders invocados aquí DEBEN ser 100% idempotentes.
     * Uso: php artisan db:seed
     */
    public function run(): void
    {
        // [Producción/Base] Configuración inicial de usuarios (idempotente)
        $this->call(AdminUserSeeder::class);
        
        // [Producción/Base] Configuración inicial de cuentas (idempotente)
        $this->call(CuentasBancariasSeeder::class);

        // [Producción/Base] Catálogos de nómina (sedes RRHH y cargos)
        $this->call(NominaCatalogSeeder::class);

        // [Producción/Base] Personal oficial y asignación de sede/área
        $this->call(NominaPersonalSeeder::class);
    }
}
