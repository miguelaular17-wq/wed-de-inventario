<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ExistenciasController extends Controller
{
    /** Orden de columnas; Nunes y Movistar también tienen stock. */
    private const SEDES_BASE = ['JRZ', 'DORAL', 'VIRTUDES', 'ZAMORA', 'CENTRO', 'SAMBIL', 'NUNES', 'MOVISTAR'];

    public function index(Request $request): View
    {
        ini_set('memory_limit', '512M');

        $filters = [
            'q'            => trim((string) $request->query('q', '')),
            'categoria'    => (string) $request->query('categoria', 'Ninguno'),
            'subcategoria' => (string) $request->query('subcategoria', 'Ninguno'),
            'proveedor'    => (string) $request->query('proveedor', 'Ninguno'),
        ];

        $page    = (int) $request->query('page', 1);
        $perPage = 75;

        $whereClauses = ['p.activo = true'];
        $bindings     = [];

        if ($filters['categoria'] !== 'Ninguno') {
            $whereClauses[] = 'p.categoria = :categoria';
            $bindings['categoria'] = $filters['categoria'];
        }
        if ($filters['subcategoria'] !== 'Ninguno') {
            $whereClauses[] = 'p.subcategoria = :subcategoria';
            $bindings['subcategoria'] = $filters['subcategoria'];
        }
        if ($filters['proveedor'] !== 'Ninguno') {
            $whereClauses[] = 'p.proveedor = :proveedor';
            $bindings['proveedor'] = $filters['proveedor'];
        }
        if ($filters['q'] !== '') {
            $whereClauses[] = '(LOWER(p.codigo) LIKE :search OR LOWER(p.nombre) LIKE :search)';
            $bindings['search'] = '%' . mb_strtolower($filters['q']) . '%';
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countSql = "SELECT COUNT(*) as total_count FROM inventario_v2.productos p WHERE {$whereSql}";
        $totalRow  = DB::connection('pgsql')->selectOne($countSql, $bindings);
        $total     = $totalRow ? (int) $totalRow->total_count : 0;

        $offset   = ($page - 1) * $perPage;
        $itemsSql = "
            SELECT p.id, p.codigo, p.nombre, p.categoria, p.subcategoria, p.proveedor,
                   p.precio_unidad, p.precio_mayor, p.url_imagen
            FROM inventario_v2.productos p
            WHERE {$whereSql}
            ORDER BY p.nombre
            LIMIT {$perPage} OFFSET {$offset}
        ";
        $dbItems = DB::connection('pgsql')->select($itemsSql, $bindings);

        $rows = collect();
        $sedesPagina = self::SEDES_BASE;
        if (count($dbItems) > 0) {
            $productIds = array_map(fn($i) => (int) $i->id, $dbItems);

            $dbStocks = DB::connection('pgsql')
                ->table('inventario_v2.stock_actual')
                ->whereIn('producto_id', $productIds)
                ->get(['producto_id', 'sede', 'existencia']);

            $stockMap = [];
            foreach ($dbStocks as $row) {
                $sede = strtoupper(trim((string) $row->sede));
                if ($sede === '') {
                    continue;
                }
                $stockMap[(int) $row->producto_id][$sede] = (int) $row->existencia;
            }

            foreach ($stockMap as $porSede) {
                foreach (array_keys($porSede) as $sede) {
                    if (! in_array($sede, $sedesPagina, true)) {
                        $sedesPagina[] = $sede;
                    }
                }
            }

            $dbVentas = DB::connection('pgsql')
                ->table('inventario_v2.ventas_historicas')
                ->whereIn('producto_id', $productIds)
                ->get(['producto_id', 'sede', 'venta_promedio', 'ventas_60d', 'ultima_venta']);

            $ventasMap = [];
            foreach ($dbVentas as $row) {
                $sede = strtoupper(trim((string) $row->sede));
                $ventasMap[(int) $row->producto_id][$sede] = [
                    'venta_15d'  => round((float) $row->venta_promedio * 15, 1),
                    'ventas_60d' => (float) $row->ventas_60d,
                    'ultima'     => $row->ultima_venta,
                ];
            }

            foreach ($dbItems as $item) {
                $pid       = (int) $item->id;
                $sedeStock = $stockMap[$pid] ?? [];
                $sedeVenta = $ventasMap[$pid] ?? [];

                $stocks    = [];
                $ventas15d = [];
                foreach ($sedesPagina as $s) {
                    $stocks[$s]    = (int) ($sedeStock[$s] ?? 0);
                    $sv            = $sedeVenta[$s] ?? null;
                    $ventas15d[$s] = $sv ? $sv['venta_15d'] : 0;
                }

                $rows->push([
                    'id'            => $pid,
                    'codigo'        => $item->codigo,
                    'nombre'        => $item->nombre,
                    'categoria'     => $item->categoria,
                    'subcategoria'  => $item->subcategoria,
                    'proveedor'     => $item->proveedor,
                    'precio_unidad' => (float) ($item->precio_unidad ?? 0),
                    'precio_mayor'  => (float) ($item->precio_mayor ?? 0),
                    'url_imagen'    => $item->url_imagen,
                    'global_stock'  => array_sum($stocks),
                    'stocks'        => $stocks,
                    'ventas_15d'    => $ventas15d,
                ]);
            }
        }

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $rows,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $categorias = Cache::remember('existencias_categorias', 3600, function () {
            return DB::connection('pgsql')
                ->table('inventario_v2.productos')
                ->where('activo', true)
                ->whereNotNull('categoria')
                ->where('categoria', '!=', '')
                ->distinct()
                ->orderBy('categoria')
                ->pluck('categoria')
                ->all();
        });

        $subcategorias = [];
        if ($filters['categoria'] !== 'Ninguno') {
            $subcategorias = DB::connection('pgsql')
                ->table('inventario_v2.productos')
                ->where('activo', true)
                ->where('categoria', $filters['categoria'])
                ->whereNotNull('subcategoria')
                ->where('subcategoria', '!=', '')
                ->distinct()
                ->orderBy('subcategoria')
                ->pluck('subcategoria')
                ->all();
        }

        $proveedores = Cache::remember('existencias_proveedores', 3600, function () {
            return DB::connection('pgsql')
                ->table('inventario_v2.productos')
                ->where('activo', true)
                ->whereNotNull('proveedor')
                ->where('proveedor', '!=', '')
                ->distinct()
                ->orderBy('proveedor')
                ->pluck('proveedor')
                ->all();
        });

        return view('existencias.existencias', [
            'rows'          => $paginator,
            'filters'       => $filters,
            'categorias'    => $categorias,
            'subcategorias' => $subcategorias,
            'proveedores'   => $proveedores,
            'sedes'         => $sedesPagina,
        ]);
    }

    public function getSubcategorias(Request $request): JsonResponse
    {
        $categoria = (string) $request->query('categoria', '');
        if ($categoria === '' || $categoria === 'Ninguno') {
            return response()->json([]);
        }

        $subs = DB::connection('pgsql')
            ->table('inventario_v2.productos')
            ->where('activo', true)
            ->where('categoria', $categoria)
            ->whereNotNull('subcategoria')
            ->where('subcategoria', '!=', '')
            ->distinct()
            ->orderBy('subcategoria')
            ->pluck('subcategoria')
            ->all();

        return response()->json($subs);
    }
}
