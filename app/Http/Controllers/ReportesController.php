<?php

namespace App\Http\Controllers;

use App\Support\SimpleXlsxWriter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportesController extends Controller
{
    private const SEDES = ['JRZ', 'DORAL', 'VIRTUDES', 'ZAMORA', 'CENTRO', 'SAMBIL'];

    public function index(): View
    {
        return view('reportes.index', [
            'reportes' => [
                [
                    'titulo' => 'Existencias por sede',
                    'descripcion' => 'Código, producto, existencia en cada sede (JRZ, Doral, Virtudes, Zamora, Centro, Sambil) y total.',
                    'url' => route('reportes.existencias_sede'),
                    'excel' => route('reportes.existencias_sede.excel'),
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
            'JRZ',
            'DORAL',
            'VIRTUDES',
            'ZAMORA',
            'CENTRO',
            'SAMBIL',
            'Total',
        ]];

        foreach (DB::connection('pgsql')->select($this->sqlExistenciasPorSede($where), $bindings) as $fila) {
            $rows[] = [
                $fila->codigo,
                $fila->producto,
                (int) $fila->jrz,
                (int) $fila->doral,
                (int) $fila->virtudes,
                (int) $fila->zamora,
                (int) $fila->centro,
                (int) $fila->sambil,
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

    private function sqlExistenciasPorSede(string $where): string
    {
        return "
            SELECT
                p.codigo,
                p.nombre AS producto,
                COALESCE(SUM(sa.existencia) FILTER (WHERE sa.sede = 'JRZ'), 0) AS jrz,
                COALESCE(SUM(sa.existencia) FILTER (WHERE sa.sede = 'DORAL'), 0) AS doral,
                COALESCE(SUM(sa.existencia) FILTER (WHERE sa.sede = 'VIRTUDES'), 0) AS virtudes,
                COALESCE(SUM(sa.existencia) FILTER (WHERE sa.sede = 'ZAMORA'), 0) AS zamora,
                COALESCE(SUM(sa.existencia) FILTER (WHERE sa.sede = 'CENTRO'), 0) AS centro,
                COALESCE(SUM(sa.existencia) FILTER (WHERE sa.sede = 'SAMBIL'), 0) AS sambil,
                COALESCE(SUM(sa.existencia), 0) AS total
            FROM inventario_v2.productos p
            LEFT JOIN inventario_v2.stock_actual sa ON sa.producto_id = p.id
            WHERE {$where}
            GROUP BY p.id, p.codigo, p.nombre
            ORDER BY p.codigo
        ";
    }
}
