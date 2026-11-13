<?php

namespace Tests\Feature;

use App\Models\ArchiveroArchivo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class ArchiveroTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        (require database_path('migrations/2026_11_13_140000_create_archivero_tables.php'))->up();
        foreach (['SUPABASE_URL' => 'https://supabase.test', 'SUPABASE_KEY' => 'clave'] as $k => $v) {
            putenv("{$k}={$v}");
            $_ENV[$k] = $_SERVER[$k] = $v;
        }
        Http::fake(['*' => Http::response('contenido-del-pdf', 200)]);
    }

    private function usuario(string $rol): User
    {
        return User::create(['name' => ucfirst($rol), 'email' => $rol.'-'.uniqid().'@test.local', 'password' => 'password123', 'role' => $rol]);
    }

    public function test_sube_navega_abre_y_borra(): void
    {
        $cobranza = $this->usuario(User::ROLE_COBRANZA);

        $this->actingAs($cobranza)->post(route('cobranza.archivero.carpeta'), ['carpeta' => 'ARCHIVO', 'nombre' => 'MARIO TIZZANI'])
            ->assertRedirect(route('cobranza.archivero.index', ['carpeta' => 'ARCHIVO/MARIO TIZZANI']));
        $this->actingAs($cobranza)->post(route('cobranza.archivero.subir'), [
            'carpeta' => 'ARCHIVO/MARIO TIZZANI',
            'archivos' => [UploadedFile::fake()->create('contrato.pdf', 20, 'application/pdf'), UploadedFile::fake()->create('contrato.pdf', 10, 'application/pdf')],
        ])->assertSessionHas('success');

        $archivos = ArchiveroArchivo::query()->orderBy('id')->get();
        $this->assertSame(['contrato.pdf', 'contrato (2).pdf'], $archivos->pluck('nombre')->all());
        $this->assertSame('ARCHIVO/MARIO TIZZANI', $archivos[0]->carpeta);
        $this->assertMatchesRegularExpression('#^\d{4}/\d{2}/[0-9a-f-]{36}\.pdf$#', $archivos[0]->objeto);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/storage/v1/object/archivero/'.$archivos[0]->objeto));

        $this->actingAs($cobranza)->get(route('cobranza.archivero.index'))->assertOk()->assertSee('ARCHIVO')->assertSee('2 archivos');
        $this->actingAs($cobranza)->get(route('cobranza.archivero.index', ['carpeta' => 'ARCHIVO']))->assertOk()->assertSee('MARIO TIZZANI');
        $this->actingAs($cobranza)->get(route('cobranza.archivero.index', ['q' => 'contrato']))->assertOk()->assertSee('contrato (2).pdf');

        $ver = $this->actingAs($cobranza)->get(route('cobranza.archivero.ver', $archivos[0]));
        $ver->assertOk()->assertHeader('Content-Disposition', 'inline; filename="contrato.pdf"');
        $this->assertSame('contenido-del-pdf', $ver->streamedContent());

        $this->actingAs($cobranza)->delete(route('cobranza.archivero.borrar', $archivos[1]))->assertSessionHas('success');
        $this->assertSame(1, ArchiveroArchivo::query()->count());

        $this->actingAs($this->usuario(User::ROLE_VENDEDOR))->get(route('cobranza.archivero.index'))->assertRedirect();
    }

    public function test_importa_una_carpeta_conservando_subcarpetas_y_sin_repetir(): void
    {
        $raiz = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pendrive_'.uniqid();
        mkdir($raiz.'/COBRANZAS JENU/2025', 0777, true);
        file_put_contents($raiz.'/COBRANZAS JENU/2025/relacion.xlsx', 'x');
        file_put_contents($raiz.'/CONTROL.pdf', 'pdf');
        file_put_contents($raiz.'/~$CONTROL.xlsx', 'lock');
        file_put_contents($raiz.'/instalar.exe', 'exe');

        $this->artisan('archivero:importar', ['origen' => $raiz])->assertSuccessful();
        $this->assertEqualsCanonicalizing(
            ['COBRANZAS JENU/2025|relacion.xlsx', '|CONTROL.pdf'],
            ArchiveroArchivo::query()->get()->map(fn ($a) => $a->carpeta.'|'.$a->nombre)->all()
        );

        // Repetir no vuelve a subir lo que ya está.
        Http::fake(['*' => Http::response('', 500)]);
        $this->artisan('archivero:importar', ['origen' => $raiz])->assertSuccessful();
        $this->assertSame(2, ArchiveroArchivo::query()->count());
    }
}
