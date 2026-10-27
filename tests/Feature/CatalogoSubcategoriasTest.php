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
}
