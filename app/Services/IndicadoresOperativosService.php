<?php

namespace App\Services;

use App\Models\Nomina\NominaEmpleado;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IndicadoresOperativosService
{
    public function __construct(private IncentivoEvaluacionService $incentivos) {}

    /**
     * @return array{kpis: array<string, int|float>, personas: list<array<string, mixed>>}
     */
    public function compras(Carbon $inicio, Carbon $fin, ?string $sede): array
    {
        $vacio = [
            'kpis' => [
                'pendientes' => 0,
                'comprados' => 0,
                'fuera' => 0,
                'productos' => 0,
                'dias' => 0,
            ],
            'personas' => [],
        ];

        if (! Schema::hasTable('pedidos_solicitados')) {
            return $vacio;
        }

        $base = DB::table('pedidos_solicitados as ps');
        if ($sede) {
            $base->whereRaw('UPPER(TRIM(ps.sede)) = ?', [$sede]);
        }

        $pendientes = (clone $base)->where('ps.estado', 'pendiente')->count();

        $cerrados = (clone $base)
            ->whereIn('ps.estado', ['comprado', 'fuera_de_mercado'])
            ->whereBetween('ps.atendido_at', [$inicio->copy()->startOfDay(), $fin->copy()->endOfDay()])
            ->selectRaw('ps.atendido_por')
            ->selectRaw("COUNT(*) FILTER (WHERE ps.estado = 'comprado') as comprados")
            ->selectRaw("COUNT(DISTINCT ps.producto) FILTER (WHERE ps.estado = 'comprado') as productos")
            ->selectRaw("COUNT(*) FILTER (WHERE ps.estado = 'fuera_de_mercado') as fuera")
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (ps.atendido_at - ps.created_at)) / 86400) FILTER (WHERE ps.atendido_at IS NOT NULL) as dias')
            ->groupBy('ps.atendido_por')
            ->get();

        $ids = $cerrados->pluck('atendido_por')->filter()->map(fn ($id) => (int) $id)->all();
        $nombres = User::query()
            ->where(function ($q) use ($ids) {
                $q->where('role', User::ROLE_COMPRADOR);
                if ($ids !== []) {
                    $q->orWhereIn('id', $ids);
                }
            })
            ->get(['id', 'name'])
            ->keyBy('id');

        $porId = $cerrados->keyBy(fn ($row) => (string) ($row->atendido_por ?? ''));
        $personas = [];
        $vistos = [];

        foreach ($nombres as $user) {
            $row = $porId->get((string) $user->id);
            $vistos[(string) $user->id] = true;
            $personas[] = $this->filaCompra($user->name, $row);
        }

        foreach ($cerrados as $row) {
            $id = (string) ($row->atendido_por ?? '');
            if (isset($vistos[$id])) {
                continue;
            }
            $personas[] = $this->filaCompra($id === '' ? 'Sin usuario' : 'Usuario '.$id, $row);
        }

        usort($personas, fn ($a, $b) => $b['cerrados'] <=> $a['cerrados']);

        $comprados = array_sum(array_column($personas, 'comprados'));
        $fuera = array_sum(array_column($personas, 'fuera'));
        $productos = array_sum(array_column($personas, 'productos'));
        $conDias = array_values(array_filter($personas, fn ($p) => $p['dias'] > 0));
        $dias = $conDias === []
            ? 0
            : array_sum(array_column($conDias, 'dias')) / count($conDias);

        return [
            'kpis' => [
                'pendientes' => (int) $pendientes,
                'comprados' => (int) $comprados,
                'fuera' => (int) $fuera,
                'productos' => (int) $productos,
                'dias' => round($dias, 1),
            ],
            'personas' => $personas,
        ];
    }

    /**
     * Misma efectividad en tienda que asesor de ventas. El total de faltante del mes es el 100%:
     * si la persona tiene faltante el porcentaje es negativo y se resta; si es positivo se suma.
     *
     * @return array{desde:string, hasta:string, filas:list<array<string, mixed>>}
     */
    public function cajas(Carbon $inicio, Carbon $fin, ?string $sede): array
    {
        $vacio = [
            'desde' => $inicio->format('d/m/Y'),
            'hasta' => $fin->format('d/m/Y'),
            'filas' => [],
        ];

        if (! Schema::hasTable('ventas_detalle') || ! Schema::hasColumn('ventas_detalle', 'cajera')) {
            return $vacio;
        }

        $tienda = $this->totalesPorSede($inicio, $fin, $sede);
        $grupos = $this->ventasPorCajera($inicio, $fin, $sede);
        $faltantes = $this->faltantesDelMes($inicio, $fin);
        $totalFaltante = round((float) array_sum($faltantes), 2);
        $productosMeta = $this->incentivos->productosMetaAsignados();
        $etiquetas = $this->etiquetasSede();

        $todosEmpleados = NominaEmpleado::query()
            ->activos()
            ->with(['cliente', 'sedeCatalogo', 'cargoCatalogo'])
            ->get();

        // Solo cajeros/cajeras; sin supervisores, telefonía ni call center.
        $empleados = $todosEmpleados
            ->filter(fn (NominaEmpleado $e) => $this->esCajeroEfectividadCaja($e) && ! $this->excluidoEfectividadCaja($e))
            ->values();

        $pares = [];
        foreach ($empleados as $empleado) {
            $tokens = $this->tokensNombre($empleado->nombre());
            foreach ($grupos as $clave => $grupo) {
                $puntos = $this->puntajeNombre($tokens, $grupo['tokens']);
                if ($puntos >= 3) {
                    $pares[] = ['id' => $empleado->id, 'clave' => $clave, 'puntos' => $puntos];
                }
            }
        }
        usort($pares, fn (array $a, array $b) => $b['puntos'] <=> $a['puntos']);
        $asignados = [];
        $usados = [];
        foreach ($pares as $par) {
            if (isset($usados[$par['clave']]) || isset($asignados[$par['id']])) {
                continue;
            }
            $asignados[$par['id']] = $par['clave'];
            $usados[$par['clave']] = true;
        }

        // Nombres del POS que corresponden a roles excluidos (no deben salir como huérfanos).
        $clavesExcluidas = [];
        foreach ($todosEmpleados as $empleado) {
            if (! $this->excluidoEfectividadCaja($empleado)) {
                continue;
            }
            $tokens = $this->tokensNombre($empleado->nombre());
            foreach ($grupos as $clave => $grupo) {
                if ($this->puntajeNombre($tokens, $grupo['tokens']) >= 3) {
                    $clavesExcluidas[$clave] = true;
                }
            }
        }

        $filas = [];
        foreach ($empleados as $empleado) {
            $grupo = isset($asignados[$empleado->id]) ? $grupos[$asignados[$empleado->id]] : null;
            $sedeEmpleado = strtoupper(trim((string) ($empleado->sedeCatalogo?->codigo ?: $empleado->sede)));
            $bucket = $this->bucketCajera($grupo, $sedeEmpleado);
            $sedeCodigo = $bucket['sede'] !== '' ? $bucket['sede'] : $sedeEmpleado;
            $filas[] = $this->filaCaja(
                $empleado->nombre(),
                $etiquetas[$sedeCodigo] ?? ($sedeCodigo !== '' ? $sedeCodigo : '—'),
                $bucket['suma'],
                $tienda[$sedeCodigo] ?? ['monto' => 0.0, 'unidades' => 0.0, 'clientes' => 0],
                $productosMeta[$empleado->id] ?? ['cumplidas' => null, 'total' => null, 'estado' => null],
                (float) ($faltantes[$empleado->id] ?? 0),
                $totalFaltante,
            );
        }

        foreach ($grupos as $clave => $grupo) {
            if (isset($usados[$clave]) || isset($clavesExcluidas[$clave])) {
                continue;
            }
            $bucket = $this->bucketCajera($grupo, '');
            $sedeCodigo = $bucket['sede'];
            $filas[] = $this->filaCaja(
                $grupo['nombre'],
                $etiquetas[$sedeCodigo] ?? ($sedeCodigo !== '' ? $sedeCodigo : '—'),
                $bucket['suma'],
                $tienda[$sedeCodigo] ?? ['monto' => 0.0, 'unidades' => 0.0, 'clientes' => 0],
                ['cumplidas' => null, 'total' => null, 'estado' => null],
                0.0,
                $totalFaltante,
            );
        }

        usort($filas, function (array $a, array $b) {
            return ($b['puntuacion'] <=> $a['puntuacion']) ?: strcasecmp((string) $a['nombre'], (string) $b['nombre']);
        });

        return [
            'desde' => $inicio->format('d/m/Y'),
            'hasta' => $fin->format('d/m/Y'),
            'filas' => $filas,
        ];
    }

    /**
     * Traslados de inventario (TRA) con signo: positivo sube existencia, negativo la baja.
     *
     * @return array{kpis: array<string, int|float>, porSede: list<array<string, mixed>>}
     */
    public function traslados(Carbon $inicio, Carbon $fin, ?string $sede): array
    {
        $vacio = [
            'kpis' => [
                'documentos' => 0,
                'entrada' => 0,
                'salida' => 0,
                'neto' => 0,
                'utilidad' => 0,
                'perdida' => 0,
                'valor' => 0,
            ],
            'porSede' => [],
        ];

        if (! Schema::hasTable('ajustes_inventario')) {
            return $vacio;
        }

        $valor = Schema::hasColumn('ajustes_inventario', 'costo_unitario')
            ? 'cantidad * COALESCE(costo_unitario, 0)'
            : '0';

        $query = DB::table('ajustes_inventario')
            ->whereRaw("UPPER(TRIM(tipo_movimiento)) = 'TRA'")
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()]);
        if ($sede) {
            $query->whereRaw('UPPER(TRIM(sede)) = ?', [$sede]);
        }

        $rows = $query
            ->selectRaw('UPPER(TRIM(sede)) as sede')
            ->selectRaw("COUNT(DISTINCT TRIM(sede) || '-' || TRIM(tipo_movimiento) || '-' || TRIM(numero_documento)) as documentos")
            ->selectRaw('SUM(CASE WHEN cantidad > 0 THEN cantidad ELSE 0 END) as entrada')
            ->selectRaw('SUM(CASE WHEN cantidad < 0 THEN cantidad ELSE 0 END) as salida')
            ->selectRaw('SUM(cantidad) as neto')
            ->selectRaw("SUM(CASE WHEN cantidad > 0 THEN {$valor} ELSE 0 END) as utilidad")
            ->selectRaw("SUM(CASE WHEN cantidad < 0 THEN {$valor} ELSE 0 END) as perdida")
            ->selectRaw("SUM({$valor}) as valor")
            ->groupBy(DB::raw('UPPER(TRIM(sede))'))
            ->orderBy('sede')
            ->get();

        $porSede = [];
        foreach ($rows as $row) {
            $porSede[] = [
                'sede' => (string) $row->sede,
                'documentos' => (int) $row->documentos,
                'entrada' => round((float) $row->entrada, 2),
                'salida' => round((float) $row->salida, 2),
                'neto' => round((float) $row->neto, 2),
                'utilidad' => round((float) $row->utilidad, 2),
                'perdida' => round((float) $row->perdida, 2),
                'valor' => round((float) $row->valor, 2),
            ];
        }

        return [
            'kpis' => [
                'documentos' => (int) array_sum(array_column($porSede, 'documentos')),
                'entrada' => round((float) array_sum(array_column($porSede, 'entrada')), 2),
                'salida' => round((float) array_sum(array_column($porSede, 'salida')), 2),
                'neto' => round((float) array_sum(array_column($porSede, 'neto')), 2),
                'utilidad' => round((float) array_sum(array_column($porSede, 'utilidad')), 2),
                'perdida' => round((float) array_sum(array_column($porSede, 'perdida')), 2),
                'valor' => round((float) array_sum(array_column($porSede, 'valor')), 2),
            ],
            'porSede' => $porSede,
        ];
    }

    /**
     * @return array<string, array{monto:float, unidades:float, clientes:int}>
     */
    private function totalesPorSede(Carbon $inicio, Carbon $fin, ?string $sede): array
    {
        $query = $this->ventasCaja($inicio, $fin, $sede);
        $rows = $query
            ->selectRaw('UPPER(TRIM(vd.sede)) as sede')
            ->selectRaw("SUM(CASE WHEN vd.tipo_documento = 'DEV' THEN -ABS(COALESCE(vd.cantidad, 0)) ELSE COALESCE(vd.cantidad, 0) END) as unidades")
            ->selectRaw("SUM(CASE WHEN vd.tipo_documento = 'DEV' THEN -ABS(COALESCE(vd.cantidad, 0) * COALESCE(vd.precio_neto, vd.precio_venta, 0)) ELSE COALESCE(vd.cantidad, 0) * COALESCE(vd.precio_neto, vd.precio_venta, 0) END) as monto")
            ->selectRaw("COUNT(DISTINCT CASE WHEN vd.tipo_documento = 'FAC' AND NULLIF(TRIM(vd.cliente), '') IS NOT NULL THEN UPPER(TRIM(vd.cliente)) END) as clientes")
            ->groupByRaw('UPPER(TRIM(vd.sede))')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $codigo = (string) $row->sede;
            if ($codigo === '') {
                continue;
            }
            $out[$codigo] = [
                'monto' => (float) $row->monto,
                'unidades' => (float) $row->unidades,
                'clientes' => (int) $row->clientes,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{nombre:string, tokens:list<string>, sedes:array<string, array{monto:float, unidades:float, clientes:int}>}>
     */
    private function ventasPorCajera(Carbon $inicio, Carbon $fin, ?string $sede): array
    {
        $rows = $this->ventasCaja($inicio, $fin, $sede)
            ->whereRaw("NULLIF(TRIM(vd.cajera), '') IS NOT NULL")
            ->whereRaw("UPPER(TRIM(vd.cajera)) <> 'SIN CAJERA'")
            ->selectRaw('UPPER(TRIM(vd.sede)) as sede')
            ->selectRaw('UPPER(TRIM(vd.cajera)) as cajera')
            ->selectRaw("SUM(CASE WHEN vd.tipo_documento = 'DEV' THEN -ABS(COALESCE(vd.cantidad, 0)) ELSE COALESCE(vd.cantidad, 0) END) as unidades")
            ->selectRaw("SUM(CASE WHEN vd.tipo_documento = 'DEV' THEN -ABS(COALESCE(vd.cantidad, 0) * COALESCE(vd.precio_neto, vd.precio_venta, 0)) ELSE COALESCE(vd.cantidad, 0) * COALESCE(vd.precio_neto, vd.precio_venta, 0) END) as monto")
            ->selectRaw("COUNT(DISTINCT CASE WHEN vd.tipo_documento = 'FAC' AND NULLIF(TRIM(vd.cliente), '') IS NOT NULL THEN UPPER(TRIM(vd.cliente)) END) as clientes")
            ->groupByRaw('UPPER(TRIM(vd.sede))')
            ->groupByRaw('UPPER(TRIM(vd.cajera))')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $nombre = (string) $row->cajera;
            $codigo = (string) $row->sede;
            if ($nombre === '' || $codigo === '') {
                continue;
            }
            $out[$nombre]['nombre'] = $nombre;
            $out[$nombre]['tokens'] = $this->tokensNombre($nombre);
            $out[$nombre]['sedes'][$codigo] = [
                'monto' => (float) $row->monto,
                'unidades' => (float) $row->unidades,
                'clientes' => (int) $row->clientes,
            ];
        }

        return $out;
    }

    private function ventasCaja(Carbon $inicio, Carbon $fin, ?string $sede)
    {
        $query = DB::table('ventas_detalle as vd')
            ->whereBetween('vd.fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->whereIn('vd.tipo_documento', ['FAC', 'DEV']);
        if ($sede) {
            $query->whereRaw('UPPER(TRIM(vd.sede)) = ?', [strtoupper(trim($sede))]);
        }
        if (Schema::hasColumn('ventas_detalle', 'anulado')) {
            $query->where(function ($q) {
                $q->whereNull('vd.anulado')->orWhere('vd.anulado', false);
            });
        }

        return $query;
    }

    /**
     * @return array<int, float>
     */
    private function faltantesDelMes(Carbon $inicio, Carbon $fin): array
    {
        if (! Schema::hasTable('nomina_comision_descuentos')) {
            return [];
        }

        $rows = DB::table('nomina_comision_descuentos')
            ->where('tipo', 'FALTANTE')
            ->where('estado', '!=', 'CANCELADO')
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->selectRaw('empleado_id')
            ->selectRaw('SUM(monto) as monto')
            ->groupBy('empleado_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->empleado_id] = round((float) $row->monto, 2);
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function etiquetasSede(): array
    {
        $out = [];
        foreach (config('inventario.display', []) as $codigo => $nombre) {
            $out[strtoupper(trim((string) $codigo))] = (string) $nombre;
        }
        if (Schema::hasTable('nomina_sedes')) {
            foreach (DB::table('nomina_sedes')->get(['codigo', 'nombre']) as $row) {
                $codigo = strtoupper(trim((string) $row->codigo));
                $nombre = trim((string) $row->nombre);
                if ($codigo !== '' && $nombre !== '') {
                    $out[$codigo] = $nombre;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array{nombre:string, tokens:list<string>, sedes:array<string, array{monto:float, unidades:float, clientes:int}>}|null  $grupo
     * @return array{sede:string, suma:array{monto:float, unidades:float, clientes:int}}
     */
    private function bucketCajera(?array $grupo, string $sedePreferida): array
    {
        $cero = ['monto' => 0.0, 'unidades' => 0.0, 'clientes' => 0];
        if ($grupo === null) {
            return ['sede' => $sedePreferida, 'suma' => $cero];
        }
        if ($sedePreferida !== '' && isset($grupo['sedes'][$sedePreferida])) {
            return ['sede' => $sedePreferida, 'suma' => $grupo['sedes'][$sedePreferida]];
        }

        $mejor = '';
        $monto = -INF;
        foreach ($grupo['sedes'] as $codigo => $suma) {
            if ($suma['monto'] > $monto) {
                $monto = $suma['monto'];
                $mejor = (string) $codigo;
            }
        }

        return ['sede' => $mejor, 'suma' => $grupo['sedes'][$mejor] ?? $cero];
    }

    /**
     * @param  array{monto:float, unidades:float, clientes:int}  $suma
     * @param  array{monto:float, unidades:float, clientes:int}  $tienda
     * @param  array{cumplidas:?int, total:?int, estado:?string}  $productosMeta
     * @return array<string, mixed>
     */
    private function filaCaja(string $nombre, string $sede, array $suma, array $tienda, array $productosMeta, float $faltante, float $faltanteMes): array
    {
        $pctVenta = $this->porcentajeCaja((float) $suma['monto'], (float) $tienda['monto']);
        $pctProductos = $this->porcentajeCaja((float) $suma['unidades'], (float) $tienda['unidades']);
        $pctClientes = $this->porcentajeCaja((float) $suma['clientes'], (float) $tienda['clientes']);
        $piso = round((($pctVenta ?? 0) + ($pctProductos ?? 0) + ($pctClientes ?? 0)) / 3, 1);
        $totalMeta = (int) ($productosMeta['total'] ?? 0);
        $metaProd = $totalMeta > 0
            ? round(((int) $productosMeta['cumplidas'] / $totalMeta) * 100, 1)
            : 0.0;
        $puntuacion = round($this->acotarCien($piso) * 0.70 + $this->acotarCien($metaProd) * 0.30, 1);
        $faltantePct = null;
        if ($faltanteMes > 0) {
            $faltantePct = round(-$faltante / $faltanteMes * 100, 1);
            $puntuacion = round($puntuacion + $faltantePct, 1);
        }

        return [
            'nombre' => $nombre,
            'sede' => $sede,
            'monto' => $suma['monto'],
            'meta_venta' => (float) $tienda['monto'],
            'pct_venta' => $pctVenta,
            'unidades' => $suma['unidades'],
            'meta_productos' => (float) $tienda['unidades'],
            'pct_productos' => $pctProductos,
            'clientes' => $suma['clientes'],
            'meta_clientes' => (float) $tienda['clientes'],
            'pct_clientes' => $pctClientes,
            'piso_pct' => $piso,
            'meta_prod_pct' => $metaProd,
            'meta_prod_cumplidas' => $productosMeta['cumplidas'],
            'meta_prod_total' => $productosMeta['total'],
            'meta_prod_estado' => $productosMeta['estado'],
            'faltante' => $faltante,
            'faltante_mes' => $faltanteMes > 0 ? $faltanteMes : null,
            'faltante_pct' => $faltantePct,
            'puntuacion' => $puntuacion,
        ];
    }

    private function cargoNombreEmpleado(NominaEmpleado $empleado): string
    {
        $empleado->loadMissing('cargoCatalogo', 'sedeCatalogo');

        return mb_strtolower(trim((string) ($empleado->cargoCatalogo?->nombre ?: $empleado->cargo ?: '')), 'UTF-8');
    }

    private function esCajeroEfectividadCaja(NominaEmpleado $empleado): bool
    {
        return str_contains($this->cargoNombreEmpleado($empleado), 'cajer');
    }

    private function excluidoEfectividadCaja(NominaEmpleado $empleado): bool
    {
        if ((bool) ($empleado->es_supervisor ?? false)) {
            return true;
        }

        $cargo = $this->cargoNombreEmpleado($empleado);
        $cargoCompacto = str_replace([' ', '-'], '', $cargo);

        if (str_contains($cargo, 'supervisor') || str_contains($cargo, 'coordinador de tienda')) {
            return true;
        }

        if (str_contains($cargo, 'telefon') || str_contains($cargoCompacto, 'telefonia')) {
            return true;
        }

        if (str_contains($cargo, 'call center') || str_contains($cargoCompacto, 'callcenter')) {
            return true;
        }

        $empleado->loadMissing('sedeCatalogo');
        $sede = mb_strtolower(trim((string) ($empleado->sedeCatalogo?->nombre ?: $empleado->sedeCatalogo?->codigo ?: $empleado->sede ?: '')), 'UTF-8');
        $sedeCompacta = str_replace([' ', '-'], '', $sede);
        if (str_contains($sede, 'call center') || str_contains($sedeCompacta, 'callcenter')) {
            return true;
        }

        return false;
    }

    private function porcentajeCaja(float $real, float $meta): ?float
    {
        if ($meta <= 0) {
            return null;
        }

        return round($real / $meta * 100, 1);
    }

    private function acotarCien(float $porcentaje): float
    {
        return min(max($porcentaje, 0), 100);
    }

    /**
     * @return list<string>
     */
    private function tokensNombre(string $nombre): array
    {
        $nombre = mb_strtoupper(trim($nombre), 'UTF-8');
        $nombre = strtr($nombre, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
        $nombre = preg_replace('/[^A-Z\s]/', ' ', $nombre) ?? '';
        $partes = preg_split('/\s+/', trim($nombre)) ?: [];
        $stop = ['DE', 'DEL', 'LA', 'LAS', 'LOS', 'DA', 'DO', 'DAS', 'Y'];

        return array_values(array_filter($partes, fn ($parte) => $parte !== '' && ! in_array($parte, $stop, true)));
    }

    /**
     * @param  list<string>  $empleado
     * @param  list<string>  $cajera
     */
    private function puntajeNombre(array $empleado, array $cajera): int
    {
        $puntos = 0;
        $usados = [];
        foreach ($cajera as $token) {
            foreach ($empleado as $i => $otro) {
                if (isset($usados[$i])) {
                    continue;
                }
                $igual = $token === $otro;
                $cerca = strlen($token) >= 5 && strlen($otro) >= 5 && levenshtein($token, $otro) <= 1;
                if ($igual || $cerca) {
                    $usados[$i] = true;
                    $puntos += $igual ? 2 : 1;
                    break;
                }
            }
        }

        return $puntos;
    }

    /**
     * @return array{nombre: string, comprados: int, productos: int, fuera: int, cerrados: int, dias: float}
     */
    private function filaCompra(string $nombre, ?object $row): array
    {
        $comprados = (int) ($row->comprados ?? 0);
        $fuera = (int) ($row->fuera ?? 0);

        return [
            'nombre' => $nombre,
            'comprados' => $comprados,
            'productos' => (int) ($row->productos ?? 0),
            'fuera' => $fuera,
            'cerrados' => $comprados + $fuera,
            'dias' => round((float) ($row->dias ?? 0), 1),
        ];
    }
}
