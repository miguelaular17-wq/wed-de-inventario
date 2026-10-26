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
        (require database_path('migrations/2026_10_24_130000_add_pagos_to_conciliacion_divisas.php'))->up();
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

    public function test_binance_se_cruza_con_el_reporte_de_pagos_de_caja_por_monto_y_hora(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'bn') . '.csv';
        file_put_contents($tmp, "ID de usuario,Tiempo,Cuenta,Operación,Moneda,Cambio,Observación\n"
            . "851908350,2026-08-31 19:55:30,Funding,Transfer,USDT,70,Binance Pay - P_A1\n"
            . "851908350,2026-08-31 19:58:02,Funding,Transfer,USDT,70,Binance Pay - P_A2\n"
            . "851908350,2026-08-31 16:10:40,Spot,Transfer,USDT,262.32,Binance Pay - P_A3\n"
            . "851908350,2026-08-31 15:00:00,Spot,Transfer Funds to Spot,USDT,11520,\n"
            . "851908350,2026-08-31 15:00:00,Funding,Transfer Funds to Funding Wallet,USDT,-11520,\n"
            . "851908350,2026-08-31 18:00:00,Funding,Transfer,USDT,45,Binance Pay - P_A4\n");
        // El historial de Binance se reconoce por su cabecera: se importa sin pasar por el mapeo de columnas.
        $this->actingAs($this->user)->post(route('finanzas.conciliacion_usd.subir'), [
            'cuenta' => 'Binance|Grupo JENU', 'archivo' => new UploadedFile($tmp, 'binance.csv', 'text/csv', null, true),
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('19:55:30', ConciliacionDivisaLinea::query()->where('descripcion', 'like', '%P_A1%')->value('hora'));
        $this->assertSame('Funding · Transfer · Binance Pay - P_A1', ConciliacionDivisaLinea::query()->where('descripcion', 'like', '%P_A1%')->value('descripcion'));
        $this->assertSame(2, ConciliacionDivisaLinea::query()->where('estado', 'ignorado')->count());

        $cloud = tempnam(sys_get_temp_dir(), 'cl') . '.csv';
        file_put_contents($cloud, "REPORTE MENSUAL DE PAGOS BINANCE\n01/08/2026 - 31/08/2026\n"
            . "NRO,ADMINISTRADOR,MONTO,LOCALIDAD,FECHA CONFIRMADO,TERMINAL,USUARIO,MEDIO,CUENTA,PAGADOR\n"
            . "1,Palacio,70,Las Virtudes,31/08/2026 7:58:17 PM,Caja 1,VALENTINA,chat,grupojenu2022@gmail.com,Abel\n"
            . "2,Palacio,70,Doral,31/08/2026 7:56:16 PM,Caja 2,ANGEL,chat,grupojenu2022@gmail.com,Rosa\n"
            . "3,Palacio,262.32,Doral,31/08/2026 4:11:17 PM,Caja 1,ANA,chat,grupojenu2022@gmail.com,User-56983\n"
            . "4,Palacio,940,Zamora,31/08/2026 11:16:17 AM,Caja 2,IVANIA,chat,grupojenu2022@gmail.com,Nilson\n"
            . "5,Palacio,45,Zamora,31/08/2026 6:01:10 PM,Caja 2,IVANIA,chat,grupojenu2022@gmail.com,Mia\n"
            . "6,Palacio,45,,31/08/2026 6:02:00 PM,,,,grupojenu2022@gmail.com,Mia\n");

        $this->actingAs($this->user)->post(route('finanzas.conciliacion_usd.pagos'), [
            'cuenta' => 'Binance|Grupo JENU', 'archivo' => new UploadedFile($cloud, 'cloud_pagos_binance.csv', 'text/csv', null, true),
        ])->assertRedirect()->assertSessionHas('success');

        $pagos = \App\Models\ConciliacionDivisaPago::query()->orderBy('nro')->get()->keyBy('nro');
        $this->assertCount(6, $pagos);
        $this->assertSame($linea = (int) ConciliacionDivisaLinea::query()->where('descripcion', 'like', '%P_A4%')->value('id'), (int) $pagos['5']->linea_id);
        $this->assertNull($pagos['6']->linea_id);
        $linea = fn (string $obs) => (int) ConciliacionDivisaLinea::query()->where('descripcion', 'like', "%{$obs}%")->value('id');
        // Dos cobros de 70 seguidos: cada uno con el abono más cercano antes de su confirmación.
        $this->assertSame($linea('P_A2'), (int) $pagos['1']->linea_id);
        $this->assertSame($linea('P_A1'), (int) $pagos['2']->linea_id);
        $this->assertSame($linea('P_A3'), (int) $pagos['3']->linea_id);
        $this->assertNull($pagos['4']->linea_id);
        $this->assertSame('conciliado', ConciliacionDivisaLinea::query()->find($linea('P_A3'))->estado);
        $this->assertSame('conciliado', ConciliacionDivisaLinea::query()->find($linea('P_A4'))->estado);

        // Subir el mismo reporte otra vez no duplica.
        $this->actingAs($this->user)->post(route('finanzas.conciliacion_usd.pagos'), [
            'cuenta' => 'Binance|Grupo JENU', 'archivo' => new UploadedFile($cloud, 'cloud_pagos_binance.csv', 'text/csv', null, true),
        ])->assertRedirect();
        $this->assertSame(6, \App\Models\ConciliacionDivisaPago::query()->count());

        $this->actingAs($this->user)
            ->get(route('finanzas.conciliacion_usd.index', ['cuenta' => 'Binance|Grupo JENU', 'fecha_desde' => '2026-08-01', 'fecha_hasta' => '2026-08-31']))
            ->assertOk()
            ->assertSee('Pagos de caja: 4 de 5 cruzados')
            ->assertSee('Pagos de caja sin abono en el banco (1)')
            ->assertSee('Avisos de Cloud repetidos (1')
            ->assertSee('mismo aviso leído otra vez')
            ->assertSee('Caja #3')
            ->assertSeeInOrder(['🏦 Banco', 'binance.csv', '🧾 App de cobros', 'cloud_pagos_binance.csv', '6 cobros']);

        // A mano: el cobro de 940 con el abono de 45 no (monto muy distinto); se liga y se desliga uno de 70.
        $this->actingAs($this->user)->post(route('finanzas.conciliacion_usd.accion'), ['linea_id' => $linea('P_A3'), 'accion' => 'desvincular'])->assertRedirect();
        $this->assertNull($pagos['3']->fresh()->linea_id);
        $this->assertSame('pendiente', ConciliacionDivisaLinea::query()->find($linea('P_A3'))->estado);
        $this->actingAs($this->user)->post(route('finanzas.conciliacion_usd.accion'), [
            'linea_id' => $linea('P_A3'), 'accion' => 'vincular_pago', 'pago_id' => $pagos['3']->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($linea('P_A3'), (int) $pagos['3']->fresh()->linea_id);
    }

    public function test_los_dos_documentos_juntos_se_reconocen_y_se_cruzan(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $bin = tempnam(sys_get_temp_dir(), 'bn') . '.csv';
        file_put_contents($bin, "Historial de transacciones\n\nID de usuario,Tiempo,Cuenta,Operación,Moneda,Cambio,Observación\n"
            . "851908350,2026-08-06 10:56:14,Funding,Transfer,USDT,940,Binance Pay - P_X1\n"
            . "851908350,2026-08-06 11:02:48,Funding,Transfer,USDT,100,Binance Pay - P_X2\n");
        $cloud = tempnam(sys_get_temp_dir(), 'cl') . '.csv';
        file_put_contents($cloud, "REPORTE MENSUAL DE PAGOS BINANCE\nNRO,ADMINISTRADOR,MONTO,LOCALIDAD,FECHA CONFIRMADO,TERMINAL,USUARIO,MEDIO,CUENTA,PAGADOR\n"
            . "293,Palacio,940,Sambil Paraguaná,06/08/2026 11:15:19 AM,Caja 1,OSCAR PALOMO,chat,x,Nilsonguanipa20\n"
            . "294,Palacio,100,Doral,06/08/2026 11:03:16 AM,Caja 2,ANA,chat,x,Pedro\n");

        // El orden no importa: primero se carga el extracto y después se cruzan los pagos.
        $this->actingAs($this->user)->post(route('finanzas.conciliacion_usd.subir'), [
            'cuenta' => 'Binance|Grupo JENU',
            'archivos' => [
                new UploadedFile($cloud, 'cloud_pagos_binance_mes_2026_08.csv', 'text/csv', null, true),
                new UploadedFile($bin, 'Binance-Historial.csv', 'text/csv', null, true),
            ],
        ])->assertRedirect(route('finanzas.conciliacion_usd.index', ['cuenta' => 'Binance|Grupo JENU', 'fecha_desde' => '2026-08-06', 'fecha_hasta' => '2026-08-06']))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '2 con pagos de caja (0 cobros sin abono)'));

        $this->assertSame(2, ConciliacionDivisaLinea::query()->where('estado', 'conciliado')->count());
        $this->assertSame(2, \App\Models\ConciliacionDivisaPago::query()->whereNotNull('linea_id')->count());
        $this->assertSame([], \Illuminate\Support\Facades\Storage::disk('local')->files('conciliacion_usd'));
    }

    public function test_formato_desconocido_junto_con_otro_pide_subirlo_solo(): void
    {
        $bin = tempnam(sys_get_temp_dir(), 'bn') . '.csv';
        file_put_contents($bin, "ID de usuario,Tiempo,Cuenta,Operación,Moneda,Cambio,Observación\n1,2026-08-06 10:56:14,Funding,Transfer,USDT,940,P\n");
        $raro = tempnam(sys_get_temp_dir(), 'rr') . '.csv';
        file_put_contents($raro, "Fecha,Concepto,Importe\n06/08/2026,Algo,10\n");

        $this->actingAs($this->user)->from(route('finanzas.conciliacion_usd.index'))->post(route('finanzas.conciliacion_usd.subir'), [
            'cuenta' => 'Binance|Grupo JENU',
            'archivos' => [new UploadedFile($bin, 'binance.csv', 'text/csv', null, true), new UploadedFile($raro, 'otro.csv', 'text/csv', null, true)],
        ])->assertRedirect(route('finanzas.conciliacion_usd.index'))->assertSessionHas('error');
        $this->assertSame(0, ConciliacionDivisaLinea::query()->count());
    }

    public function test_zelle_estado_de_cuenta_pdf_con_el_reporte_de_cloud_por_nombre(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        // El PDF real lo lee smalot; aquí se entrega directo el texto que devuelve para un estado de Citizens.
        $this->app->instance(\App\Services\Conciliacion\EstadoCuentaPdfParser::class, new class extends \App\Services\Conciliacion\EstadoCuentaPdfParser
        {
            public function leer(string $path): ?array
            {
                return $this->leerTexto(\Tests\Unit\EstadoCuentaPdfParserTest::CITIZENS);
            }
        });
        $pdf = tempnam(sys_get_temp_dir(), 'ct') . '.pdf';
        file_put_contents($pdf, "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj << >> endobj\ntrailer << >>\n%%EOF\n");
        $cloud = tempnam(sys_get_temp_dir(), 'cz') . '.csv';
        file_put_contents($cloud, "REPORTE MENSUAL DE PAGOS ZELLE\n"
            . "NRO,ADMINISTRADOR,MONTO,LOCALIDAD,FECHA CONFIRMADO,TERMINAL,USUARIO,MEDIO,BANCO,MENSAJE,PAGADOR\n"
            . "1,Palacio,100,Doral,10/08/2026 4:29:14 PM,Caja Avance,Dayana,chat,Onlineservices@citizensbankonline.com,x,EDILIO PEREA GUARDIA\n"
            . "2,Palacio,92,Doral,10/08/2026 4:00:15 PM,Caja Avance,Dayana,chat,Onlineservices@citizensbankonline.com,x,JOSE CARRASQUERO\n"
            . "3,Palacio,100,Doral,10/08/2026 5:10:00 PM,Caja Avance,Dayana,chat,Onlineservices@citizensbankonline.com,x,ANA PEREZ\n"
            . "4,Palacio,10000,,22/08/2026 12:42:15 PM,,,,Onlineservices@citizensbankonline.com,x,EDILIOPEREAGUARDIA\n"
            . "5,Palacio,50,Doral,12/08/2026 1:00:00 PM,Caja 1,Ana,chat,Onlineservices@citizensbankonline.com,x,MARIA LOPEZ\n"
            . "6,Palacio,500,Doral,10/08/2026 3:00:00 PM,Caja 1,Ana,chat,alerts@notify.wellsfargo.com,x,PEDRO\n"
            . "7,Palacio,0,,11/08/2026 12:27:51 PM,,,,Onlineservices@citizensbankonline.com,Citizens account update,AVISO\n");

        $this->actingAs($this->user)->post(route('finanzas.conciliacion_usd.subir'), [
            'cuenta' => 'Citizens Money Market|Doral',
            'archivos' => [
                new UploadedFile($pdf, 'EDO_CTA_CITIZENS_AGOSTO.pdf', 'application/pdf', null, true),
                new UploadedFile($cloud, 'cloud_pagos_zelle_mes.csv', 'text/csv', null, true),
            ],
        ])->assertRedirect(route('finanzas.conciliacion_usd.index', ['cuenta' => 'Citizens Money Market|Doral', 'fecha_desde' => '2026-08-01', 'fecha_hasta' => '2026-08-31']))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Estado de cuenta Citizens …5803 (PDF): 4 movimientos nuevos')
                && str_contains($m, 'cuadra con los totales') && str_contains($m, '1 de Wells Fargo'));

        $pagos = \App\Models\ConciliacionDivisaPago::query()->orderBy('nro')->get()->keyBy('nro');
        $this->assertSame(['1', '2', '3', '4', '5'], $pagos->keys()->map(fn ($k) => (string) $k)->all());
        $abono = fn (string $nombre) => (int) ConciliacionDivisaLinea::query()->where('descripcion', 'like', "%{$nombre}%")->value('id');
        // Dos Zelle de 100 el 10 y el 11: cada cobro va con el de su pagador, no con el más cercano en fecha.
        $this->assertSame($abono('EDILIO'), (int) $pagos['1']->linea_id);
        $this->assertSame($abono('CARRASQUERO'), (int) $pagos['2']->linea_id);
        $this->assertSame($abono('ANAPEREZ'), (int) $pagos['3']->linea_id);
        $this->assertNull($pagos['4']->linea_id);
        $this->assertNull($pagos['5']->linea_id);
        $this->assertSame('comision', ConciliacionDivisaLinea::query()->where('descripcion', 'like', 'SERVICECHARGE%')->value('estado'));

        $this->actingAs($this->user)
            ->get(route('finanzas.conciliacion_usd.index', ['cuenta' => 'Citizens Money Market|Doral', 'fecha_desde' => '2026-08-01', 'fecha_hasta' => '2026-08-31']))
            ->assertOk()
            ->assertSee('Pagos de caja: 3 de 4 cruzados')
            ->assertSee('Pagos de caja sin abono en el banco (1)')
            ->assertSee('Avisos de Cloud repetidos (1')
            ->assertSee('monto leído sin el punto decimal')
            ->assertSeeInOrder(['🏦 Banco', 'EDO_CTA_CITIZENS_AGOSTO.pdf', '🧾 App de cobros', 'cloud_pagos_zelle_mes.csv']);
        $this->assertSame([], \Illuminate\Support\Facades\Storage::disk('local')->files('conciliacion_usd'));
    }

    public function test_pdf_con_movimientos_identicos_los_guarda_todos_y_no_duplica_al_resubir(): void
    {
        $this->app->instance(\App\Services\Conciliacion\EstadoCuentaPdfParser::class, new class extends \App\Services\Conciliacion\EstadoCuentaPdfParser
        {
            public function leer(string $path): ?array
            {
                return $this->leerTexto(\Tests\Unit\EstadoCuentaPdfParserTest::AMERANT);
            }
        });
        $servicio = app(\App\Services\Conciliacion\ConciliacionDivisasService::class);

        $r = $servicio->importarPdf('x.pdf', 'Amerant Investment', 'Doral', 'amerant.pdf', null);
        $this->assertSame([4, 0, true], [$r['nuevas'], $r['duplicadas'], $r['cuadra']]);
        $this->assertSame(2, ConciliacionDivisaLinea::query()->where('monto', -2)->count());

        $otra = $servicio->importarPdf('x.pdf', 'Amerant Investment', 'Doral', 'amerant.pdf', null);
        $this->assertSame([0, 4], [$otra['nuevas'], $otra['duplicadas']]);
    }

    public function test_pdf_de_otro_banco_que_la_cuenta_elegida_se_rechaza(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->app->instance(\App\Services\Conciliacion\EstadoCuentaPdfParser::class, new class extends \App\Services\Conciliacion\EstadoCuentaPdfParser
        {
            public function leer(string $path): ?array
            {
                return $this->leerTexto(\Tests\Unit\EstadoCuentaPdfParserTest::AMERANT);
            }
        });
        $pdf = tempnam(sys_get_temp_dir(), 'am') . '.pdf';
        file_put_contents($pdf, "%PDF-1.4\n%%EOF\n");

        $this->actingAs($this->user)->from(route('finanzas.conciliacion_usd.index'))->post(route('finanzas.conciliacion_usd.subir'), [
            'cuenta' => 'Citizens Money Market|Doral', 'archivos' => [new UploadedFile($pdf, 'amerant.pdf', 'application/pdf', null, true)],
        ])->assertSessionHasErrors(['archivos' => '«amerant.pdf» es un estado de cuenta de Amerant y la cuenta elegida es Citizens Money Market.']);
        $this->assertSame(0, ConciliacionDivisaLinea::query()->count());
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
