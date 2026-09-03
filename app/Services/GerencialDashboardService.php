<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GerencialDashboardService
{
    /**
     * @return list<string>
     */
    public function sedesVentas(): array
    {
        return array_values(config('inventario.sedes_gerencial', [
            'DORAL', 'VIRTUDES', 'ZAMORA', 'CENTRO', 'SAMBIL', 'NUNES', 'JRZ', 'MOVISTAR',
        ]));
    }

    /**
     * @return array{inicio:Carbon,fin:Carbon,anterior_inicio:Carbon,anterior_fin:Carbon,etiqueta:string,preset:string}
     */
    public function resolverPeriodo(?string $preset, ?string $desde, ?string $hasta): array
    {
        $hoy = Carbon::now('America/Caracas')->startOfDay();
        $preset = $preset ?: 'mes';

        if ($preset === 'quincena') {
            $q = app(Nomina\SalaryAdvanceService::class)->quincenaDe($hoy);
            $inicio = $q['inicio']->copy();
            $fin = $q['fin']->copy();
        } elseif ($preset === 'mes_anterior') {
            $inicio = $hoy->copy()->subMonthNoOverflow()->startOfMonth();
            $fin = $hoy->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay();
        } elseif ($preset === 'personalizado' && $desde && $hasta) {
            $inicio = Carbon::parse($desde)->startOfDay();
            $fin = Carbon::parse($hasta)->startOfDay();
            if ($fin->lt($inicio)) {
                [$inicio, $fin] = [$fin, $inicio];
            }
        } else {
            $preset = 'mes';
            $inicio = $hoy->copy()->startOfMonth();
            $fin = $hoy->copy();
        }

        $dias = $inicio->diffInDays($fin);
        $anteriorFin = $inicio->copy()->subDay();
        $anteriorInicio = $anteriorFin->copy()->subDays($dias);

        return [
            'inicio' => $inicio,
            'fin' => $fin,
            'anterior_inicio' => $anteriorInicio,
            'anterior_fin' => $anteriorFin,
            'etiqueta' => $inicio->format('d/m/Y').' al '.$fin->format('d/m/Y'),
            'preset' => $preset,
        ];
    }

    /**
     * @param  array{inicio:Carbon,fin:Carbon,anterior_inicio:Carbon,anterior_fin:Carbon}  $periodo
     * @return array<string, mixed>
     */
    public function resumen(array $periodo, ?string $sede, ?string $categoria, ?string $vendedor, ?string $producto, string $ranking = 'usd'): array
    {
        $sedes = $this->sedesVentas();
        if ($sede && $sede !== 'todas') {
            $sede = strtoupper(trim($sede));
            $sedes = in_array($sede, $sedes, true) ? [$sede] : $sedes;
        }

        $usaLineas = filled($categoria) || filled($vendedor) || filled($producto);
        $actual = $this->kpisPorSede($periodo['inicio'], $periodo['fin'], $sedes, $usaLineas, $categoria, $vendedor, $producto);
        $anterior = $this->kpisPorSede($periodo['anterior_inicio'], $periodo['anterior_fin'], $sedes, $usaLineas, $categoria, $vendedor, $producto);
        $inventario = $this->inventarioPorSede($sedes);
        $ajustes = $this->ajustesPorSede($periodo['inicio'], $periodo['fin'], $sedes);

        $filas = [];
        foreach ($sedes as $codigo) {
            $a = $actual[$codigo] ?? $this->kpiVacio($codigo);
            $b = $anterior[$codigo] ?? $this->kpiVacio($codigo);
            $inv = $inventario[$codigo] ?? ['unidades' => 0.0, 'valor' => 0.0];
            $aju = $ajustes[$codigo] ?? ['unidades' => 0.0, 'valor' => 0.0];
            $ventaNeta = (float) $a['ventas_usd'];
            $ventasBrutas = (float) ($a['ventas_brutas'] ?? ($ventaNeta + (float) $a['devoluciones_usd']));
            $utilidad = (float) $a['margen_usd'];
            $filas[] = $a + [
                'ventas_brutas' => round($ventasBrutas, 2),
                'venta_neta' => round($ventaNeta, 2),
                'utilidad' => round($utilidad, 2),
                'margen_pct' => $ventaNeta > 0 ? round($utilidad / $ventaNeta * 100, 1) : 0.0,
                'inventario_unidades' => $inv['unidades'],
                'inventario_valor' => $inv['valor'],
                'ajustes_unidades' => $aju['unidades'],
                'ajustes_valor' => $aju['valor'],
                'delta_ventas_usd' => $this->delta($a['ventas_usd'], $b['ventas_usd']),
                'delta_unidades' => $this->delta($a['unidades'], $b['unidades']),
                'anterior_ventas_usd' => $b['ventas_usd'],
            ];
        }

        return [
            'por_sede' => $filas,
            'total' => $this->sumarFilas($filas),
            'usa_lineas' => $usaLineas,
            'tops' => $usaLineas || Schema::hasTable('ventas_detalle')
                ? $this->tops($periodo['inicio'], $periodo['fin'], $sedes, $categoria, $vendedor, $producto, $ranking)
                : ['productos' => [], 'vendedores' => [], 'categorias' => []],
            'diario' => $this->diario($periodo['inicio'], $periodo['fin'], $sedes, $usaLineas, $categoria, $vendedor, $producto),
        ];
    }

    public function catalogos(): array
    {
        $categorias = collect();
        $vendedores = collect();
        if (Schema::hasTable('productos')) {
            $categorias = DB::table('productos')
                ->whereNotNull('categoria')
                ->where('categoria', '!=', '')
                ->distinct()
                ->orderBy('categoria')
                ->pluck('categoria');
        }
        if (Schema::hasTable('ventas_detalle')) {
            $vendedores = DB::table('ventas_detalle')
                ->whereNotNull('vendedor')
                ->where('vendedor', '!=', '')
                ->distinct()
                ->orderBy('vendedor')
                ->limit(400)
                ->pluck('vendedor');
        }

        return compact('categorias', 'vendedores');
    }

    /**
     * @param  list<string>  $sedes
     * @return array<string, array<string, float|int|string>>
     */
    public function kpisPorSede(
        Carbon $inicio,
        Carbon $fin,
        array $sedes,
        bool $usaLineas,
        ?string $categoria,
        ?string $vendedor,
        ?string $producto
    ): array {
        $base = [];
        foreach ($sedes as $sede) {
            $base[$sede] = $this->kpiVacio($sede);
        }

        if ($usaLineas || ! Schema::hasTable('ventas_documentos')) {
            return $this->kpisDesdeLineas($inicio, $fin, $sedes, $base, $categoria, $vendedor, $producto);
        }

        $docs = DB::table('ventas_documentos')
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->whereIn(DB::raw('UPPER(TRIM(sede))'), $sedes)
            ->whereRaw("LOWER(TRIM(COALESCE(estado, ''))) = 'registrado'")
            ->selectRaw('UPPER(TRIM(sede)) as sede')
            ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='FAC' THEN 1 ELSE 0 END) as facturas")
            ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN 1 ELSE 0 END) as devoluciones")
            ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='FAC' THEN ABS(total_neto_usd) ELSE 0 END) as ventas_brutas")
            ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN ABS(total_neto_usd) ELSE 0 END) as devoluciones_usd")
            ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN ABS(total_neto_bs) ELSE 0 END) as devoluciones_bs")
            ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -ABS(total_neto_usd) ELSE ABS(total_neto_usd) END) as ventas_usd")
            ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -ABS(total_neto_bs) ELSE ABS(total_neto_bs) END) as ventas_bs")
            ->groupBy(DB::raw('UPPER(TRIM(sede))'))
            ->get();

        foreach ($docs as $row) {
            $sede = (string) $row->sede;
            if (! isset($base[$sede])) {
                continue;
            }
            $base[$sede]['facturas'] = (int) $row->facturas;
            $base[$sede]['devoluciones'] = (int) $row->devoluciones;
            $base[$sede]['devoluciones_usd'] = round((float) $row->devoluciones_usd, 2);
            $base[$sede]['devoluciones_bs'] = round((float) $row->devoluciones_bs, 2);
            $base[$sede]['ventas_brutas'] = round((float) $row->ventas_brutas, 2);
            $base[$sede]['ventas_usd'] = round((float) $row->ventas_usd, 2);
            $base[$sede]['ventas_bs'] = round((float) $row->ventas_bs, 2);
        }

        $lineas = $this->kpisDesdeLineas($inicio, $fin, $sedes, [], null, null, null);
        foreach ($lineas as $sede => $linea) {
            if (! isset($base[$sede])) {
                continue;
            }
            $base[$sede]['unidades'] = $linea['unidades'];
            $base[$sede]['margen_usd'] = $linea['margen_usd'];
            $base[$sede]['productos'] = $linea['productos'];
        }

        return $base;
    }

    /**
     * @param  list<string>  $sedes
     * @param  array<string, array<string, float|int|string>>  $base
     * @return array<string, array<string, float|int|string>>
     */
    private function kpisDesdeLineas(
        Carbon $inicio,
        Carbon $fin,
        array $sedes,
        array $base,
        ?string $categoria,
        ?string $vendedor,
        ?string $producto
    ): array {
        foreach ($sedes as $sede) {
            $base[$sede] = $base[$sede] ?? $this->kpiVacio($sede);
        }

        if (! Schema::hasTable('ventas_detalle')) {
            return $base;
        }

        $query = $this->queryLineas($inicio, $fin, $sedes, $categoria, $vendedor, $producto);
        $query->selectRaw('UPPER(TRIM(vd.sede)) as sede')
            ->selectRaw("COUNT(DISTINCT CASE WHEN UPPER(vd.tipo_documento)='FAC' THEN vd.numero_documento END) as facturas")
            ->selectRaw("COUNT(DISTINCT CASE WHEN UPPER(vd.tipo_documento)='DEV' THEN vd.numero_documento END) as devoluciones")
            ->selectRaw("SUM(CASE WHEN UPPER(vd.tipo_documento)='DEV' THEN -ABS(vd.cantidad) ELSE ABS(vd.cantidad) END) as unidades")
            ->selectRaw($this->sqlImporteFac().' as ventas_brutas')
            ->selectRaw($this->sqlImporte('venta').' as ventas_usd')
            ->selectRaw($this->sqlImporte('neto').' as ventas_neto')
            ->selectRaw($this->sqlImporte('costo').' as costo')
            ->selectRaw($this->sqlImporteDev().' as devoluciones_usd')
            ->selectRaw($this->sqlProductosDistintos().' as productos')
            ->groupBy(DB::raw('UPPER(TRIM(vd.sede))'));

        foreach ($query->get() as $row) {
            $sede = (string) $row->sede;
            if (! isset($base[$sede])) {
                continue;
            }
            $ventas = round((float) ($row->ventas_neto ?: $row->ventas_usd), 2);
            $base[$sede]['facturas'] = (int) $row->facturas;
            $base[$sede]['devoluciones'] = (int) $row->devoluciones;
            $base[$sede]['devoluciones_usd'] = round((float) $row->devoluciones_usd, 2);
            $base[$sede]['ventas_brutas'] = round((float) ($row->ventas_brutas ?? ($ventas + (float) $row->devoluciones_usd)), 2);
            $base[$sede]['ventas_usd'] = $ventas;
            $base[$sede]['unidades'] = round((float) $row->unidades, 2);
            $base[$sede]['margen_usd'] = round($ventas - (float) $row->costo, 2);
            $base[$sede]['productos'] = (int) $row->productos;
        }

        return $base;
    }

    /**
     * @param  list<string>  $sedes
     */
    public function queryLineas(
        Carbon $inicio,
        Carbon $fin,
        array $sedes,
        ?string $categoria,
        ?string $vendedor,
        ?string $producto
    ) {
        $query = DB::table('ventas_detalle as vd')
            ->whereBetween('vd.fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->whereIn(DB::raw('UPPER(TRIM(vd.sede))'), $sedes);

        if (Schema::hasColumn('ventas_detalle', 'anulado')) {
            $query->where('vd.anulado', false);
        }
        if ($vendedor) {
            $query->whereRaw('UPPER(TRIM(vd.vendedor)) = ?', [mb_strtoupper(trim($vendedor), 'UTF-8')]);
        }
        if ($producto) {
            $like = '%'.$producto.'%';
            $query->where(function ($q) use ($like) {
                $q->where('vd.codigo_producto', 'like', $like)
                    ->orWhere('vd.nombre_producto', 'like', $like);
            });
        }
        if ($categoria && Schema::hasTable('productos')) {
            $query->leftJoin('productos as p', 'p.id', '=', 'vd.producto_id')
                ->whereRaw('UPPER(TRIM(p.categoria)) = ?', [mb_strtoupper(trim($categoria), 'UTF-8')]);
        }

        return $query;
    }

    public function sqlImporte(string $tipo): string
    {
        $campo = match ($tipo) {
            'neto' => Schema::hasColumn('ventas_detalle', 'precio_neto')
                ? 'COALESCE(vd.precio_neto, vd.precio_venta)'
                : 'vd.precio_venta',
            'costo' => 'COALESCE(vd.costo_unitario, 0)',
            default => 'vd.precio_venta',
        };

        return "SUM(CASE WHEN UPPER(vd.tipo_documento)='DEV' THEN -ABS(vd.cantidad * {$campo}) ELSE ABS(vd.cantidad * {$campo}) END)";
    }

    public function sqlImporteFac(): string
    {
        $campo = Schema::hasColumn('ventas_detalle', 'precio_neto')
            ? 'COALESCE(vd.precio_neto, vd.precio_venta)'
            : 'vd.precio_venta';

        return "SUM(CASE WHEN UPPER(vd.tipo_documento)='FAC' THEN ABS(vd.cantidad * {$campo}) ELSE 0 END)";
    }

    private function sqlUnidades(): string
    {
        return "SUM(CASE WHEN UPPER(vd.tipo_documento)='DEV' THEN -ABS(vd.cantidad) ELSE ABS(vd.cantidad) END)";
    }

    public function sqlUnidadesNetas(): string
    {
        return $this->sqlUnidades();
    }

    public function sqlImporteDev(): string
    {
        $campo = Schema::hasColumn('ventas_detalle', 'precio_neto')
            ? 'COALESCE(vd.precio_neto, vd.precio_venta)'
            : 'vd.precio_venta';

        return "SUM(CASE WHEN UPPER(vd.tipo_documento)='DEV' THEN ABS(vd.cantidad * {$campo}) ELSE 0 END)";
    }

    public function sqlProductosDistintos(): string
    {
        return "COUNT(DISTINCT CASE WHEN UPPER(vd.tipo_documento)='FAC' THEN COALESCE(NULLIF(TRIM(vd.codigo_producto), ''), NULLIF(TRIM(vd.nombre_producto), ''), CAST(vd.producto_id AS TEXT)) END)";
    }

    /**
     * @param  list<string>  $sedes
     * @return array<string, array{unidades:float,valor:float}>
     */
    private function inventarioPorSede(array $sedes): array
    {
        $out = [];
        foreach ($sedes as $sede) {
            $out[$sede] = ['unidades' => 0.0, 'valor' => 0.0];
        }
        if (! Schema::hasTable('stock_actual')) {
            return $out;
        }

        $valorSql = Schema::hasTable('productos') && Schema::hasColumn('productos', 'costo_actual')
            ? 'SUM(sa.existencia * COALESCE(p.costo_actual, 0))'
            : 'SUM(0)';

        $query = DB::table('stock_actual as sa')
            ->whereIn(DB::raw('UPPER(TRIM(sa.sede))'), $sedes)
            ->selectRaw('UPPER(TRIM(sa.sede)) as sede')
            ->selectRaw('SUM(sa.existencia) as unidades')
            ->selectRaw($valorSql.' as valor')
            ->groupBy(DB::raw('UPPER(TRIM(sa.sede))'));

        if (Schema::hasTable('productos') && Schema::hasColumn('productos', 'costo_actual')) {
            $query->leftJoin('productos as p', 'p.id', '=', 'sa.producto_id');
        }

        foreach ($query->get() as $row) {
            $out[(string) $row->sede] = [
                'unidades' => round((float) $row->unidades, 2),
                'valor' => round((float) $row->valor, 2),
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $sedes
     * @return array<string, array{unidades:float,valor:float}>
     */
    private function ajustesPorSede(Carbon $inicio, Carbon $fin, array $sedes): array
    {
        $out = [];
        foreach ($sedes as $sede) {
            $out[$sede] = ['unidades' => 0.0, 'valor' => 0.0];
        }
        if (! Schema::hasTable('ajustes_inventario')) {
            return $out;
        }

        $rows = DB::table('ajustes_inventario')
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->whereIn(DB::raw('UPPER(TRIM(sede))'), $sedes)
            ->whereIn(DB::raw('UPPER(TRIM(tipo_movimiento))'), $this->tiposAjustePermitidos())
            ->selectRaw('UPPER(TRIM(sede)) as sede')
            ->selectRaw('SUM(cantidad) as unidades')
            ->selectRaw('SUM(cantidad * COALESCE(costo_unitario, 0)) as valor')
            ->groupBy(DB::raw('UPPER(TRIM(sede))'))
            ->get();

        foreach ($rows as $row) {
            $out[(string) $row->sede] = [
                'unidades' => round((float) $row->unidades, 2),
                'valor' => round((float) $row->valor, 2),
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $sedes
     * @return array{productos:list<array<string,mixed>>,vendedores:list<array<string,mixed>>,categorias:list<array<string,mixed>>}
     */
    private function tops(Carbon $inicio, Carbon $fin, array $sedes, ?string $categoria, ?string $vendedor, ?string $producto, string $ranking = 'usd'): array
    {
        if (! Schema::hasTable('ventas_detalle')) {
            return ['productos' => [], 'vendedores' => [], 'categorias' => []];
        }

        $base = $this->queryLineas($inicio, $fin, $sedes, $categoria, $vendedor, $producto);
        $importe = $this->sqlImporte('neto');
        $unidadesSql = $this->sqlUnidades();
        $clientesSql = "COUNT(DISTINCT CASE WHEN UPPER(vd.tipo_documento)='FAC' THEN vd.numero_documento END)";
        $utilidadSql = '('.$importe.') - ('.$this->sqlImporte('costo').')';
        $orden = match ($ranking) {
            'unidades' => 'unidades',
            'clientes' => 'clientes',
            'utilidad' => 'utilidad',
            default => 'ventas_usd',
        };
        $mapTop = fn ($r) => [
            'nombre' => $r->nombre,
            'unidades' => round((float) $r->unidades, 2),
            'ventas_usd' => round((float) $r->ventas_usd, 2),
            'clientes' => (int) $r->clientes,
            'utilidad' => round((float) $r->utilidad, 2),
        ];

        $productos = (clone $base)
            ->selectRaw('COALESCE(vd.nombre_producto, vd.codigo_producto, \'Sin nombre\') as nombre')
            ->selectRaw($unidadesSql.' as unidades')
            ->selectRaw($importe.' as ventas_usd')
            ->selectRaw($clientesSql.' as clientes')
            ->selectRaw($utilidadSql.' as utilidad')
            ->groupBy(DB::raw('COALESCE(vd.nombre_producto, vd.codigo_producto, \'Sin nombre\')'))
            ->orderByDesc($orden)
            ->limit(8)
            ->get()
            ->map($mapTop)
            ->all();

        $vendedores = (clone $base)
            ->selectRaw("COALESCE(NULLIF(TRIM(vd.vendedor), ''), 'Sin vendedor') as nombre")
            ->selectRaw($unidadesSql.' as unidades')
            ->selectRaw($importe.' as ventas_usd')
            ->selectRaw($clientesSql.' as clientes')
            ->selectRaw($utilidadSql.' as utilidad')
            ->groupBy(DB::raw("COALESCE(NULLIF(TRIM(vd.vendedor), ''), 'Sin vendedor')"))
            ->orderByDesc($orden)
            ->limit(8)
            ->get()
            ->map($mapTop)
            ->all();

        $categorias = [];
        if (Schema::hasTable('productos')) {
            $catQuery = $this->queryLineas($inicio, $fin, $sedes, $categoria, $vendedor, $producto);
            if (! $categoria) {
                $catQuery->leftJoin('productos as p', 'p.id', '=', 'vd.producto_id');
            }
            $categorias = $catQuery
                ->selectRaw("COALESCE(NULLIF(TRIM(p.categoria), ''), 'Sin categoría') as nombre")
                ->selectRaw($unidadesSql.' as unidades')
                ->selectRaw($importe.' as ventas_usd')
                ->selectRaw($clientesSql.' as clientes')
                ->selectRaw($utilidadSql.' as utilidad')
                ->groupBy(DB::raw("COALESCE(NULLIF(TRIM(p.categoria), ''), 'Sin categoría')"))
                ->orderByDesc($orden)
                ->limit(8)
                ->get()
                ->map($mapTop)
                ->all();
        }

        return compact('productos', 'vendedores', 'categorias');
    }

    /**
     * @param  list<string>  $sedes
     * @return list<array{fecha:string,ventas_usd:float}>
     */
    private function diario(
        Carbon $inicio,
        Carbon $fin,
        array $sedes,
        bool $usaLineas,
        ?string $categoria,
        ?string $vendedor,
        ?string $producto
    ): array {
        if (! $usaLineas && Schema::hasTable('ventas_documentos')) {
            return DB::table('ventas_documentos')
                ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
                ->whereIn(DB::raw('UPPER(TRIM(sede))'), $sedes)
                ->whereRaw("LOWER(TRIM(COALESCE(estado, ''))) = 'registrado'")
                ->selectRaw('fecha')
                ->selectRaw("SUM(CASE WHEN UPPER(tipo_documento)='DEV' THEN -ABS(total_neto_usd) ELSE ABS(total_neto_usd) END) as ventas_usd")
                ->groupBy('fecha')
                ->orderBy('fecha')
                ->get()
                ->map(fn ($r) => ['fecha' => (string) $r->fecha, 'ventas_usd' => round((float) $r->ventas_usd, 2)])
                ->all();
        }

        if (! Schema::hasTable('ventas_detalle')) {
            return [];
        }

        return $this->queryLineas($inicio, $fin, $sedes, $categoria, $vendedor, $producto)
            ->selectRaw('vd.fecha')
            ->selectRaw($this->sqlImporte('neto').' as ventas_usd')
            ->groupBy('vd.fecha')
            ->orderBy('vd.fecha')
            ->get()
            ->map(fn ($r) => ['fecha' => (string) $r->fecha, 'ventas_usd' => round((float) $r->ventas_usd, 2)])
            ->all();
    }

    /**
     * @return array<string, float|int|string>
     */
    private function kpiVacio(string $sede): array
    {
        return [
            'sede' => $sede,
            'facturas' => 0,
            'devoluciones' => 0,
            'devoluciones_usd' => 0.0,
            'devoluciones_bs' => 0.0,
            'ventas_brutas' => 0.0,
            'ventas_usd' => 0.0,
            'ventas_bs' => 0.0,
            'unidades' => 0.0,
            'margen_usd' => 0.0,
            'productos' => 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    private function sumarFilas(array $filas): array
    {
        $total = $this->kpiVacio('TODAS') + [
            'inventario_unidades' => 0.0,
            'inventario_valor' => 0.0,
            'ajustes_unidades' => 0.0,
            'ajustes_valor' => 0.0,
            'anterior_ventas_usd' => 0.0,
            'venta_neta' => 0.0,
            'utilidad' => 0.0,
            'margen_pct' => 0.0,
        ];
        foreach ($filas as $fila) {
            foreach (['facturas', 'devoluciones', 'devoluciones_usd', 'devoluciones_bs', 'ventas_brutas', 'ventas_usd', 'ventas_bs', 'unidades', 'margen_usd', 'productos', 'inventario_unidades', 'inventario_valor', 'ajustes_unidades', 'ajustes_valor', 'anterior_ventas_usd', 'venta_neta', 'utilidad'] as $campo) {
                $total[$campo] = ($total[$campo] ?? 0) + ($fila[$campo] ?? 0);
            }
        }
        $total['delta_ventas_usd'] = $this->delta((float) $total['ventas_usd'], (float) $total['anterior_ventas_usd']);
        $total['delta_unidades'] = $this->delta((float) $total['unidades'], 0);
        $ventaNeta = (float) $total['venta_neta'] ?: (float) $total['ventas_usd'];
        $total['margen_pct'] = $ventaNeta > 0 ? round((float) $total['utilidad'] / $ventaNeta * 100, 1) : 0.0;

        return $total;
    }

    private function delta(float $actual, float $anterior): ?float
    {
        if (abs($anterior) < 0.009) {
            return $actual > 0 ? 100.0 : null;
        }

        return round((($actual - $anterior) / abs($anterior)) * 100, 1);
    }

    /**
     * @param  array{inicio:Carbon,fin:Carbon}  $periodo
     * @return array<string, mixed>
     */
    public function devoluciones(array $periodo, ?string $sede, ?string $vendedor, ?string $producto): array
    {
        $sedes = $this->filtrarSedes($sede);
        $kpis = [
            'documentos' => 0,
            'usd' => 0.0,
            'bs' => 0.0,
            'unidades' => 0.0,
        ];

        if (Schema::hasTable('ventas_documentos')) {
            $query = DB::table('ventas_documentos')
                ->whereBetween('fecha', [$periodo['inicio']->toDateString(), $periodo['fin']->toDateString()])
                ->whereIn(DB::raw('UPPER(TRIM(sede))'), $sedes)
                ->whereRaw("UPPER(TRIM(tipo_documento)) = 'DEV'")
                ->whereRaw("LOWER(TRIM(COALESCE(estado, ''))) = 'registrado'");

            $kpisRow = (clone $query)
                ->selectRaw('COUNT(*) as documentos')
                ->selectRaw('SUM(ABS(total_neto_usd)) as usd')
                ->selectRaw('SUM(ABS(total_neto_bs)) as bs')
                ->first();
            $kpis['documentos'] = (int) ($kpisRow->documentos ?? 0);
            $kpis['usd'] = round((float) ($kpisRow->usd ?? 0), 2);
            $kpis['bs'] = round((float) ($kpisRow->bs ?? 0), 2);
        }

        $porProducto = collect();
        $porSede = collect();
        $porMotivo = collect();
        $motivoTop = null;
        if (Schema::hasTable('ventas_detalle')) {
            $lineasQuery = $this->queryLineas($periodo['inicio'], $periodo['fin'], $sedes, null, $vendedor, $producto)
                ->whereRaw("UPPER(TRIM(vd.tipo_documento)) = 'DEV'");

            $importe = Schema::hasColumn('ventas_detalle', 'precio_neto')
                ? 'COALESCE(vd.precio_neto, vd.precio_venta)'
                : 'vd.precio_venta';

            $agg = (clone $lineasQuery)
                ->selectRaw('COUNT(DISTINCT vd.numero_documento) as documentos')
                ->selectRaw('SUM(ABS(vd.cantidad)) as unidades')
                ->selectRaw("SUM(ABS(vd.cantidad * {$importe})) as usd")
                ->first();
            $kpis['unidades'] = round((float) ($agg->unidades ?? 0), 2);
            if ($kpis['documentos'] === 0) {
                $kpis['documentos'] = (int) ($agg->documentos ?? 0);
            }
            if ($kpis['usd'] === 0.0) {
                $kpis['usd'] = round((float) ($agg->usd ?? 0), 2);
            }

            $porProducto = (clone $lineasQuery)
                ->selectRaw('COALESCE(vd.nombre_producto, vd.codigo_producto, \'Sin nombre\') as nombre')
                ->selectRaw('COALESCE(vd.codigo_producto, \'\') as codigo')
                ->selectRaw('SUM(ABS(vd.cantidad)) as unidades')
                ->selectRaw("SUM(ABS(vd.cantidad * {$importe})) as usd")
                ->groupBy(DB::raw('COALESCE(vd.nombre_producto, vd.codigo_producto, \'Sin nombre\')'), DB::raw("COALESCE(vd.codigo_producto, '')"))
                ->orderByDesc('usd')
                ->limit(20)
                ->get();

            $porSede = (clone $lineasQuery)
                ->selectRaw('UPPER(TRIM(vd.sede)) as sede')
                ->selectRaw('COUNT(DISTINCT vd.numero_documento) as documentos')
                ->selectRaw('SUM(ABS(vd.cantidad)) as unidades')
                ->selectRaw("SUM(ABS(vd.cantidad * {$importe})) as usd")
                ->groupBy(DB::raw('UPPER(TRIM(vd.sede))'))
                ->orderByDesc('usd')
                ->get();

            $porMotivo = collect();
            $motivoTop = null;
            if (Schema::hasColumn('ventas_detalle', 'motivo_devolucion')) {
                $porMotivo = (clone $lineasQuery)
                    ->selectRaw("COALESCE(NULLIF(TRIM(vd.motivo_devolucion), ''), 'Sin motivo') as motivo")
                    ->selectRaw('COUNT(*) as veces')
                    ->selectRaw('SUM(ABS(vd.cantidad)) as unidades')
                    ->selectRaw("SUM(ABS(vd.cantidad * {$importe})) as usd")
                    ->groupBy(DB::raw("COALESCE(NULLIF(TRIM(vd.motivo_devolucion), ''), 'Sin motivo')"))
                    ->orderByDesc('veces')
                    ->limit(15)
                    ->get();
                $motivoTop = $porMotivo->first();
            }
        }

        return compact('kpis', 'porProducto', 'porSede', 'porMotivo', 'motivoTop');
    }

    /**
     * @return array<string, mixed>
     */
    public function valorizados(?string $sede, ?string $categoria, ?string $producto): array
    {
        $sedes = $this->filtrarSedes($sede);
        $porSede = $this->inventarioPorSede($sedes);
        $porCategoria = collect();
        $productos = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 50);

        if (! Schema::hasTable('stock_actual')) {
            return [
                'por_sede' => $porSede,
                'por_categoria' => $porCategoria,
                'productos' => $productos,
                'total_unidades' => array_sum(array_column($porSede, 'unidades')),
                'total_valor' => array_sum(array_column($porSede, 'valor')),
            ];
        }

        $query = DB::table('stock_actual as sa')
            ->whereIn(DB::raw('UPPER(TRIM(sa.sede))'), $sedes)
            ->where('sa.existencia', '>', 0);

        $joinProductos = Schema::hasTable('productos');
        if ($joinProductos) {
            $query->leftJoin('productos as p', 'p.id', '=', 'sa.producto_id');
        }
        if ($categoria && $joinProductos) {
            $query->whereRaw('UPPER(TRIM(p.categoria)) = ?', [mb_strtoupper(trim($categoria), 'UTF-8')]);
        }
        if ($producto) {
            $like = '%'.$producto.'%';
            $query->where(function ($q) use ($like, $joinProductos) {
                if ($joinProductos) {
                    $q->where('p.codigo', 'like', $like)->orWhere('p.nombre', 'like', $like);
                }
                if (Schema::hasColumn('stock_actual', 'codigo_producto')) {
                    $q->orWhere('sa.codigo_producto', 'like', $like);
                }
            });
        }

        $costoSql = $joinProductos && Schema::hasColumn('productos', 'costo_actual')
            ? 'COALESCE(p.costo_actual, 0)'
            : '0';

        if ($joinProductos) {
            $porCategoria = (clone $query)
                ->selectRaw("COALESCE(NULLIF(TRIM(p.categoria), ''), 'Sin categoría') as categoria")
                ->selectRaw('SUM(sa.existencia) as unidades')
                ->selectRaw("SUM(sa.existencia * {$costoSql}) as valor")
                ->groupBy(DB::raw("COALESCE(NULLIF(TRIM(p.categoria), ''), 'Sin categoría')"))
                ->orderByDesc('valor')
                ->get();
        }

        $productos = (clone $query)
            ->selectRaw('UPPER(TRIM(sa.sede)) as sede')
            ->selectRaw($joinProductos ? 'COALESCE(p.codigo, \'\') as codigo' : '\'\' as codigo')
            ->selectRaw($joinProductos ? 'COALESCE(p.nombre, \'Sin nombre\') as nombre' : '\'Sin nombre\' as nombre')
            ->selectRaw($joinProductos ? "COALESCE(NULLIF(TRIM(p.categoria), ''), 'Sin categoría') as categoria" : '\'Sin categoría\' as categoria')
            ->selectRaw('sa.existencia as unidades')
            ->selectRaw("{$costoSql} as costo")
            ->selectRaw("sa.existencia * {$costoSql} as valor")
            ->orderByDesc(DB::raw("sa.existencia * {$costoSql}"))
            ->paginate(60)
            ->withQueryString();

        $totalUnidades = array_sum(array_column($porSede, 'unidades'));
        $totalValor = array_sum(array_column($porSede, 'valor'));
        if ($categoria || $producto) {
            $tot = (clone $query)
                ->selectRaw('SUM(sa.existencia) as unidades')
                ->selectRaw("SUM(sa.existencia * {$costoSql}) as valor")
                ->first();
            $totalUnidades = (float) ($tot->unidades ?? 0);
            $totalValor = (float) ($tot->valor ?? 0);
        }

        return [
            'por_sede' => $porSede,
            'por_categoria' => $porCategoria,
            'productos' => $productos,
            'total_unidades' => $totalUnidades,
            'total_valor' => $totalValor,
        ];
    }

    /**
     * @param  array{inicio:Carbon,fin:Carbon}  $periodo
     * @return array<string, mixed>
     */
    public function ajustesConsolidados(array $periodo, ?string $sede, ?string $tipo): array
    {
        $sedes = $this->filtrarSedes($sede);
        $vacio = [
            'kpis' => ['movimientos' => 0, 'unidades' => 0.0, 'valor' => 0.0],
            'por_sede_tipo' => collect(),
            'por_motivo' => collect(),
            'motivo_top' => null,
        ];
        if (! Schema::hasTable('ajustes_inventario')) {
            return $vacio;
        }

        $tiposOk = $this->tiposAjustePermitidos();
        $docs = $this->sqlCountDocumentosAjuste();
        $query = DB::table('ajustes_inventario')
            ->whereBetween('fecha', [$periodo['inicio']->toDateString(), $periodo['fin']->toDateString()])
            ->whereIn(DB::raw('UPPER(TRIM(sede))'), $sedes)
            ->whereIn(DB::raw('UPPER(TRIM(tipo_movimiento))'), $tiposOk);
        $tipoNorm = $tipo ? mb_strtoupper(trim($tipo), 'UTF-8') : '';
        if ($tipoNorm !== '' && in_array($tipoNorm, $tiposOk, true)) {
            $query->whereRaw('UPPER(TRIM(tipo_movimiento)) = ?', [$tipoNorm]);
        }

        $kpisRow = (clone $query)
            ->selectRaw("{$docs} as movimientos")
            ->selectRaw('SUM(cantidad) as unidades')
            ->selectRaw('SUM(cantidad * COALESCE(costo_unitario, 0)) as valor')
            ->first();

        $porSedeTipo = (clone $query)
            ->selectRaw('UPPER(TRIM(sede)) as sede')
            ->selectRaw('UPPER(TRIM(tipo_movimiento)) as tipo_movimiento')
            ->selectRaw("{$docs} as movimientos")
            ->selectRaw('SUM(cantidad) as unidades')
            ->selectRaw('SUM(cantidad * COALESCE(costo_unitario, 0)) as valor')
            ->groupBy(DB::raw('UPPER(TRIM(sede))'), DB::raw('UPPER(TRIM(tipo_movimiento))'))
            ->orderBy('sede')
            ->orderBy('tipo_movimiento')
            ->get();

        $porMotivo = collect();
        $motivoTop = null;
        if (Schema::hasColumn('ajustes_inventario', 'motivo')) {
            $porMotivo = (clone $query)
                ->selectRaw("COALESCE(NULLIF(TRIM(motivo), ''), 'Sin motivo') as motivo")
                ->selectRaw("{$docs} as veces")
                ->selectRaw('SUM(cantidad) as unidades')
                ->selectRaw('SUM(cantidad * COALESCE(costo_unitario, 0)) as valor')
                ->groupBy(DB::raw("COALESCE(NULLIF(TRIM(motivo), ''), 'Sin motivo')"))
                ->orderByDesc('veces')
                ->limit(15)
                ->get();
            $motivoTop = $porMotivo->first();
        }

        return [
            'kpis' => [
                'movimientos' => (int) ($kpisRow->movimientos ?? 0),
                'unidades' => round((float) ($kpisRow->unidades ?? 0), 2),
                'valor' => round((float) ($kpisRow->valor ?? 0), 2),
            ],
            'por_sede_tipo' => $porSedeTipo,
            'por_motivo' => $porMotivo,
            'motivo_top' => $motivoTop,
        ];
    }

    /**
     * @return list<string>
     */
    public function tiposAjustePermitidos(): array
    {
        return ['AJU', 'CAR', 'DES'];
    }

    public function sqlCountDocumentosAjuste(): string
    {
        return "COUNT(DISTINCT TRIM(sede) || '-' || TRIM(tipo_movimiento) || '-' || TRIM(numero_documento))";
    }

    /**
     * @return list<string>
     */
    public function filtrarSedes(?string $sede): array
    {
        $sedes = $this->sedesVentas();
        if ($sede && $sede !== 'todas') {
            $sede = strtoupper(trim($sede));

            return in_array($sede, $sedes, true) ? [$sede] : $sedes;
        }

        return $sedes;
    }
}
