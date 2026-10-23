<?php

namespace Tests\Feature;

use App\Models\ConciliacionLinea;
use App\Services\Conciliacion\TransferenciasDivisasImporter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TransferenciasDivisasImporterTest extends TestCase
{
    use \Tests\Concerns\CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('conciliacion_lineas')) {
            Schema::create('conciliacion_lineas', function (Blueprint $t) {
                $t->id();
                $t->string('banco')->nullable();
                $t->string('titular')->nullable();
                $t->date('fecha');
                $t->string('descripcion');
                $t->string('referencia')->nullable();
                $t->decimal('monto', 15, 2);
                $t->string('estado')->default('pendiente');
                $t->string('tipo')->nullable();
                $t->unsignedBigInteger('flujo_caja_id')->nullable();
                $t->unsignedBigInteger('tesoreria_ingreso_id')->nullable();
                $t->unsignedBigInteger('compra_divisa_id')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('compra_divisas')) {
            Schema::create('compra_divisas', function (Blueprint $t) {
                $t->id();
                $t->date('fecha');
                $t->string('banco')->nullable();
                $t->string('titular')->nullable();
                $t->string('categoria_cuenta')->nullable();
                $t->string('referencia')->nullable();
                $t->string('concepto')->nullable();
                $t->string('motivo')->nullable();
                $t->decimal('monto_bs', 18, 2)->default(0);
                $t->decimal('monto_usd', 18, 4)->nullable();
                $t->decimal('tasa_cambio', 18, 6)->nullable();
                $t->boolean('es_conciliado')->default(false);
                $t->string('comprobante_url')->nullable();
                $t->json('comprobantes')->nullable();
                $t->timestamps();
            });
        }
    }

    private function linea(string $fecha, string $ref, float $monto, string $desc = 'CREDITO INMEDIATO CAMARA DE COMPENSACION'): ConciliacionLinea
    {
        return ConciliacionLinea::create([
            'banco' => 'MERCANTIL', 'titular' => 'GRUPO JENU', 'fecha' => $fecha, 'descripcion' => $desc,
            'referencia' => $ref, 'monto' => $monto, 'tipo' => 'cargo', 'estado' => 'pendiente',
        ]);
    }

    private function transferencia(int $nro, string $fecha, string $ref, float $usd, float $tasa): array
    {
        return ['nro' => $nro, 'banco' => 'wellsfargo', 'titular' => 'JUAN PEREZ', 'correo' => 'x@y.com', 'referencia' => $ref,
            'monto_usd' => $usd, 'fecha' => $fecha, 'tasa' => $tasa, 'monto_bs' => round($usd * $tasa, 2)];
    }

    public function test_se_sube_como_documento_adicional_con_vista_previa_y_confirmacion(): void
    {
        $this->setUpNominaSchema();
        $linea = $this->linea('2026-08-03', '20491032708', 161400.00);
        $user = \App\Models\User::create([
            'name' => 'Contabilidad', 'email' => 'contab-divisas@test.local', 'password' => 'password123', 'role' => \App\Models\User::ROLE_CONTABILIDAD,
        ]);

        $html = '<table style="font-family: DejaVu Sans; font-size: 9px;"><tr>'
            . implode('', array_map(fn ($h) => "<td>{$h}</td>", ['NRO', 'Banco', 'Títular', 'Correo', 'Referencia', 'Monto', 'Fecha', 'BCV', 'Binance', 'Interna']))
            . '</tr><tr>'
            . implode('', array_map(fn ($v) => "<td>{$v}</td>", ['1', 'wellsfargo', 'JUAN PEREZ', 'x@y.com', '032708', '200.00', '03/08/2026', '790.00', '930.00', '807.00']))
            . '</tr><tr>'
            . implode('', array_map(fn ($v) => "<td>{$v}</td>", ['2', 'citizens', 'ANA ROJAS', 'a@b.com', '555555', '50.00', '04/08/2026', '790.00', '930.00', '810.00']))
            . '</tr></table>';
        $tmp = tempnam(sys_get_temp_dir(), 'divisas') . '.pdf';
        file_put_contents($tmp, \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('letter', 'landscape')->output());
        $archivo = new \Illuminate\Http\UploadedFile($tmp, 'transferencias.pdf', 'application/pdf', null, true);

        $this->actingAs($user)
            ->post(route('finanzas.conciliaciones.divisas.preview'), [
                'archivo' => $archivo, 'banco' => 'MERCANTIL', 'titular' => 'GRUPO JENU',
            ])
            ->assertOk()
            ->assertSee('Confirmar y conciliar')
            ->assertSee('ANA ROJAS');

        $this->assertSame('pendiente', $linea->fresh()->estado);
        $token = array_key_first(session('conciliacion_divisas'));

        $this->actingAs($user)
            ->post(route('finanzas.conciliaciones.divisas.aplicar'), ['token' => $token])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('conciliado', $linea->fresh()->estado);
        $this->assertSame(1, DB::table('compra_divisas')->count());

        // El token se usa una sola vez.
        $this->actingAs($user)
            ->post(route('finanzas.conciliaciones.divisas.aplicar'), ['token' => $token])
            ->assertSessionHas('error');
    }

    public function test_por_monto_no_se_toma_el_debito_que_tiene_la_referencia_de_otra_transferencia(): void
    {
        // Dos envíos de $100 × 842 = 84.200: el de Jhonny trae la ref del débito, el de Yanira no aparece por ref.
        $deJhonny = $this->linea('2026-08-13', '20498492736', 84200.00);
        $sinRef = $this->linea('2026-08-13', '20400060812', 84200.00);

        $r = (new TransferenciasDivisasImporter())->emparejar([
            $this->transferencia(325, '2026-08-13', '111111', 100, 842),
            $this->transferencia(343, '2026-08-13', '492736', 100, 842),
        ], 'MERCANTIL', 'GRUPO JENU');

        $por = collect($r['pares'])->mapWithKeys(fn ($p) => [$p['t']['nro'] => $p['linea']->id]);
        $this->assertSame($deJhonny->id, $por[343]);
        $this->assertSame($sinRef->id, $por[325]);
    }

    public function test_empareja_por_referencia_final_y_por_monto_y_registra_compra_conciliada(): void
    {
        $porRef = $this->linea('2026-08-03', '20491032708', 161400.00);
        $comision = $this->linea('2026-08-03', '10791032708', 484.20, 'COMISION POR TRANSFERENCIA DE FONDOS');
        $porMonto = $this->linea('2026-08-24', '20400000001', 44250.00);
        $otro = $this->linea('2026-08-24', '20400000002', 9999.00);

        $importer = new TransferenciasDivisasImporter();
        $r = $importer->emparejar([
            $this->transferencia(1, '2026-08-03', '032708', 200, 807),
            $this->transferencia(2, '2026-08-23', '071187', 50, 885),
            $this->transferencia(3, '2026-08-01', '999999', 100, 814),
        ], 'MERCANTIL', 'GRUPO JENU');

        $this->assertSame([$porRef->id, $porMonto->id], array_map(fn ($p) => $p['linea']->id, $r['pares']));
        $this->assertSame(['referencia_final', 'monto_fecha'], array_column($r['pares'], 'como'));
        $this->assertSame([3], array_column($r['sin_pareja'], 'nro'));

        $this->assertSame(2, $importer->aplicar($r['pares']));

        $l = $porRef->fresh();
        $this->assertSame('conciliado', $l->estado);
        $compra = DB::table('compra_divisas')->find($l->compra_divisa_id);
        $this->assertEquals(161400.00, (float) $compra->monto_bs);
        $this->assertEquals(200.0, (float) $compra->monto_usd);
        $this->assertEquals(807.0, (float) $compra->tasa_cambio);
        $this->assertTrue((bool) $compra->es_conciliado);
        $this->assertStringContainsString('WELLSFARGO · JUAN PEREZ · $200.00 x 807', $compra->motivo);

        $this->assertSame('pendiente', $comision->fresh()->estado);
        $this->assertSame('pendiente', $otro->fresh()->estado);

        // Misma referencia pero el banco cobró con otra tasa: $50 × 885 en el reporte, 43.750 (= × 875) en el banco.
        $otraTasa = $this->linea('2026-08-24', '47900071188', 43750.00, 'TRANSF ENTRE CTAS BM PERS JURIDICA');
        $r3 = $importer->emparejar([$this->transferencia(131, '2026-08-24', '071188', 50, 885)], 'MERCANTIL', 'GRUPO JENU');
        $this->assertSame([$otraTasa->id], array_map(fn ($p) => $p['linea']->id, $r3['pares']));
        $this->assertSame('referencia_otra_tasa', $r3['pares'][0]['como']);
        $importer->aplicar($r3['pares']);
        $compraOtra = DB::table('compra_divisas')->find($otraTasa->fresh()->compra_divisa_id);
        $this->assertEquals(43750.00, (float) $compraOtra->monto_bs);
        $this->assertEquals(875.0, (float) $compraOtra->tasa_cambio);
        $this->assertStringContainsString('tasa en el reporte 885', $compraOtra->motivo);
        DB::table('compra_divisas')->where('id', $compraOtra->id)->delete();

        // Pago partido: 4.000.000 con la referencia + 1.307.000 el mismo día = $5.800 × 915.
        $parte1 = $this->linea('2026-08-28', '47900062614', 4000000.00, 'TRANSF ENTRE CTAS BM PERS JURIDICA');
        $parte2 = $this->linea('2026-08-28', '47900076720', 1307000.00, 'TRANSF ENTRE CTAS BM PERS JURIDICA');
        $this->linea('2026-08-28', '47900076721', 500.00, 'TRANSF ENTRE CTAS BM PERS JURIDICA');
        $r4 = $importer->emparejar([$this->transferencia(67, '2026-08-28', '062614', 5800, 915)], 'MERCANTIL', 'GRUPO JENU');
        $this->assertSame('pago_partido', $r4['pares'][0]['como']);
        $this->assertSame($parte1->id, $r4['pares'][0]['linea']->id);
        $this->assertSame([$parte2->id], array_map(fn ($x) => $x->id, $r4['pares'][0]['extras']));
        $importer->aplicar($r4['pares']);
        $this->assertSame('conciliado', $parte2->fresh()->estado);
        $this->assertSame($parte1->fresh()->compra_divisa_id, $parte2->fresh()->compra_divisa_id);
        $compraPartida = DB::table('compra_divisas')->find($parte1->fresh()->compra_divisa_id);
        $this->assertEquals(5307000.00, (float) $compraPartida->monto_bs);
        $this->assertStringContainsString('pagado en 2 débitos', $compraPartida->motivo);
        DB::table('compra_divisas')->where('id', $compraPartida->id)->delete();

        // Correrlo otra vez no duplica nada, aunque haya otro débito pendiente con el mismo monto.
        $mismoMonto = $this->linea('2026-08-03', '20400099999', 161400.00);
        $r2 = $importer->emparejar([$this->transferencia(1, '2026-08-03', '032708', 200, 807)], 'MERCANTIL', 'GRUPO JENU');
        $this->assertSame([], $r2['pares']);
        $this->assertSame([1], array_column($r2['ya_registradas'], 'nro'));
        $this->assertSame('pendiente', $mismoMonto->fresh()->estado);
        $this->assertSame(2, DB::table('compra_divisas')->count());
    }
}
