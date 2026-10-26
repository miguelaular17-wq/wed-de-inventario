<?php

namespace App\Http\Controllers;

use App\Support\SimpleXlsxWriter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ReportesController extends Controller
{
    private const SEDES = ['JRZ', 'DORAL', 'VIRTUDES', 'ZAMORA', 'CENTRO', 'SAMBIL', 'TECNOHOUSE'];

    public function index(): View
    {
        return view('reportes.index', [
            'reportes' => [
                [
                    'titulo' => 'Existencias por sede',
                    'descripcion' => 'Código, producto, categoría, subcategoría, existencia en cada sede (JRZ, Doral, Virtudes, Zamora, Centro, Sambil, Tecno House) y total.',
                    'url' => route('reportes.existencias_sede'),
                    'excel' => route('reportes.existencias_sede.excel'),
                ],
                [
                    'titulo' => 'Ventas por proveedor',
                    'descripcion' => 'Elige proveedor y rango de fechas: unidades, ítems y monto a precio de venta, más inventario consolidado que queda.',
                    'url' => route('reportes.proveedor'),
                    'excel' => null,
                ],
                [
                    'titulo' => 'Análisis de categorías',
                    'descripcion' => 'Por sede: marca subcategorías en verde; al completarlas, la categoría también queda en verde.',
                    'url' => route('analisis_categorias.index'),
                    'excel' => null,
                ],
            ],
        ]);
    }

    public function existenciasSede(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 75;

        $bindings = [];
        $where = '1=1';
        if ($q !== '') {
            $where = '(LOWER(p.codigo) LIKE :q OR LOWER(p.nombre) LIKE :q)';
            $bindings['q'] = '%'.mb_strtolower($q).'%';
        }

        $countRow = DB::connection('pgsql')->selectOne(
            "SELECT COUNT(*) AS total FROM inventario_v2.productos p WHERE {$where}",
            $bindings
        );
        $total = (int) ($countRow->total ?? 0);
        $offset = ($page - 1) * $perPage;

        $filas = collect(DB::connection('pgsql')->select(
            $this->sqlExistenciasPorSede($where)." LIMIT {$perPage} OFFSET {$offset}",
            $bindings
        ));

        return view('reportes.existencias_sede', [
            'filas' => $filas,
            'sedes' => self::SEDES,
            'q' => $q,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'lastPage' => max(1, (int) ceil($total / $perPage)),
        ]);
    }

    public function existenciasSedeExcel(Request $request): Response
    {
        ini_set('memory_limit', '512M');

        $q = trim((string) $request->query('q', ''));
        $bindings = [];
        $where = '1=1';
        if ($q !== '') {
            $where = '(LOWER(p.codigo) LIKE :q OR LOWER(p.nombre) LIKE :q)';
            $bindings['q'] = '%'.mb_strtolower($q).'%';
        }

        $rows = [[
            'Código',
            'Producto',
            'Categoría',
            'Subcategoría',
            'JRZ',
            'DORAL',
            'VIRTUDES',
            'ZAMORA',
            'CENTRO',
            'SAMBIL',
            'TECNO HOUSE',
            'Total',
        ]];

        foreach (DB::connection('pgsql')->select($this->sqlExistenciasPorSede($where), $bindings) as $fila) {
            $rows[] = [
                $fila->codigo,
                $fila->producto,
                $fila->categoria,
                $fila->subcategoria,
                (int) $fila->jrz,
                (int) $fila->doral,
                (int) $fila->virtudes,
                (int) $fila->zamora,
                (int) $fila->centro,
                (int) $fila->sambil,
                (int) $fila->tecnohouse,
                (int) $fila->total,
            ];
        }

        $xlsx = SimpleXlsxWriter::toString(['Existencias por sede' => $rows]);
        $filename = 'existencias_por_sede_'.now()->format('Y-m-d_His').'.xlsx';

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function proveedor(Request $request): View
    {
        $proveedores = $this->listaProveedores();
        $filtros = $this->filtrosProveedor($request);
        $filas = collect();
        $resumen = null;

        if ($filtros['proveedor'] !== '') {
            $filas = $this->datosProveedor($filtros['proveedor'], $filtros['desde'], $filtros['hasta']);
            $resumen = $this->resumenProveedor($filas);
        }

        return view('reportes.proveedor', [
            'proveedores' => $proveedores,
            'filtros' => $filtros,
            'filas' => $filas,
            'resumen' => $resumen,
        ]);
    }

    public function proveedorExcel(Request $request): Response
    {
        ini_set('memory_limit', '512M');

        $filtros = $this->filtrosProveedor($request);
        if ($filtros['proveedor'] === '') {
            abort(422, 'Selecciona un proveedor.');
        }

        $filas = $this->datosProveedor($filtros['proveedor'], $filtros['desde'], $filtros['hasta']);
        $resumen = $this->resumenProveedor($filas);

        $rows = [
            ['Proveedor', $filtros['proveedor']],
            ['Desde', $filtros['desde']],
            ['Hasta', $filtros['hasta']],
            ['Unidades vendidas', $resumen['unidades']],
            ['Ítems (SKUs) con venta', $resumen['items']],
            ['Monto venta', $resumen['monto']],
            ['Inventario consolidado', $resumen['inventario']],
            [],
            [
                'Código',
                'Producto',
                'Categoría',
                'Subcategoría',
                'Unidades vendidas',
                'Monto venta',
                'Inventario consolidado',
            ],
        ];

        foreach ($filas as $fila) {
            $rows[] = [
                $fila->codigo,
                $fila->producto,
                $fila->categoria,
                $fila->subcategoria,
                (int) $fila->unidades_vendidas,
                round((float) $fila->monto_venta, 2),
                (int) $fila->inventario,
            ];
        }

        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $filtros['proveedor']) ?: 'proveedor';
        $xlsx = SimpleXlsxWriter::toString(['Ventas proveedor' => $rows]);
        $filename = 'ventas_proveedor_'.$safeName.'_'.$filtros['desde'].'_'.$filtros['hasta'].'.xlsx';

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return array{proveedor: string, desde: string, hasta: string}
     */
    private function filtrosProveedor(Request $request): array
    {
        $desde = (string) $request->query('desde', now()->startOfMonth()->toDateString());
        $hasta = (string) $request->query('hasta', now()->toDateString());
        if ($desde !== '' && $hasta !== '' && $hasta < $desde) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [
            'proveedor' => trim((string) $request->query('proveedor', '')),
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    private function listaProveedores(): Collection
    {
        return Cache::remember('reportes_proveedores', 3600, function () {
            return DB::connection('pgsql')
                ->table('inventario_v2.productos')
                ->whereNotNull('proveedor')
                ->where('proveedor', '!=', '')
                ->distinct()
                ->orderBy('proveedor')
                ->pluck('proveedor');
        });
    }

    private function datosProveedor(string $proveedor, string $desde, string $hasta): Collection
    {
        $anuladoSql = Schema::connection('pgsql')->hasColumn('ventas_detalle', 'anulado')
            ? 'AND COALESCE(vd.anulado, false) = false'
            : '';

        $sql = "
            SELECT
                p.codigo,
                p.nombre AS producto,
                p.categoria,
                p.subcategoria,
                COALESCE(v.unidades_vendidas, 0) AS unidades_vendidas,
                COALESCE(v.monto_venta, 0) AS monto_venta,
                COALESCE(s.inventario, 0) AS inventario
            FROM inventario_v2.productos p
            LEFT JOIN (
                SELECT
                    vd.producto_id,
                    SUM(
                        CASE
                            WHEN UPPER(TRIM(vd.tipo_documento)) = 'DEV' THEN -ABS(vd.cantidad)
                            ELSE ABS(vd.cantidad)
                        END
                    ) AS unidades_vendidas,
                    SUM(
                        CASE
                            WHEN UPPER(TRIM(vd.tipo_documento)) = 'DEV'
                                THEN -ABS(vd.cantidad * COALESCE(vd.precio_venta, 0))
                            ELSE ABS(vd.cantidad * COALESCE(vd.precio_venta, 0))
                        END
                    ) AS monto_venta
                FROM inventario_v2.ventas_detalle vd
                WHERE vd.fecha >= :desde
                  AND vd.fecha <= :hasta
                  AND vd.producto_id IS NOT NULL
                  {$anuladoSql}
                GROUP BY vd.producto_id
            ) v ON v.producto_id = p.id
            LEFT JOIN (
                SELECT producto_id, SUM(existencia) AS inventario
                FROM inventario_v2.stock_actual
                GROUP BY producto_id
            ) s ON s.producto_id = p.id
            WHERE UPPER(TRIM(p.proveedor)) = UPPER(TRIM(:proveedor))
              AND (
                    COALESCE(v.unidades_vendidas, 0) <> 0
                    OR COALESCE(v.monto_venta, 0) <> 0
                    OR COALESCE(s.inventario, 0) <> 0
              )
            ORDER BY COALESCE(v.monto_venta, 0) DESC, p.codigo
        ";

        return collect(DB::connection('pgsql')->select($sql, [
            'desde' => $desde,
            'hasta' => $hasta,
            'proveedor' => $proveedor,
        ]));
    }

    /**
     * @param  Collection<int, object>  $filas
     * @return array{unidades: int, items: int, monto: float, inventario: int}
     */
    private function resumenProveedor(Collection $filas): array
    {
        $conVenta = $filas->filter(fn ($f) => (int) $f->unidades_vendidas !== 0 || abs((float) $f->monto_venta) > 0.004);

        return [
            'unidades' => (int) $filas->sum(fn ($f) => (int) $f->unidades_vendidas),
            'items' => $conVenta->count(),
            'monto' => round((float) $filas->sum(fn ($f) => (float) $f->monto_venta), 2),
            'inventario' => (int) $filas->sum(fn ($f) => (int) $f->inventario),
        ];
    }

    private function sqlExistenciasPorSede(string $where): string
    {
        return "
            SELECT
                p.codigo,
                p.nombre AS producto,
                p.categoria,
                p.subcategoria,
                COALESCE(SUM(sa.existencia) FILTER (WHERE sa.sede = 'JRZ'), 0) AS jrz,
                COALESCE(SUM(sa.existencia) FILTER (WHERE sa.sede = 'DORAL'), 0) AS doral,
                COALESCE(SUM(sa.existencia) FILTER (WHERE sa.sede = 'VIRTUDES'), 0) AS virtudes,
                COALESCE(SUM(sa.existencia) FILTER (WHERE sa.sede = 'ZAMORA'), 0) AS zamora,
                COALESCE(SUM(sa.existencia) FILTER (WHERE sa.sede = 'CENTRO'), 0) AS centro,
                COALESCE(SUM(sa.existencia) FILTER (WHERE sa.sede = 'SAMBIL'), 0) AS sambil,
                COALESCE(SUM(sa.existencia) FILTER (WHERE sa.sede = 'TECNOHOUSE'), 0) AS tecnohouse,
                COALESCE(SUM(sa.existencia), 0) AS total
            FROM inventario_v2.productos p
            LEFT JOIN inventario_v2.stock_actual sa ON sa.producto_id = p.id
            WHERE {$where}
            GROUP BY p.id, p.codigo, p.nombre, p.categoria, p.subcategoria
            ORDER BY p.codigo
        ";
    }
}
