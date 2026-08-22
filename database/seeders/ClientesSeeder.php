<?php

namespace Database\Seeders;

use App\Models\Cliente;
use Illuminate\Database\Seeder;

class ClientesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (NominaPersonalSeeder::personal() as [$cedula, $nombre]) {
            Cliente::query()->updateOrCreate(
                ['cedula' => $cedula],
                ['nombre' => $nombre]
            );
        }
    }
}
