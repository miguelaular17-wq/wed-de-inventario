<?php

namespace Tests\Unit\Nomina;

use App\Models\Cliente;
use App\Models\Nomina\NominaCargo;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaSede;
use App\Services\Nomina\OrganizationService;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class OrganizationTreeTest extends TestCase
{
    use CreatesNominaSchema;

    private OrganizationService $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        $this->org = app(OrganizationService::class);
    }

    public function test_tienda_agrupa_supervisores_y_deja_a_la_gerente_arriba(): void
    {
        $gerencia = NominaSede::create(['nombre' => 'Gerencia', 'codigo' => 'GERENCIA', 'tipo' => 'AREA', 'estado' => 'ACTIVO']);
        $centro = NominaSede::create(['nombre' => 'Centro', 'codigo' => 'CENTRO', 'tipo' => 'SEDE', 'estado' => 'ACTIVO']);
        $cargoGerente = NominaCargo::create(['nombre' => 'Gerente', 'estado' => 'ACTIVO']);
        $cargoSup = NominaCargo::create(['nombre' => 'Supervisor de sede', 'estado' => 'ACTIVO']);
        $cargoLider = NominaCargo::create(['nombre' => 'Líder de tienda senior', 'estado' => 'ACTIVO']);
        $cargoVen = NominaCargo::create(['nombre' => 'Asesor de venta', 'estado' => 'ACTIVO']);

        $auri = $this->empleado('AURILES', 'AURILES LUGO', $gerencia, $cargoGerente, true);
        $carlos = $this->empleado('19648944', 'CARLOS GOMEZ', $centro, $cargoSup, true);
        $keisy = $this->empleado('25848623', 'KEISY DA SILVA', $centro, $cargoLider, true);
        $asesor = $this->empleado('28501943', 'GEOVANNI GUTIERREZ', $centro, $cargoVen, false);

        $this->org->syncJefes($carlos, [$auri->id]);
        $this->org->syncJefes($keisy, [$auri->id]);
        $this->org->syncJefes($asesor, [$carlos->id]);

        $nodo = $this->org->tree($centro->id)->first();

        $this->assertEquals(['AURILES LUGO'], $nodo['gerentes']->map->nombre()->all());
        $this->assertEqualsCanonicalizing(['CARLOS GOMEZ', 'KEISY DA SILVA'], $nodo['supervisores']->map->nombre()->all());
        $this->assertEquals(['GEOVANNI GUTIERREZ'], $nodo['equipo']->map->nombre()->all());
        $this->assertTrue($nodo['grupos']->isEmpty());
        $this->assertFalse($nodo['equipo']->contains(fn ($e) => $e->id === $carlos->id));
        $this->assertFalse($nodo['supervisores']->contains(fn ($e) => $e->id === $auri->id));
    }

    private function empleado(string $cedula, string $nombre, NominaSede $sede, NominaCargo $cargo, bool $esSupervisor): NominaEmpleado
    {
        $cliente = Cliente::create(['cedula' => $cedula, 'nombre' => $nombre]);

        return NominaEmpleado::create([
            'cliente_id' => $cliente->id,
            'sede_id' => $sede->id,
            'sede' => $sede->codigo,
            'cargo_id' => $cargo->id,
            'cargo' => $cargo->nombre,
            'salario_base' => 100,
            'tipo_salario' => 'MENSUAL',
            'estado' => 'ACTIVO',
            'es_supervisor' => $esSupervisor,
        ]);
    }
}
