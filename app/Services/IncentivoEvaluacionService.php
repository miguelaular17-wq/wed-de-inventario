<?php

namespace App\Services;

use App\Models\Nomina\NominaEmpleado;
use App\Models\VentaDiariaMeta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IncentivoEvaluacionService
{
    public function __construct(
        private IncentivoCruceService $cruce,
        private MetaQuincenaService $metasQuincena,
    ) {}

    /**
     * @param  array<string, mixed>  $modulo
     * @return array{desde:string, hasta:string, filas:list<array<string, mixed>>}
     */
    public function evaluar(string $clave, array $modulo): array
    {
        $desde = now()->startOfMonth();
        $hasta = now();
        $personas = $this->personas($modulo['cargos'] ?? []);
        $metas = $this->metasPorSede();
        $claves = [];
        foreach ($personas as $persona) {
            foreach ($this->clavesDe($persona) as $codigo) {
                $claves[$codigo] = true;
            }
        }

        $equipos = [];
        if ($clave === 'supervisor') {
            $equipos = $this->equipos($personas->pluck('id')->all());
            foreach ($equipos as $miembros) {
                foreach ($miembros as $miembro) {
                    foreach ($this->clavesDe($miembro) as $codigo) {
                        $claves[$codigo] = true;
                    }
                }
            }
        }

        $cruceActivo = false;
        $cruces = [];
        $tiendas = null;
        $gruposCruce = [];
        $crucesPorItem = [];
        $filtroMeta = null;
        $sinMedicion = false;
        if ($clave === 'arreglos') {
            $seleccion = $this->cruce->activos($clave);
            $cruceActivo = $seleccion['categorias'] !== [] || $seleccion['productos'] !== [];
            $filtroMeta = $seleccion;
            $sinMedicion = ! $cruceActivo;
            $ventas = $cruceActivo
                ? $this->ventasPorClave(array_keys($claves), $desde, $hasta, $seleccion['categorias'], $seleccion['productos'])
                : [];
            if ($cruceActivo) {
                $tiendas = $this->totalesTienda($desde, $hasta, $seleccion['categorias'], $seleccion['productos'], true);
            }
        } else {
            $ventas = $this->ventasPorClave(array_keys($claves), $desde, $hasta);
            if ($clave === 'ventas') {
                $tiendas = $this->totalesTienda($desde, $hasta, [], []);
            }
            if ($clave === 'telefonia') {
                $gruposCruce = $this->cruce->activosDetalle($clave);
                $cruceActivo = $gruposCruce !== [];
                $tiendas = $this->totalesTienda($desde, $hasta, [], []);
                if ($cruceActivo) {
                    $crucesPorItem = $this->cruceVendedores(array_keys($claves), $desde, $hasta, $gruposCruce);
                    foreach ($this->cruceSedes($desde, $hasta, $gruposCruce) as $sede => $porClave) {
                        $tiendas[$sede] ??= ['monto' => 0.0, 'unidades' => 0.0, 'clientes' => 0, 'cruce' => 0.0];
                        $tiendas[$sede]['cruces'] = $porClave;
                    }
                }
            }
        }
        $productosMeta = $this->productosMetaPorEmpleado($filtroMeta, $clave === 'arreglos');

        $filas = [];
        foreach ($personas as $persona) {
            $filas[] = $clave === 'supervisor'
                ? $this->filaSupervisor($persona, $equipos[$persona->id] ?? [], $ventas, $metas, $productosMeta)
                : $this->filaPersona($persona, $ventas, $metas, $cruces, $clave === 'telefonia', $cruceActivo, $productosMeta, $sinMedicion, $tiendas, $gruposCruce, $crucesPorItem, match ($clave) {
                    'telefonia' => 'telefonia',
                    'ventas', 'arreglos' => 'piso',
                    default => null,
                });
        }

        usort($filas, function (array $a, array $b) {
            $pa = $a['puntuacion'];
            $pb = $b['puntuacion'];
            if ($pa === null && $pb === null) {
                return strcasecmp($a['nombre'], $b['nombre']);
            }
            if ($pa === null) {
                return 1;
            }
            if ($pb === null) {
                return -1;
            }

            return $pb <=> $pa;
        });

        return [
            'desde' => $desde->format('d/m/Y'),
            'hasta' => $hasta->format('d/m/Y'),
            'filas' => $filas,
            'grupos_cruce' => $clave === 'telefonia' ? $this->gruposCruceVisibles($gruposCruce) : [],
        ];
    }

    /**
     * @param  list<string>  $cargos
     */
    private function personas(array $cargos)
    {
        $esperados = array_flip($cargos);

        return NominaEmpleado::query()
            ->with(['cliente', 'cargoCatalogo', 'sedeCatalogo', 'vendedores'])
            ->where('estado', 'ACTIVO')
            ->get()
            ->filter(fn (NominaEmpleado $empleado) => isset($esperados[$this->texto($empleado->nombreCargo())]))
            ->values();
    }

    /**
     * @return array<string, VentaDiariaMeta>
     */
    private function metasPorSede(): array
    {
        $out = [];
        foreach (VentaDiariaMeta::query()->get() as $meta) {
            $out[strtoupper(trim((string) $meta->sede))] = $meta;
        }

        return $out;
    }

    /**
     * @param  list<int>  $supervisorIds
     * @return array<int, list<NominaEmpleado>>
     */
    private function equipos(array $supervisorIds): array
    {
        $out = [];
        foreach ($supervisorIds as $id) {
            $out[$id] = [];
        }
        if ($supervisorIds === []) {
            return $out;
        }

        $ids = [];
        $porSupervisor = [];
        if (Schema::hasTable('nomina_empleado_supervisores')) {
            $pivot = DB::table('nomina_empleado_supervisores')
                ->whereIn('supervisor_id', $supervisorIds)
                ->get(['supervisor_id', 'empleado_id']);
            foreach ($pivot as $row) {
                $porSupervisor[(int) $row->supervisor_id][] = (int) $row->empleado_id;
                $ids[] = (int) $row->empleado_id;
            }
        }

        $directos = NominaEmpleado::query()
            ->whereIn('supervisor_id', $supervisorIds)
            ->where('estado', 'ACTIVO')
            ->pluck('supervisor_id', 'id');
        foreach ($directos as $empleadoId => $supervisorId) {
            $porSupervisor[(int) $supervisorId][] = (int) $empleadoId;
            $ids[] = (int) $empleadoId;
        }

        $miembros = NominaEmpleado::query()
            ->with(['sedeCatalogo', 'vendedores'])
            ->whereIn('id', array_values(array_unique($ids)))
            ->where('estado', 'ACTIVO')
            ->get()
            ->keyBy('id');

        foreach ($porSupervisor as $supervisorId => $miembroIds) {
            $lista = [];
            foreach (array_unique($miembroIds) as $miembroId) {
                if ($miembros->has($miembroId)) {
                    $lista[] = $miembros->get($miembroId);
                }
            }
            $out[$supervisorId] = $lista;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function clavesDe(NominaEmpleado $empleado): array
    {
        return $empleado->codigosVendedor();
    }

    /**
     * @param  list<string>  $claves
     * @param  list<string>|null  $categorias
     * @param  list<string>|null  $productos
     * @return array<string, array{monto:float, unidades:float, clientes:int}>
     */
    private function ventasPorClave(array $claves, Carbon $desde, Carbon $hasta, ?array $categorias = null, ?array $productos = null): array
    {
        $out = [];
        $filtrar = $categorias !== null || $productos !== null;
        $categorias = $categorias ?? [];
        $productos = $productos ?? [];
        if ($claves === [] || ! Schema::hasTable('ventas_detalle') || ($filtrar && $categorias === [] && $productos === [])) {
            return $out;
        }

        $query = DB::table('ventas_detalle as vd')
            ->whereBetween('vd.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereIn('vd.tipo_documento', ['FAC', 'DEV'])
            ->whereIn(DB::raw('UPPER(TRIM(vd.vendedor))'), $claves);

        if ($filtrar) {
            $query->leftJoin('productos as p', function ($join) {
                $join->on(
                    DB::raw('UPPER(TRIM(p.codigo))'),
                    '=',
                    DB::raw("UPPER(TRIM(SPLIT_PART(COALESCE(vd.codigo_producto, ''), '/', 1)))")
                );
            })->where(function ($filtro) use ($categorias, $productos) {
                if ($categorias !== []) {
                    $filtro->whereIn(DB::raw("UPPER(TRIM(COALESCE(p.categoria, '')))"), $categorias);
                }
                if ($productos !== []) {
                    $metodo = $categorias === [] ? 'whereIn' : 'orWhereIn';
                    $codigoVenta = "UPPER(TRIM(SPLIT_PART(COALESCE(vd.codigo_producto, ''), '/', 1)))";
                    $filtro->{$metodo}(DB::raw($codigoVenta), $productos);
                    $filtro->orWhereIn(DB::raw("UPPER(TRIM(COALESCE(p.codigo, '')))"), $productos);
                }
            });
        }

        if (Schema::hasColumn('ventas_detalle', 'anulado')) {
            $query->where(function ($q) {
                $q->whereNull('vd.anulado')->orWhere('vd.anulado', false);
            });
        }

        $rows = $query
            ->selectRaw('UPPER(TRIM(vd.vendedor)) as clave')
            ->selectRaw("SUM(CASE WHEN vd.tipo_documento = 'DEV' THEN -ABS(COALESCE(vd.cantidad, 0)) ELSE COALESCE(vd.cantidad, 0) END) as unidades")
            ->selectRaw("SUM(CASE WHEN vd.tipo_documento = 'DEV' THEN -ABS(COALESCE(vd.cantidad, 0) * COALESCE(vd.precio_neto, vd.precio_venta, 0)) ELSE COALESCE(vd.cantidad, 0) * COALESCE(vd.precio_neto, vd.precio_venta, 0) END) as monto")
            ->selectRaw("COUNT(DISTINCT CASE WHEN vd.tipo_documento = 'FAC' AND NULLIF(TRIM(vd.cliente), '') IS NOT NULL THEN UPPER(TRIM(vd.cliente)) END) as clientes")
            ->groupByRaw('UPPER(TRIM(vd.vendedor))')
            ->get();

        foreach ($rows as $row) {
            $out[(string) $row->clave] = [
                'monto' => (float) $row->monto,
                'unidades' => (float) $row->unidades,
                'clientes' => (int) $row->clientes,
            ];
        }

        return $out;
    }

    /**
     * Unidades vendidas de las categorías o productos que están activos.
     * Una línea cuenta una sola vez aunque coincida en los dos.
     *
     * @param  list<string>  $claves
     * @param  list<string>  $categorias
     * @param  list<string>  $productos
     * @return array<string, array{unidades:float}>
     */
    private function crucePorClave(array $claves, Carbon $desde, Carbon $hasta, array $categorias, array $productos): array
    {
        if ($claves === [] || ($categorias === [] && $productos === []) || ! Schema::hasTable('ventas_detalle') || ! Schema::hasTable('productos')) {
            return [];
        }

        $query = DB::table('ventas_detalle as vd')
            ->leftJoin('productos as p', function ($join) {
                $join->on(
                    DB::raw('UPPER(TRIM(p.codigo))'),
                    '=',
                    DB::raw("UPPER(TRIM(SPLIT_PART(COALESCE(vd.codigo_producto, ''), '/', 1)))")
                );
            })
            ->whereBetween('vd.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereIn('vd.tipo_documento', ['FAC', 'DEV'])
            ->whereIn(DB::raw('UPPER(TRIM(vd.vendedor))'), $claves)
            ->where(function ($filtro) use ($categorias, $productos) {
                if ($categorias !== []) {
                    $filtro->whereIn(DB::raw("UPPER(TRIM(COALESCE(p.categoria, '')))"), $categorias);
                }
                if ($productos !== []) {
                    $metodo = $categorias === [] ? 'whereIn' : 'orWhereIn';
                    $codigoVenta = "UPPER(TRIM(SPLIT_PART(COALESCE(vd.codigo_producto, ''), '/', 1)))";
                    $filtro->{$metodo}(DB::raw($codigoVenta), $productos);
                    $filtro->orWhereIn(DB::raw("UPPER(TRIM(COALESCE(p.codigo, '')))"), $productos);
                }
            });

        if (Schema::hasColumn('ventas_detalle', 'anulado')) {
            $query->where(function ($q) {
                $q->whereNull('vd.anulado')->orWhere('vd.anulado', false);
            });
        }

        $rows = $query
            ->selectRaw('UPPER(TRIM(vd.vendedor)) as clave')
            ->selectRaw("SUM(CASE WHEN vd.tipo_documento = 'DEV' THEN -ABS(COALESCE(vd.cantidad, 0)) ELSE COALESCE(vd.cantidad, 0) END) as unidades")
            ->groupByRaw('UPPER(TRIM(vd.vendedor))')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->clave] = ['unidades' => (float) $row->unidades];
        }

        return $out;
    }

    /**
     * Unidades de cada categoría o producto activo, por vendedor.
     *
     * @param  list<string>  $claves
     * @param  list<array{tipo:string, clave:string, nombre:string}>  $grupos
     * @return array<string, array<string, float>>
     */
    private function cruceVendedores(array $claves, Carbon $desde, Carbon $hasta, array $grupos): array
    {
        $out = [];
        foreach ($grupos as $grupo) {
            foreach ($this->sumarCruce($desde, $hasta, $grupo, $claves) as $clave => $unidades) {
                $out[$clave][$grupo['clave']] = $unidades;
            }
        }

        return $out;
    }

    /**
     * Unidades de cada categoría o producto activo, por tienda.
     *
     * @param  list<array{tipo:string, clave:string, nombre:string}>  $grupos
     * @return array<string, array<string, float>>
     */
    private function cruceSedes(Carbon $desde, Carbon $hasta, array $grupos): array
    {
        $out = [];
        foreach ($grupos as $grupo) {
            foreach ($this->sumarCruce($desde, $hasta, $grupo, null) as $sede => $unidades) {
                $out[$sede][$grupo['clave']] = $unidades;
            }
        }

        return $out;
    }

    /**
     * @param  array{tipo:string, clave:string, nombre:string}  $grupo
     * @param  list<string>|null  $vendedores
     * @return array<string, float>
     */
    private function sumarCruce(Carbon $desde, Carbon $hasta, array $grupo, ?array $vendedores): array
    {
        if (! Schema::hasTable('ventas_detalle') || ! Schema::hasTable('productos')) {
            return [];
        }
        if ($vendedores !== null && $vendedores === []) {
            return [];
        }

        $query = DB::table('ventas_detalle as vd')
            ->leftJoin('productos as p', function ($join) {
                $join->on(
                    DB::raw('UPPER(TRIM(p.codigo))'),
                    '=',
                    DB::raw("UPPER(TRIM(SPLIT_PART(COALESCE(vd.codigo_producto, ''), '/', 1)))")
                );
            })
            ->whereBetween('vd.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereIn('vd.tipo_documento', ['FAC', 'DEV']);

        if ($grupo['tipo'] === 'producto') {
            $codigoVenta = "UPPER(TRIM(SPLIT_PART(COALESCE(vd.codigo_producto, ''), '/', 1)))";
            $query->where(function ($filtro) use ($grupo, $codigoVenta) {
                $filtro->whereRaw($codigoVenta.' = ?', [$grupo['clave']])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(p.codigo, ''))) = ?", [$grupo['clave']]);
            });
        } else {
            $query->whereRaw("UPPER(TRIM(COALESCE(p.categoria, ''))) = ?", [$grupo['clave']]);
        }

        if ($vendedores !== null) {
            $query->whereIn(DB::raw('UPPER(TRIM(vd.vendedor))'), $vendedores);
        }
        if (Schema::hasColumn('ventas_detalle', 'anulado')) {
            $query->where(function ($q) {
                $q->whereNull('vd.anulado')->orWhere('vd.anulado', false);
            });
        }

        $campo = $vendedores === null ? 'UPPER(TRIM(vd.sede))' : 'UPPER(TRIM(vd.vendedor))';
        $rows = $query
            ->selectRaw($campo.' as clave')
            ->selectRaw("SUM(CASE WHEN vd.tipo_documento = 'DEV' THEN -ABS(COALESCE(vd.cantidad, 0)) ELSE COALESCE(vd.cantidad, 0) END) as unidades")
            ->groupByRaw($campo)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $clave = (string) $row->clave;
            if ($clave === '') {
                continue;
            }
            $out[$clave] = (float) $row->unidades;
        }

        return $out;
    }

    /**
     * Lo que vendió cada tienda en el mes. En telefonía ese total es el 100%.
     *
     * @param  list<string>  $categorias
     * @param  list<string>  $productos
     * @return array<string, array{monto:float, unidades:float, clientes:int, cruce:float}>
     */
    private function totalesTienda(Carbon $desde, Carbon $hasta, array $categorias, array $productos, bool $medirSeleccion = false): array
    {
        $out = [];
        if (! Schema::hasTable('ventas_detalle')) {
            return $out;
        }

        $query = DB::table('ventas_detalle as vd')
            ->whereBetween('vd.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereIn('vd.tipo_documento', ['FAC', 'DEV']);
        if ($medirSeleccion && ($categorias !== [] || $productos !== []) && Schema::hasTable('productos')) {
            $query->leftJoin('productos as p', function ($join) {
                $join->on(
                    DB::raw('UPPER(TRIM(p.codigo))'),
                    '=',
                    DB::raw("UPPER(TRIM(SPLIT_PART(COALESCE(vd.codigo_producto, ''), '/', 1)))")
                );
            })->where(function ($filtro) use ($categorias, $productos) {
                if ($categorias !== []) {
                    $filtro->whereIn(DB::raw("UPPER(TRIM(COALESCE(p.categoria, '')))"), $categorias);
                }
                if ($productos !== []) {
                    $metodo = $categorias === [] ? 'whereIn' : 'orWhereIn';
                    $codigoVenta = "UPPER(TRIM(SPLIT_PART(COALESCE(vd.codigo_producto, ''), '/', 1)))";
                    $filtro->{$metodo}(DB::raw($codigoVenta), $productos);
                    $filtro->orWhereIn(DB::raw("UPPER(TRIM(COALESCE(p.codigo, '')))"), $productos);
                }
            });
        }
        if (Schema::hasColumn('ventas_detalle', 'anulado')) {
            $query->where(function ($q) {
                $q->whereNull('vd.anulado')->orWhere('vd.anulado', false);
            });
        }

        $rows = $query
            ->selectRaw('UPPER(TRIM(vd.sede)) as sede')
            ->selectRaw("SUM(CASE WHEN vd.tipo_documento = 'DEV' THEN -ABS(COALESCE(vd.cantidad, 0)) ELSE COALESCE(vd.cantidad, 0) END) as unidades")
            ->selectRaw("SUM(CASE WHEN vd.tipo_documento = 'DEV' THEN -ABS(COALESCE(vd.cantidad, 0) * COALESCE(vd.precio_neto, vd.precio_venta, 0)) ELSE COALESCE(vd.cantidad, 0) * COALESCE(vd.precio_neto, vd.precio_venta, 0) END) as monto")
            ->selectRaw("COUNT(DISTINCT CASE WHEN vd.tipo_documento = 'FAC' AND NULLIF(TRIM(vd.cliente), '') IS NOT NULL THEN UPPER(TRIM(vd.cliente)) END) as clientes")
            ->groupByRaw('UPPER(TRIM(vd.sede))')
            ->get();

        foreach ($rows as $row) {
            $sede = (string) $row->sede;
            if ($sede === '') {
                continue;
            }
            $out[$sede] = [
                'monto' => (float) $row->monto,
                'unidades' => (float) $row->unidades,
                'clientes' => (int) $row->clientes,
                'cruce' => 0.0,
            ];
        }

        if ($medirSeleccion || ($categorias === [] && $productos === []) || ! Schema::hasTable('productos')) {
            return $out;
        }

        $cruce = DB::table('ventas_detalle as vd')
            ->leftJoin('productos as p', function ($join) {
                $join->on(
                    DB::raw('UPPER(TRIM(p.codigo))'),
                    '=',
                    DB::raw("UPPER(TRIM(SPLIT_PART(COALESCE(vd.codigo_producto, ''), '/', 1)))")
                );
            })
            ->whereBetween('vd.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereIn('vd.tipo_documento', ['FAC', 'DEV'])
            ->where(function ($filtro) use ($categorias, $productos) {
                if ($categorias !== []) {
                    $filtro->whereIn(DB::raw("UPPER(TRIM(COALESCE(p.categoria, '')))"), $categorias);
                }
                if ($productos !== []) {
                    $metodo = $categorias === [] ? 'whereIn' : 'orWhereIn';
                    $codigoVenta = "UPPER(TRIM(SPLIT_PART(COALESCE(vd.codigo_producto, ''), '/', 1)))";
                    $filtro->{$metodo}(DB::raw($codigoVenta), $productos);
                    $filtro->orWhereIn(DB::raw("UPPER(TRIM(COALESCE(p.codigo, '')))"), $productos);
                }
            });
        if (Schema::hasColumn('ventas_detalle', 'anulado')) {
            $cruce->where(function ($q) {
                $q->whereNull('vd.anulado')->orWhere('vd.anulado', false);
            });
        }

        foreach ($cruce
            ->selectRaw('UPPER(TRIM(vd.sede)) as sede')
            ->selectRaw("SUM(CASE WHEN vd.tipo_documento = 'DEV' THEN -ABS(COALESCE(vd.cantidad, 0)) ELSE COALESCE(vd.cantidad, 0) END) as unidades")
            ->groupByRaw('UPPER(TRIM(vd.sede))')
            ->get() as $row) {
            $sede = (string) $row->sede;
            if ($sede === '') {
                continue;
            }
            $out[$sede] ??= ['monto' => 0.0, 'unidades' => 0.0, 'clientes' => 0, 'cruce' => 0.0];
            $out[$sede]['cruce'] = (float) $row->unidades;
        }

        return $out;
    }

    /**
     * @param  array<string, array{monto:float, unidades:float, clientes:int}>  $ventas
     * @param  array<string, VentaDiariaMeta>  $metas
     * @param  array<string, array{unidades:float}>  $cruces
     * @param  array<string, array{cumplidas:int, total:int, estado:?string}>  $productosMeta
     * @param  array<string, array{monto:float, unidades:float, clientes:int, cruce:float, cruces?:array<string, float>}>|null  $tiendas
     * @param  list<array{tipo:string, clave:string, nombre:string}>  $gruposCruce
     * @param  array<string, array<string, float>>  $crucesPorItem
     * @return array<string, mixed>
     */
    private function filaPersona(NominaEmpleado $persona, array $ventas, array $metas, array $cruces, bool $conCruce, bool $cruceActivo, array $productosMeta, bool $sinMedicion = false, ?array $tiendas = null, array $gruposCruce = [], array $crucesPorItem = [], ?string $formula = null): array
    {
        $suma = $sinMedicion
            ? ['monto' => null, 'unidades' => null, 'clientes' => null]
            : $this->sumarClaves($this->clavesDe($persona), $ventas);
        $meta = $metas[$this->sedeCodigo($persona)] ?? null;
        $tienda = null;
        if ($tiendas !== null) {
            $tienda = $tiendas[$this->sedeCodigo($persona)] ?? ['monto' => 0.0, 'unidades' => 0.0, 'clientes' => 0, 'cruce' => 0.0, 'cruces' => []];
            $tienda['cruces'] ??= [];
        }

        $columnasCruce = [];
        $cruceBase = null;
        $cruceUnidades = null;
        if ($conCruce) {
            $cruceBase = $cruceActivo ? 'Activo' : 'Desactivado';
            if ($gruposCruce === []) {
                $columnasCruce[] = [
                    'clave' => '',
                    'nombre' => 'Sin categoría',
                    'unidades' => null,
                    'pct' => null,
                    'estado' => 'Desactivado',
                ];
            } else {
                foreach ($gruposCruce as $grupo) {
                    $unidades = 0.0;
                    foreach ($this->clavesDe($persona) as $codigo) {
                        $unidades += $crucesPorItem[$codigo][$grupo['clave']] ?? 0;
                    }
                    $totalTienda = (float) ($tienda['cruces'][$grupo['clave']] ?? 0);
                    $columnasCruce[] = [
                        'clave' => $grupo['clave'],
                        'nombre' => $grupo['nombre'],
                        'unidades' => $unidades,
                        'pct' => $this->porcentaje($unidades, $totalTienda),
                        'estado' => 'Activo',
                    ];
                }
            }
            while (count($columnasCruce) < 3) {
                $columnasCruce[] = [
                    'clave' => '',
                    'nombre' => 'Sin categoría',
                    'unidades' => null,
                    'pct' => null,
                    'estado' => 'Desactivado',
                ];
            }
        }

        return $this->armarFila(
            $persona->nombre(),
            $persona->nombreCargo(),
            $persona->nombreSede(),
            $this->clavesDe($persona) === [],
            $suma,
            $meta,
            $cruceBase,
            $cruceUnidades,
            null,
            $productosMeta[$persona->id] ?? ['cumplidas' => null, 'total' => null, 'estado' => null],
            $sinMedicion,
            $tienda,
            $columnasCruce,
            $formula,
        );
    }

    /**
     * @param  list<NominaEmpleado>  $equipo
     * @param  array<string, array{monto:float, unidades:float, clientes:int}>  $ventas
     * @param  array<string, VentaDiariaMeta>  $metas
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, array{cumplidas:int, total:int, estado:?string}>  $productosMeta
     */
    private function filaSupervisor(NominaEmpleado $persona, array $equipo, array $ventas, array $metas, array $productosMeta): array
    {
        $claves = [];
        foreach ($equipo as $miembro) {
            foreach ($this->clavesDe($miembro) as $codigo) {
                $claves[] = $codigo;
            }
        }
        $suma = $this->sumarClaves(array_values(array_unique($claves)), $ventas);
        $meta = $metas[$this->sedeCodigo($persona)] ?? null;
        if (! $meta) {
            foreach ($equipo as $miembro) {
                if ($encontrada = $metas[$this->sedeCodigo($miembro)] ?? null) {
                    $meta = $encontrada;
                    break;
                }
            }
        }

        return $this->armarFila(
            $persona->nombre(),
            $persona->nombreCargo(),
            $persona->nombreSede(),
            false,
            $suma,
            $meta,
            null,
            0,
            count($equipo),
            $productosMeta[$persona->id] ?? ['cumplidas' => null, 'total' => null, 'estado' => null],
        );
    }

    /**
     * @param  list<string>  $claves
     * @param  array<string, array{monto:float, unidades:float, clientes:int}>  $ventas
     * @return array{monto:float, unidades:float, clientes:int}
     */
    private function sumarClaves(array $claves, array $ventas): array
    {
        $suma = ['monto' => 0.0, 'unidades' => 0.0, 'clientes' => 0];
        foreach ($claves as $codigo) {
            if (! isset($ventas[$codigo])) {
                continue;
            }
            $suma['monto'] += $ventas[$codigo]['monto'];
            $suma['unidades'] += $ventas[$codigo]['unidades'];
            $suma['clientes'] += $ventas[$codigo]['clientes'];
        }

        return $suma;
    }

    /**
     * @param  array{monto:?float, unidades:?float, clientes:?int}  $suma
     * @param  array{monto:float, unidades:float, clientes:int, cruce:float, cruces?:array<string, float>}|null  $tienda
     * @param  list<array{clave:string, nombre:string, unidades:?float, pct:?float, estado:string}>  $cruceColumnas
     * @return array<string, mixed>
     */
    private function armarFila(
        string $nombre,
        string $cargo,
        string $sede,
        bool $sinCodigo,
        array $suma,
        ?VentaDiariaMeta $meta,
        ?string $cruceBase,
        ?float $cruceUnidades,
        ?int $equipo,
        array $productosMeta = ['cumplidas' => null, 'total' => null, 'estado' => null],
        bool $sinMedicion = false,
        ?array $tienda = null,
        array $cruceColumnas = [],
        ?string $formula = null,
    ): array {
        $referenciaTienda = $tienda !== null;
        $pctVenta = $sinMedicion ? null : $this->porcentaje(
            (float) $suma['monto'],
            $referenciaTienda ? (float) $tienda['monto'] : ($meta ? (float) $meta->venta_meta_mes : null),
        );
        $pctProductos = $sinMedicion ? null : $this->porcentaje(
            (float) $suma['unidades'],
            $referenciaTienda ? (float) $tienda['unidades'] : ($meta ? (float) $meta->productos_meta_mes : null),
        );
        $pctClientes = $sinMedicion ? null : $this->porcentaje(
            (float) $suma['clientes'],
            $referenciaTienda ? (float) $tienda['clientes'] : ($meta ? (float) $meta->clientes_meta_mes : null),
        );
        $crucePct = null;
        $pisoPct = null;
        $metaProdPct = null;
        $puntuacion = null;
        if ($referenciaTienda && ! $sinMedicion) {
            $activas = array_values(array_filter($cruceColumnas, fn (array $col) => ($col['estado'] ?? '') === 'Activo'));
            if ($activas !== []) {
                $sumaCruce = 0.0;
                foreach ($activas as $col) {
                    $sumaCruce += (float) ($col['pct'] ?? 0);
                }
                $crucePct = round($sumaCruce / count($activas), 1);
            }
            $pisoPct = round((($pctVenta ?? 0) + ($pctProductos ?? 0) + ($pctClientes ?? 0)) / 3, 1);
            $totalMeta = (int) ($productosMeta['total'] ?? 0);
            $metaProdPct = $totalMeta > 0
                ? round(((int) $productosMeta['cumplidas'] / $totalMeta) * 100, 1)
                : 0.0;
            if ($formula === 'piso') {
                $puntuacion = round(
                    $this->acotarCien($pisoPct) * 0.70
                    + $this->acotarCien($metaProdPct) * 0.30,
                    1
                );
            } else {
                $puntuacion = round(
                    $this->acotarCien($pisoPct) * 0.30
                    + $this->acotarCien($crucePct ?? 0) * 0.50
                    + $this->acotarCien($metaProdPct) * 0.20,
                    1
                );
            }
        } elseif (! $referenciaTienda) {
            $partes = array_filter([$pctVenta, $pctProductos, $pctClientes], fn ($valor) => $valor !== null);
            $metaPct = $partes === [] ? null : array_sum(array_map(fn ($valor) => $this->acotar($valor), $partes)) / count($partes);
            if ($pctVenta !== null || $pctProductos !== null || $pctClientes !== null) {
                $usados = array_filter([
                    $pctVenta !== null ? $this->acotar($pctVenta) : null,
                    $pctProductos !== null ? $this->acotar($pctProductos) : null,
                    $pctClientes !== null ? $this->acotar($pctClientes) : null,
                    $metaPct,
                ], fn ($valor) => $valor !== null);
                $puntuacion = round(array_sum($usados) / count($usados), 1);
            }
        }

        return [
            'nombre' => $nombre,
            'cargo' => $cargo,
            'sede' => $sede,
            'sin_codigo' => $sinCodigo,
            'monto' => $suma['monto'],
            'meta_venta' => $referenciaTienda ? (float) $tienda['monto'] : ($meta ? (float) $meta->venta_meta_mes : null),
            'pct_venta' => $pctVenta,
            'unidades' => $suma['unidades'],
            'meta_productos' => $referenciaTienda ? (float) $tienda['unidades'] : ($meta ? (float) $meta->productos_meta_mes : null),
            'pct_productos' => $pctProductos,
            'clientes' => $suma['clientes'],
            'meta_clientes' => $referenciaTienda ? (float) $tienda['clientes'] : ($meta ? (float) $meta->clientes_meta_mes : null),
            'pct_clientes' => $pctClientes,
            'cruce_base' => $cruceBase,
            'cruce_unidades' => $cruceUnidades,
            'cruce_pct' => $crucePct,
            'cruces' => $cruceColumnas,
            'piso_pct' => $pisoPct,
            'meta_prod_pct' => $metaProdPct,
            'equipo' => $equipo,
            'puntuacion' => $puntuacion,
            'tramo' => $referenciaTienda ? null : $this->tramo($puntuacion),
            'meta_prod_cumplidas' => $productosMeta['cumplidas'],
            'meta_prod_total' => $productosMeta['total'],
            'meta_prod_estado' => $productosMeta['estado'],
        ];
    }

    /**
     * La meta de producto asignada a cada persona en la quincena.
     * En Arreglo solo entra si además está en la selección activa.
     *
     * @param  array{categorias:list<string>, productos:list<string>}|null  $filtro
     * @return array<int, array{cumplidas:int, total:int, estado:string}>
     */
    private function productosMetaPorEmpleado(?array $filtro, bool $soloSeleccion): array
    {
        if ($soloSeleccion && ($filtro === null || ($filtro['categorias'] === [] && $filtro['productos'] === []))) {
            return [];
        }
        if (! Schema::hasTable('meta_quincena_productos') || ! Schema::hasTable('productos') || ! Schema::hasTable('ventas_detalle')) {
            return [];
        }

        $quincena = $this->metasQuincena->resolverQuincenaListado(null);
        $inicio = $quincena['inicio']->toDateString();
        $fin = $quincena['fin']->toDateString();
        $categorias = $filtro['categorias'] ?? [];
        $codigos = $filtro['productos'] ?? [];

        $metas = DB::table('meta_quincena_productos as m')
            ->join('productos as p', 'p.id', '=', 'm.producto_id')
            ->whereDate('m.quincena_inicio', $inicio)
            ->whereNotNull('m.responsable_empleado_id')
            ->get(['m.producto_id', 'm.sede', 'm.cantidad_inicial', 'm.responsable_empleado_id', 'p.codigo', 'p.categoria']);

        if ($metas->isEmpty()) {
            return [];
        }

        $ids = $metas->pluck('producto_id')->unique()->values()->all();
        $ventas = DB::table('ventas_detalle')
            ->whereIn('producto_id', $ids)
            ->whereBetween('fecha', [$inicio, $fin])
            ->when(Schema::hasColumn('ventas_detalle', 'anulado'), function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('anulado')->orWhere('anulado', false);
                });
            })
            ->selectRaw('producto_id')
            ->selectRaw('UPPER(TRIM(sede)) as sede')
            ->selectRaw("SUM(CASE WHEN UPPER(COALESCE(tipo_documento, '')) = 'DEV' THEN -ABS(COALESCE(cantidad, 0)) ELSE ABS(COALESCE(cantidad, 0)) END) as unidades")
            ->groupBy('producto_id', DB::raw('UPPER(TRIM(sede))'))
            ->get();

        $vendido = [];
        foreach ($ventas as $venta) {
            $vendido[(int) $venta->producto_id.'|'.(string) $venta->sede] = (float) $venta->unidades;
        }

        $porEmpleado = [];
        foreach ($metas as $meta) {
            $categoria = mb_strtoupper(trim((string) $meta->categoria));
            $codigo = mb_strtoupper(trim((string) $meta->codigo));
            if ($filtro !== null && ! in_array($categoria, $categorias, true) && ! in_array($codigo, $codigos, true)) {
                continue;
            }
            $empleadoId = (int) $meta->responsable_empleado_id;
            $sede = mb_strtoupper(trim((string) $meta->sede));
            $inicial = (float) $meta->cantidad_inicial;
            $unidades = $vendido[(int) $meta->producto_id.'|'.$sede] ?? 0.0;
            $cumplida = $inicial > 0 ? $unidades >= $inicial : $unidades > 0;
            $porEmpleado[$empleadoId]['cumplidas'] = ($porEmpleado[$empleadoId]['cumplidas'] ?? 0) + ($cumplida ? 1 : 0);
            $porEmpleado[$empleadoId]['total'] = ($porEmpleado[$empleadoId]['total'] ?? 0) + 1;
        }

        foreach ($porEmpleado as $empleadoId => $dato) {
            $porEmpleado[$empleadoId]['estado'] = $dato['total'] > 0 && $dato['cumplidas'] === $dato['total']
                ? 'Cumplida'
                : 'No cumplió';
        }

        return $porEmpleado;
    }

    /**
     * Siempre hay tres espacios. Cada categoría activa ocupa uno y el resto queda para elegir otra.
     *
     * @param  list<array{tipo:string, clave:string, nombre:string}>  $grupos
     * @return list<array{tipo:string, clave:string, nombre:string}>
     */
    private function gruposCruceVisibles(array $grupos): array
    {
        $n = count($grupos);
        while (count($grupos) < 3) {
            $n++;
            $grupos[] = ['tipo' => '', 'clave' => '', 'nombre' => 'Categoría '.$n];
        }

        return $grupos;
    }

    private function sedeCodigo(NominaEmpleado $empleado): string
    {
        $codigo = $empleado->sedeCatalogo?->codigo ?: $empleado->sede;

        return strtoupper(trim((string) $codigo));
    }

    private function porcentaje(float $real, ?float $meta): ?float
    {
        if ($meta === null || $meta <= 0) {
            return null;
        }

        return round($real / $meta * 100, 1);
    }

    private function acotar(float $porcentaje): float
    {
        return min($porcentaje, 120);
    }

    private function acotarCien(float $porcentaje): float
    {
        return min(max($porcentaje, 0), 100);
    }

    private function tramo(?float $puntuacion): ?string
    {
        if ($puntuacion === null) {
            return null;
        }
        if ($puntuacion < 70) {
            return '0%';
        }
        if ($puntuacion < 85) {
            return '50%';
        }
        if ($puntuacion < 100) {
            return '80%';
        }
        if ($puntuacion < 110) {
            return '100%';
        }

        return '120%';
    }

    private function texto(string $valor): string
    {
        $valor = mb_strtolower(trim($valor), 'UTF-8');

        return strtr($valor, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ]);
    }
}
