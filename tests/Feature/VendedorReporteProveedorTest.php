<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesNominaSchema;
use Tests\TestCase;

class VendedorReporteProveedorTest extends TestCase
{
    use CreatesNominaSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNominaSchema();
        if (! Schema::hasColumn('productos', 'proveedor')) {
            Schema::table('productos', fn (Blueprint $t) => $t->string('proveedor')->nullable());
        }
    }

    public function test_compras_entra_y_el_excel_trae_ventas_por_sede_y_existencia_total(): void
    {
        $a = DB::table('productos')->insertGetId(['codigo' => 'A1 / 7700001', 'nombre' => 'PROTECTOR SOLAR', 'categoria' => 'HIGIENE', 'proveedor' => 'ACME']);
        $b = DB::table('productos')->insertGetId(['codigo' => 'B2', 'nombre' => 'MACA POWER', 'categoria' => 'CAPILAR', 'proveedor' => 'ACME']);
        $otro = DB::table('productos')->insertGetId(['codigo' => 'Z9', 'nombre' => 'OTRA MARCA', 'proveedor' => 'OTRO']);
        $hoy = now()->toDateString();
        foreach ([[$a, 'DORAL', 'FAC', 3], [$a, 'VIRTUDES', 'FAC', 5], [$a, 'DORAL', 'DEV', 1], [$b, 'CENTRO', 'FAC', 4], [$otro, 'DORAL', 'FAC', 9]] as $i => [$pid, $sede, $tipo, $cant]) {
            DB::table('ventas_detalle')->insert(['sede' => $sede, 'tipo_documento' => $tipo, 'numero_documento' => 'N'.$i,
                'fecha' => $hoy, 'producto_id' => $pid, 'cantidad' => $cant]);
        }
        DB::table('ventas_detalle')->insert(['sede' => 'DORAL', 'tipo_documento' => 'FAC', 'numero_documento' => 'VIEJA',
            'fecha' => now()->subDays(40)->toDateString(), 'producto_id' => $a, 'cantidad' => 50]);
        DB::table('stock_actual')->insert([
            ['producto_id' => $a, 'sede' => 'DORAL', 'existencia' => 2],
            ['producto_id' => $a, 'sede' => 'VIRTUDES', 'existencia' => 6],
            ['producto_id' => $b, 'sede' => 'CENTRO', 'existencia' => 10],
        ]);

        $compras = User::create(['name' => 'Compras', 'email' => 'compras-'.uniqid().'@test.local', 'password' => 'password123',
            'role' => User::ROLE_COMPRADOR]);
        $this->assertTrue($compras->canAccess('vendedor.jrz'));

        $res = $this->actingAs($compras)->get(route('vendedor.jrz.reporte_proveedor', ['proveedor' => 'acme', 'dias' => 30]));
        $res->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $res->headers->get('content-type'));

        $xml = $this->hoja($res->getContent());
        $this->assertStringContainsString('PROTECTOR SOLAR', $xml);
        $this->assertStringContainsString('>A1<', $xml);
        $this->assertStringNotContainsString('OTRA MARCA', $xml);
        $this->assertStringContainsString('Existencia total', $xml);
        $this->assertStringContainsString('TOTAL', $xml);
        // Total vendido de A: 3 + 5 - 1 = 7 (la venta de hace 40 días no entra); total general vendido 11, existencia 18.
        $this->assertMatchesRegularExpression('#<v>7</v>#', $xml);
        $this->assertMatchesRegularExpression('#<v>11</v>#', $xml);
        $this->assertMatchesRegularExpression('#<v>18</v>#', $xml);
    }

    public function test_marketing_no_entra(): void
    {
        $marketing = User::create(['name' => 'Mkt', 'email' => 'mkt-'.uniqid().'@test.local', 'password' => 'password123', 'role' => 'marketing']);
        $this->assertFalse($marketing->canAccess('vendedor.jrz'));
    }

    private function hoja(string $xlsx): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($ruta, $xlsx);
        $zip = new \ZipArchive();
        $zip->open($ruta);
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($ruta);

        return $xml;
    }
}
