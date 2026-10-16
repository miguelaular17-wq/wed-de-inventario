<?php

namespace App\Http\Controllers;

use App\Models\AnalisisCategoriaCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AnalisisCategoriasController extends Controller
{
    public function index(Request $request): View
    {
        $sedes = $this->sedes();
        $sede = strtoupper(trim((string) $request->query('sede', $sedes[0] ?? 'DORAL')));
        if (! in_array($sede, $sedes, true)) {
            $sede = $sedes[0] ?? 'DORAL';
        }

        $arbol = $this->arbolCategorias($sede);
        $marcadas = $this->marcadasDeSede($sede);

        $categorias = $arbol->map(function (array $cat) use ($marcadas) {
            $subs = collect($cat['subcategorias'])->map(function (string $sub) use ($cat, $marcadas) {
                $key = $this->clave($cat['nombre'], $sub);

                return [
                    'nombre' => $sub,
                    'marcada' => isset($marcadas[$key]),
                ];
            })->values();

            $total = $subs->count();
            $hechas = $subs->where('marcada', true)->count();

            return [
                'nombre' => $cat['nombre'],
                'subcategorias' => $subs,
                'total' => $total,
                'hechas' => $hechas,
                'completa' => $total > 0 && $hechas === $total,
            ];
        })->values();

        $totalSubs = $categorias->sum('total');
        $hechasSubs = $categorias->sum('hechas');
        $catsCompletas = $categorias->where('completa', true)->count();

        return view('analisis_categorias.index', [
            'sedes' => $sedes,
            'sede' => $sede,
            'sedeLabel' => config('inventario.display.'.$sede, $sede),
            'categorias' => $categorias,
            'progreso' => [
                'subs_total' => $totalSubs,
                'subs_hechas' => $hechasSubs,
                'cats_total' => $categorias->count(),
                'cats_completas' => $catsCompletas,
                'pct' => $totalSubs > 0 ? round(($hechasSubs / $totalSubs) * 100) : 0,
            ],
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        if (! Schema::hasTable('analisis_categoria_checks')) {
            return response()->json([
                'ok' => false,
                'error' => 'Falta migrar la tabla. Ejecuta: php artisan migrate',
            ], 503);
        }

        $data = $request->validate([
            'sede' => ['required', 'string', 'max:40'],
            'categoria' => ['required', 'string', 'max:191'],
            'subcategoria' => ['required', 'string', 'max:191'],
        ]);

        $sede = strtoupper(trim($data['sede']));
        if (! in_array($sede, $this->sedes(), true)) {
            return response()->json(['ok' => false, 'error' => 'Sede no válida.'], 422);
        }

        $categoria = trim($data['categoria']);
        $subcategoria = trim($data['subcategoria']);

        $existente = AnalisisCategoriaCheck::query()
            ->where('sede', $sede)
            ->where('categoria', $categoria)
            ->where('subcategoria', $subcategoria)
            ->first();

        if ($existente) {
            $existente->delete();
            $marcada = false;
        } else {
            AnalisisCategoriaCheck::create([
                'sede' => $sede,
                'categoria' => $categoria,
                'subcategoria' => $subcategoria,
                'user_id' => $request->user()?->id,
            ]);
            $marcada = true;
        }

        $subsDeCat = $this->subcategoriasDeCategoria($sede, $categoria);
        $marcadasCat = AnalisisCategoriaCheck::query()
            ->where('sede', $sede)
            ->where('categoria', $categoria)
            ->whereIn('subcategoria', $subsDeCat)
            ->pluck('subcategoria')
            ->all();
        $hechas = count(array_unique($marcadasCat));
        $total = count($subsDeCat);

        return response()->json([
            'ok' => true,
            'marcada' => $marcada,
            'categoria' => [
                'nombre' => $categoria,
                'hechas' => $hechas,
                'total' => $total,
                'completa' => $total > 0 && $hechas === $total,
            ],
        ]);
    }

    /** @return list<string> */
    private function sedes(): array
    {
        $sedes = config('inventario.sedes_locales', ['DORAL', 'VIRTUDES', 'ZAMORA', 'CENTRO', 'SAMBIL', 'NUNES', 'MOVISTAR']);

        return array_values(array_unique(array_map('strtoupper', $sedes)));
    }

    /**
     * Categorías/subcategorías del catálogo con existencia en la sede.
     *
     * @return Collection<int, array{nombre: string, subcategorias: list<string>}>
     */
    private function arbolCategorias(string $sede): Collection
    {
        try {
            $rows = DB::connection('pgsql')->select("
                SELECT
                    TRIM(p.categoria) AS categoria,
                    TRIM(COALESCE(NULLIF(TRIM(p.subcategoria), ''), '(Sin subcategoría)')) AS subcategoria
                FROM inventario_v2.productos p
                INNER JOIN inventario_v2.stock_actual sa
                    ON sa.producto_id = p.id
                   AND UPPER(TRIM(sa.sede)) = :sede
                   AND sa.existencia > 0
                WHERE p.categoria IS NOT NULL
                  AND TRIM(p.categoria) <> ''
                GROUP BY TRIM(p.categoria), TRIM(COALESCE(NULLIF(TRIM(p.subcategoria), ''), '(Sin subcategoría)'))
                ORDER BY 1, 2
            ", ['sede' => $sede]);
        } catch (\Throwable $e) {
            return collect();
        }

        return collect($rows)
            ->groupBy(fn ($r) => (string) $r->categoria)
            ->map(fn ($subs, $cat) => [
                'nombre' => (string) $cat,
                'subcategorias' => $subs->pluck('subcategoria')->map(fn ($s) => (string) $s)->values()->all(),
            ])
            ->values();
    }

    /** @return array<string, true> */
    private function marcadasDeSede(string $sede): array
    {
        if (! Schema::hasTable('analisis_categoria_checks')) {
            return [];
        }

        $out = [];
        foreach (
            AnalisisCategoriaCheck::query()
                ->where('sede', $sede)
                ->get(['categoria', 'subcategoria']) as $row
        ) {
            $out[$this->clave($row->categoria, $row->subcategoria)] = true;
        }

        return $out;
    }

    /** @return list<string> */
    private function subcategoriasDeCategoria(string $sede, string $categoria): array
    {
        $cat = $this->arbolCategorias($sede)->firstWhere('nombre', $categoria);

        return $cat['subcategorias'] ?? [];
    }

    private function clave(string $categoria, string $subcategoria): string
    {
        return mb_strtoupper(trim($categoria), 'UTF-8').'|'.mb_strtoupper(trim($subcategoria), 'UTF-8');
    }
}
