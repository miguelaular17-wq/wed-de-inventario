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

class PagosManualesTest extends TestCase
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

    private function linea(string $banco, string $titular, string $fecha, string $ref, float $monto, array $extra = []): ConciliacionLinea
    {
        return ConciliacionLinea::create($extra + [
            'banco' => $banco, 'titular' => $titular, 'fecha' => $fecha, 'descripcion' => 'PAGOMOVIL', 'referencia' => $ref,
            'monto' => $monto, 'estado' => 'pendiente', 'tipo' => 'abono',
        ]);
    }

    private function reporte(): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pm') . '.csv';
        file_put_contents($tmp, "REPORTE ESPECIFICO DE PAGOS MANUALES PALACIO DE LOS DETALLES\n01/08/2026 - 31/08/2026\n"
            . "NRO,Banco Receptor,Banco,Terminal,Cajero,Ref,Monto Bs.,Monto USD / USDT,Fecha Operación,Fecha\n"
            . "1,BANCO NACIONAL,0102,Caja 3 - Doral,MARIANGELA DIAZ,084918,6418,8.57,,01/08/2026 15:53:06\n"
            . "2,BANCO NACIONAL,0134,Caja 2,JHONAYSY NAVAS,791586,1189.46,0,,01/08/2026 17:47:19\n"
            . "3,BANCO NACIONAL,0191,Caja 1,ANA,767725,21405056,0,07/08/2026 00:00:00,07/08/2026 10:00:00\n"
            . "4,BANCO NACIONAL,0102,Caja 1,TESORERIA,4561,1300,0,,15/08/2026 10:00:00\n"
            . "5,BINANCE,,Caja 2,JHONAYSY NAVAS,50763,0,111,,01/08/2026 17:50:15\n");

        return new UploadedFile($tmp, 'cloud_mai_pagos_terminal_8166.csv', 'text/csv', null, true);
    }

    public function test_busca_cada_pago_en_todos_los_bancos_y_concilia_al_confirmar(): void
    {
        $banesco = $this->linea('BANESCO', 'GRUPO JRZ', '2026-08-03', '965084918', 6418);
        $venezuela = $this->linea('VENEZUELA', 'GRUPO JRZ', '2026-08-01', '62137791586', 1189.46);
        $otra = $this->linea('BNC', 'LNACEH', '2026-08-01', '62137791586', 1189.46, ['estado' => 'conciliado', 'tesoreria_ingreso_id' => 99]);
        // Copia del abono de Banesco (extracto cargado dos veces): al resubir no se concilia la copia.
        $copiaBanesco = $this->linea('BANESCO', 'GRUPO JRZ', '2026-08-03', '965084918', 6418);
        $x100 = $this->linea('BANESCO', 'LNACEH', '2026-08-07', '33767725', 214050.56);
        $user = User::create(['name' => 'Contab', 'email' => 'contab-pm@test.local', 'password' => 'password123', 'role' => User::ROLE_CONTABILIDAD]);

        $this->actingAs($user)->post(route('finanzas.conciliaciones.pagos_manuales.preview'), ['archivo' => $this->reporte()])
            ->assertOk()
            ->assertSee('Se concilian al confirmar (2)')
            ->assertSee('Para revisar (1)')
            ->assertSee('el monto en Cloud está ×100')
            ->assertSee('Sin abono en ningún banco (2)')
            ->assertSee('no hay extractos en divisas cargados');
        $this->assertSame('pendiente', $banesco->fresh()->estado);

        $token = array_key_first(session('pagos_manuales'));
        $this->actingAs($user)->post(route('finanzas.conciliaciones.pagos_manuales.aplicar'), ['token' => $token])
            ->assertRedirect(route('finanzas.conciliaciones'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '2 conciliados en bolívares'));

        foreach ([$banesco, $venezuela] as $l) {
            $l = $l->fresh();
            $this->assertSame('conciliado', $l->estado);
            $ing = TesoreriaIngreso::find($l->tesoreria_ingreso_id);
            $this->assertSame([$l->banco, $l->titular, 'banco'], [$ing->banco, $ing->titular, $ing->tipo]);
            $this->assertTrue((bool) $ing->es_conciliado);
        }
        $this->assertStringContainsString('Pago manual Cloud #1', TesoreriaIngreso::find($banesco->fresh()->tesoreria_ingreso_id)->descripcion);
        $this->assertSame(99, (int) $otra->fresh()->tesoreria_ingreso_id);
        $this->assertSame('pendiente', $x100->fresh()->estado);

        // Subirlo otra vez: lo ya conciliado no se duplica.
        $this->actingAs($user)->post(route('finanzas.conciliaciones.pagos_manuales.preview'), ['archivo' => $this->reporte()])
            ->assertOk()
            ->assertSee('Ya conciliados (2)')
            ->assertDontSee('Confirmar y conciliar');
        $this->assertSame(2, TesoreriaIngreso::query()->count());
        $this->assertSame('pendiente', $copiaBanesco->fresh()->estado);
    }
}
