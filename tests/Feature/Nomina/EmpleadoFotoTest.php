<?php

namespace Tests\Feature\Nomina;

use App\Models\Nomina\NominaEmpleado;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class EmpleadoFotoTest extends TestCase
{
    use CreatesNominaSchema;

    private User $rrhh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();

        $this->rrhh = User::create([
            'name' => 'RRHH',
            'email' => 'rrhh-foto@test.local',
            'password' => 'password123',
            'role' => User::ROLE_RRHH,
        ]);
    }

    protected function tearDown(): void
    {
        foreach (['SUPABASE_KEY', 'SUPABASE_URL'] as $k) {
            putenv($k);
            unset($_ENV[$k], $_SERVER[$k]);
        }
        parent::tearDown();
    }

    public function test_sube_foto_a_supabase_bucket_user_recortada_cuadrada(): void
    {
        $this->setEnv('SUPABASE_URL', 'https://hbhqbmzixgcvxkilwsau.supabase.co');
        $this->setEnv('SUPABASE_KEY', 'test-key');
        $subidas = [];
        Http::fake(function ($request) use (&$subidas) {
            $subidas[] = $request;

            return Http::response(['Key' => 'ok'], 200);
        });

        $empleado = $this->crearEmpleado();

        $this->actingAs($this->rrhh)
            ->put(route('nomina.empleados.update', $empleado), $this->datos() + [
                'foto' => UploadedFile::fake()->image('cara.jpg', 900, 1400),
            ])
            ->assertRedirect(route('nomina.empleados.show', $empleado));

        $url = $empleado->fresh()->foto_url;
        $this->assertStringStartsWith('https://hbhqbmzixgcvxkilwsau.supabase.co/storage/v1/object/public/user/empleado_'.$empleado->id.'_', $url);
        $this->assertCount(1, $subidas);
        $this->assertStringContainsString('/storage/v1/object/user/empleado_', $subidas[0]->url());
        $this->assertSame('image/jpeg', $subidas[0]->header('Content-Type')[0]);

        $img = imagecreatefromstring($subidas[0]->body());
        $this->assertSame(600, imagesx($img));
        $this->assertSame(600, imagesy($img));

        $this->actingAs($this->rrhh)
            ->get(route('nomina.empleados.index'))
            ->assertOk()
            ->assertSee($url, false);

        $this->actingAs($this->rrhh)
            ->put(route('nomina.empleados.update', $empleado), $this->datos() + ['quitar_foto' => 1])
            ->assertRedirect();
        $this->assertNull($empleado->fresh()->foto_url);
        $this->assertSame('DELETE', $subidas[1]->method());
    }

    public function test_sin_supabase_guarda_foto_en_disco_local(): void
    {
        Storage::fake('public');
        $empleado = $this->crearEmpleado();

        $this->actingAs($this->rrhh)
            ->put(route('nomina.empleados.update', $empleado), $this->datos() + [
                'foto' => UploadedFile::fake()->image('cara.png', 300, 200),
            ])
            ->assertRedirect();

        $url = $empleado->fresh()->foto_url;
        $this->assertNotNull($url);
        Storage::disk('public')->assertExists('empleados/'.basename($url));
    }

    public function test_rechaza_archivo_que_no_es_imagen(): void
    {
        $empleado = $this->crearEmpleado();

        $this->actingAs($this->rrhh)
            ->put(route('nomina.empleados.update', $empleado), $this->datos() + [
                'foto' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('foto');

        $this->assertNull($empleado->fresh()->foto_url);
    }

    private function crearEmpleado(): NominaEmpleado
    {
        $this->actingAs($this->rrhh)->post(route('nomina.empleados.store'), $this->datos())->assertRedirect();

        return NominaEmpleado::query()->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function datos(): array
    {
        return [
            'cedula' => '25470483',
            'nombre' => 'Abel David Yajuris',
            'salario_base' => 240,
            'tipo_salario' => 'QUINCENAL',
            'estado' => 'ACTIVO',
        ];
    }

    private function setEnv(string $k, string $v): void
    {
        putenv("{$k}={$v}");
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }
}
