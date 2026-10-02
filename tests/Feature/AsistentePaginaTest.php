<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AsistentePaginaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);

        if (! Schema::hasTable('asistente_mensajes')) {
            Schema::create('asistente_mensajes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('rol', 16);
                $table->text('texto');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('role', 32)->default('sede');
                $table->string('sede')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }
    }
    public function test_invitado_no_puede_consultar(): void
    {
        $this->postJson(route('asistente.consultar'), ['mensaje' => 'hola'])
            ->assertUnauthorized();
    }

    public function test_sin_clave_responde_que_falta_configurar(): void
    {
        Env::getRepository()->set('GEMINI_API_KEY', '');
        $this->actingAs($this->usuario());

        $this->postJson(route('asistente.consultar'), [
            'mensaje' => '¿Qué dice esta página?',
            'pagina' => ['titulo' => 'Órdenes', 'texto' => 'Pendiente'],
        ])->assertStatus(422)
            ->assertJsonPath('error', 'Falta GEMINI_API_KEY en el archivo .env.');
    }

    public function test_rellena_solo_campos_de_la_pagina_y_acepta_imagen(): void
    {
        Env::getRepository()->set('GEMINI_API_KEY', 'test-key');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => '{"respuesta":"Tomé el nombre de la imagen.","rellenar":[{"clave":"cliente_nombre","valor":"Ana Pérez"},{"clave":"password","valor":"secreto"}]}',
                        ]],
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($this->usuario());

        $this->post(route('asistente.consultar'), [
            'mensaje' => 'Rellena el cliente con la foto',
            'pagina' => [
                'titulo' => 'Nueva orden',
                'url' => '/servicio/ordenes/crear',
                'texto' => 'Cliente Equipo',
                'campos' => [
                    ['clave' => 'cliente_nombre', 'etiqueta' => 'Cliente', 'tipo' => 'text', 'valor' => ''],
                ],
            ],
            'imagen' => UploadedFile::fake()->image('referencia.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('respuesta', 'Tomé el nombre de la imagen.')
            ->assertJsonPath('rellenar.0.clave', 'cliente_nombre')
            ->assertJsonPath('rellenar.0.valor', 'Ana Pérez')
            ->assertJsonMissing(['clave' => 'password']);

        Http::assertSentCount(1);
    }

    public function test_cada_usuario_ve_solo_su_conversacion(): void
    {
        Env::getRepository()->set('GEMINI_API_KEY', 'test-key');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => '{"respuesta":"Solo para ti.","rellenar":[]}']]],
                ]],
            ]),
        ]);

        $uno = $this->usuario();
        $dos = User::create([
            'name' => 'Otro usuario',
            'email' => 'otro-asistente@test.local',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->actingAs($uno)->postJson(route('asistente.consultar'), [
            'mensaje' => 'mi pedido secreto',
        ])->assertOk();

        $this->actingAs($uno)->getJson(route('asistente.mensajes'))
            ->assertOk()
            ->assertJsonPath('mensajes.0.texto', 'mi pedido secreto')
            ->assertJsonPath('mensajes.1.texto', 'Solo para ti.');

        $this->actingAs($dos)->getJson(route('asistente.mensajes'))
            ->assertOk()
            ->assertJsonPath('mensajes', []);
    }

    public function test_el_panel_esta_en_el_layout(): void
    {
        $html = view('partials.asistente')->render();

        $this->assertStringContainsString('Asistente', $html);
        $this->assertStringContainsString('asistente.js', $html);
    }

    private function usuario(): User
    {
        return User::create([
            'name' => 'Admin Asistente',
            'email' => 'asistente@test.local',
            'password' => 'password123',
            'role' => User::ROLE_ADMIN,
        ]);
    }
}
