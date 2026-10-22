<?php

namespace Tests\Unit;

use App\Services\AnalisisCategoriasImportador;
use PHPUnit\Framework\TestCase;

class AnalisisCategoriasImportadorTest extends TestCase
{
    private function importador(): AnalisisCategoriasImportador
    {
        $arbol = [
            ['ACCESORIOS DE VESTIR', 'RELOJES'], ['ACCESORIOS DE VESTIR', 'GORRAS'],
            ['TEXTILES', 'CAMISA'], ['TEXTILES', 'MEDIAS'],
            ['RELOJES INTELIGENTES', 'RELOJ AMAZFIT'], ['RELOJES INTELIGENTES', 'RELOJ XIAOMI'], ['RELOJES INTELIGENTES', 'RELOJ CUBITT'],
            ['AMAZFIT', 'RELOJ AMAZFIT'],
            ['DECORACION Y ARREGLOS', 'GLOBOS'], ['DECORACION Y ARREGLOS', 'FLORES'],
            ['ARTICULOS ESCOLARES', 'BOLIGRAFOS/LAPICES/CREYONES/COLORES'],
            ['JUGUETERIA', 'JUEGUETERIA NIÑA'],
        ];

        return (new AnalisisCategoriasImportador())->conArbol(
            array_map(fn ($p) => (object) ['categoria' => $p[0], 'subcategoria' => $p[1]], $arbol)
        );
    }

    public function test_ubica_cada_subcategoria_en_su_categoria_real(): void
    {
        $res = $this->importador()->resolver([
            'DORAL' => [
                ['categoria' => 'ACCESORIOS DE VESTIR', 'subcategoria' => 'GORRAS'],
                // La hoja la deja bajo RELOJES INTELIGENTES, pero en el catálogo es de TEXTILES.
                ['categoria' => 'RELOJES INTELIGENTES', 'subcategoria' => 'CAMISA'],
                // Sin categoría (hoja CENTRO) y con espacios/acentos distintos.
                ['categoria' => null, 'subcategoria' => 'BOLIGRAFOS / LAPICES/CREYONES/COLORES '],
                ['categoria' => null, 'subcategoria' => 'JUEGUETERÍA NIÑA '],
                // Repetida en una categoría suelta: se queda con la principal.
                ['categoria' => null, 'subcategoria' => 'RELOJ AMAZFIT'],
                // Categoría sola: marca todas sus subcategorías.
                ['categoria' => 'DECORACION Y ARREGLOS', 'subcategoria' => null],
                ['categoria' => null, 'subcategoria' => 'TELEFONOS GAMER'],
            ],
        ]);

        $marcas = array_map(fn ($p) => $p[0] . ' > ' . $p[1], $res['DORAL']['marcas']);
        sort($marcas);

        $this->assertSame([
            'ACCESORIOS DE VESTIR > GORRAS',
            'ARTICULOS ESCOLARES > BOLIGRAFOS/LAPICES/CREYONES/COLORES',
            'DECORACION Y ARREGLOS > FLORES',
            'DECORACION Y ARREGLOS > GLOBOS',
            'JUGUETERIA > JUEGUETERIA NIÑA',
            'RELOJES INTELIGENTES > RELOJ AMAZFIT',
            'TEXTILES > CAMISA',
        ], $marcas);
        $this->assertSame(['TELEFONOS GAMER'], $res['DORAL']['no_encontradas']);
    }
}
