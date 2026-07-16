<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DevDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Este orquestador es EXCLUSIVO para Desarrollo/Testing.
     * Carga la base estructural y luego inyecta fixtures o datos falsos.
     * Uso: php artisan db:seed --class=DevDatabaseSeeder
     */
    public function run(): void
    {
        // 1. Cargar la estructura base segura
        $this->call(DatabaseSeeder::class);

        // 2. Inyectar datos de prueba
        $this->call(SampleProductsSeeder::class);
    }
}
