<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Nomina\NominaAbonoSueldo;
use App\Models\Nomina\NominaEmpleado;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class AsistenteAdelantoTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();

        if (! Schema::hasTable('asistente_mensajes')) {
            Schema::create('asistente_mensajes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('rol', 16);
                $table->text('texto');
                $table->timestamps();
            });
        }

        Env::getRepository()->set('GEMINI_API_KEY', 'test-key');
    }

    public function test_pregunta_el_empleado_y_luego_registra_el_anticipo(): void
    {
        \Carbon\Carbon::setTestNow('2026-10-02 12:00:00');
        $admin = $this->admin();
        $this->empleado('27844739', 'Ivania Rosa');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push($this->gemini('{"respuesta":"voy a registrarlo","rellenar":[],"accion":{"tipo":"crear_adelanto","monto":10,"empleado":"","fecha":"2026-09-29","motivo":""}}'))
                ->push($this->gemini('{"respuesta":"ok","rellenar":[],"accion":null}')),
        ]);

        $this->actingAs($admin)->postJson(route('asistente.consultar'), [
            'mensaje' => 'crea un anticipo de nomina de 10$',
        ])->assertOk()
            ->assertJsonPath('respuesta', '¿A qué empleado le registro el anticipo de $10.00?');

        $this->assertSame(0, NominaAbonoSueldo::query()->count());

        $this->actingAs($admin)->postJson(route('asistente.consultar'), [
            'mensaje' => 'Ivania Rosa',
        ])->assertOk()
            ->assertJsonPath('respuesta', fn ($texto) => str_contains($texto, 'Ivania Rosa') && str_contains($texto, '$10.00'));

        $abono = NominaAbonoSueldo::query()->first();
        $this->assertNotNull($abono);
        $this->assertEquals(10.0, (float) $abono->monto);
        $this->assertSame('PENDIENTE', $abono->estado);
        $this->assertSame('2026-10-02', $abono->fecha->toDateString());
    }

    public function test_sin_permiso_de_nomina_no_registra_el_anticipo(): void
    {
        $vendedor = User::create([
            'name' => 'Vendedor',
            'email' => 'vendedor-asistente@test.local',
            'password' => 'password123',
            'role' => User::ROLE_VENDEDOR,
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(
                $this->gemini('{"respuesta":"listo","rellenar":[],"accion":{"tipo":"crear_adelanto","monto":10,"empleado":"Ivania","fecha":"","motivo":""}}')
            ),
        ]);

        $this->actingAs($vendedor)->postJson(route('asistente.consultar'), [
            'mensaje' => 'crea un anticipo de 10$',
        ])->assertOk()
            ->assertJsonPath('respuesta', 'No tienes permiso para registrar anticipos de nómina.');

        $this->assertSame(0, NominaAbonoSueldo::query()->count());
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Nómina',
            'email' => 'admin-adelanto@test.local',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
        ]);
    }

    private function empleado(string $cedula, string $nombre): NominaEmpleado
    {
        $cliente = Cliente::create(['cedula' => $cedula, 'nombre' => $nombre]);

        return NominaEmpleado::create([
            'cliente_id' => $cliente->id,
            'salario_base' => 100,
            'tipo_salario' => 'MENSUAL',
            'estado' => 'ACTIVO',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function gemini(string $texto): array
    {
        return [
            'candidates' => [[
                'content' => ['parts' => [['text' => $texto]]],
            ]],
        ];
    }
}
