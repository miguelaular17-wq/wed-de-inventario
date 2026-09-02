<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class ServicioTecnicoViewsSmokeTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        $this->ensureStTables();
    }

    public function test_create_pages_render_for_tecnico(): void
    {
        $tecnico = User::create([
            'name' => 'Técnico',
            'email' => 'tec-smoke-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_TECNICO,
            'sede' => 'DORAL',
        ]);

        $this->actingAs($tecnico)
            ->withSession(['sede_local' => 'DORAL'])
            ->get(route('servicio.reparaciones.create'))
            ->assertOk()
            ->assertSee('Nuevo registro')
            ->assertSee('Producto');

        $this->get(route('servicio.facturas.create'))
            ->assertOk()
            ->assertSee('Nueva factura')
            ->assertSee('Cliente');
    }

    public function test_index_pages_render_empty_state(): void
    {
        $tecnico = User::create([
            'name' => 'Técnico',
            'email' => 'tec-smoke2-'.uniqid().'@test.local',
            'password' => 'password123',
            'role' => User::ROLE_TECNICO,
            'sede' => 'DORAL',
        ]);

        $this->actingAs($tecnico)
            ->withSession(['sede_local' => 'DORAL'])
            ->get(route('servicio.reparaciones.index'))
            ->assertOk()
            ->assertSee('Garantías / servicio interno')
            ->assertSee('Aún no hay registros de garantía')
            ->assertSee('Nuevo registro');

        $this->get(route('servicio.facturas.index'))
            ->assertOk()
            ->assertSee('Facturas de taller')
            ->assertSee('Aún no hay facturas');
    }

    private function ensureStTables(): void
    {
        if (! Schema::hasTable('st_reparaciones')) {
            Schema::create('st_reparaciones', function (Blueprint $table) {
                $table->id();
                $table->string('sede', 32);
                $table->string('tipo', 16);
                $table->string('cliente_nombre')->nullable();
                $table->string('producto');
                $table->string('categoria', 32)->default('otro');
                $table->string('accion', 32)->default('pendiente');
                $table->string('estado', 32)->default('en_proceso');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('st_facturas')) {
            Schema::create('st_facturas', function (Blueprint $table) {
                $table->id();
                $table->string('sede', 32);
                $table->unsignedInteger('numero');
                $table->string('cliente_nombre');
                $table->text('descripcion')->nullable();
                $table->decimal('total', 12, 2);
                $table->string('estado_pago', 16)->default('pendiente');
                $table->date('fecha');
                $table->timestamps();
                $table->unique(['sede', 'numero']);
            });
        }
    }
}
