<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\GerencialDashboardService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class GerencialDashboardServiceTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        Carbon::setTestNow(Carbon::parse('2026-08-22', 'America/Caracas'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_ventas_por_sede_usan_cabecera_fac_menos_dev(): void
    {
        DB::table('ventas_documentos')->insert([
            ['sede' => 'DORAL', 'tipo_documento' => 'FAC', 'numero_documento' => '1', 'fecha' => '2026-08-10', 'estado' => 'registrado', 'total_neto_usd' => 151645.02, 'total_neto_bs' => 100, 'created_at' => now(), 'updated_at' => now()],
            ['sede' => 'DORAL', 'tipo_documento' => 'DEV', 'numero_documento' => '2', 'fecha' => '2026-08-11', 'estado' => 'registrado', 'total_neto_usd' => 6529.98, 'total_neto_bs' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['sede' => 'CENTRO', 'tipo_documento' => 'FAC', 'numero_documento' => '3', 'fecha' => '2026-08-10', 'estado' => 'registrado', 'total_neto_usd' => 50, 'total_neto_bs' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['sede' => 'DORAL', 'tipo_documento' => 'FAC', 'numero_documento' => '4', 'fecha' => '2026-08-10', 'estado' => 'guardado', 'total_neto_usd' => 9999, 'total_neto_bs' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $service = app(GerencialDashboardService::class);
        $periodo = $service->resolverPeriodo('mes', null, null);
        $resumen = $service->resumen($periodo, 'todas', null, null, null);
        $doral = collect($resumen['por_sede'])->firstWhere('sede', 'DORAL');

        $this->assertSame(145115.04, $doral['ventas_usd']);
        $this->assertSame(1, $doral['facturas']);
        $this->assertSame(1, $doral['devoluciones']);
        $this->assertSame(6529.98, $doral['devoluciones_usd']);
        $this->assertEquals(145165.04, $resumen['total']['ventas_usd']);
    }

    public function test_gerente_ve_el_dashboard_y_supervisor_no(): void
    {
        $gerente = User::create([
            'name' => 'Gerente',
            'email' => 'gerente-dash@test.local',
            'password' => 'password123',
            'role' => User::ROLE_GERENTE,
        ]);
        $this->actingAs($gerente)
            ->get(route('gerencial.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard gerencial')
            ->assertSee('DORAL');

        $supervisor = User::create([
            'name' => 'Supervisor',
            'email' => 'sup-dash@test.local',
            'password' => 'password123',
            'role' => User::ROLE_SUPERVISOR,
            'sede' => 'DORAL',
        ]);
        $this->actingAs($supervisor)
            ->get(route('gerencial.dashboard'))
            ->assertRedirect();
    }
}
