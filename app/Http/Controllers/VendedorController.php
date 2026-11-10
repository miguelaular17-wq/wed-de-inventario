<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesCollections;
use App\Services\ProductRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

        $ventasPorSede = $modoJrz ? $this->ventasUnidadesPorSede($dias, $sedes) : [];

        $mappedProducts = $products->map(function ($row) use ($modoJrz, $ventasPorSede, $sedes) {
            $stocks = (isset($row['stocks']) && is_array($row['stocks'])) ? $row['stocks'] : [];
            $row['existencia_global'] = array_sum($stocks);

            if ($modoJrz) {
                $vendidas = [];
                foreach ($sedes as $sedeCol) {
                    $sedeKey = mb_strtoupper(trim((string) $sedeCol), 'UTF-8');
                    $vendidas[$sedeKey] = $this->unidadesVendidasParaCodigo(
                        $ventasPorSede,
                        (string) ($row['cod_centro'] ?? ''),
                        $sedeKey
                    );
                }
                $row['vendidas_por_sede'] = $vendidas;
            }

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
            'proveedores' => $modoJrz ? $this->proveedores() : collect(),
        ]);
    }

    /**
     * Excel por proveedor: unidades vendidas de cada producto en cada sede durante los días elegidos,
     * el total vendido y la existencia total (todas las sedes), con una fila de totales al final.
     */
    public function reporteProveedor(Request $request): Response
    {
        ini_set('memory_limit', '512M');
        $data = $request->validate([
            'proveedor' => ['required', 'string', 'max:255'],
            'dias' => ['required', 'integer', 'min:1', 'max:730'],
        ], [
            'proveedor.required' => 'Elige el proveedor.',
        ]);
        $proveedor = trim($data['proveedor']);
        $dias = (int) $data['dias'];
        $desde = now()->subDays($dias)->toDateString();
        $sedes = array_values(array_unique(array_map(
            fn ($s) => mb_strtoupper(trim((string) $s), 'UTF-8'),
            config('inventario.sedes_stock', [])
        )));

        $productos = DB::table('productos')
            ->whereRaw('UPPER(TRIM(proveedor)) = ?', [mb_strtoupper($proveedor, 'UTF-8')])
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'categoria', 'subcategoria']);
        $ids = $productos->pluck('id')->all();

        $ventas = [];
        $existencias = [];
        if ($ids !== []) {
            foreach (array_chunk($ids, 1000) as $lote) {
                $q = DB::table('ventas_detalle')
                    ->whereIn('producto_id', $lote)
                    ->whereDate('fecha', '>=', $desde);
                if (Schema::hasColumn('ventas_detalle', 'anulado')) {
                    $q->where(fn ($w) => $w->where('anulado', false)->orWhereNull('anulado'));
                }
                $filas = $q->selectRaw('producto_id, UPPER(TRIM(sede)) as sede')
                    ->selectRaw("SUM(CASE WHEN UPPER(TRIM(tipo_documento)) = 'DEV' THEN -ABS(cantidad) ELSE ABS(cantidad) END) as unidades")
                    ->groupBy('producto_id', DB::raw('UPPER(TRIM(sede))'))
                    ->get();
                foreach ($filas as $f) {
                    $ventas[(int) $f->producto_id][(string) $f->sede] = (float) $f->unidades;
                }

                $stock = DB::table('stock_actual')->whereIn('producto_id', $lote)
                    ->selectRaw('producto_id, SUM(existencia) as existencia')
                    ->groupBy('producto_id')
                    ->pluck('existencia', 'producto_id');
                foreach ($stock as $pid => $existencia) {
                    $existencias[(int) $pid] = (float) $existencia;
                }
            }
        }

        $nombreSede = fn (string $s) => config('inventario.display.'.$s, $s);
        $encabezado = array_merge(
            ['Código', 'Producto', 'Categoría', 'Subcategoría'],
            array_map(fn ($s) => 'Vendido '.$nombreSede($s), $sedes),
            ['Total vendido', 'Existencia total']
        );
        $rows = [
            ['Proveedor', $proveedor],
            ['Ventas de los últimos', $dias.' días (desde '.\Carbon\Carbon::parse($desde)->format('d/m/Y').')'],
            ['Generado', now()->format('d/m/Y H:i')],
            [],
            $encabezado,
        ];
        $totalesSede = array_fill_keys($sedes, 0.0);
        $totalVendido = 0.0;
        $totalExistencia = 0.0;
        foreach ($productos as $p) {
            $fila = [\App\Support\Sku::principal($p->codigo), $p->nombre, $p->categoria, $p->subcategoria];
            $vendidoProducto = 0.0;
            foreach ($sedes as $s) {
                $u = round($ventas[(int) $p->id][$s] ?? 0, 2);
                $fila[] = $u;
                $totalesSede[$s] += $u;
                $vendidoProducto += $u;
            }
            $existencia = round($existencias[(int) $p->id] ?? 0, 2);
            $fila[] = round($vendidoProducto, 2);
            $fila[] = $existencia;
            $totalVendido += $vendidoProducto;
            $totalExistencia += $existencia;
            $rows[] = $fila;
        }
        $rows[] = array_merge(
            ['TOTAL', $productos->count().' productos', '', ''],
            array_map(fn ($s) => round($totalesSede[$s], 2), $sedes),
            [round($totalVendido, 2), round($totalExistencia, 2)]
        );

        $xlsx = \App\Support\SimpleXlsxWriter::toString(['Ventas por sede' => $rows]);
        $nombre = 'ventas_por_sede_'.(preg_replace('/[^A-Za-z0-9_-]+/', '_', $proveedor) ?: 'proveedor').'_'.$dias.'d.xlsx';

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    private function proveedores()
    {
        return \Illuminate\Support\Facades\Cache::remember('vendedor_jrz_proveedores', 3600, fn () => DB::table('productos')
            ->whereNotNull('proveedor')->where('proveedor', '!=', '')
            ->distinct()->orderBy('proveedor')->pluck('proveedor'));
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

    /**
     * Resuelve unidades vendidas para un código de catálogo.
     * Códigos compuestos ("EAN / SKU / REF") se cruzan también con el EAN puro,
     * que es como suelen quedar registradas las ventas en ventas_detalle.
     *
     * @param  array<string, array<string, float>>  $ventasPorSede
     */
    private function unidadesVendidasParaCodigo(array $ventasPorSede, string $codigoProducto, string $sede): float
    {
        $codigo = mb_strtoupper(trim($codigoProducto), 'UTF-8');
        $sede = mb_strtoupper(trim($sede), 'UTF-8');
        if ($codigo === '' || $sede === '') {
            return 0.0;
        }

        $total = (float) ($ventasPorSede[$codigo][$sede] ?? 0);

        if (str_contains($codigo, '/')) {
            $primary = mb_strtoupper(trim(explode('/', $codigo, 2)[0]), 'UTF-8');
            if ($primary !== '' && $primary !== $codigo) {
                $total += (float) ($ventasPorSede[$primary][$sede] ?? 0);
            }
        }

        return round($total, 2);
    }
}
