<?php

namespace Tests\Feature\Nomina;

use App\Models\Cliente;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaPrestamoAbono;
use App\Models\Nomina\NominaPrestamoPlan;
use App\Models\User;
use App\Services\Nomina\LoanService;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class PrestamosEscritorioLibreTest extends TestCase
{
    use CreatesNominaSchema;

    private User $rrhh;

    private NominaEmpleado $empleado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();

        $this->rrhh = User::create([
            'name' => 'RRHH',
            'email' => 'prestamos-libre@test.local',
            'password' => 'password123',
            'role' => User::ROLE_RRHH,
        ]);

        $this->empleado = NominaEmpleado::create([
            'cliente_id' => Cliente::create(['cedula' => '28111222', 'nombre' => 'Deudor Libre'])->id,
            'salario_base' => 800,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'fecha_ingreso' => '2025-01-01',
        ]);
    }

    public function test_escritorio_lista_deudores_con_totales(): void
    {
        $loan = app(LoanService::class);
        $loan->create($this->empleado, [
            'fecha' => '2026-09-04',
            'monto_original' => 100,
            'motivo' => 'Uno',
        ], $this->rrhh->id);
        $loan->create($this->empleado, [
            'fecha' => '2026-09-04',
            'monto_original' => 50,
            'motivo' => 'Dos',
        ], $this->rrhh->id);

        $this->actingAs($this->rrhh)
            ->get(route('nomina.prestamos.index'))
            ->assertOk()
            ->assertSee('Deudor Libre')
            ->assertSee('150.00')
            ->assertSee('Cobrar / descontar');
    }

    public function test_modal_pago_y_programar_nomina(): void
    {
        $loan = app(LoanService::class);
        $p1 = $loan->create($this->empleado, [
            'fecha' => '2026-09-01',
            'monto_original' => 100,
        ], $this->rrhh->id);

        $this->actingAs($this->rrhh)
            ->post(route('nomina.prestamos.cobrar', $this->empleado), [
                'modo' => 'PAGO',
                'fecha' => '2026-09-04',
                'monto' => 40,
                'tipo' => 'EFECTIVO',
            ])
            ->assertRedirect();

        $this->assertEquals(60.0, (float) $p1->fresh()->saldo_pendiente);
        $this->assertDatabaseHas('nomina_prestamo_abonos', [
            'prestamo_id' => $p1->id,
            'monto' => 40,
            'tipo' => NominaPrestamoAbono::TIPO_EFECTIVO,
        ]);

        $this->actingAs($this->rrhh)
            ->post(route('nomina.prestamos.cobrar', $this->empleado), [
                'modo' => 'NOMINA',
                'fecha' => '2026-09-04',
                'monto' => 25,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('nomina_prestamo_planes', [
            'prestamo_id' => $p1->id,
            'empleado_id' => $this->empleado->id,
            'monto' => 25,
            'destino' => NominaPrestamoPlan::DESTINO_NOMINA,
            'estado' => NominaPrestamoPlan::PENDIENTE,
            'cuota_id' => null,
        ]);
    }

    public function test_genera_txt_con_tasa_bcv(): void
    {
        $bcv = \Mockery::mock(\App\Services\BcvRateService::class);
        $bcv->shouldReceive('getRateForToday')->andReturn(40.0);
        $this->app->instance(\App\Services\BcvRateService::class, $bcv);

        $loan = app(LoanService::class);
        $prestamo = $loan->create($this->empleado, [
            'fecha' => now()->toDateString(),
            'monto_original' => 10,
            'motivo' => 'TXT',
        ], $this->rrhh->id);

        $txt = $this->actingAs($this->rrhh)
            ->get(route('nomina.prestamos.txt', ['prestamo' => $prestamo->id]))
            ->assertOk()
            ->streamedContent();

        // 10 USD * 40 = 400 Bs = 40000 céntimos
        $this->assertStringContainsString('00000000000000040000', $txt);
        $this->assertStringContainsString('V028111222', $txt);
    }

    public function test_txt_del_dia_separa_un_archivo_por_empresa(): void
    {
        $doral = \App\Models\Nomina\NominaEmpresa::create(['codigo' => 'J401722296', 'nombre' => 'Doral', 'estado' => 'ACTIVO']);
        $nunes = \App\Models\Nomina\NominaEmpresa::create(['codigo' => 'J123', 'nombre' => 'Nunes', 'estado' => 'ACTIVO']);
        $this->empleado->update(['empresa_id' => $doral->id]);

        $otro = NominaEmpleado::create([
            'cliente_id' => Cliente::create(['cedula' => '888111', 'nombre' => 'Otra Persona'])->id,
            'salario_base' => 100,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'empresa_id' => $nunes->id,
        ]);

        $loan = app(LoanService::class);
        $loan->create($this->empleado, ['fecha' => '2026-09-05', 'monto_original' => 10], $this->rrhh->id);
        $loan->create($otro, ['fecha' => '2026-09-05', 'monto_original' => 20], $this->rrhh->id);

        $bcv = \Mockery::mock(\App\Services\BcvRateService::class);
        $bcv->shouldReceive('getRateForToday')->andReturn(40.0);
        $this->app->instance(\App\Services\BcvRateService::class, $bcv);

        $zipBin = $this->actingAs($this->rrhh)
            ->get(route('nomina.prestamos.txt', ['fecha' => '2026-09-05']))
            ->assertOk()
            ->assertHeader('content-type', 'application/zip')
            ->streamedContent();

        $tmp = tempnam(sys_get_temp_dir(), 'prestamos_zip_test_');
        file_put_contents($tmp, $zipBin);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $this->assertNotFalse($zip->locateName('prestamos_J401722296_20260905.txt'));
        $this->assertNotFalse($zip->locateName('prestamos_J123_20260905.txt'));
        $zip->close();
        @unlink($tmp);

        $doralTxt = $this->actingAs($this->rrhh)
            ->get(route('nomina.prestamos.txt', ['fecha' => '2026-09-05', 'empresa' => $doral->id]))
            ->assertOk()
            ->streamedContent();
        $this->assertStringContainsString('V028111222', $doralTxt);
    }

    public function test_alta_desde_escritorio(): void
    {
        $this->actingAs($this->rrhh)
            ->post(route('nomina.prestamos.escritorio'), [
                'empleado_id' => $this->empleado->id,
                'fecha' => '2026-09-04',
                'monto_original' => 75.5,
                'motivo' => 'Desde escritorio',
                'q' => 'Deudor',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('nomina_prestamos', [
            'empleado_id' => $this->empleado->id,
            'monto_original' => 75.5,
            'frecuencia' => 'LIBRE',
            'numero_cuotas' => 0,
        ]);
    }
}
