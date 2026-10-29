<?php

namespace App\Services;

use App\Models\Nomina\NominaEmpleado;
use App\Models\VentaDiariaMeta;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
    public function evaluar(string $clave, array $modulo, ?Carbon $desde = null, ?Carbon $hasta = null): array
    {
        $desde = ($desde ?? now()->startOfMonth())->copy()->startOfDay();
        $hasta = ($hasta ?? now())->copy()->endOfDay();
        if ($hasta->lt($desde)) {
            $hasta = $desde->copy()->endOfDay();
        }
        if ($clave === 'supervisor') {
            return $this->evaluarSedes($desde, $hasta);
        }
        $personas = $this->personas($modulo['cargos'] ?? []);
        $metas = $this->metasPorSede();
        $claves = [];
        foreach ($personas as $persona) {
            foreach ($this->clavesDe($persona) as $codigo) {
                $claves[$codigo] = true;
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
            if ($clave === 'call_center') {
                $tiendas = $this->totalesEquipo($personas, $ventas);
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
            $filas[] = $this->filaPersona($persona, $ventas, $metas, $cruces, $clave === 'telefonia', $cruceActivo, $productosMeta, $sinMedicion, $tiendas, $gruposCruce, $crucesPorItem, match ($clave) {
                'telefonia' => 'telefonia',
                'ventas', 'arreglos', 'call_center' => 'piso',
                default => null,
            });
        }

        usort($filas, fn (array $a, array $b) => $this->porNotaDesc($a, $b));

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
                    $codigos = [];
                    foreach ($productos as $prod) {
                        $prod = mb_strtoupper(trim((string) $prod));
                        $codigos[] = $prod;
                        $codigos[] = mb_strtoupper(trim(explode('/', $prod)[0]));
                    }
                    $codigos = array_values(array_unique(array_filter($codigos)));
                    $metodo = $categorias === [] ? 'where' : 'orWhere';
                    $codigoVenta = "UPPER(TRIM(SPLIT_PART(COALESCE(vd.codigo_producto, ''), '/', 1)))";
                    $filtro->{$metodo}(function ($q) use ($codigos, $codigoVenta) {
                        $q->whereIn(DB::raw($codigoVenta), $codigos)
                            ->orWhereIn(DB::raw("UPPER(TRIM(COALESCE(p.codigo, '')))"), $codigos)
                            ->orWhereIn(DB::raw("UPPER(TRIM(SPLIT_PART(COALESCE(p.codigo, ''), '/', 1)))"), $codigos);
                    });
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
            // Clave puede venir como "2311545510212 / VT06"; en ventas suele ir solo el primer tramo.
            $clave = mb_strtoupper(trim((string) $grupo['clave']));
            $clavePrimario = mb_strtoupper(trim(explode('/', $clave)[0]));
            $nombre = mb_strtoupper(trim((string) ($grupo['nombre'] ?? '')));
            $codigoVenta = "UPPER(TRIM(SPLIT_PART(COALESCE(vd.codigo_producto, ''), '/', 1)))";
            $query->where(function ($filtro) use ($clave, $clavePrimario, $nombre, $codigoVenta) {
                $filtro->whereRaw($codigoVenta.' = ?', [$clave])
                    ->orWhereRaw($codigoVenta.' = ?', [$clavePrimario])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(p.codigo, ''))) = ?", [$clave])
                    ->orWhereRaw("UPPER(TRIM(SPLIT_PART(COALESCE(p.codigo, ''), '/', 1))) = ?", [$clavePrimario]);
                // Si activaron "VIDRIO BLINDADO" buscando un SKU, contar todos los del mismo nombre.
                if ($nombre !== '' && $nombre !== $clave && $nombre !== $clavePrimario) {
                    $filtro->orWhereRaw("UPPER(TRIM(COALESCE(p.nombre, ''))) = ?", [$nombre])
                        ->orWhereRaw("UPPER(TRIM(COALESCE(vd.nombre_producto, ''))) = ?", [$nombre]);
                }
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
     * Lo que vendió todo el equipo, como si fuera una sola tienda: en call center ese total es el 100%.
     * Se guarda bajo la sede de cada persona porque la fila busca su "tienda" por sede.
     *
     * @param  iterable<NominaEmpleado>  $personas
     * @param  array<string, array{monto:float, unidades:float, clientes:int}>  $ventas
     * @return array<string, array{monto:float, unidades:float, clientes:int, cruce:float}>
     */
    private function totalesEquipo(iterable $personas, array $ventas): array
    {
        $claves = [];
        foreach ($personas as $persona) {
            foreach ($this->clavesDe($persona) as $codigo) {
                $claves[$codigo] = true;
            }
        }
        $total = $this->sumarClaves(array_keys($claves), $ventas) + ['cruce' => 0.0];

        $out = [];
        foreach ($personas as $persona) {
            $out[$this->sedeCodigo($persona)] = $total;
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
     * Una fila por sede. El real es lo que vendió la tienda y la meta es la de esa tienda.
     *
     * @return array{desde:string, hasta:string, filas:list<array<string, mixed>>, grupos_cruce:list<empty>}
     */
    private function evaluarSedes(Carbon $desde, Carbon $hasta): array
    {
        $metas = $this->metasPorSede();
        $tiendas = $this->totalesTienda($desde, $hasta, [], []);
        $productos = $this->productosMetaPorSede();
        $nombres = $this->nombresSede();
        $nivelacion = $this->nivelacionPorSede($desde, $hasta);
        $inventario = $this->rotacionInventarioPorSede($desde, $hasta);
        $faltante = $this->faltantePorSede($desde, $hasta);
        $cruce = $this->cruceEfectividadPorSede($desde, $hasta, $tiendas);
        $filas = [];
        foreach (config('inventario.sedes_locales', []) as $codigo) {
            $codigo = strtoupper(trim((string) $codigo));
            if ($codigo === '') {
                continue;
            }
            $suma = $tiendas[$codigo] ?? ['monto' => 0.0, 'unidades' => 0.0, 'clientes' => 0];
            $fila = $this->armarFila(
                $nombres[$codigo] ?? $codigo,
                '',
                $nombres[$codigo] ?? $codigo,
                false,
                [
                    'monto' => (float) $suma['monto'],
                    'unidades' => (float) $suma['unidades'],
                    'clientes' => (int) $suma['clientes'],
                ],
                $metas[$codigo] ?? null,
                null,
                null,
                null,
                $productos[$codigo] ?? ['cumplidas' => null, 'total' => null, 'estado' => null],
            );
            $fila['metas_pct'] = $fila['puntuacion'];
            $fila['nivelacion_pct'] = $nivelacion[$codigo] ?? null;
            $fila['faltante_pct'] = $faltante['por_sede'][$codigo] ?? ($faltante['total'] > 0 ? 0.0 : null);
            $fila['cruce_pct'] = $cruce[$codigo] ?? null;
            $fila['inventario_pct'] = $inventario[$codigo] ?? null;
            $fila['puntuacion'] = $this->notaSede($fila);
            $fila['tramo'] = $this->tramo($fila['puntuacion']);
            $filas[] = $fila;
        }

        usort($filas, fn (array $a, array $b) => $this->porNotaDesc($a, $b));

        return [
            'desde' => $desde->format('d/m/Y'),
            'hasta' => $hasta->format('d/m/Y'),
            'filas' => $filas,
            'grupos_cruce' => [],
        ];
    }

    /**
     * Un mismo rango para todos los módulos de incentivos.
     *
     * @return array{inicio: Carbon, fin: Carbon, desde: string, hasta: string}
     */
    public function periodo(Request $request): array
    {
        $guardado = $request->session()->get('incentivos.periodo', []);
        $desdeIn = $request->query('desde', $guardado['desde'] ?? null);
        $hastaIn = $request->query('hasta', $guardado['hasta'] ?? null);
        $desde = $this->fechaPeriodo(is_string($desdeIn) ? $desdeIn : null) ?? now()->startOfMonth();
        $hasta = $this->fechaPeriodo(is_string($hastaIn) ? $hastaIn : null) ?? now();
        $desde = $desde->copy()->startOfDay();
        $hasta = $hasta->copy()->endOfDay();
        if ($hasta->lt($desde)) {
            $hasta = $desde->copy()->endOfDay();
        }

        $iso = [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
        ];
        $request->session()->put('incentivos.periodo', $iso);

        return [
            'inicio' => $desde,
            'fin' => $hasta,
            'desde' => $iso['desde'],
            'hasta' => $iso['hasta'],
        ];
    }

    private function fechaPeriodo(?string $valor): ?Carbon
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }
        try {
            return Carbon::parse($valor);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function porNotaDesc(array $a, array $b): int
    {
        $pa = $a['puntuacion'] ?? null;
        $pb = $b['puntuacion'] ?? null;
        if ($pa === null && $pb === null) {
            return strcasecmp((string) ($a['nombre'] ?? ''), (string) ($b['nombre'] ?? ''));
        }
        if ($pa === null) {
            return 1;
        }
        if ($pb === null) {
            return -1;
        }

        return ((float) $pb <=> (float) $pa) ?: strcasecmp((string) ($a['nombre'] ?? ''), (string) ($b['nombre'] ?? ''));
    }

    /**
     * @return array<string, string>
     */
    private function nombresSede(): array
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
     * De los productos que otra sede tiene con más de 20, cuántos también tiene esta sede.
     * Si aquí está en cero, no suma.
     *
     * @return array<string, float>
     */
    private function nivelacionPorSede(Carbon $desde, Carbon $hasta): array
    {
        if (! Schema::hasTable('stock_actual')) {
            return [];
        }

        $sedes = $this->codigosSede();
        if ($sedes === []) {
            return [];
        }

        $marcas = implode(',', array_fill(0, count($sedes), '?'));
        $valores = implode(',', array_fill(0, count($sedes), '(?)'));
        $sql = "
            WITH stock AS (
                SELECT producto_id,
                       UPPER(TRIM(sede)) AS sede,
                       SUM(GREATEST(COALESCE(existencia, 0), 0)) AS stock
                FROM stock_actual
                WHERE producto_id IS NOT NULL
                  AND UPPER(TRIM(sede)) IN ({$marcas})
                GROUP BY producto_id, UPPER(TRIM(sede))
            ),
            sedes_eval AS (
                SELECT UPPER(TRIM(sede)) AS sede
                FROM (VALUES {$valores}) AS t(sede)
            ),
            universo AS (
                SELECT e.sede,
                       o.producto_id,
                       COALESCE(m.stock, 0) AS mio
                FROM sedes_eval e
                JOIN stock o ON o.sede <> e.sede AND o.stock > 20
                LEFT JOIN stock m ON m.sede = e.sede AND m.producto_id = o.producto_id
            )
            SELECT sede,
                   COUNT(DISTINCT producto_id) AS universo,
                   COUNT(DISTINCT CASE WHEN mio <= 0 THEN producto_id END) AS en_cero
            FROM universo
            GROUP BY sede
        ";

        $out = [];
        foreach (DB::select($sql, array_merge($sedes, $sedes)) as $row) {
            $universo = (int) $row->universo;
            if ($universo <= 0) {
                continue;
            }
            $conExistencia = $universo - (int) $row->en_cero;
            $out[(string) $row->sede] = round(max(0, min(100, $conExistencia / $universo * 100)), 1);
        }

        return $out;
    }

    /**
     * Productos con existencia que además se vendieron en el mes.
     *
     * @return array<string, float>
     */
    private function rotacionInventarioPorSede(Carbon $desde, Carbon $hasta): array
    {
        if (! Schema::hasTable('stock_actual') || ! Schema::hasTable('ventas_detalle') || ! Schema::hasColumn('ventas_detalle', 'producto_id')) {
            return [];
        }

        $anulado = Schema::hasColumn('ventas_detalle', 'anulado')
            ? 'AND (vd.anulado IS NULL OR vd.anulado = false)'
            : '';
        $sql = "
            WITH stock AS (
                SELECT UPPER(TRIM(sede)) AS sede, producto_id
                FROM stock_actual
                WHERE COALESCE(existencia, 0) > 0 AND producto_id IS NOT NULL
                GROUP BY UPPER(TRIM(sede)), producto_id
            ),
            vendidos AS (
                SELECT DISTINCT UPPER(TRIM(vd.sede)) AS sede, vd.producto_id
                FROM ventas_detalle vd
                WHERE vd.fecha BETWEEN ? AND ?
                  AND vd.tipo_documento = 'FAC'
                  AND COALESCE(vd.cantidad, 0) > 0
                  AND vd.producto_id IS NOT NULL
                  {$anulado}
            )
            SELECT s.sede, COUNT(*) AS con_stock, COUNT(v.producto_id) AS rotaron
            FROM stock s
            LEFT JOIN vendidos v ON v.sede = s.sede AND v.producto_id = s.producto_id
            GROUP BY s.sede
        ";

        $out = [];
        foreach (DB::select($sql, [$desde->toDateString(), $hasta->toDateString()]) as $row) {
            $conStock = (int) $row->con_stock;
            $out[(string) $row->sede] = $conStock > 0
                ? round(((int) $row->rotaron / $conStock) * 100, 1)
                : null;
        }

        return $out;
    }

    /**
     * El total de faltante del mes es el 100%. El de la sede sale negativo.
     *
     * @return array{total:float, por_sede:array<string, float>}
     */
    private function faltantePorSede(Carbon $desde, Carbon $hasta): array
    {
        $vacio = ['total' => 0.0, 'por_sede' => []];
        if (! Schema::hasTable('nomina_comision_descuentos') || ! Schema::hasTable('nomina_empleados')) {
            return $vacio;
        }

        $sedeExpr = Schema::hasTable('nomina_sedes')
            ? "UPPER(TRIM(COALESCE(ns.codigo, e.sede, '')))"
            : "UPPER(TRIM(COALESCE(e.sede, '')))";
        $query = DB::table('nomina_comision_descuentos as d')
            ->join('nomina_empleados as e', 'e.id', '=', 'd.empleado_id')
            ->where('d.tipo', 'FALTANTE')
            ->where('d.estado', '!=', 'CANCELADO')
            ->whereBetween('d.fecha', [$desde->toDateString(), $hasta->toDateString()]);
        if (Schema::hasTable('nomina_sedes')) {
            $query->leftJoin('nomina_sedes as ns', 'ns.id', '=', 'e.sede_id');
        }

        $porMonto = [];
        $total = 0.0;
        foreach ($query->selectRaw($sedeExpr.' as sede')->selectRaw('SUM(d.monto) as monto')->groupByRaw($sedeExpr)->get() as $row) {
            $monto = round((float) $row->monto, 2);
            $total += $monto;
            if ((string) $row->sede !== '') {
                $porMonto[(string) $row->sede] = $monto;
            }
        }
        if ($total <= 0) {
            return $vacio;
        }

        $porSede = [];
        foreach ($this->codigosSede() as $codigo) {
            $porSede[$codigo] = round(-($porMonto[$codigo] ?? 0) / $total * 100, 1);
        }

        return ['total' => round($total, 2), 'por_sede' => $porSede];
    }

    /**
     * Parte de las unidades de la sede que son de las categorías o productos de cruce activos.
     *
     * @param  array<string, array{monto:float, unidades:float, clientes:int, cruce:float}>  $tiendas
     * @return array<string, float|null>
     */
    private function cruceEfectividadPorSede(Carbon $desde, Carbon $hasta, array $tiendas): array
    {
        if (! Schema::hasTable('incentivo_cruce_items')) {
            return [];
        }

        $grupos = [];
        foreach (DB::table('incentivo_cruce_items')->where('activo', true)->get(['tipo', 'clave', 'nombre']) as $row) {
            $clave = strtoupper(trim((string) $row->clave));
            if ($clave === '') {
                continue;
            }
            $grupos[$row->tipo.'|'.$clave] = [
                'tipo' => (string) $row->tipo,
                'clave' => $clave,
                'nombre' => (string) $row->nombre,
            ];
        }
        if ($grupos === []) {
            return [];
        }

        $unidades = [];
        foreach ($this->cruceSedes($desde, $hasta, array_values($grupos)) as $sede => $porClave) {
            $unidades[$sede] = array_sum($porClave);
        }

        $out = [];
        foreach ($this->codigosSede() as $codigo) {
            $total = (float) ($tiendas[$codigo]['unidades'] ?? 0);
            $pct = $this->porcentaje((float) ($unidades[$codigo] ?? 0), $total > 0 ? $total : null);
            $out[$codigo] = $pct === null ? null : min(100, $pct);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function notaSede(array $fila): ?float
    {
        $partes = [];
        foreach (['metas_pct', 'nivelacion_pct', 'cruce_pct', 'inventario_pct'] as $campo) {
            if (($fila[$campo] ?? null) !== null) {
                $partes[] = $this->acotarCien((float) $fila[$campo]);
            }
        }
        if ($partes === []) {
            return null;
        }

        $nota = array_sum($partes) / count($partes);
        if (($fila['faltante_pct'] ?? null) !== null) {
            $nota += (float) $fila['faltante_pct'];
        }

        return round($nota, 1);
    }

    /**
     * @return list<string>
     */
    private function codigosSede(): array
    {
        $out = [];
        foreach (config('inventario.sedes_locales', []) as $codigo) {
            $codigo = strtoupper(trim((string) $codigo));
            if ($codigo !== '') {
                $out[] = $codigo;
            }
        }

        return $out;
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
     * @return array<int, array{cumplidas:int, total:int, estado:string}>
     */
    public function productosMetaAsignados(): array
    {
        return $this->productosMetaPorEmpleado(null, false);
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
            $unidades = $this->metasQuincena->esArea($sede)
                ? $this->metasQuincena->unidadesVendidas((int) $meta->producto_id, $sede, $inicio, $fin)
                : ($vendido[(int) $meta->producto_id.'|'.$sede] ?? 0.0);
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
     * Productos de la quincena cumplidos en cada sede.
     *
     * @return array<string, array{cumplidas:int, total:int, estado:string}>
     */
    private function productosMetaPorSede(): array
    {
        if (! Schema::hasTable('meta_quincena_productos') || ! Schema::hasTable('productos') || ! Schema::hasTable('ventas_detalle')) {
            return [];
        }

        $quincena = $this->metasQuincena->resolverQuincenaListado(null);
        $inicio = $quincena['inicio']->toDateString();
        $fin = $quincena['fin']->toDateString();

        $metas = DB::table('meta_quincena_productos as m')
            ->join('productos as p', 'p.id', '=', 'm.producto_id')
            ->whereDate('m.quincena_inicio', $inicio)
            ->get(['m.producto_id', 'm.sede', 'm.cantidad_inicial']);

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

        $porSede = [];
        foreach ($metas as $meta) {
            $sede = strtoupper(trim((string) $meta->sede));
            if ($sede === '') {
                continue;
            }
            $inicial = (float) $meta->cantidad_inicial;
            $unidades = $vendido[(int) $meta->producto_id.'|'.$sede] ?? 0.0;
            $cumplida = $inicial > 0 ? $unidades >= $inicial : $unidades > 0;
            $porSede[$sede]['cumplidas'] = ($porSede[$sede]['cumplidas'] ?? 0) + ($cumplida ? 1 : 0);
            $porSede[$sede]['total'] = ($porSede[$sede]['total'] ?? 0) + 1;
        }

        foreach ($porSede as $sede => $dato) {
            $porSede[$sede]['estado'] = $dato['total'] > 0 && $dato['cumplidas'] === $dato['total']
                ? 'Cumplida'
                : 'No cumplió';
        }

        return $porSede;
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
