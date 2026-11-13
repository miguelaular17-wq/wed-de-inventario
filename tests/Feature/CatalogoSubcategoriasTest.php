<?php

namespace Tests\Feature;

use App\Http\Controllers\CatalogoController;
use Illuminate\Http\Request;
use Tests\TestCase;

class CatalogoSubcategoriasTest extends TestCase
{
    private function elegidas(array $query): array
    {
        return (new \ReflectionMethod(CatalogoController::class, 'subcategoriasElegidas'))
            ->invoke(app(CatalogoController::class), Request::create('/catalogo', 'GET', $query));
    }

    public function test_acepta_varias_subcategorias_y_los_enlaces_viejos_de_una(): void
    {
        $this->assertSame(['CONSOLAS', 'JBL'], $this->elegidas(['subcategoria' => ['CONSOLAS', 'JBL', 'CONSOLAS', '']]));
        $this->assertSame(['ALEXA'], $this->elegidas(['subcategoria' => 'ALEXA']));
        $this->assertSame([], $this->elegidas(['subcategoria' => 'todas']));
        $this->assertSame([], $this->elegidas([]));
    }

    public function test_la_consulta_filtra_con_in(): void
    {
        $query = (new \ReflectionMethod(CatalogoController::class, 'buildQuery'))
            ->invoke(app(CatalogoController::class), Request::create('/catalogo', 'GET', ['subcategoria' => ['CONSOLAS', 'JBL']]));

        $this->assertStringContainsString('"subcategoria" in (?, ?)', $query->toSql());
        $this->assertContains('JBL', $query->getBindings());
    }

    public function test_barras_de_stock_y_precio(): void
    {
        $construir = fn (array $q) => (new \ReflectionMethod(CatalogoController::class, 'buildQuery'))
            ->invoke(app(CatalogoController::class), Request::create('/catalogo', 'GET', $q));

        $query = $construir(['stock_min' => '5', 'stock_max' => '100', 'precio_min' => '10', 'precio_max' => '50', 'sede' => 'DORAL']);
        $sql = $query->toSql();
        $this->assertSame(2, substr_count($sql, 'COALESCE(SUM(existencia), 0)'));
        $this->assertStringContainsString('"precio_unidad" >= ?', $sql);
        $this->assertStringContainsString('"precio_unidad" <= ?', $sql);
        $bindings = $query->getBindings();
        foreach ([5.0, 100.0, 10.0, 50.0, 'DORAL'] as $esperado) {
            $this->assertContains($esperado, $bindings);
        }

        // Desde 0 o vacío no filtra; si vienen al revés se acomodan.
        $this->assertStringNotContainsString('SUM(existencia)', $construir(['stock_min' => '0', 'stock_max' => ''])->toSql());
        $alReves = $construir(['precio_min' => '50', 'precio_max' => '10'])->getBindings();
        $this->assertSame([10.0, 50.0], array_values(array_filter($alReves, 'is_float')));
    }
}
