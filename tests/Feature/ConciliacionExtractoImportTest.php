<?php

namespace Tests\Feature;

use App\Jobs\ProcesarImportacionExtracto;
use App\Models\ConciliacionImportacion;
use App\Models\ConciliacionLinea;
use App\Models\FlujoCaja;
use App\Models\User;
use App\Services\Conciliacion\ConciliacionAutoMatcher;
use App\Services\Conciliacion\ExtractoImporter;
use App\Services\Conciliacion\ExtractoParser;
use App\Support\SimpleXlsxWriter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class ConciliacionExtractoImportTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        $this->crearTablasConciliacion();
        Storage::fake('local');
        config(['conciliacion.import_chunk' => 50, 'conciliacion.import_async' => false]);
    }

    public function test_importa_excel_banesco_valido(): void
    {
        $path = $this->xlsxBanesco([
            ['03/08/2026', '1001', 'PAGO PROVEEDOR', '-1.500,25', '0'],
            ['04/08/2026', '1002', 'TRANSFERENCIA RECIBIDA', '300,00', '0'],
            ['05/08/2026', '1003', 'SALDO INICIAL', '999,00', '0'],
        ]);

        $imp = $this->importar($path);
        $this->assertSame(ConciliacionImportacion::COMPLETADA, $imp->estado);
        $this->assertSame(2, $imp->filas_importadas);
        $this->assertSame(0, $imp->filas_rechazadas);

        $lineas = ConciliacionLinea::query()->orderBy('id')->get();
        $this->assertCount(2, $lineas);
        $this->assertSame('cargo', $lineas[0]->tipo);
        $this->assertEquals(1500.25, (float) $lineas[0]->monto);
        $this->assertSame('abono', $lineas[1]->tipo);
        $this->assertEquals(300.0, (float) $lineas[1]->monto);
    }

    public function test_archivo_vacio_queda_fallido(): void
    {
        $path = $this->xlsxBanesco([]);
        $imp = $this->importar($path);

        $this->assertSame(ConciliacionImportacion::FALLIDA, $imp->estado);
        $this->assertSame(0, $imp->filas_importadas);
        $this->assertStringContainsString('No se encontró ninguna fila', (string) $imp->mensaje);
    }

    public function test_cabeceras_incorrectas_no_importan_movimientos(): void
    {
        Storage::disk('local')->put('mal.xlsx', SimpleXlsxWriter::toString(['H' => [
            ['ColA', 'ColB', 'ColC', 'ColD'],
            ['99/99/9999', 'x', 'y', '10,00'],
        ]]));

        $imp = ConciliacionImportacion::create([
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'archivo_nombre' => 'mal.xlsx',
            'archivo_path' => 'mal.xlsx',
            'extension' => 'xlsx',
            'estado' => ConciliacionImportacion::PENDIENTE,
            'user_id' => $this->makeUser(User::ROLE_CONTABILIDAD)->id,
        ]);

        $imp = app(ExtractoImporter::class)->importarArchivo($imp);
        $this->assertTrue($imp->terminada());
        $this->assertSame(0, $imp->filas_importadas);
        $this->assertSame(0, ConciliacionLinea::query()->count());
        $this->assertGreaterThan(0, $imp->filas_rechazadas);
    }

    public function test_duplicados_no_se_vuelven_a_insertar(): void
    {
        $filas = [
            ['03/08/2026', '1001', 'PAGO', '-100,00', '0'],
            ['03/08/2026', '1001', 'PAGO', '-100,00', '0'],
        ];
        $path = $this->xlsxBanesco($filas);

        $imp1 = $this->importar($path);
        $this->assertSame(1, $imp1->filas_importadas);
        $this->assertSame(1, $imp1->filas_duplicadas);

        // Primera importación borra el archivo; regeneramos para la re-subida.
        $path2 = $this->xlsxBanesco($filas, 'dup2.xlsx');
        $imp2 = ConciliacionImportacion::create([
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'archivo_nombre' => 'dup2.xlsx',
            'archivo_path' => $path2,
            'extension' => 'xlsx',
            'estado' => ConciliacionImportacion::PENDIENTE,
        ]);
        $imp2 = app(ExtractoImporter::class)->importarArchivo($imp2);
        $this->assertSame(0, $imp2->filas_importadas);
        $this->assertSame(2, $imp2->filas_duplicadas);
        $this->assertSame(1, ConciliacionLinea::query()->count());
    }

    public function test_rechaza_montos_y_fechas_invalidas(): void
    {
        $path = $this->xlsxBanesco([
            ['99/99/9999', '1', 'MALA FECHA', '10,00', '0'],
            ['03/08/2026', '2', 'SIN MONTO', '0,00', '0'],
            ['03/08/2026', '3', 'MONTO TEXTO', 'N/A', '0'],
            ['04/08/2026', '4', 'OK', '50,00', '0'],
        ]);

        $imp = $this->importar($path);
        $this->assertSame(ConciliacionImportacion::COMPLETADA_CON_PENDIENTES, $imp->estado);
        $this->assertSame(1, $imp->filas_importadas);
        $this->assertGreaterThanOrEqual(3, $imp->filas_rechazadas);
        $this->assertNotEmpty($imp->rechazos);
    }

    public function test_concilia_coincidencia_exacta_y_deja_sin_match_pendiente(): void
    {
        ConciliacionLinea::create([
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'fecha' => '2026-08-06',
            'descripcion' => 'PAGO PROVEEDOR',
            'referencia' => '5003745290',
            'monto' => 30268,
            'estado' => 'pendiente',
            'tipo' => 'cargo',
            'importacion_id' => 1,
        ]);
        ConciliacionLinea::create([
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'fecha' => '2026-08-07',
            'descripcion' => 'SIN MATCH',
            'referencia' => '999',
            'monto' => 10,
            'estado' => 'pendiente',
            'tipo' => 'cargo',
            'importacion_id' => 1,
        ]);

        FlujoCaja::create([
            'tipo' => 'egreso',
            'banco' => 'Banesco',
            'titular' => 'Doral',
            'fecha' => '2026-08-06',
            'monto_bs' => 30268,
            'referencia' => '5003745290',
            'concepto' => 'ANTICIPO',
            'motivo' => '073 - ANTICIPO',
            'es_conciliado' => false,
            'oculto' => false,
        ]);

        $n = app(ConciliacionAutoMatcher::class)->conciliar(
            ConciliacionLinea::query()->get(),
            '2026-08-01',
            '2026-08-31',
            'BANESCO',
            'DORAL',
            500
        );

        $this->assertSame(1, $n);
        $this->assertSame('conciliado', ConciliacionLinea::query()->where('referencia', '5003745290')->value('estado'));
        $this->assertSame('pendiente', ConciliacionLinea::query()->where('referencia', '999')->value('estado'));
        $this->assertTrue((bool) FlujoCaja::query()->first()->es_conciliado);
    }

    public function test_no_permite_doble_conciliacion_del_mismo_egreso(): void
    {
        $l1 = ConciliacionLinea::create([
            'banco' => 'BANESCO', 'titular' => 'DORAL', 'fecha' => '2026-08-06',
            'descripcion' => 'PAGO A', 'referencia' => '111', 'monto' => 100,
            'estado' => 'pendiente', 'tipo' => 'cargo',
        ]);
        $l2 = ConciliacionLinea::create([
            'banco' => 'BANESCO', 'titular' => 'DORAL', 'fecha' => '2026-08-06',
            'descripcion' => 'PAGO B', 'referencia' => '111', 'monto' => 100,
            'estado' => 'pendiente', 'tipo' => 'cargo',
        ]);
        FlujoCaja::create([
            'tipo' => 'egreso', 'banco' => 'BANESCO', 'titular' => 'DORAL',
            'fecha' => '2026-08-06', 'monto_bs' => 100, 'referencia' => '111',
            'motivo' => '073 - ANTICIPO', 'es_conciliado' => false, 'oculto' => false,
        ]);

        $matcher = app(ConciliacionAutoMatcher::class);
        $matcher->conciliar(collect([$l1]), '2026-08-01', '2026-08-31', 'BANESCO', 'DORAL', 10);
        $matcher->conciliar(collect([$l2->fresh()]), '2026-08-01', '2026-08-31', 'BANESCO', 'DORAL', 10);

        $this->assertSame(1, ConciliacionLinea::query()->where('estado', 'conciliado')->count());
        $this->assertSame(1, ConciliacionLinea::query()->where('estado', 'pendiente')->count());
        $this->assertSame(1, FlujoCaja::query()->where('es_conciliado', true)->count());
    }

    public function test_conciliar_en_transito_a_mano_saca_la_linea_de_movimientos_del_banco(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        $linea = ConciliacionLinea::create([
            'banco' => 'BANESCO', 'titular' => 'DORAL', 'fecha' => '2026-08-11',
            'descripcion' => 'PAGO PROVEEDOR', 'referencia' => '777', 'monto' => 2500,
            'estado' => 'pendiente', 'tipo' => 'cargo',
        ]);
        $otraCuenta = ConciliacionLinea::create([
            'banco' => 'BANESCO', 'titular' => 'JRZ', 'fecha' => '2026-08-06',
            'descripcion' => 'PAGO', 'referencia' => '1', 'monto' => 2500,
            'estado' => 'pendiente', 'tipo' => 'cargo',
        ]);
        $flujo = FlujoCaja::create([
            'tipo' => 'egreso', 'banco' => 'BANESCO', 'titular' => 'DORAL',
            'fecha' => '2026-08-06', 'monto_bs' => 2500, 'referencia' => '999',
            'motivo' => '073 - ANTICIPO', 'oculto' => false,
        ]);

        $this->actingAs($user)
            ->post(route('finanzas.conciliaciones.transito_conciliar'), [
                'flujo_id' => $flujo->id,
                'fecha_desde' => '2026-08-01',
                'fecha_hasta' => '2026-08-31',
                'banco_filtro' => 'BANESCO',
                'titular_filtro' => 'DORAL',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', fn ($m) => str_contains($m, '11/08/2026'));

        $linea->refresh();
        $this->assertSame('conciliado', $linea->estado);
        $this->assertSame((int) $flujo->id, (int) $linea->flujo_caja_id);
        $this->assertTrue((bool) $flujo->fresh()->es_conciliado);
        $this->assertSame('pendiente', $otraCuenta->fresh()->estado);
    }

    public function test_conciliar_en_transito_sin_linea_en_el_extracto_solo_marca_el_egreso(): void
    {
        $user = $this->makeUser(User::ROLE_CONTABILIDAD);
        $flujo = FlujoCaja::create([
            'tipo' => 'egreso', 'banco' => 'BANESCO', 'titular' => 'DORAL',
            'fecha' => '2026-08-06', 'monto_bs' => 123.45, 'motivo' => 'X', 'oculto' => false,
        ]);

        $this->actingAs($user)
            ->post(route('finanzas.conciliaciones.transito_conciliar'), ['flujo_id' => $flujo->id])
            ->assertRedirect()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'No había en el extracto'));

        $this->assertTrue((bool) $flujo->fresh()->es_conciliado);
    }

    public function test_estado_importacion_solo_visible_al_dueno_o_admin(): void
    {
        $dueno = $this->makeUser(User::ROLE_CONTABILIDAD);
        $otro = $this->makeUser(User::ROLE_CONTABILIDAD);
        $imp = ConciliacionImportacion::create([
            'user_id' => $dueno->id,
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'archivo_nombre' => 'a.xlsx',
            'extension' => 'xlsx',
            'estado' => ConciliacionImportacion::COMPLETADA,
        ]);

        $this->actingAs($dueno)
            ->getJson(route('finanzas.conciliaciones.importaciones.show', $imp))
            ->assertOk()
            ->assertJsonPath('estado', ConciliacionImportacion::COMPLETADA);

        $this->actingAs($otro)
            ->getJson(route('finanzas.conciliaciones.importaciones.show', $imp))
            ->assertForbidden();
    }

    public function test_job_procesa_archivo_y_actualiza_contadores(): void
    {
        $path = $this->xlsxBanesco([
            ['03/08/2026', '7001', 'PAGO X', '-200,00', '0'],
            ['04/08/2026', '7002', 'ABONO Y', '50,00', '0'],
        ]);
        $imp = ConciliacionImportacion::create([
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'archivo_nombre' => 'bench.xlsx',
            'archivo_path' => 'bench.xlsx',
            'extension' => 'xlsx',
            'estado' => ConciliacionImportacion::PENDIENTE,
        ]);

        ProcesarImportacionExtracto::dispatchSync($imp->id);
        $imp->refresh();

        $this->assertTrue($imp->terminada());
        $this->assertSame(2, $imp->filas_importadas);
        $this->assertSame(2, ConciliacionLinea::query()->count());
        $this->assertNull($imp->archivo_path);
    }

    public function test_procesamiento_por_lotes_de_6000_sin_crecimiento_descontrolado(): void
    {
        $rows = [];
        for ($i = 1; $i <= 6000; $i++) {
            $dia = str_pad((string) (1 + ($i % 28)), 2, '0', STR_PAD_LEFT);
            $signo = $i % 2 === 0 ? '-' : '';
            $rows[] = ["{$dia}/08/2026", (string) (800000000 + $i), "MOV {$i}", "{$signo}10,00", '0'];
        }
        $path = $this->xlsxBanesco($rows);
        $imp = $this->importar($path);

        $this->assertSame(6000, $imp->filas_importadas);
        $this->assertSame(0, $imp->filas_duplicadas);
        $this->assertSame(ConciliacionImportacion::COMPLETADA, $imp->estado);
        $this->assertSame(6000, ConciliacionLinea::query()->count());
    }

    /** @param list<list<string|int|float|null>> $filas */
    private function xlsxBanesco(array $filas, string $nombre = 'bench.xlsx'): string
    {
        $sheet = array_merge([['Fecha', 'Referencia', 'Descripción', 'Monto', 'Balance']], $filas);
        Storage::disk('local')->put($nombre, SimpleXlsxWriter::toString(['Hoja1' => $sheet]));

        return $nombre;
    }

    private function importar(string $pathRelativo): ConciliacionImportacion
    {
        $imp = ConciliacionImportacion::create([
            'banco' => 'BANESCO',
            'titular' => 'DORAL',
            'archivo_nombre' => basename($pathRelativo),
            'archivo_path' => $pathRelativo,
            'extension' => 'xlsx',
            'estado' => ConciliacionImportacion::PENDIENTE,
        ]);

        return app(ExtractoImporter::class)->importarArchivo($imp);
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => 'Contabilidad',
            'email' => 'contab-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => $role,
        ]);
    }

    private function crearTablasConciliacion(): void
    {
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
                $table->boolean('forzar_comision')->default(false);
                $table->string('tipo', 32)->nullable();
                $table->string('session_id')->nullable();
                $table->unsignedBigInteger('flujo_caja_id')->nullable();
                $table->unsignedBigInteger('tesoreria_ingreso_id')->nullable();
                $table->unsignedBigInteger('compra_divisa_id')->nullable();
                $table->unsignedBigInteger('importacion_id')->nullable();
                $table->unsignedInteger('fila_origen')->nullable();
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('conciliacion_lineas', 'importacion_id')) {
            Schema::table('conciliacion_lineas', function (Blueprint $table) {
                $table->unsignedBigInteger('importacion_id')->nullable();
                $table->unsignedInteger('fila_origen')->nullable();
            });
        }

        if (! Schema::hasTable('conciliacion_importaciones')) {
            Schema::create('conciliacion_importaciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('session_id')->nullable();
                $table->string('banco', 80);
                $table->string('titular', 120);
                $table->string('archivo_nombre', 255);
                $table->string('archivo_path', 500)->nullable();
                $table->string('archivo_hash', 64)->nullable();
                $table->string('extension', 10);
                $table->string('estado', 30)->default('pendiente');
                $table->unsignedInteger('filas_leidas')->default(0);
                $table->unsignedInteger('filas_importadas')->default(0);
                $table->unsignedInteger('filas_duplicadas')->default(0);
                $table->unsignedInteger('filas_rechazadas')->default(0);
                $table->unsignedInteger('filas_conciliadas')->default(0);
                $table->unsignedInteger('filas_pendientes')->default(0);
                $table->unsignedInteger('ultima_fila')->default(0);
                $table->unsignedInteger('intentos')->default(0);
                $table->json('rechazos')->nullable();
                $table->text('mensaje')->nullable();
                $table->timestamp('iniciado_at')->nullable();
                $table->timestamp('finalizado_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('flujo_cajas')) {
            Schema::create('flujo_cajas', function (Blueprint $table) {
                $table->id();
                $table->string('tipo')->nullable();
                $table->date('fecha')->nullable();
                $table->string('banco')->nullable();
                $table->string('titular')->nullable();
                $table->string('banco_receptor')->nullable();
                $table->string('titular_receptor')->nullable();
                $table->string('categoria_egreso')->nullable();
                $table->string('tipo_gasto')->nullable();
                $table->string('concepto')->nullable();
                $table->string('motivo')->nullable();
                $table->string('referencia')->nullable();
                $table->decimal('monto_bs', 15, 2)->nullable();
                $table->decimal('monto_usd', 15, 2)->nullable();
                $table->boolean('es_conciliado')->default(false);
                $table->boolean('oculto')->default(false);
                $table->timestamps();
            });
        } else {
            $faltantes = array_values(array_filter([
                'banco', 'titular', 'banco_receptor', 'titular_receptor',
                'categoria_egreso', 'concepto', 'motivo', 'referencia',
                'es_conciliado', 'oculto',
            ], fn (string $col) => ! Schema::hasColumn('flujo_cajas', $col)));
            if ($faltantes !== []) {
                Schema::table('flujo_cajas', function (Blueprint $table) use ($faltantes) {
                    foreach ($faltantes as $col) {
                        if (in_array($col, ['es_conciliado', 'oculto'], true)) {
                            $table->boolean($col)->default(false);
                        } else {
                            $table->string($col)->nullable();
                        }
                    }
                });
            }
        }

        if (! Schema::hasTable('compra_divisas')) {
            Schema::create('compra_divisas', function (Blueprint $table) {
                $table->id();
                $table->date('fecha');
                $table->string('banco')->nullable();
                $table->string('titular')->nullable();
                $table->string('referencia')->nullable();
                $table->string('concepto')->nullable();
                $table->string('motivo')->nullable();
                $table->decimal('monto_bs', 18, 2)->default(0);
                $table->boolean('es_conciliado')->default(false);
                $table->timestamps();
            });
        }
    }
}
