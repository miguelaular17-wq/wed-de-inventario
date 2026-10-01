<?php

namespace Tests\Unit;

use App\Services\StockSedeDashboardService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockSedeDashboardServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['inventario.sedes_stock' => ['JRZ', 'DORAL']]);
        config(['inventario.display' => ['JRZ' => 'JRZ', 'DORAL' => 'Doral']]);

        Schema::dropIfExists('requisiciones_manuales');
        Schema::create('requisiciones_manuales', function ($table) {
            $table->id();
            $table->string('sede_local');
            $table->unsignedInteger('cantidad');
            $table->timestamp('aplicada_at')->nullable();
        });
        DB::table('requisiciones_manuales')->insert([
            ['sede_local' => 'DORAL', 'cantidad' => 3, 'aplicada_at' => null],
            ['sede_local' => 'DORAL', 'cantidad' => 9, 'aplicada_at' => now()],
        ]);
        Schema::dropIfExists('stock_actual');
        Schema::dropIfExists('productos');
        Schema::create('productos', function ($table) {
            $table->id();
            $table->string('codigo');
            $table->string('nombre');
            $table->decimal('costo_actual', 12, 2)->default(0);
        });
        Schema::create('stock_actual', function ($table) {
            $table->id();
            $table->unsignedBigInteger('producto_id');
            $table->string('sede');
            $table->integer('existencia')->default(0);
        });

        $id = DB::table('productos')->insertGetId([
            'codigo' => 'SKU-1',
            'nombre' => 'Audifono',
            'costo_actual' => 10,
        ]);
        DB::table('stock_actual')->insert([
            ['producto_id' => $id, 'sede' => 'JRZ', 'existencia' => 4],
            ['producto_id' => $id, 'sede' => 'DORAL', 'existencia' => 2],
        ]);
        $otro = DB::table('productos')->insertGetId([
            'codigo' => 'SKU-2',
            'nombre' => 'Cargador',
            'costo_actual' => 5,
        ]);
        DB::table('stock_actual')->insert([
            ['producto_id' => $otro, 'sede' => 'DORAL', 'existencia' => 0],
        ]);
    }

    public function test_resume_existencia_por_sede_y_sku_sin_contar_ceros(): void
    {
        $data = app(StockSedeDashboardService::class)->resumen();

        $this->assertSame(6, $data['totales']['unidades']);
        $this->assertSame(1, $data['totales']['skus']);
        $this->assertSame(4, $data['por_sede'][0]['unidades']);
        $this->assertSame(1, $data['por_sede'][0]['skus']);
        $this->assertSame(2, $data['por_sede'][1]['unidades']);
        $this->assertSame(1, $data['por_sede'][1]['skus']);
        $this->assertSame(40.0, $data['por_sede'][0]['valorizado']);
        $this->assertSame(20.0, $data['por_sede'][1]['valorizado']);
        $this->assertSame(0, $data['por_sede'][0]['traslado']);
        $this->assertSame(3, $data['por_sede'][1]['traslado']);
        $this->assertSame(60.0, $data['totales']['valorizado']);
        $this->assertSame(3, $data['totales']['traslado']);
    }
}
