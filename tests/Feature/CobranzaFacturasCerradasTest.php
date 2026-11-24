<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class CobranzaFacturasCerradasTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        config(['database.connections.pgsql' => config('database.connections.sqlite')]);
        DB::purge('pgsql');

        $pdo = DB::connection('pgsql')->getPdo();
        $btrim = fn ($s) => trim((string) $s);
        method_exists($pdo, 'createFunction') ? $pdo->createFunction('BTRIM', $btrim, 1) : $pdo->sqliteCreateFunction('BTRIM', $btrim, 1);

        Schema::connection('pgsql')->dropIfExists('cobranzas_pagadas_manualmente');
        Schema::connection('pgsql')->dropIfExists('historial_cobranzas');
        Schema::connection('pgsql')->create('cobranzas_pagadas_manualmente', function (Blueprint $table) {
            $table->id();
            $table->string('id_documento')->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
        Schema::connection('pgsql')->create('historial_cobranzas', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_registro');
            $table->string('sede_nombre')->nullable();
            $table->string('codigo_cliente')->nullable();
            $table->string('nombre_cliente')->nullable();
            $table->string('id_documento')->nullable();
            $table->date('fecha_emision')->nullable();
            $table->string('tipo_cxc')->nullable();
            $table->string('numero_documento')->nullable();
            $table->string('factura_padre')->nullable();
            $table->decimal('monto_neto', 15, 2)->default(0);
            $table->decimal('saldo', 15, 2)->default(0);
            $table->integer('tipo_fila')->nullable();
            $table->string('estatus')->nullable();
        });
    }

    public function test_lista_y_reabre_facturas_cerradas(): void
    {
        $user = User::create([
            'name' => 'Cobranza',
            'email' => 'cerradas-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => 'cobranza',
        ]);

        DB::connection('pgsql')->table('historial_cobranzas')->insert([
            'fecha_registro' => '2026-11-18',
            'sede_nombre' => 'DORAL',
            'codigo_cliente' => 'V1',
            'nombre_cliente' => 'Cliente Cerrado',
            'id_documento' => '999001',
            'numero_documento' => '999001',
            'tipo_cxc' => 'FAC',
            'fecha_emision' => '2026-10-01',
            'monto_neto' => 150,
            'saldo' => 0,
            'tipo_fila' => 1,
            'estatus' => 'RECIENTE',
        ]);
        $id = DB::connection('pgsql')->table('cobranzas_pagadas_manualmente')->insertGetId([
            'id_documento' => '999001',
            'user_id' => $user->id,
            'created_at' => '2026-11-18 12:00:00',
            'updated_at' => '2026-11-18 12:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('cobranza.facturas_cerradas'))
            ->assertOk()
            ->assertSee('Facturas cerradas')
            ->assertSee('Cliente Cerrado')
            ->assertSee('999001');

        $this->actingAs($user)
            ->get(route('cobranza.facturas_cerradas', ['q' => 'Cliente Cerrado']))
            ->assertOk()
            ->assertSee('999001');

        $this->actingAs($user)
            ->delete(route('cobranza.facturas_cerradas.reabrir', $id))
            ->assertRedirect(route('cobranza.facturas_cerradas'));

        $this->assertFalse(
            DB::connection('pgsql')->table('cobranzas_pagadas_manualmente')->where('id_documento', '999001')->exists()
        );
    }
}
