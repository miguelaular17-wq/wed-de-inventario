<?php

namespace Tests\Feature\Patrimonial;

use App\Models\Patrimonial\PatTransaccion;
use App\Models\Patrimonial\Propiedad;
use App\Models\User;
use App\Services\Patrimonial\PatrimonioReporteService;
use Tests\Concerns\CreatesPatrimonialSchema;
use Tests\TestCase;

class PatrimonioIncrementoValorTest extends TestCase
{
    use CreatesPatrimonialSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPatrimonialSchema();
    }

    public function test_reporte_mensual_excluye_remodelaciones_de_totales_y_detalle(): void
    {
        $propiedad = $this->propiedad();
        $this->transaccion($propiedad, 'Remodelación', 500, 'Techo nuevo', '2026-09-02');
        $this->transaccion($propiedad, 'Mantenimiento', 100, 'Limpieza mensual', '2026-09-03');

        $data = app(PatrimonioReporteService::class)->mensual(9, 2026, true);
        $fila = $data['filas']->first();

        $this->assertSame(100.0, $data['totales']['gastos']);
        $this->assertSame(['Mantenimiento'], collect($data['gastosPorCategoria'])->pluck('categoria')->all());
        $this->assertCount(1, $fila['transacciones']);
        $this->assertSame('Limpieza mensual', $fila['transacciones']->first()->descripcion);
    }

    public function test_incremento_de_valor_suma_solo_remodelaciones_hasta_fecha_de_corte(): void
    {
        $propiedad = $this->propiedad();
        $this->transaccion($propiedad, 'Remodelacion', 1000, 'Cocina', '2026-08-15');
        $this->transaccion($propiedad, 'Remodelación', 2000, 'Techo', '2026-09-10');
        $this->transaccion($propiedad, 'Mantenimiento', 400, 'Limpieza', '2026-09-11');
        $this->transaccion($propiedad, 'Remodelación', 3000, 'Fachada futura', '2026-10-01');

        $data = app(PatrimonioReporteService::class)->incrementoValor(9, 2026);
        $fila = $data['filas']->first();

        $this->assertSame(50000.0, $fila['valor_anterior']);
        $this->assertSame(3000.0, $fila['inversion_remodelaciones']);
        $this->assertSame(53000.0, $fila['valor_aproximado']);
        $this->assertCount(2, $fila['remodelaciones']);
    }

    public function test_reporte_de_incremento_tiene_vista_y_pdf_independientes(): void
    {
        $propiedad = $this->propiedad();
        $this->transaccion($propiedad, 'Remodelación', 1250, 'Baños renovados', '2026-09-04');
        $admin = User::create([
            'name' => 'Admin Patrimonial',
            'email' => 'pat-incremento-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
            'sede' => 'DORAL',
        ]);

        $this->actingAs($admin)
            ->withSession(['sede_local' => 'DORAL'])
            ->get(route('patrimonial.reportes.incremento_valor', ['mes' => 9, 'anio' => 2026]))
            ->assertOk()
            ->assertSee('Incremento de valor de las propiedades')
            ->assertSee('Valor anterior')
            ->assertSee('Baños renovados')
            ->assertSee('$51,250.00');

        $this->actingAs($admin)
            ->withSession(['sede_local' => 'DORAL'])
            ->get(route('patrimonial.reportes.incremento_valor.pdf', ['mes' => 9, 'anio' => 2026]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function propiedad(): Propiedad
    {
        return Propiedad::create([
            'codigo' => 'CASA-VALOR',
            'nombre' => 'Casa de Prueba',
            'tipo' => 'casa',
            'estado' => 'remodelacion',
            'valor_inversion' => 50000,
        ]);
    }

    private function transaccion(
        Propiedad $propiedad,
        string $categoria,
        float $monto,
        string $descripcion,
        string $fecha,
    ): PatTransaccion {
        $fechaCarbon = \Carbon\Carbon::parse($fecha);

        return PatTransaccion::create([
            'propiedad_id' => $propiedad->id,
            'tipo' => 'gasto',
            'categoria' => $categoria,
            'descripcion' => $descripcion,
            'monto' => $monto,
            'moneda' => 'usd',
            'mes' => $fechaCarbon->month,
            'anio' => $fechaCarbon->year,
            'fecha' => $fecha,
        ]);
    }
}
