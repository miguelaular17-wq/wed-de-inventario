<?php

namespace Tests\Feature;

use App\Models\Mayor\MayorCliente;
use App\Models\Mayor\MayorVisita;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class VisitasMayorTest extends TestCase
{
    use CreatesNominaSchema;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        (require database_path('migrations/2026_11_12_150000_create_ventas_mayor_tables.php'))->up();
        (require database_path('migrations/2026_11_12_160000_create_mayor_visitas_table.php'))->up();
        Http::fake(['*' => Http::response([], 200)]);
        Storage::fake('public');
        $this->user = User::create(['name' => 'Mayorista', 'email' => 'visitas-'.uniqid().'@test.local', 'password' => 'password123', 'role' => User::ROLE_MAYOR]);
    }

    private function firma(): string
    {
        $img = imagecreatetruecolor(300, 120);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        $negro = imagecolorallocate($img, 0, 0, 0);
        for ($i = 0; $i < 40; $i++) {
            imageline($img, random_int(0, 299), random_int(0, 119), random_int(0, 299), random_int(0, 119), $negro);
        }
        ob_start();
        imagepng($img);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    private function visita(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->post(route('ventas_mayor.visitas.store'), $extra + [
            'fecha' => now()->toDateString(),
            'negocio_nombre' => 'Bodegón La Esquina',
            'negocio_documento' => 'j-30111222-3',
            'negocio_telefono' => '0261-5550000',
            'zona' => 'Centro',
            'atendio_nombre' => 'Carlos Pérez',
            'atendio_cargo' => 'Encargado',
            'resultado' => 'interesado',
            'proxima_visita' => now()->addDays(3)->toDateString(),
            'firma' => $this->firma(),
            'evidencias' => [UploadedFile::fake()->image('local.jpg')],
            'latitud' => 10.6427,
            'longitud' => -71.6125,
        ]);
    }

    public function test_registra_visita_con_firma_y_evidencia_obligatorias(): void
    {
        $this->visita(['firma' => ''])->assertSessionHasErrors('firma');
        $this->visita(['evidencias' => []])->assertSessionHasErrors('evidencias');
        $this->assertSame(0, MayorVisita::query()->count());

        $this->visita()->assertSessionHasNoErrors()->assertRedirect();
        $v = MayorVisita::query()->firstOrFail();
        $this->assertSame('J301112223', $v->negocio_documento);
        $this->assertSame('Carlos Pérez', $v->atendio_nombre);
        $this->assertNotEmpty($v->firma_url);
        $this->assertCount(1, $v->evidencias);
        $this->assertNull($v->cliente_id);

        $this->actingAs($this->user)->get(route('ventas_mayor.visitas.index'))->assertOk()->assertSee('Bodegón La Esquina')->assertSee('Próximas visitas');
        $this->actingAs($this->user)->get(route('ventas_mayor.visitas.show', $v))->assertOk()->assertSee('Carlos Pérez')->assertSee('Ver en el mapa');

        // Otra vendedora no ve visitas ajenas.
        $otra = User::create(['name' => 'Otra', 'email' => 'otra-'.uniqid().'@test.local', 'password' => 'password123', 'role' => User::ROLE_MAYOR]);
        $this->actingAs($otra)->get(route('ventas_mayor.visitas.show', $v))->assertForbidden();
        $this->actingAs($otra)->get(route('ventas_mayor.visitas.index'))->assertOk()->assertDontSee('Bodegón La Esquina');
    }

    public function test_desde_la_visita_se_registra_el_cliente_para_facturarle(): void
    {
        $this->visita()->assertSessionHasNoErrors();
        $v = MayorVisita::query()->firstOrFail();

        $this->actingAs($this->user)->post(route('ventas_mayor.visitas.cliente', $v))
            ->assertRedirect(route('ventas_mayor.create', ['cliente_id' => MayorCliente::query()->value('id')]));
        $cliente = MayorCliente::query()->firstOrFail();
        $this->assertSame('Bodegón La Esquina', $cliente->nombre);
        $this->assertSame('J301112223', $cliente->documento);
        $this->assertSame($cliente->id, (int) $v->fresh()->cliente_id);

        // La siguiente visita a ese RIF queda ligada al cliente sola.
        $this->visita(['resultado' => 'pedido'])->assertSessionHasNoErrors();
        $this->assertSame($cliente->id, (int) MayorVisita::query()->latest('id')->value('cliente_id'));
    }
}
