<?php

namespace Tests\Feature;

use App\Models\ConciliacionLinea;
use App\Models\TesoreriaIngreso;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class PagosMovilesCloudTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        Storage::fake('local');
        if (! Schema::hasTable('tesoreria_ingresos')) {
            Schema::create('tesoreria_ingresos', function (Blueprint $t) {
                $t->id();
                $t->string('tipo', 32);
                $t->string('banco')->nullable();
                $t->string('titular')->nullable();
                $t->date('fecha');
                $t->decimal('monto', 15, 2);
                $t->string('lote_referencia')->nullable();
                $t->boolean('es_conciliado')->default(false);
                $t->text('descripcion')->nullable();
                $t->string('comprobante_path')->nullable();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('conciliacion_lineas')) {
            Schema::create('conciliacion_lineas', function (Blueprint $t) {
                $t->id();
                $t->string('banco')->nullable();
                $t->string('titular')->nullable();
                $t->date('fecha')->nullable();
                $t->text('descripcion')->nullable();
                $t->string('referencia')->nullable();
                $t->decimal('monto', 15, 2)->default(0);
                $t->string('estado', 32)->default('pendiente');
                $t->string('tipo', 32)->nullable();
                $t->unsignedBigInteger('flujo_caja_id')->nullable();
                $t->unsignedBigInteger('tesoreria_ingreso_id')->nullable();
                $t->unsignedBigInteger('compra_divisa_id')->nullable();
                $t->timestamps();
            });
        }
    }

    private function linea(string $fecha, string $ref, float $monto, array $extra = []): ConciliacionLinea
    {
        return ConciliacionLinea::create($extra + [
            'banco' => 'BANESCO',
            'titular' => 'GRUPO JRZ',
            'fecha' => $fecha,
            'descripcion' => 'PAGO MOVIL RECIBIDO',
            'referencia' => $ref,
            'monto' => $monto,
            'estado' => 'pendiente',
            'tipo' => 'abono',
        ]);
    }

    private function reporte(): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pmc') . '.csv';
        file_put_contents($tmp, "REPORTE ESPECIFICO DE BANESCO PALACIO DE LOS DETALLES\n01/09/2026 - 30/09/2026\n"
            . "NRO,RIF,ADMINISTRADOR,CAJERO,TERMINAL,LOCALIDAD,FECHA,BANCO EMISOR,REFERENCIA PAGO,MONTO,REFERENCIA BANCO,TIPO\n"
            . "1,J501653895,Palacio,JETSY NAVARRO,Caja 2,Las Virtudes,29/09/2026 8:26:57 PM,0191,951181,21611,5841************4499,Pago Movíl\n"
            . "2,J501653895,Palacio,OSCAR PALOMO,Caja 1,Sambil,29/09/2026 8:25:44 PM,0102,057500,237361,5841************0098,Pago Movíl\n"
            . "3,J501653895,Palacio,VALENTINA,Caja 1,Las Virtudes,29/09/2026 7:57:06 PM,0102,961603,4597,5842************2406,Pago Movíl\n");

        return new UploadedFile($tmp, 'Pagomovil banesco.csv', 'text/csv', null, true);
    }

    public function test_concilia_pagos_moviles_cloud_banesco_al_confirmar(): void
    {
        $ok1 = $this->linea('2026-09-29', '000951181', 21611);
        $ok2 = $this->linea('2026-09-30', '5841057500', 237361);
        $otraRef = $this->linea('2026-09-29', '999999', 4597);
        $user = User::create([
            'name' => 'Contab',
            'email' => 'contab-pmc@test.local',
            'password' => 'password123',
            'role' => User::ROLE_CONTABILIDAD,
        ]);

        $this->actingAs($user)->post(route('finanzas.conciliaciones.pagos_moviles.preview'), [
            'archivo' => $this->reporte(),
            'banco' => 'BANESCO',
            'titular' => 'GRUPO JRZ',
        ])
            ->assertOk()
            ->assertSee('Se concilian al confirmar (2)')
            ->assertSee('Para revisar (1)');

        $this->assertSame('pendiente', $ok1->fresh()->estado);

        $token = array_key_first(session('pagos_moviles_cloud'));
        $this->actingAs($user)->post(route('finanzas.conciliaciones.pagos_moviles.aplicar'), ['token' => $token])
            ->assertRedirect()
            ->assertSessionHas('success', fn ($m) => str_contains($m, '2 conciliados'));

        foreach ([$ok1, $ok2] as $l) {
            $l = $l->fresh();
            $this->assertSame('conciliado', $l->estado);
            $ing = TesoreriaIngreso::find($l->tesoreria_ingreso_id);
            $this->assertNotNull($ing);
            $this->assertTrue((bool) $ing->es_conciliado);
            $this->assertStringContainsString('Pago móvil Cloud', $ing->descripcion);
        }
        $this->assertSame('pendiente', $otraRef->fresh()->estado);

        $this->actingAs($user)->post(route('finanzas.conciliaciones.pagos_moviles.preview'), [
            'archivo' => $this->reporte(),
            'banco' => 'BANESCO',
            'titular' => 'GRUPO JRZ',
        ])
            ->assertOk()
            ->assertSee('Ya conciliados (2)')
            ->assertDontSee('Confirmar y conciliar');
    }

    public function test_rechaza_excel_sin_columnas_de_pago_movil(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'bad') . '.csv';
        file_put_contents($tmp, "Pago,Fecha,Importe,Monto USD\n1,01/09/2026,100,10\n");
        $user = User::create([
            'name' => 'Contab',
            'email' => 'contab-pmc2@test.local',
            'password' => 'password123',
            'role' => User::ROLE_CONTABILIDAD,
        ]);

        $this->actingAs($user)->post(route('finanzas.conciliaciones.pagos_moviles.preview'), [
            'archivo' => new UploadedFile($tmp, 'c2p.csv', 'text/csv', null, true),
            'banco' => 'BANESCO',
            'titular' => 'GRUPO JRZ',
        ])->assertRedirect()
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Referencia Pago'));
    }
}
