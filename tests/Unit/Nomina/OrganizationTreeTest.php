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

    public function test_reporte_equipo_incluye_ambos_supervisores_de_la_sede(): void
    {
        $gerencia = NominaSede::create(['nombre' => 'Gerencia', 'codigo' => 'GERENCIA', 'tipo' => 'AREA', 'estado' => 'ACTIVO']);
        $centro = NominaSede::create(['nombre' => 'Centro', 'codigo' => 'CENTRO', 'tipo' => 'SEDE', 'estado' => 'ACTIVO']);
        $cargoGerente = NominaCargo::create(['nombre' => 'Gerente', 'estado' => 'ACTIVO']);
        $cargoSup = NominaCargo::create(['nombre' => 'Supervisor de sede', 'estado' => 'ACTIVO']);
        $cargoVen = NominaCargo::create(['nombre' => 'Asesor de venta', 'estado' => 'ACTIVO']);

        $auri = $this->empleado('AURILES', 'AURILES LUGO', $gerencia, $cargoGerente, true);
        $carlos = $this->empleado('19648944', 'CARLOS GOMEZ', $centro, $cargoSup, true);
        $keisy = $this->empleado('25848623', 'KEISY DA SILVA', $centro, $cargoSup, true);
        $asesor = $this->empleado('28501943', 'GEOVANNI GUTIERREZ', $centro, $cargoVen, false);

        $userCarlos = \App\Models\User::create([
            'name' => 'Carlos',
            'email' => 'carlos-org@test.local',
            'password' => 'password123',
            'role' => \App\Models\User::ROLE_SUPERVISOR,
            'sede' => 'CENTRO',
        ]);
        $carlos->user_id = $userCarlos->id;
        $carlos->save();

        $this->org->syncJefes($carlos, [$auri->id]);
        $this->org->syncJefes($keisy, [$auri->id]);
        // Sin marcar jefes a mano: el asesor de la sede queda a cargo por unidad.
        $this->org->asignarEquipoPorUnidad($carlos);

        $idsReporte = $this->org->idsPersonalACargo($userCarlos, true);
        $idsSoloCargo = $this->org->idsPersonalACargo($userCarlos, false);

        $this->assertContains((int) $carlos->id, $idsReporte);
        $this->assertContains((int) $keisy->id, $idsReporte);
        $this->assertContains((int) $asesor->id, $idsSoloCargo);

        $this->assertNotContains((int) $carlos->id, $idsSoloCargo);
        $this->assertNotContains((int) $keisy->id, $idsSoloCargo);
        $this->assertTrue($asesor->fresh()->jefes->pluck('id')->contains($carlos->id));
    }

    public function test_supervisor_de_area_toma_todo_el_personal_del_area(): void
    {
        $flota = NominaSede::create(['nombre' => 'Flota', 'codigo' => 'FLOTA', 'tipo' => 'AREA', 'estado' => 'ACTIVO']);
        $cargoSup = NominaCargo::create(['nombre' => 'Supervisor de área', 'estado' => 'ACTIVO']);
        $cargoTec = NominaCargo::create(['nombre' => 'Técnico', 'estado' => 'ACTIVO']);

        $sup = $this->empleado('3001', 'SUP FLOTA', $flota, $cargoSup, true);
        $tec1 = $this->empleado('3002', 'TEC UNO', $flota, $cargoTec, false);
        $tec2 = $this->empleado('3003', 'TEC DOS', $flota, $cargoTec, false);

        $user = \App\Models\User::create([
            'name' => 'Sup Flota',
            'email' => 'sup-flota@test.local',
            'password' => 'password123',
            'role' => \App\Models\User::ROLE_SUPERVISOR,
            'sede' => 'FLOTA',
        ]);
        $sup->user_id = $user->id;
        $sup->save();

        $this->org->asignarEquipoPorUnidad($sup);

        $nodo = $this->org->tree($flota->id)->first();
        $this->assertEqualsCanonicalizing(['TEC UNO', 'TEC DOS'], $nodo['equipo']->map->nombre()->all());
        $this->assertEqualsCanonicalizing(['TEC UNO', 'TEC DOS'], $nodo['grupos']->first()['empleados']->map->nombre()->all());

        $ids = $this->org->idsPersonalACargo($user, false);
        $this->assertEqualsCanonicalizing([(int) $tec1->id, (int) $tec2->id], $ids);
        $this->assertEqualsCanonicalizing([$sup->id], $tec1->fresh()->jefes->pluck('id')->all());
    }

    public function test_supervisor_no_ve_personal_de_otra_sede_por_vinculo_viejo(): void
    {
        $centro = NominaSede::create(['nombre' => 'Centro', 'codigo' => 'CENTRO', 'tipo' => 'SEDE', 'estado' => 'ACTIVO']);
        $virtudes = NominaSede::create(['nombre' => 'Virtudes', 'codigo' => 'VIRTUDES', 'tipo' => 'SEDE', 'estado' => 'ACTIVO']);
        $cargoSup = NominaCargo::create(['nombre' => 'Supervisor de sede', 'estado' => 'ACTIVO']);
        $cargoVen = NominaCargo::create(['nombre' => 'Asesor de venta', 'estado' => 'ACTIVO']);

        $ivania = $this->empleado('27844739', 'IVANIA SANCHEZ', $centro, $cargoSup, true);
        $deCentro = $this->empleado('111', 'ASESOR CENTRO', $centro, $cargoVen, false);
        $deVirtudes = $this->empleado('222', 'ASESOR VIRTUDES', $virtudes, $cargoVen, false);

        $user = \App\Models\User::create([
            'name' => 'Ivania',
            'email' => 'ivania-org@test.local',
            'password' => 'password123',
            'role' => \App\Models\User::ROLE_SUPERVISOR,
            'sede' => 'CENTRO',
        ]);
        $ivania->user_id = $user->id;
        $ivania->save();

        // Vínculo viejo incorrecto: Virtudes apuntando a supervisora de Centro.
        $this->org->syncJefes($deVirtudes, [$ivania->id]);
        $this->org->asignarEquipoPorUnidad($ivania);

        $ids = $this->org->idsPersonalACargo($user, true);
        $this->assertContains((int) $deCentro->id, $ids);
        $this->assertContains((int) $ivania->id, $ids);
        $this->assertNotContains((int) $deVirtudes->id, $ids);
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
