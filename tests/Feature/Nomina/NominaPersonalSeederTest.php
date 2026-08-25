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

        $this->assertCount(102, NominaPersonalSeeder::personal());
        $this->assertSame(102, Cliente::query()->count());
        $this->assertSame(102, NominaEmpleado::query()->count());
        $this->assertSame(24, NominaSede::query()->count());
        $this->assertGreaterThanOrEqual(15, NominaCargo::query()->count());
        $this->assertSame(14, NominaEmpleado::query()->where('sede', 'DORAL')->count());
        $this->assertSame(5, NominaEmpleado::query()->where('sede', 'MARKETING')->count());

        $joseph = Cliente::query()->where('cedula', '26058437')->firstOrFail();
        $this->assertSame('SOPORTE_TECNICO', $joseph->empleadoNomina->sede);
        $this->assertSame('JOSEMAR', NominaEmpleado::query()->whereHas('cliente', fn ($q) => $q->where('cedula', '28369045'))->value('codigo_vendedor'));
        $this->assertDatabaseHas('nomina_empleado_vendedores', [
            'nombre_normalizado' => 'JOSEMAR MAVAREZ',
        ]);
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
