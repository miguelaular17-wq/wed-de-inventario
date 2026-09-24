<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesCollections;
use App\Services\ProductRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class VendedorController extends Controller
{
    use PaginatesCollections;

    /** @var list<int> */
    private const DIAS_VENTAS = [7, 15, 30, 60, 90];

    public function __construct(
        private ProductRepository $products,
    ) {}

    public function index(Request $request): View
    {
        return $this->renderCatalogo($request, modoJrz: false);
    }

    /** Catálogo vendedor JRZ: bajo cada sede muestra ventas del período elegido. */
    public function jrz(Request $request): View
    {
        return $this->renderCatalogo($request, modoJrz: true);
    }

    private function renderCatalogo(Request $request, bool $modoJrz): View
    {
        ini_set('memory_limit', '512M');
        $q = trim((string) $request->query('q', ''));
        $categoria = trim((string) $request->query('categoria', ''));
        $subcategoria = trim((string) $request->query('subcategoria', ''));
        $sedeLocal = (string) $request->session()->get('sede_local');
        $sedes = config('inventario.sedes_stock', []);

        if (! $sedeLocal) {
            $sedeLocal = ! empty($sedes) ? $sedes[0] : 'DORAL';
        }

        if ($modoJrz) {
            $sedeLocal = 'JRZ';
        }

        $dias = (int) $request->query('dias', 30);
        if (! in_array($dias, self::DIAS_VENTAS, true)) {
            $dias = 30;
        }

        $products = $this->products->loadForSede($sedeLocal);

        $categoriasTree = [];
        if ($modoJrz) {
            $categoriasTree = $products
                ->map(fn ($row) => [
                    'categoria' => trim((string) ($row['categoria'] ?? '')),
                    'subcategoria' => trim((string) ($row['subcategoria'] ?? '')),
                ])
                ->filter(fn ($row) => $row['categoria'] !== '')
                ->groupBy('categoria')
                ->map(function ($items) {
                    return $items->pluck('subcategoria')
                        ->filter(fn ($sub) => $sub !== '')
                        ->unique()
                        ->sort()
                        ->values()
                        ->all();
                })
                ->sortKeys()
                ->all();

            if ($categoria !== '' && ! array_key_exists($categoria, $categoriasTree)) {
                $categoria = '';
                $subcategoria = '';
            }
            if ($categoria === '') {
                $subcategoria = '';
            } elseif ($subcategoria !== '' && ! in_array($subcategoria, $categoriasTree[$categoria] ?? [], true)) {
                $subcategoria = '';
            }
        } else {
            $categoria = '';
            $subcategoria = '';
        }

        if ($q !== '') {
            $qLower = mb_strtolower($q);
            $products = $products->filter(function ($row) use ($qLower) {
                return str_contains(mb_strtolower((string) ($row['cod_centro'] ?? '')), $qLower)
                    || str_contains(mb_strtolower((string) ($row['producto'] ?? '')), $qLower);
            });
        }

        if ($modoJrz && $categoria !== '') {
            $products = $products->filter(
                fn ($row) => strcasecmp(trim((string) ($row['categoria'] ?? '')), $categoria) === 0
            );
            if ($subcategoria !== '') {
                $products = $products->filter(
                    fn ($row) => strcasecmp(trim((string) ($row['subcategoria'] ?? '')), $subcategoria) === 0
                );
            }
        }

        $mappedProducts = $products->map(function ($row) {
            $stocks = (isset($row['stocks']) && is_array($row['stocks'])) ? $row['stocks'] : [];
            $row['existencia_global'] = array_sum($stocks);

            return $row;
        });

        $rows = $this->paginateCollection($mappedProducts, $request);

        $casheaLevelsPath = storage_path('app/cashea_levels.json');
        $defaultLevels = [
            1 => 60,
            2 => 50,
            3 => 40,
            4 => 40,
            5 => 40,
            6 => 40,
        ];
        $casheaLevels = $defaultLevels;
        if (file_exists($casheaLevelsPath)) {
            $stored = json_decode(file_get_contents($casheaLevelsPath), true);
            if (is_array($stored)) {
                foreach (range(1, 6) as $nivel) {
                    if (isset($stored[$nivel])) {
                        $casheaLevels[$nivel] = (int) $stored[$nivel];
                    }
                }
            }
        }

        $ventasPorSede = $modoJrz ? $this->ventasUnidadesPorSede($dias, $sedes) : [];

        return view('vendedor.index', [
            'rows' => $rows,
            'q' => $q,
            'sedes' => $sedes,
            'stockUpdatedAt' => $this->products->lastStockUpdate(),
            'casheaLevels' => $casheaLevels,
            'modoJrz' => $modoJrz,
            'diasVentas' => $dias,
            'diasOpciones' => self::DIAS_VENTAS,
            'ventasPorSede' => $ventasPorSede,
            'categoria' => $categoria,
            'subcategoria' => $subcategoria,
            'categoriasTree' => $categoriasTree,
        ]);
    }

    /**
     * Unidades netas vendidas por código y sede (últimos N días).
     *
     * @param  list<string>  $sedes
     * @return array<string, array<string, float>>  [CODIGO][SEDE] => unidades
     */
    private function ventasUnidadesPorSede(int $dias, array $sedes): array
    {
        if (! Schema::hasTable('ventas_detalle') || $dias < 1 || $sedes === []) {
            return [];
        }

        $sedesUpper = array_values(array_unique(array_map(
            fn ($s) => mb_strtoupper(trim((string) $s), 'UTF-8'),
            $sedes
        )));
        $desde = now()->subDays($dias)->toDateString();

        $query = DB::table('ventas_detalle')
            ->whereIn(DB::raw('UPPER(TRIM(sede))'), $sedesUpper)
            ->whereDate('fecha', '>=', $desde)
            ->whereNotNull('codigo_producto')
            ->where('codigo_producto', '!=', '');

        if (Schema::hasColumn('ventas_detalle', 'anulado')) {
            $query->where('anulado', false);
        }

        $rows = $query
            ->selectRaw('UPPER(TRIM(codigo_producto)) as codigo')
            ->selectRaw('UPPER(TRIM(sede)) as sede')
            ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -ABS(cantidad) ELSE ABS(cantidad) END) as unidades")
            ->groupBy(DB::raw('UPPER(TRIM(codigo_producto))'), DB::raw('UPPER(TRIM(sede))'))
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $codigo = (string) ($row->codigo ?? '');
            $sede = (string) ($row->sede ?? '');
            if ($codigo === '' || $sede === '') {
                continue;
            }
            $out[$codigo][$sede] = round((float) $row->unidades, 2);
        }

        return $out;
    }
}
