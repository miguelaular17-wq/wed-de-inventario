<?php

namespace Tests\Feature\Nomina;

use App\Models\Cliente;
use App\Models\Nomina\NominaComisionDescuento;
use App\Models\Nomina\NominaEmpleado;
use App\Models\Nomina\NominaEmpleadoAjuste;
use App\Models\Nomina\NominaPeriodo;
use App\Models\Nomina\NominaPrestamo;
use App\Models\Nomina\NominaPrestamoPlan;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class RegistrarAjusteComisionTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        Cache::put('tasa_bcv_'.now()->toDateString(), 40, 3600);
    }

    public function test_registra_un_bono_de_comision_en_la_quincena(): void
    {
        [$user, $empleado, $periodo] = $this->escena();

        $this->actingAs($user)
            ->post(route('nomina.comisiones.ajustes.store', $periodo), [
                'concepto' => 'bono',
                'empleado_id' => $empleado->id,
                'empleado_nombre' => 'Ana Vende',
                'monto' => 30,
                'motivo' => 'Bono de caja',
            ])
            ->assertRedirect(route('nomina.comisiones.show', ['periodo' => $periodo, 'ajuste' => 'bonos']));

        $bono = NominaEmpleadoAjuste::query()->first();
        $this->assertNotNull($bono);
        $this->assertSame(NominaEmpleadoAjuste::TIPO_BONIFICACION, $bono->tipo);
        $this->assertSame(NominaEmpleadoAjuste::DESTINO_COMISION, $bono->destino);
        $this->assertSame('30.00', number_format((float) $bono->monto, 2, '.', ''));
        $this->assertSame('2026-10-01', $bono->quincena_inicio->toDateString());
        $this->assertSame('2026-10-15', $bono->quincena_fin->toDateString());
    }

    public function test_no_registra_bono_si_la_persona_no_genera_comision(): void
    {
        [$user, $empleado, $periodo] = $this->escena(false);

        $this->actingAs($user)
            ->from(route('nomina.comisiones.show', $periodo))
            ->post(route('nomina.comisiones.ajustes.store', $periodo), [
                'concepto' => 'bono',
                'empleado_id' => $empleado->id,
                'monto' => 10,
                'motivo' => 'No debe entrar',
            ])
            ->assertRedirect(route('nomina.comisiones.show', ['periodo' => $periodo, 'ajuste' => 'bonos']))
            ->assertSessionHasErrors('empleado_id');

        $this->assertSame(0, NominaEmpleadoAjuste::query()->count());
    }

    public function test_registra_prestamo_y_lo_programa_en_la_quincena(): void
    {
        [$user, $empleado, $periodo] = $this->escena();

        $this->actingAs($user)
            ->post(route('nomina.comisiones.ajustes.store', $periodo), [
                'concepto' => 'prestamo',
                'empleado_id' => $empleado->id,
                'monto' => 80,
                'motivo' => 'Adelanto',
            ])
            ->assertRedirect(route('nomina.comisiones.show', ['periodo' => $periodo, 'ajuste' => 'prestamos']));

        $prestamo = NominaPrestamo::query()->first();
        $plan = NominaPrestamoPlan::query()->first();
        $this->assertNotNull($prestamo);
        $this->assertNotNull($plan);
        $this->assertSame($prestamo->id, $plan->prestamo_id);
        $this->assertSame(NominaPrestamoPlan::DESTINO_COMISION, $plan->destino);
        $this->assertSame('80.00', number_format((float) $plan->monto, 2, '.', ''));
    }

    public function test_registra_faltante_de_caja_para_descontar_en_comision(): void
    {
        [$user, $empleado, $periodo] = $this->escena();
        $empleado->cargo = 'Cajera';
        $empleado->save();

        $this->actingAs($user)
            ->post(route('nomina.comisiones.ajustes.store', $periodo), [
                'concepto' => 'faltante',
                'empleado_id' => $empleado->id,
                'monto' => 15,
                'motivo' => 'Cierre del sábado',
            ])
            ->assertRedirect(route('nomina.comisiones.show', ['periodo' => $periodo, 'ajuste' => 'faltantes']));

        $faltante = NominaComisionDescuento::query()->first();
        $this->assertNotNull($faltante);
        $this->assertSame('FALTANTE', $faltante->tipo);
        $this->assertSame('15.00', number_format((float) $faltante->monto, 2, '.', ''));
    }

    public function test_registra_deduccion_de_comision(): void
    {
        [$user, $empleado, $periodo] = $this->escena();

        $this->actingAs($user)
            ->post(route('nomina.comisiones.ajustes.store', $periodo), [
                'concepto' => 'descuento',
                'formato' => 'deduccion',
                'empleado_id' => $empleado->id,
                'monto' => 12,
                'motivo' => 'Uniforme',
            ])
            ->assertRedirect(route('nomina.comisiones.show', ['periodo' => $periodo, 'ajuste' => 'descuentos']));

        $descuento = NominaEmpleadoAjuste::query()->first();
        $this->assertSame(NominaEmpleadoAjuste::TIPO_DEDUCCION, $descuento->tipo);
        $this->assertSame(NominaEmpleadoAjuste::DESTINO_COMISION, $descuento->destino);
    }

    /**
     * @return array{0: User, 1: NominaEmpleado, 2: NominaPeriodo}
     */
    private function escena(bool $comision = true): array
    {
        $user = User::create([
            'name' => 'RRHH Alta',
            'email' => 'rrhh-alta-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_RRHH,
        ]);
        $cliente = Cliente::create([
            'cedula' => (string) random_int(30000000, 39999999),
            'nombre' => 'Ana Vende',
        ]);
        $empleado = NominaEmpleado::create([
            'cliente_id' => $cliente->id,
            'salario_base' => 500,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
            'modo_comision' => $comision ? NominaEmpleado::COMISION_VENTAS_PROPIAS : NominaEmpleado::COMISION_NINGUNA,
            'fecha_ingreso' => '2025-01-01',
        ]);
        $periodo = NominaPeriodo::create([
            'fecha_inicio' => '2026-10-01',
            'fecha_fin' => '2026-10-15',
            'etiqueta' => '01/10/2026 al 15/10/2026',
            'estado' => NominaPeriodo::ABIERTO,
            'tasa_bcv' => 40,
        ]);

        return [$user, $empleado, $periodo];
    }
}
