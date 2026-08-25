<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class NominaVendedorCodesSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(NominaPersonalSeeder::class);
    }
}
