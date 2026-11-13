<?php

namespace Tests\Feature;

use App\Models\ProductoImagen;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class CatalogoFotosTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        (require database_path('migrations/2026_11_13_100000_create_producto_imagenes_table.php'))->up();
        foreach (['SUPABASE_URL' => 'https://supabase.test', 'SUPABASE_KEY' => 'clave'] as $k => $v) {
            putenv("{$k}={$v}");
            $_ENV[$k] = $_SERVER[$k] = $v;
        }
        Http::fake(['*' => Http::response(['Key' => 'ok'], 200)]);
    }

    /** Imagen con degradado (una foto de verdad, no un solo color). */
    private function foto(string $nombre = 'foto.png', bool $plana = false): UploadedFile
    {
        $img = imagecreatetruecolor(400, 300);
        for ($x = 0; $x < 400; $x++) {
            imageline($img, $x, 0, $x, 299, imagecolorallocate($img, $plana ? 200 : (int) ($x * 255 / 400), $plana ? 30 : 120, $plana ? 120 : 255 - (int) ($x * 255 / 400)));
        }
        $ruta = tempnam(sys_get_temp_dir(), 'foto').'.png';
        imagepng($img, $ruta);
        imagedestroy($img);

        return new UploadedFile($ruta, $nombre, 'image/png', null, true);
    }

    public function test_rechaza_una_imagen_de_un_solo_color(): void
    {
        $this->actingAs($this->usuario(User::ROLE_VENDEDOR))->post(route('catalogo.upload_image'), [
            'codigo' => '1775', 'modo' => 'extra', 'archivo' => $this->foto('muestra.png', true),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonFragment(['success' => false]);
        $this->assertSame(0, ProductoImagen::query()->count());
    }

    private function usuario(string $rol): User
    {
        return User::create(['name' => ucfirst($rol), 'email' => $rol.'-'.uniqid().'@test.local', 'password' => 'password123', 'role' => $rol]);
    }

    public function test_agrega_fotos_desde_el_equipo_hasta_el_maximo_y_las_borra(): void
    {
        $vendedor = $this->usuario(User::ROLE_VENDEDOR);
        $subir = fn () => $this->actingAs($vendedor)->post(route('catalogo.upload_image'), [
            'codigo' => '1775 / MAXG1775',
            'modo' => 'extra',
            'archivo' => $this->foto(),
        ], ['Accept' => 'application/json']);

        $subir()->assertOk()->assertJson(['success' => true]);
        $foto = ProductoImagen::query()->firstOrFail();
        $this->assertSame('1775', $foto->codigo);
        $this->assertMatchesRegularExpression('/^1775_[a-z0-9]{6}\.jpg$/', $foto->archivo);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/storage/v1/object/imagenes_producto/imagenes/1775_') && $r->header('Content-Type')[0] === 'image/jpeg');
        $this->assertSame(['1775' => [['id' => $foto->id, 'url' => $foto->url]]], \App\Support\FotoProducto::extras(['1775 / MAXG1775']));

        for ($i = 1; $i < ProductoImagen::MAXIMO_EXTRA; $i++) {
            $subir()->assertOk();
        }
        $subir()->assertStatus(422);
        $this->assertSame(ProductoImagen::MAXIMO_EXTRA, ProductoImagen::query()->count());

        $this->actingAs($vendedor)->deleteJson(route('catalogo.imagen.destroy', $foto))->assertOk();
        $this->assertSame(ProductoImagen::MAXIMO_EXTRA - 1, ProductoImagen::query()->count());
    }

    public function test_sin_permiso_no_cambia_fotos_y_el_archivo_tiene_que_ser_imagen(): void
    {
        $this->actingAs($this->usuario(User::ROLE_COMPRADOR))->post(route('catalogo.upload_image'), [
            'codigo' => '1775', 'modo' => 'extra', 'archivo' => $this->foto(),
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->actingAs($this->usuario(User::ROLE_ADMIN))->post(route('catalogo.upload_image'), [
            'codigo' => '1775', 'modo' => 'extra', 'archivo' => UploadedFile::fake()->create('lista.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertSame(0, ProductoImagen::query()->count());
    }
}
