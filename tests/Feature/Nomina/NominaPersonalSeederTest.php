<?php

namespace Tests\Feature\Nomina;

use App\Models\Cliente;
use App\Models\Nomina\NominaCargo;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaSede;
use Database\Seeders\NominaPersonalSeeder;
use Database\Seeders\NominaSedesAreasSeeder;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class NominaPersonalSeederTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
    }

    public function test_importa_personal_y_unidades_del_archivo_oficial_de_forma_idempotente(): void
    {
        $this->seed(NominaSedesAreasSeeder::class);
        $this->seed(NominaPersonalSeeder::class);
        $this->seed(NominaPersonalSeeder::class);

        $this->assertCount(99, NominaPersonalSeeder::personal());
        $this->assertSame(99, Cliente::query()->count());
        $this->assertSame(99, NominaEmpleado::query()->count());
        $this->assertSame(22, NominaSede::query()->count());
        $this->assertSame(15, NominaCargo::query()->count());
        $this->assertSame(15, NominaEmpleado::query()->where('sede', 'DORAL')->count());
        $this->assertSame(5, NominaEmpleado::query()->where('sede', 'MARKETING')->count());

        $joseph = Cliente::query()->where('cedula', '26058437')->firstOrFail();
        $this->assertSame('SIN_ASIGNAR', $joseph->empleadoNomina->sede);
        $this->assertDatabaseHas('nomina_sedes', [
            'codigo' => 'NUNES',
            'tipo' => 'SEDE',
        ]);
        $this->assertDatabaseHas('nomina_sedes', [
            'codigo' => 'CUENTAS_COBRAR',
            'tipo' => 'AREA',
        ]);
    }
}
