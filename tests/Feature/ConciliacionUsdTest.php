<?php

namespace Tests\Feature;

use App\Models\ConciliacionDivisaLinea;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class ConciliacionUsdTest extends TestCase
{
    use CreatesNominaSchema;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        \Illuminate\Support\Facades\Schema::dropIfExists('flujo_cajas');
        \Illuminate\Support\Facades\Schema::create('flujo_cajas', function (\Illuminate\Database\Schema\Blueprint $t) {
            $t->id();
            $t->date('fecha');
            $t->string('banco')->nullable();
            $t->string('titular')->nullable();
            $t->string('tipo')->nullable();
            $t->string('categoria_egreso')->nullable();
            $t->string('referencia')->nullable();
            $t->decimal('monto_usd', 18, 2)->nullable();
            $t->decimal('monto_bs', 18, 2)->nullable();
            $t->string('motivo')->nullable();
            $t->string('banco_receptor')->nullable();
            $t->string('titular_receptor')->nullable();
            $t->boolean('es_conciliado')->default(false);
            $t->boolean('anulado')->default(false);
            $t->timestamps();
        });
        (require database_path('migrations/2026_10_23_200000_create_conciliacion_divisas_tables.php'))->up();
        $this->user = User::create([
            'name' => 'Contabilidad', 'email' => 'contab-usd@test.local', 'password' => 'password123', 'role' => User::ROLE_CONTABILIDAD,
        ]);
    }

    private function egreso(array $datos): int
    {
        return DB::table('flujo_cajas')->insertGetId($datos + [
            'banco' => 'Binance', 'titular' => 'Grupo JENU', 'tipo' => 'egreso', 'categoria_egreso' => 'egreso_divisas',
            'monto_bs' => 0, 'es_conciliado' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function csv(): UploadedFile
    {
        $contenido = "Date(UTC),Type,Amount,Order ID\n"
            . "2026-10-20 14:00:00,Withdraw GO GLOBAL,-7000,461052490130497538\n"
            . "2026-10-20 15:10:00,Withdraw,-294,555\n"
            . "2026-10-20 15:11:00,Network Fee,-1.50,\n"
            . "2026-10-21 09:00:00,Deposit,500,777\n";
        $tmp = tempnam(sys_get_temp_dir(), 'usd') . '.csv';
        file_put_contents($tmp, $contenido);

        return new UploadedFile($tmp, 'binance_octubre.csv', 'text/csv', null, true);
    }

    private function subirEImportar(): \Illuminate\Testing\TestResponse
    {
        $this->actingAs($this->user)
            ->post(route('finanzas.conciliacion_usd.subir'), ['cuenta' => 'Binance|Grupo JENU', 'archivo' => $this->csv()])
            ->assertOk()
            ->assertSee('¿Qué columna es cada dato?');
        $token = array_key_last(session('conciliacion_usd'));

        return $this->actingAs($this->user)->post(route('finanzas.conciliacion_usd.importar'), [
            'token' => $token, 'fila_cabecera' => 0, 'col_fecha' => 0, 'col_descripcion' => [1], 'col_referencia' => 3,
            'modo_monto' => 'una', 'col_monto' => 2, 'formato_fecha' => 'auto',
        ]);
    }

    public function test_importa_extracto_generico_y_concilia_en_usd(): void
    {
        $conRef = $this->egreso(['fecha' => '2026-10-20', 'referencia' => '461052490130497538', 'monto_usd' => 7000, 'motivo' => 'GO GLOBAL 1ER PAGO']);
        $porMonto = $this->egreso(['fecha' => '2026-10-19', 'referencia' => null, 'monto_usd' => 294, 'motivo' => 'CAMBIO ZELLE A USDT']);
        $otraCuenta = $this->egreso(['banco' => 'Wells Fargo', 'titular' => 'Investment Doral', 'fecha' => '2026-10-20', 'referencia' => null, 'monto_usd' => 294, 'motivo' => 'OTRA CUENTA']);

        $this->subirEImportar()->assertRedirect()->assertSessionHas('success');

        $lineas = ConciliacionDivisaLinea::query()->orderBy('id')->get();
        $this->assertCount(4, $lineas);
        $this->assertSame([-7000.0, -294.0, -1.5, 500.0], $lineas->pluck('monto')->all());
        $this->assertSame(['conciliado', 'conciliado', 'comision', 'pendiente'], $lineas->pluck('estado')->all());
        $this->assertSame([$conRef, $porMonto], $lineas->take(2)->pluck('flujo_caja_id')->all());
        $this->assertTrue((bool) DB::table('flujo_cajas')->where('id', $conRef)->value('es_conciliado'));
        $this->assertFalse((bool) DB::table('flujo_cajas')->where('id', $otraCuenta)->value('es_conciliado'));

        // El mismo archivo otra vez no duplica (y el formato de columnas se recordó).
        $this->subirEImportar()->assertRedirect();
        $this->assertSame(4, ConciliacionDivisaLinea::query()->count());
        $this->assertDatabaseHas('conciliacion_divisas_formatos', ['banco' => 'Binance', 'titular' => 'Grupo JENU']);

        // Desligar y volver a ligar a mano.
        $linea = $lineas->first();
        $this->actingAs($this->user)->post(route('finanzas.conciliacion_usd.accion'), ['linea_id' => $linea->id, 'accion' => 'desvincular'])->assertRedirect();
        $this->assertSame('pendiente', $linea->fresh()->estado);
        $this->assertFalse((bool) DB::table('flujo_cajas')->where('id', $conRef)->value('es_conciliado'));
        $this->actingAs($this->user)->post(route('finanzas.conciliacion_usd.accion'), ['linea_id' => $linea->id, 'accion' => 'vincular', 'flujo_caja_id' => $conRef])->assertRedirect();
        $this->assertSame('conciliado', $linea->fresh()->estado);

        $this->actingAs($this->user)
            ->get(route('finanzas.conciliacion_usd.index', ['cuenta' => 'Binance|Grupo JENU', 'fecha_desde' => '2026-10-01', 'fecha_hasta' => '2026-10-31']))
            ->assertOk()
            ->assertSee('Consolidados (2)')
            ->assertSee('Movimientos en el banco sin pareja (1)');
    }

    public function test_fechas_de_ee_uu_y_debito_credito_por_separado(): void
    {
        $this->egreso(['banco' => 'Wells Fargo', 'titular' => 'Investment Doral', 'fecha' => '2026-08-03', 'referencia' => null, 'monto_usd' => 120, 'motivo' => 'ZELLE A PROVEEDOR']);
        $tmp = tempnam(sys_get_temp_dir(), 'wf') . '.csv';
        file_put_contents($tmp, "Date,Description,Debit,Credit\n08/03/2026,ZELLE TO JUAN,120.00,\n08/04/2026,MONTHLY SERVICE FEE,10.00,\n08/05/2026,ZELLE FROM ANA,,300.00\n");

        $this->actingAs($this->user)->post(route('finanzas.conciliacion_usd.subir'), [
            'cuenta' => 'Wells Fargo|Investment Doral', 'archivo' => new UploadedFile($tmp, 'wells.csv', 'text/csv', null, true),
        ])->assertOk();
        $token = array_key_last(session('conciliacion_usd'));
        $this->actingAs($this->user)->post(route('finanzas.conciliacion_usd.importar'), [
            'token' => $token, 'fila_cabecera' => 0, 'col_fecha' => 0, 'col_descripcion' => [1],
            'modo_monto' => 'dos', 'col_debito' => 2, 'col_credito' => 3, 'formato_fecha' => 'm/d/Y',
        ])->assertRedirect();

        $lineas = ConciliacionDivisaLinea::query()->orderBy('id')->get();
        $this->assertSame(['2026-08-03', '2026-08-04', '2026-08-05'], $lineas->map(fn ($l) => $l->fecha->format('Y-m-d'))->all());
        $this->assertSame([-120.0, -10.0, 300.0], $lineas->pluck('monto')->all());
        $this->assertSame(['conciliado', 'comision', 'pendiente'], $lineas->pluck('estado')->all());
    }
}
