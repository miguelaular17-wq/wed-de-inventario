<?php

namespace Tests\Feature;

use App\Models\ConciliacionLinea;
use App\Models\TesoreriaIngreso;
use App\Models\User;
use App\Services\Conciliacion\MercantilLotesConciliador;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class MercantilLotesConciliadorTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        if (! Schema::hasTable('tesoreria_ingresos')) {
            Schema::create('tesoreria_ingresos', function (Blueprint $table) {
                $table->id();
                $table->string('tipo', 32);
                $table->string('banco')->nullable();
                $table->string('titular')->nullable();
                $table->date('fecha');
                $table->decimal('monto', 15, 2);
                $table->string('lote_referencia')->nullable();
                $table->boolean('es_conciliado')->default(false);
                $table->text('descripcion')->nullable();
                $table->string('comprobante_path')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('conciliacion_lineas')) {
            Schema::create('conciliacion_lineas', function (Blueprint $table) {
                $table->id();
                $table->string('banco')->nullable();
                $table->string('titular')->nullable();
                $table->date('fecha')->nullable();
                $table->text('descripcion')->nullable();
                $table->string('referencia')->nullable();
                $table->decimal('monto', 15, 2)->default(0);
                $table->string('estado', 32)->default('pendiente');
                $table->string('tipo', 32)->nullable();
                $table->boolean('forzar_comision')->default(false);
                $table->string('session_id')->nullable();
                $table->unsignedBigInteger('flujo_caja_id')->nullable();
                $table->unsignedBigInteger('tesoreria_ingreso_id')->nullable();
                $table->unsignedBigInteger('compra_divisa_id')->nullable();
                $table->timestamps();
            });
        }
        (require database_path('migrations/2026_10_29_140000_create_conciliacion_lotes_parciales_table.php'))->up();
    }

    public function test_el_abono_del_cierre_se_concilia_con_los_lotes_del_dia_anterior_y_el_descuento_es_comision(): void
    {
        $l277 = $this->lote('2026-08-31', '277', 425647.15);
        $copia = $this->lote('2026-08-31', '277', 425647.15);
        $l338 = $this->lote('2026-08-31', '338', 893426.91);
        $otroDia = $this->lote('2026-09-01', '278', 823680.51);
        $abono = $this->linea('2026-09-01', '14502001243', 'CANCELACION RESUMEN DE VENTAS Y RETENCIO', 1319074.06, 'abono');
        $descuento = $this->linea('2026-09-01', '95402001243', 'DESCUENTO TARJETA', 26381.47, 'cargo');

        $r = $this->servicio()->conciliarAutomatico('2026-09-01', '2026-09-30', 'GRUPO JRZ');

        $this->assertSame(['conciliados' => 1, 'descuentos' => 1, 'sin_cuadrar' => 0], $r);
        $this->assertSame('conciliado', $abono->fresh()->estado);
        $this->assertSame((int) $l338->id, (int) $abono->fresh()->tesoreria_ingreso_id);
        $this->assertTrue((bool) $l277->fresh()->es_conciliado);
        $this->assertTrue((bool) $l338->fresh()->es_conciliado);
        // La copia del lote registrado dos veces queda a la vista para depurarla; el lote del día siguiente no se toca.
        $this->assertFalse((bool) $copia->fresh()->es_conciliado);
        $this->assertFalse((bool) $otroDia->fresh()->es_conciliado);
        $this->assertTrue((bool) $descuento->fresh()->forzar_comision);
    }

    public function test_el_lunes_cada_abono_toma_su_sesion(): void
    {
        $viernes = [$this->lote('2026-09-04', '281', 853288.33), $this->lote('2026-09-04', '342', 277359.00)];
        $sabado = [$this->lote('2026-09-05', '282', 990738.00), $this->lote('2026-09-05', '343', 434805.52)];
        $domingo = [$this->lote('2026-09-06', '283', 424848.00), $this->lote('2026-09-06', '344', 134511.83)];
        $a1 = $this->linea('2026-09-07', '14502001247', 'CANCELACION RESUMEN DE VENTAS Y RETENCIO', 1130647.33, 'abono');
        $a2 = $this->linea('2026-09-07', '14502001248', 'CANCELACION RESUMEN DE VENTAS Y RETENCIO', 1425543.52, 'abono');
        $a3 = $this->linea('2026-09-07', '14502001249', 'CANCELACION RESUMEN DE VENTAS Y RETENCIO', 559359.83, 'abono');

        $this->assertSame(3, $this->servicio()->conciliarAutomatico('2026-09-01', '2026-09-30')['conciliados']);

        $this->assertSame((int) $viernes[0]->id, (int) $a1->fresh()->tesoreria_ingreso_id);
        $this->assertSame((int) $sabado[0]->id, (int) $a2->fresh()->tesoreria_ingreso_id);
        $this->assertSame((int) $domingo[0]->id, (int) $a3->fresh()->tesoreria_ingreso_id);
        $this->assertSame(6, TesoreriaIngreso::query()->where('es_conciliado', true)->count());
    }

    public function test_con_un_lote_que_falta_el_balance_dice_cual_registrar_y_no_lo_crea(): void
    {
        $l278 = $this->lote('2026-09-01', '278', 823680.51);
        $l339 = $this->lote('2026-09-01', '339', 454660.50);
        $abono = $this->linea('2026-09-02', '14502001244', 'CANCELACION RESUMEN DE VENTAS Y RETENCIO', 1308438.05, 'abono');
        $descuento = $this->linea('2026-09-02', '95402001244', 'DESCUENTO TARJETA', 26168.77, 'cargo');

        $this->assertSame(1, $this->servicio()->conciliarAutomatico('2026-09-01', '2026-09-30')['sin_cuadrar']);
        $this->assertSame('pendiente', $abono->fresh()->estado);

        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        $excel = $this->excelBalance([
            ['278', 823680.51, 807206.90],
            ['285', 30097.04, 29495.10],
            ['339', 454660.50, 445567.28],
        ], 1308438.05, 1282269.28);

        $vista = $this->actingAs($user)->post(route('finanzas.conciliaciones.mercantil_lotes.preview'), [
            'titular' => 'GRUPO JRZ',
            'archivos' => [$excel],
            'volver' => 'lotes',
        ]);
        $vista->assertOk()
            ->assertSee('Falta en Tesorería: se concilia solo cuando lo registres', false)
            ->assertSee('Falta registrar el lote 285 por Bs 30.097,04 (cierre del 01/09/2026)')
            ->assertSee('Lote #'.$l278->id)
            ->assertSee('Confirmar y conciliar');
        $token = $vista->viewData('token');

        // Se concilian los lotes presentes; el abono queda pendiente por lo que falta y no se crea nada en Tesorería.
        $this->actingAs($user)->post(route('finanzas.conciliaciones.mercantil_lotes.aplicar'), ['token' => $token])
            ->assertRedirect(route('finanzas.conciliaciones.lotes'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 cierre(s) conciliados en parte'));
        $this->assertSame(2, TesoreriaIngreso::query()->count());
        $this->assertTrue((bool) $l278->fresh()->es_conciliado);
        $this->assertTrue((bool) $l339->fresh()->es_conciliado);
        $this->assertSame('pendiente', $abono->fresh()->estado);
        $this->assertNull($abono->fresh()->tesoreria_ingreso_id);
        $this->assertTrue((bool) $descuento->fresh()->forzar_comision);
        $parcial = $this->servicio()->parciales([$abono->id])->get($abono->id);
        $this->assertSame(['285'], $parcial->whereNull('tesoreria_ingreso_id')->pluck('lote')->all());

        // Subir otra vez el mismo balance no lo aplica dos veces.
        $otra = $this->actingAs($user)->post(route('finanzas.conciliaciones.mercantil_lotes.preview'), [
            'titular' => 'GRUPO JRZ', 'archivos' => [$this->excelBalance([['278', 823680.51, 807206.90], ['285', 30097.04, 29495.10], ['339', 454660.50, 445567.28]], 1308438.05, 1282269.28)],
        ]);
        $otra->assertSee('Este abono ya tiene su balance aplicado')->assertDontSee('Confirmar y conciliar');

        // Registrado el lote que faltaba, se completa solo.
        $l285 = $this->lote('2026-09-01', '285', 30097.04);
        $this->assertSame(1, $this->servicio()->conciliarAutomatico('2026-09-01', '2026-09-30')['conciliados']);
        $this->assertTrue((bool) $l285->fresh()->es_conciliado);
        $this->assertSame('conciliado', $abono->fresh()->estado);
        $this->assertSame((int) $l278->id, (int) $abono->fresh()->tesoreria_ingreso_id);
    }

    public function test_el_lote_faltante_registrado_con_otro_numero_se_completa_a_mano(): void
    {
        $l278 = $this->lote('2026-09-01', '278', 823680.51);
        $abono = $this->linea('2026-09-02', '14502001244', 'CANCELACION RESUMEN DE VENTAS Y RETENCIO', 853777.55, 'abono');
        $plan = $this->servicio()->planBalance([
            ['lote' => '278', 'bruto' => 823680.51, 'descuento' => 0, 'retencion' => 0, 'abonado' => 823680.51],
            ['lote' => '285', 'bruto' => 30097.04, 'descuento' => 0, 'retencion' => 0, 'abonado' => 30097.04],
        ], 'GRUPO JRZ');
        $this->assertNull($plan['error']);
        $this->assertSame('parcial', $this->servicio()->aplicarBalance($plan));

        // Lo registraron como lote 9285: no se completa solo (otro número)…
        $mal = $this->lote('2026-09-01', '9285', 30097.04);
        $this->assertSame(0, $this->servicio()->conciliarAutomatico('2026-09-01', '2026-09-30')['conciliados']);
        $this->assertSame('pendiente', $abono->fresh()->estado);

        // …pero «Conciliar manualmente» con el abono lo completa: el monto es el que falta.
        $otro = $this->lote('2026-09-01', '1', 500.00);
        $this->assertFalse($this->servicio()->completarConLote($abono->fresh(), $otro));
        $this->assertTrue($this->servicio()->completarConLote($abono->fresh(), $mal));
        $this->assertSame('conciliado', $abono->fresh()->estado);
        $this->assertTrue((bool) $mal->fresh()->es_conciliado);
        $this->assertFalse((bool) $otro->fresh()->es_conciliado);
        $this->assertSame((int) $l278->id, (int) $abono->fresh()->tesoreria_ingreso_id);
    }

    public function test_lotes_repetidos_se_marcan_y_se_quitan_dejando_el_conciliado(): void
    {
        $conciliado = $this->lote('2026-09-01', '278', 823680.51);
        $conciliado->forceFill(['es_conciliado' => true])->save();
        $copia = $this->lote('2026-09-01', '278', 823680.51);
        $a = $this->lote('2026-09-02', '279', 619822.17);
        $b = $this->lote('2026-09-02', '279', 619822.17);
        $ligada = $this->lote('2026-09-02', '279', 619822.17);
        $this->linea('2026-09-03', '1', 'CANCELACION RESUMEN DE VENTAS Y RETENCIO', 1, 'abono')->forceFill(['tesoreria_ingreso_id' => $ligada->id])->save();
        $distinto = $this->lote('2026-09-02', '340', 533001.66);

        $servicio = app(\App\Services\Conciliacion\LotesRepetidos::class);
        $copias = $servicio->copias(TesoreriaIngreso::query()->get());
        $this->assertSame([$copia->id => $conciliado->id, $b->id => $a->id, $ligada->id => $a->id], $copias);

        \Illuminate\Support\Facades\Storage::fake('local');
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        $this->actingAs($user)->post(route('finanzas.conciliaciones.lotes.quitar_repetidos'), ['ids' => [$copia->id, $b->id, $ligada->id, $distinto->id, $conciliado->id]])
            ->assertRedirect(route('finanzas.conciliaciones.lotes'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Se quitaron 2 lote(s)'));

        $this->assertNull(TesoreriaIngreso::find($copia->id));
        $this->assertNull(TesoreriaIngreso::find($b->id));
        // El conciliado, el ligado a un movimiento del banco y el que no es copia se quedan.
        foreach ([$conciliado, $a, $ligada, $distinto] as $l) {
            $this->assertNotNull(TesoreriaIngreso::find($l->id));
        }
        $this->assertCount(1, \Illuminate\Support\Facades\Storage::disk('local')->files('respaldos'));
    }

    public function test_balance_sin_abono_o_con_otro_monto_en_tesoreria_da_error(): void
    {
        $this->lote('2026-09-01', '278', 800000.00);
        $this->linea('2026-09-02', '14502001244', 'CANCELACION RESUMEN DE VENTAS Y RETENCIO', 823680.51, 'abono');

        $plan = $this->servicio()->planBalance([['lote' => '278', 'bruto' => 823680.51, 'descuento' => 0, 'retencion' => 0, 'abonado' => 823680.51]], 'GRUPO JRZ');
        $this->assertStringContainsString('el lote 278 está por Bs 800.000,00', (string) $plan['error']);
        $this->assertSame([], collect($plan['filas'])->where('crear', true)->all());

        $sinAbono = $this->servicio()->planBalance([['lote' => '999', 'bruto' => 5.00, 'descuento' => 0, 'retencion' => 0, 'abonado' => 5.00]], 'GRUPO JRZ');
        $this->assertStringContainsString('No hay en el extracto', (string) $sinAbono['error']);
    }

    public function test_excel_del_balance_se_comprueba_con_el_descuento_tarjeta_si_no_hay_abono(): void
    {
        // Cierre del 01/10 (el abono «CANCELACION» ya no se importa): el banco deja el DESCUENTO TARJETA por bruto − abonado.
        $l303 = $this->lote('2026-10-01', '303', 227539.29);
        $l368 = $this->lote('2026-10-01', '368', 2009723.03);
        $descuento = $this->linea('2026-10-02', '95402001300', 'DESCUENTO TARJETA', 54920.73, 'cargo');
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        $excel = $this->excelBalance([['303', 227539.29, 222988.51], ['308', 508771.49, 498596.04], ['368', 2009723.03, 1969528.53]], 2746033.81, 2691113.08);

        $vista = $this->actingAs($user)->post(route('finanzas.conciliaciones.mercantil_lotes.preview'), ['titular' => 'GRUPO JRZ', 'archivos' => [$excel]]);
        $vista->assertOk()->assertSee('Comprobado con el banco')->assertSee('Falta registrar el lote 308 por Bs 508.771,49 (cierre del 01/10/2026)')
            ->assertSee('Confirmar y conciliar');
        $this->actingAs($user)->post(route('finanzas.conciliaciones.mercantil_lotes.aplicar'), ['token' => $vista->viewData('token')])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'conciliados en parte'));
        $this->assertTrue((bool) $l303->fresh()->es_conciliado);
        $this->assertTrue((bool) $l368->fresh()->es_conciliado);
        $this->assertSame('pendiente', $descuento->fresh()->estado);

        // Al registrar el lote 308, el cierre se completa con su descuento.
        $l308 = $this->lote('2026-10-01', '308', 508771.49);
        $this->assertSame(1, $this->servicio()->conciliarAutomatico('2026-10-01', '2026-10-31')['conciliados']);
        $this->assertTrue((bool) $l308->fresh()->es_conciliado);
        $this->assertSame('conciliado', $descuento->fresh()->estado);
        $this->assertSame((int) $l368->id, (int) $descuento->fresh()->tesoreria_ingreso_id);
    }

    public function test_lectura_del_excel_valida_las_cuentas(): void
    {
        $lector = app(\App\Services\Conciliacion\MercantilBalanceExcel::class);
        $leer = function (UploadedFile $f) use ($lector) {
            return $lector->leer($f->getRealPath(), 'xlsx');
        };
        $ok = $leer($this->excelBalance([['0303', 227539.29, 222988.51], ['368', 2009723.03, 1969528.53]], 2237262.32, 2192517.04));
        $this->assertSame(['303', '368'], array_column($ok['lotes'], 'lote'));
        $this->assertSame(4550.78, $ok['lotes'][0]['descuento']);

        try {
            $leer($this->excelBalance([['303', 227539.29, 222988.51]], 300000.00, 222988.51));
            $this->fail('Debió rechazar un total que no cuadra');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('no da el Total General', $e->getMessage());
        }
        try {
            $leer($this->excelBalance([['303', 100.00, 150.00]], 100.00, 150.00));
            $this->fail('Debió rechazar un abonado mayor que el bruto');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Lote 303: no cuadra', $e->getMessage());
        }
    }

    /** Excel como el del portal: Lote | Importe Bruto | Total Abonado, y la fila «Total General». */
    private function excelBalance(array $filas, float $totalBruto, float $totalAbonado): UploadedFile
    {
        $hoja = array_merge([['Lote', 'Importe Bruto', 'Total Abonado']], $filas, [['Total General', $totalBruto, $totalAbonado]]);

        return UploadedFile::fake()->createWithContent('resumen_transacciones.xlsx', \App\Support\SimpleXlsxWriter::toString(['Balance' => $hoja]));
    }

    private function servicio(): MercantilLotesConciliador
    {
        return app(MercantilLotesConciliador::class);
    }

    private function lote(string $fecha, string $lote, float $monto): TesoreriaIngreso
    {
        return TesoreriaIngreso::create([
            'tipo' => 'punto_venta', 'banco' => 'MERCANTIL', 'titular' => 'JRZ', 'fecha' => $fecha,
            'monto' => $monto, 'lote_referencia' => $lote, 'descripcion' => 'SEDE DORAL', 'es_conciliado' => false,
        ]);
    }

    private function linea(string $fecha, string $ref, string $desc, float $monto, string $tipo): ConciliacionLinea
    {
        return ConciliacionLinea::create([
            'banco' => 'MERCANTIL', 'titular' => 'GRUPO JRZ', 'fecha' => $fecha, 'descripcion' => $desc,
            'referencia' => $ref, 'monto' => $monto, 'estado' => 'pendiente', 'tipo' => $tipo,
        ]);
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => 'Contabilidad', 'email' => 'conta'.uniqid().'@example.com', 'password' => bcrypt('x'), 'role' => $role,
        ]);
    }
}
