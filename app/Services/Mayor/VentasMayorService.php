<?php

namespace App\Services\Mayor;

use App\Models\FinanzasResumen;
use App\Models\Mayor\MayorCatalogo;
use App\Models\Mayor\MayorCliente;
use App\Models\Mayor\MayorClienteArchivo;
use App\Models\Mayor\MayorConfig;
use App\Models\Mayor\MayorFactura;
use App\Models\Mayor\MayorFacturaPago;
use App\Models\Nomina\NominaEmpleado;
use App\Models\ProductoEdicion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Ventas al mayor: se ve el stock de todas las sedes, pero la factura solo puede salir del de JRZ.
 * Lo facturado aquí y aún no cargado en Profit se resta del stock de JRZ (al cargarlo, Profit ya lo descuenta).
 */
class VentasMayorService
{
    public const SEDE_FACTURA = 'JRZ';

    /** Formas de pago en divisas: aplican el descuento configurado por la gerente. */
    public const FORMAS_DIVISA = ['Zelle', 'Efectivo $', 'Binance'];

    public const CLAVE_DESCUENTO_DIVISA = 'descuento_divisa_pct';

    /** @return list<string> */
    public function formasPago(): array
    {
        return ['Transferencia', 'Pago móvil', 'Zelle', 'Efectivo $', 'Efectivo Bs', 'Binance', 'Punto de venta'];
    }

    public function esFormaDivisa(?string $forma): bool
    {
        $forma = trim((string) $forma);

        return $forma !== '' && in_array($forma, self::FORMAS_DIVISA, true);
    }

    public function descuentoDivisaPct(): float
    {
        return max(0, min(100, MayorConfig::getDecimal(self::CLAVE_DESCUENTO_DIVISA, 0)));
    }

    /**
     * @return array{subtotal: float, pct: float, descuento: float, total: float, aplica: bool}
     */
    public function calcularTotalesConDescuento(float $subtotal, ?string $formaPago = null): array
    {
        $subtotal = round(max(0, $subtotal), 2);
        $pct = $this->descuentoDivisaPct();
        $aplica = $this->esFormaDivisa($formaPago) && $pct > 0 && $subtotal > 0;
        $descuento = $aplica ? round($subtotal * $pct / 100, 2) : 0.0;
        $total = round(max(0, $subtotal - $descuento), 2);

        return [
            'subtotal' => $subtotal,
            'pct' => $aplica ? $pct : 0.0,
            'descuento' => $descuento,
            'total' => $total,
            'aplica' => $aplica,
        ];
    }

    /** @return list<string> JRZ primero y luego el resto de sedes con stock. */
    public function sedes(): array
    {
        $sedes = array_map(fn ($s) => mb_strtoupper(trim((string) $s), 'UTF-8'), config('inventario.sedes_stock', []));

        return array_values(array_unique(array_merge([self::SEDE_FACTURA], $sedes)));
    }

    /**
     * Productos activos con su stock por sede, lo apartado y lo disponible en JRZ.
     *
     * @return Collection<int, object>
     */
    public function productos(string $q = '', int $limite = 60, bool|string $soloConStock = false, ?string $categoria = null): Collection
    {
        $filtro = $soloConStock === true ? 'alguna' : ($soloConStock ?: 'todos');
        $query = DB::table('productos')->where('activo', true)
            ->where(fn ($w) => $w->where('oculto', false)->orWhereNull('oculto'))
            ->when($categoria !== null && $categoria !== '', fn ($w) => $w->where('categoria', $categoria));
        $q = trim($q);
        if ($q !== '') {
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            foreach (preg_split('/\s+/', $q) ?: [] as $palabra) {
                $query->where(fn ($w) => $w->where('nombre', $like, '%'.$palabra.'%')->orWhere('codigo', $like, '%'.$palabra.'%'));
            }
        }
        if ($filtro !== 'todos') {
            $query->whereExists(fn ($e) => $e->selectRaw('1')->from('stock_actual')
                ->whereColumn('stock_actual.producto_id', 'productos.id')->where('existencia', '>', 0)
                ->when($filtro === 'jrz', fn ($s) => $s->whereRaw('UPPER(TRIM(sede)) = ?', [self::SEDE_FACTURA])));
        }
        // Lo que más hay en JRZ primero; luego por nombre.
        $productos = $query
            ->orderByRaw('(SELECT COALESCE(SUM(s.existencia), 0) FROM stock_actual s WHERE s.producto_id = productos.id AND UPPER(TRIM(s.sede)) = ?) DESC', [self::SEDE_FACTURA])
            ->orderBy('nombre')->limit($limite)
            ->get($this->columnasFicha());

        return $this->conStock($productos);
    }

    /**
     * Catálogo al mayor con los mismos filtros del catálogo normal (buscar, categoría, subcategoría,
     * sede, excluir sedes, con stock, rangos de stock/precio, mostrar).
     *
     * @param  array{
     *   q?: string,
     *   categoria?: string,
     *   subcategorias?: list<string>,
     *   sede?: string,
     *   excluir_sedes?: list<string>,
     *   solo_existencia?: bool,
     *   stock_min?: float|null,
     *   stock_max?: float|null,
     *   precio_min?: float|null,
     *   precio_max?: float|null,
     *   limite?: int
     * }  $filtros
     * @return Collection<int, object>
     */
    public function catalogoClientes(array|string $filtros = [], ?string $categoria = null, int $limite = 200): Collection
    {
        // Compatibilidad: llamadas viejas catalogoClientes($q, $categoria, $limite).
        if (is_string($filtros)) {
            $filtros = [
                'q' => $filtros,
                'categoria' => $categoria,
                'limite' => $limite,
                'sede' => self::SEDE_FACTURA,
                'solo_existencia' => true,
            ];
        }

        $q = trim((string) ($filtros['q'] ?? ''));
        $categoria = trim((string) ($filtros['categoria'] ?? ''));
        if ($categoria === '' || strtolower($categoria) === 'todas') {
            $categoria = '';
        }
        $subcats = array_values(array_unique(array_filter(array_map(
            fn ($s) => trim((string) $s),
            $filtros['subcategorias'] ?? []
        ), fn ($s) => $s !== '' && strtolower($s) !== 'todas')));
        $sedeRaw = trim((string) ($filtros['sede'] ?? 'todas'));
        $sede = strtolower($sedeRaw) === 'todas' || $sedeRaw === ''
            ? 'todas'
            : strtoupper($sedeRaw);
        if ($sede !== 'todas' && $this->sedeOcultaEnCatalogo($sede)) {
            $sede = 'todas';
        }
        $excluidas = array_values(array_unique(array_filter(array_map(
            fn ($s) => strtoupper(trim((string) $s)),
            $filtros['excluir_sedes'] ?? []
        ))));
        $soloExistencia = (bool) ($filtros['solo_existencia'] ?? false);
        $stockMin = $this->numeroFiltro($filtros['stock_min'] ?? null);
        $stockMax = $this->numeroFiltro($filtros['stock_max'] ?? null);
        $precioMin = $this->numeroFiltro($filtros['precio_min'] ?? null);
        $precioMax = $this->numeroFiltro($filtros['precio_max'] ?? null);
        if ($stockMin !== null && $stockMin <= 0) {
            $stockMin = null;
        }
        if ($stockMin !== null && $stockMax !== null && $stockMax < $stockMin) {
            [$stockMin, $stockMax] = [$stockMax, $stockMin];
        }
        if ($precioMin !== null && $precioMin <= 0) {
            $precioMin = null;
        }
        if ($precioMin !== null && $precioMax !== null && $precioMax < $precioMin) {
            [$precioMin, $precioMax] = [$precioMax, $precioMin];
        }
        $limite = max(1, min(500, (int) ($filtros['limite'] ?? 24)));

        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $query = DB::table('productos')->where('activo', true)
            ->where(fn ($w) => $w->where('oculto', false)->orWhereNull('oculto'))
            ->when($categoria !== '', fn ($w) => $w->where('categoria', $categoria))
            ->when($subcats !== [], fn ($w) => $w->whereIn('subcategoria', $subcats));

        if ($q !== '') {
            foreach (preg_split('/\s+/', $q) ?: [] as $palabra) {
                $query->where(fn ($w) => $w->where('nombre', $like, '%'.$palabra.'%')->orWhere('codigo', $like, '%'.$palabra.'%'));
            }
        }

        if ($precioMin !== null) {
            $query->where('precio_mayor', '>=', $precioMin);
        }
        if ($precioMax !== null) {
            $query->where('precio_mayor', '<=', $precioMax);
        }

        // La tarjeta muestra el stock de JRZ (de donde se factura). Con sede Global
        // los filtros de stock/existencia usan JRZ para que coincidan con lo que se ve.
        $sedeStock = $sede === 'todas' ? self::SEDE_FACTURA : $sede;

        $aplicarSedeStock = function ($q) use ($sedeStock): void {
            $q->whereRaw('UPPER(TRIM(sede)) = ?', [$sedeStock]);
        };

        if ($soloExistencia) {
            $query->whereExists(function ($e) use ($aplicarSedeStock) {
                $e->selectRaw('1')->from('stock_actual')
                    ->whereColumn('stock_actual.producto_id', 'productos.id')
                    ->where('existencia', '>', 0);
                $aplicarSedeStock($e);
            });
        }

        if ($stockMin !== null || $stockMax !== null) {
            $sumaSql = '(SELECT COALESCE(SUM(s.existencia), 0) FROM stock_actual s'
                .' WHERE s.producto_id = productos.id AND UPPER(TRIM(s.sede)) = ?)';
            if ($stockMin !== null) {
                $query->whereRaw("{$sumaSql} >= ?", [$sedeStock, $stockMin]);
            }
            if ($stockMax !== null) {
                $query->whereRaw("{$sumaSql} <= ?", [$sedeStock, $stockMax]);
            }
        }

        // Pedir de más: luego se resta el apartado de JRZ y puede bajar del mínimo.
        $limiteSql = ($stockMin !== null || $stockMax !== null || $soloExistencia)
            ? min(500, max($limite * 3, $limite + 40))
            : $limite;

        $productos = $query
            ->orderByRaw('(SELECT COALESCE(SUM(s.existencia), 0) FROM stock_actual s WHERE s.producto_id = productos.id AND UPPER(TRIM(s.sede)) = ?) DESC', [self::SEDE_FACTURA])
            ->orderBy('nombre')
            ->limit($limiteSql)
            ->get($this->columnasFicha());

        return $this->conStock($productos)
            ->filter(function ($p) use ($precioMin, $precioMax, $stockMin, $stockMax, $soloExistencia, $sedeStock) {
                if ($p->precio_presentacion <= 0) {
                    return false;
                }
                if ($precioMin !== null && $p->precio_presentacion < $precioMin) {
                    return false;
                }
                if ($precioMax !== null && $p->precio_presentacion > $precioMax) {
                    return false;
                }
                // Mismo número que el badge de la tarjeta (JRZ disponible, ya sin apartado).
                $und = $sedeStock === self::SEDE_FACTURA
                    ? (int) $p->disponible_jrz
                    : (int) ($p->stocks[$sedeStock] ?? 0);
                if ($soloExistencia && $und <= 0) {
                    return false;
                }
                if ($stockMin !== null && $und < $stockMin) {
                    return false;
                }
                if ($stockMax !== null && $und > $stockMax) {
                    return false;
                }

                return true;
            })
            ->take($limite)
            ->values();
    }

    /** @return list<string> Categorías con stock en JRZ. */
    public function categoriasJrz(): array
    {
        return DB::table('productos as p')->join('stock_actual as s', 's.producto_id', '=', 'p.id')
            ->where('p.activo', true)->whereRaw('UPPER(TRIM(s.sede)) = ?', [self::SEDE_FACTURA])->where('s.existencia', '>', 0)
            ->whereNotNull('p.categoria')->where('p.categoria', '!=', '')
            ->distinct()->orderBy('p.categoria')->pluck('p.categoria')->all();
    }

    /**
     * Opciones de filtro del catálogo al mayor (mismas que el catálogo normal).
     *
     * @return array{categorias: list<string>, subcategorias: list<string>, catSubcatMap: array<string, list<string>>, sedes: list<string>}
     */
    public function opcionesFiltroCatalogo(): array
    {
        $ocultas = array_map('strtoupper', config('inventario.catalogo_sedes_ocultas', ['NUNES', 'MOVISTAR']));

        $categorias = Cache::remember('mayor_catalogo_categorias', 3600, function () {
            return DB::table('productos')->where('activo', true)
                ->where(fn ($w) => $w->where('oculto', false)->orWhereNull('oculto'))
                ->whereNotNull('categoria')->where('categoria', '!=', '')
                ->distinct()->orderBy('categoria')->pluck('categoria')->all();
        });

        $subcategorias = Cache::remember('mayor_catalogo_subcategorias', 3600, function () {
            return DB::table('productos')->where('activo', true)
                ->where(fn ($w) => $w->where('oculto', false)->orWhereNull('oculto'))
                ->whereNotNull('subcategoria')->where('subcategoria', '!=', '')
                ->distinct()->orderBy('subcategoria')->pluck('subcategoria')->all();
        });

        $catSubcatMap = Cache::remember('mayor_catalogo_cat_subcat_map', 3600, function () {
            $filas = DB::table('productos')->where('activo', true)
                ->where(fn ($w) => $w->where('oculto', false)->orWhereNull('oculto'))
                ->whereNotNull('categoria')->where('categoria', '!=', '')
                ->whereNotNull('subcategoria')->where('subcategoria', '!=', '')
                ->select('categoria', 'subcategoria')->distinct()->get();
            $map = [];
            foreach ($filas as $row) {
                $map[$row->categoria][] = $row->subcategoria;
            }
            foreach ($map as &$subs) {
                $subs = array_values(array_unique($subs));
                sort($subs);
            }

            return $map;
        });

        $sedes = Cache::remember('mayor_catalogo_sedes', 3600, function () use ($ocultas) {
            return DB::table('stock_actual')->select('sede')->distinct()->pluck('sede')
                ->map(fn ($s) => (string) $s)
                ->reject(fn ($s) => in_array(strtoupper(trim($s)), $ocultas, true))
                ->sort()->values()->all();
        });

        return [
            'categorias' => $categorias,
            'subcategorias' => $subcategorias,
            'catSubcatMap' => $catSubcatMap,
            'sedes' => $sedes,
        ];
    }

    private function sedeOcultaEnCatalogo(string $sede): bool
    {
        $ocultas = array_map('strtoupper', config('inventario.catalogo_sedes_ocultas', ['NUNES', 'MOVISTAR']));

        return in_array(strtoupper(trim($sede)), $ocultas, true);
    }

    private function numeroFiltro(mixed $valor): ?float
    {
        return is_numeric($valor) && (float) $valor >= 0 ? (float) $valor : null;
    }

    /** Columnas de la ficha del producto que existan en esta base. */
    private function columnasFicha(): array
    {
        $deseadas = ['id', 'codigo', 'nombre', 'categoria', 'subcategoria', 'proveedor', 'precio_unidad', 'precio_mayor',
            'ultima_cantidad_compra', 'ultimo_costo_compra'];

        return array_values(array_intersect($deseadas, Schema::getColumnListing('productos')));
    }

    /** @return array{productos: int, unidades: int, valor_mayor: float} Stock de JRZ en total, ya sin lo facturado. */
    public function resumenJrz(): array
    {
        $facturado = DB::table('mayor_factura_items as i')->join('mayor_facturas as f', 'f.id', '=', 'i.factura_id')
            ->where('f.estado', MayorFactura::ESTADO_EMITIDA)->where('f.en_profit', false)
            ->groupBy('i.producto_id')->selectRaw('i.producto_id, SUM('.$this->unidadesSql().') as unidades');
        $restante = 'CASE WHEN s.existencia - COALESCE(fa.unidades, 0) > 0 THEN s.existencia - COALESCE(fa.unidades, 0) ELSE 0 END';
        $fila = DB::table('stock_actual as s')->join('productos as p', 'p.id', '=', 's.producto_id')
            ->leftJoinSub($facturado, 'fa', 'fa.producto_id', '=', 's.producto_id')
            ->where('p.activo', true)->whereRaw('UPPER(TRIM(s.sede)) = ?', [self::SEDE_FACTURA])
            ->whereRaw("{$restante} > 0")
            ->selectRaw("COUNT(DISTINCT s.producto_id) as productos, COALESCE(SUM({$restante}), 0) as unidades, COALESCE(SUM({$restante} * p.precio_mayor), 0) as valor")
            ->first();

        return [
            'productos' => (int) ($fila->productos ?? 0),
            'unidades' => (int) ($fila->unidades ?? 0),
            'valor_mayor' => round((float) ($fila->valor ?? 0), 2),
        ];
    }

    /**
     * @param  Collection<int, object>  $productos
     * @return Collection<int, object>
     */
    public function conStock(Collection $productos): Collection
    {
        $ids = $productos->pluck('id')->map(fn ($id) => (int) $id)->all();
        $stock = $this->stockPorSede($ids);
        $apartado = $this->apartadoJrz($ids);
        $catalogo = $this->catalogo($ids);
        $lotes = Schema::hasTable('producto_ediciones') && Schema::hasColumn('producto_ediciones', 'lote_inicial')
            ? ProductoEdicion::query()->whereIn('producto_id', $ids)->whereNotNull('lote_inicial')->pluck('lote_inicial', 'producto_id')
            : collect();

        return $productos->map(function ($p) use ($stock, $apartado, $catalogo, $lotes) {
            $id = (int) $p->id;
            $p->stocks = [];
            foreach ($this->sedes() as $sede) {
                $p->stocks[$sede] = (int) ($stock[$id][$sede] ?? 0);
            }
            $p->stocks[self::SEDE_FACTURA] = max(0, $p->stocks[self::SEDE_FACTURA] - (int) ($apartado[$id] ?? 0));
            $p->stock_total = array_sum($p->stocks);
            $p->disponible_jrz = $p->stocks[self::SEDE_FACTURA];
            $p->precio_mayor = round((float) ($p->precio_mayor ?? 0), 2);
            $p->precio_unidad = round((float) ($p->precio_unidad ?? 0), 2);
            $c = $catalogo[$id] ?? ['presentacion' => 'unidad', 'unidades' => 1, 'precio' => $p->precio_mayor, 'costo_unidad' => 0.0, 'costo_suite' => 0.0, 'precio_editado' => false, 'costo_editado' => false];
            $p->costo_suite = $c['costo_suite'];
            $p->presentacion = $c['presentacion'];
            $p->unidades_por = $c['unidades'];
            $p->presentacion_texto = MayorCatalogo::etiqueta($c['presentacion'], $c['unidades']);
            $p->precio_presentacion = $c['precio'];
            $p->precio_editado = $c['precio_editado'];
            $p->costo_unidad = $c['costo_unidad'];
            $p->costo_presentacion = round($c['costo_unidad'] * $c['unidades'], 2);
            $p->costo_editado = $c['costo_editado'];
            $p->ganancia_pct = $p->costo_presentacion > 0 ? round(($p->precio_presentacion - $p->costo_presentacion) / $p->costo_presentacion * 100, 1) : null;
            $p->disponible_presentaciones = intdiv($p->disponible_jrz, max(1, $c['unidades']));
            $lote = $lotes->get($id);
            $p->lote_inicial = $lote !== null && (int) $lote > 0 ? (int) $lote : null;

            return $p;
        })->values();
    }

    /**
     * @param  list<int>  $productoIds
     * @return array<int, array<string, int>>
     */
    public function stockPorSede(array $productoIds): array
    {
        if ($productoIds === []) {
            return [];
        }
        $out = [];
        foreach (array_chunk($productoIds, 500) as $lote) {
            $filas = DB::table('stock_actual')->whereIn('producto_id', $lote)
                ->selectRaw('producto_id, UPPER(TRIM(sede)) as sede, SUM(existencia) as existencia')
                ->groupBy('producto_id', DB::raw('UPPER(TRIM(sede))'))
                ->get();
            foreach ($filas as $f) {
                $out[(int) $f->producto_id][(string) $f->sede] = (int) $f->existencia;
            }
        }

        return $out;
    }

    /**
     * Unidades de JRZ facturadas al mayor y aún no cargadas en Profit (se restan del stock de JRZ).
     *
     * @param  list<int>  $productoIds
     * @return array<int, int>
     */
    public function apartadoJrz(array $productoIds, ?int $exceptoFacturaId = null): array
    {
        if ($productoIds === []) {
            return [];
        }

        return DB::table('mayor_factura_items as i')
            ->join('mayor_facturas as f', 'f.id', '=', 'i.factura_id')
            ->where('f.estado', MayorFactura::ESTADO_EMITIDA)
            ->where('f.en_profit', false)
            ->when($exceptoFacturaId, fn ($q) => $q->where('f.id', '!=', $exceptoFacturaId))
            ->whereIn('i.producto_id', $productoIds)
            ->groupBy('i.producto_id')
            ->selectRaw('i.producto_id, SUM('.$this->unidadesSql().') as unidades')
            ->pluck('unidades', 'producto_id')
            ->map(fn ($u) => (int) $u)
            ->all();
    }

    /** Unidades que sacó cada renglón: las cajas o paquetes ya convertidos a unidades. */
    private function unidadesSql(): string
    {
        return Schema::hasColumn('mayor_factura_items', 'unidades') ? 'COALESCE(i.unidades, i.cantidad)' : 'i.cantidad';
    }

    /**
     * Cómo se vende cada producto al mayor. Sin configurar: por unidad, al precio mayor y con el costo de Suite.
     *
     * @param  list<int>  $productoIds
     * @return array<int, array{presentacion: string, unidades: int, precio: float, costo_unidad: float, costo_suite: float,
     *               precio_editado: bool, costo_editado: bool}>
     */
    public function catalogo(array $productoIds): array
    {
        if ($productoIds === []) {
            return [];
        }
        $productos = DB::table('productos')->whereIn('id', $productoIds)
            ->get(array_merge(['id', 'precio_mayor'], Schema::hasColumn('productos', 'costo_actual') ? ['costo_actual'] : []))
            ->keyBy('id');
        $config = Schema::hasTable('mayor_catalogo')
            ? MayorCatalogo::query()->whereIn('producto_id', $productoIds)->get()->keyBy('producto_id')
            : collect();

        $out = [];
        foreach ($productos as $id => $p) {
            $c = $config->get($id);
            $presentacion = $c && isset(MayorCatalogo::PRESENTACIONES[$c->presentacion]) ? $c->presentacion : 'unidad';
            $unidades = $presentacion === 'unidad' ? 1 : max(1, (int) ($c->unidades ?? 1));
            $out[(int) $id] = [
                'presentacion' => $presentacion,
                'unidades' => $unidades,
                'precio' => round($c?->precio_usd !== null ? (float) $c->precio_usd : (float) $p->precio_mayor * $unidades, 2),
                'costo_unidad' => round($c?->costo_usd !== null ? (float) $c->costo_usd : (float) ($p->costo_actual ?? 0), 4),
                'costo_suite' => round((float) ($p->costo_actual ?? 0), 4),
                'precio_editado' => $c?->precio_usd !== null,
                'costo_editado' => $c?->costo_usd !== null,
            ];
        }

        return $out;
    }

    /**
     * Guarda cómo se vende un producto al mayor. Precio o costo vacíos vuelven a los de Suite.
     *
     * @param  array{presentacion: string, unidades?: ?int, precio_usd?: ?float, costo_usd?: ?float}  $datos
     */
    public function guardarCatalogo(int $productoId, array $datos, ?int $userId): MayorCatalogo
    {
        $presentacion = isset(MayorCatalogo::PRESENTACIONES[$datos['presentacion']]) ? $datos['presentacion'] : 'unidad';
        $unidades = $presentacion === 'unidad' ? 1 : (int) ($datos['unidades'] ?? 0);
        if ($unidades < 1) {
            throw ValidationException::withMessages(['unidades' => 'Indica cuántas unidades trae cada '.mb_strtolower(MayorCatalogo::PRESENTACIONES[$presentacion]).'.']);
        }

        return MayorCatalogo::query()->updateOrCreate(['producto_id' => $productoId], [
            'presentacion' => $presentacion,
            'unidades' => $unidades,
            'precio_usd' => isset($datos['precio_usd']) && $datos['precio_usd'] !== '' ? round((float) $datos['precio_usd'], 2) : null,
            'costo_usd' => isset($datos['costo_usd']) && $datos['costo_usd'] !== '' ? round((float) $datos['costo_usd'], 4) : null,
            'user_id' => $userId,
        ]);
    }

    /** Tasa BCV del día (o la última registrada en Finanzas). */
    public function tasaBcv(?string $fecha = null): float
    {
        $fecha = $fecha ?: now()->toDateString();
        $tasa = FinanzasResumen::query()->whereDate('fecha', '<=', $fecha)->where('tasa_bcv_usd', '>', 1)
            ->orderByDesc('fecha')->value('tasa_bcv_usd');

        return round((float) $tasa, 4);
    }

    /** @return Collection<int, NominaEmpleado> */
    public function vendedoras(): Collection
    {
        return NominaEmpleado::query()->activos()
            ->whereNotNull('codigo_vendedor')->where('codigo_vendedor', '!=', '')
            ->with('cliente')
            ->get()
            ->sortBy(fn ($e) => $e->nombre())
            ->values();
    }

    /** @return Collection<int, MayorCliente> */
    public function buscarClientes(string $q, int $limite = 15): Collection
    {
        $q = trim($q);
        if ($q === '') {
            return collect();
        }
        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $doc = MayorCliente::normalizarDocumento($q);

        return MayorCliente::query()
            ->withCount('archivos')
            ->where(function ($w) use ($q, $doc, $like) {
                $w->where('nombre', $like, '%'.$q.'%');
                if ($doc !== '') {
                    $w->orWhere('documento', $like, '%'.$doc.'%');
                }
            })
            ->orderBy('nombre')->limit($limite)->get();
    }

    /**
     * Guarda hasta 4 archivos del cliente (fotos / documentos) en los slots libres.
     *
     * @param  list<UploadedFile|null>  $archivos
     * @return list<MayorClienteArchivo>
     */
    public function guardarArchivosCliente(MayorCliente $cliente, array $archivos, ?int $userId = null): array
    {
        $guardados = [];
        $ocupados = $cliente->archivos()->pluck('slot')->map(fn ($s) => (int) $s)->all();
        foreach ($archivos as $archivo) {
            if (! $archivo instanceof UploadedFile) {
                continue;
            }
            $libre = collect(range(1, MayorClienteArchivo::MAX_POR_CLIENTE))
                ->first(fn ($s) => ! in_array((int) $s, $ocupados, true));
            if ($libre === null) {
                throw ValidationException::withMessages(['archivos' => 'Este cliente ya tiene '.MayorClienteArchivo::MAX_POR_CLIENTE.' archivos. Borra uno para subir otro.']);
            }
            $guardados[] = $this->guardarArchivoClienteEnSlot($cliente, (int) $libre, $archivo, $userId);
            $ocupados[] = (int) $libre;
        }

        return $guardados;
    }

    public function guardarArchivoClienteEnSlot(MayorCliente $cliente, int $slot, UploadedFile $archivo, ?int $userId = null): MayorClienteArchivo
    {
        if ($slot < 1 || $slot > MayorClienteArchivo::MAX_POR_CLIENTE) {
            throw ValidationException::withMessages(['slot' => 'Solo se permiten '.MayorClienteArchivo::MAX_POR_CLIENTE.' archivos por cliente.']);
        }

        $storage = app(\App\Services\ClientesVmStorage::class);
        $prefijo = 'c'.$cliente->id.'_'.MayorCliente::normalizarDocumento($cliente->documento).'_s'.$slot;
        $url = $storage->subir($archivo, $prefijo);

        $existente = MayorClienteArchivo::query()
            ->where('cliente_id', $cliente->id)
            ->where('slot', $slot)
            ->first();

        if ($existente) {
            if ($existente->url && $existente->url !== $url) {
                $storage->borrarPorUrl($existente->url);
            }
            $existente->fill([
                'url' => $url,
                'nombre_archivo' => mb_substr($archivo->getClientOriginalName(), 0, 200),
                'mime' => mb_substr((string) $archivo->getClientMimeType(), 0, 120),
                'user_id' => $userId,
            ])->save();

            return $existente;
        }

        return MayorClienteArchivo::query()->create([
            'cliente_id' => $cliente->id,
            'slot' => $slot,
            'url' => $url,
            'nombre_archivo' => mb_substr($archivo->getClientOriginalName(), 0, 200),
            'mime' => mb_substr((string) $archivo->getClientMimeType(), 0, 120),
            'user_id' => $userId,
        ]);
    }

    public function borrarArchivoCliente(MayorCliente $cliente, int $archivoId): void
    {
        $archivo = MayorClienteArchivo::query()
            ->where('cliente_id', $cliente->id)
            ->where('id', $archivoId)
            ->firstOrFail();
        app(\App\Services\ClientesVmStorage::class)->borrarPorUrl($archivo->url);
        $archivo->delete();
    }

    /** @return list<array{id:int,slot:int,url:string,nombre:string,es_imagen:bool}> */
    public function archivosClienteParaApi(MayorCliente $cliente): array
    {
        return $cliente->archivos()->get()->map(fn (MayorClienteArchivo $a) => [
            'id' => (int) $a->id,
            'slot' => (int) $a->slot,
            'url' => (string) $a->url,
            'nombre' => (string) $a->nombre_archivo,
            'es_imagen' => $a->esImagen(),
        ])->values()->all();
    }

    /**
     * Cliente elegido o nuevo. Si el documento ya existe se usa ese y se completan sus datos.
     *
     * @param  array{cliente_id?: ?int, documento?: ?string, nombre?: ?string, telefono?: ?string, email?: ?string, direccion?: ?string}  $datos
     */
    public function resolverCliente(array $datos, ?int $userId): MayorCliente
    {
        if (! empty($datos['cliente_id'])) {
            $cliente = MayorCliente::query()->find((int) $datos['cliente_id']);
            if ($cliente) {
                return $cliente;
            }
        }
        $documento = MayorCliente::normalizarDocumento($datos['documento'] ?? '');
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($documento === '' || $nombre === '') {
            throw ValidationException::withMessages(['cliente' => 'Elige el cliente o registra uno nuevo con su RIF/cédula y nombre.']);
        }
        $extra = array_filter([
            'telefono' => trim((string) ($datos['telefono'] ?? '')),
            'email' => trim((string) ($datos['email'] ?? '')),
            'direccion' => trim((string) ($datos['direccion'] ?? '')),
        ], fn ($v) => $v !== '');

        $cliente = MayorCliente::query()->firstOrNew(['documento' => $documento]);
        if (! $cliente->exists) {
            $cliente->fill(['nombre' => mb_substr($nombre, 0, 200), 'user_id' => $userId] + $extra)->save();
        } elseif ($extra !== []) {
            $cliente->fill(array_filter($extra, fn ($v, $k) => empty($cliente->{$k}), ARRAY_FILTER_USE_BOTH))->save();
        }

        return $cliente;
    }

    /**
     * Emite la factura. Cada cantidad tiene que caber en lo disponible de JRZ (stock − apartado).
     *
     * Contado: se paga todo al emitir. Crédito: queda por cobrar todo o una parte, con fecha de vencimiento.
     *
     * @param  array{fecha: string, tasa_bcv: float, vendedor_empleado_id: ?int, observaciones: ?string,
     *               condicion?: string, fecha_vencimiento?: ?string, forma_pago?: ?string,
     *               items: list<array{producto_id: int, cantidad: int, precio_usd: float}>}  $datos
     */
    public function emitir(MayorCliente $cliente, array $datos, User $user, ?float $abonoInicial = null): MayorFactura
    {
        $items = collect($datos['items'])
            ->filter(fn ($i) => (int) ($i['cantidad'] ?? 0) > 0)
            ->groupBy(fn ($i) => (int) $i['producto_id'])
            ->map(fn ($g) => ['producto_id' => (int) $g->first()['producto_id'], 'cantidad' => (int) $g->sum('cantidad'), 'precio_usd' => round((float) $g->first()['precio_usd'], 2)])
            ->values();
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Agrega al menos un producto con su cantidad.']);
        }
        // El precio se pone en la ficha del producto: la factura usa el del catálogo al mayor aunque el formulario traiga otro.
        $precios = $this->catalogo($items->pluck('producto_id')->all());
        $items = $items->map(fn ($i) => ['precio_usd' => (float) ($precios[$i['producto_id']]['precio'] ?? 0)] + $i)->values();
        if ($items->contains(fn ($i) => $i['precio_usd'] <= 0)) {
            throw ValidationException::withMessages(['items' => 'Hay productos sin precio al mayor: hay que ponerle precio en su ficha.']);
        }
        $tasa = round((float) $datos['tasa_bcv'], 4);
        if ($tasa <= 0) {
            throw ValidationException::withMessages(['tasa_bcv' => 'Indica la tasa BCV del día.']);
        }

        $vendedor = ! empty($datos['vendedor_empleado_id'])
            ? NominaEmpleado::query()->with('cliente')->find((int) $datos['vendedor_empleado_id'])
            : null;
        if (! $vendedor) {
            throw ValidationException::withMessages(['vendedor_empleado_id' => 'Elige la vendedora.']);
        }

        $subtotal = round($items->sum(fn ($i) => $i['cantidad'] * $i['precio_usd']), 2);
        $totales = $this->calcularTotalesConDescuento($subtotal, $datos['forma_pago'] ?? null);
        $total = $totales['total'];
        $condicion = ($datos['condicion'] ?? MayorFactura::CONTADO) === MayorFactura::CREDITO ? MayorFactura::CREDITO : MayorFactura::CONTADO;
        if ($abonoInicial !== null && $abonoInicial > $total + 0.009) {
            throw ValidationException::withMessages(['pago_monto_usd' => 'Lo pagado ($'.number_format($abonoInicial, 2).') es más que el total de la factura ($'.number_format($total, 2).').']);
        }
        if ($condicion === MayorFactura::CONTADO && $abonoInicial !== null && $abonoInicial < $total - 0.009) {
            throw ValidationException::withMessages(['pago_monto_usd' => 'De contado se paga todo ($'.number_format($total, 2).'). Si queda una parte por pagar, márcala a crédito.']);
        }
        $vence = $datos['fecha_vencimiento'] ?? null;
        if ($condicion === MayorFactura::CREDITO && (! $vence || $vence < $datos['fecha'])) {
            throw ValidationException::withMessages(['fecha_vencimiento' => 'A crédito: indica hasta cuándo tiene para pagar (desde la fecha de la factura).']);
        }
        $datos['condicion'] = $condicion;
        $datos['fecha_vencimiento'] = $condicion === MayorFactura::CREDITO ? $vence : null;

        // Una factura a la vez: dos personas no pueden apartar las mismas unidades de JRZ.
        return Cache::lock('ventas_mayor_emitir', 15)->block(10, function () use ($items, $tasa, $vendedor, $cliente, $datos, $user, $total, $totales) {
            $ids = $items->pluck('producto_id')->all();
            $productos = DB::table('productos')->whereIn('id', $ids)->get(['id', 'codigo', 'nombre'])->keyBy('id');
            $stock = $this->stockPorSede($ids);
            $apartado = $this->apartadoJrz($ids);
            $catalogo = $this->catalogo($ids);
            $faltan = [];
            foreach ($items as $i) {
                $p = $productos->get($i['producto_id']);
                if (! $p) {
                    throw ValidationException::withMessages(['items' => 'Un producto de la factura ya no existe.']);
                }
                $presentacion = $catalogo[$i['producto_id']]['presentacion'] ?? 'unidad';
                $unidadesPor = $catalogo[$i['producto_id']]['unidades'] ?? 1;
                $disponible = max(0, (int) ($stock[$i['producto_id']][self::SEDE_FACTURA] ?? 0) - (int) ($apartado[$i['producto_id']] ?? 0));
                if ($i['cantidad'] * $unidadesPor > $disponible) {
                    $faltan[] = $unidadesPor > 1
                        ? "{$p->nombre}: pides ".MayorCatalogo::cantidadTexto($i['cantidad'], $presentacion).' ('.($i['cantidad'] * $unidadesPor)." und.) y en JRZ hay {$disponible} und."
                        : "{$p->nombre}: pides {$i['cantidad']} y en JRZ hay {$disponible} disponibles";
                }
            }
            if ($faltan !== []) {
                throw ValidationException::withMessages(['items' => 'No alcanza el stock de JRZ. '.implode(' · ', $faltan).'.']);
            }

            return DB::transaction(function () use ($items, $productos, $catalogo, $tasa, $vendedor, $cliente, $datos, $user, $total, $totales) {
                $extras = [];
                if (Schema::hasColumn('mayor_facturas', 'subtotal_usd')) {
                    $extras['subtotal_usd'] = $totales['subtotal'];
                    $extras['descuento_divisa_pct'] = $totales['pct'];
                    $extras['descuento_usd'] = $totales['descuento'];
                }
                $factura = MayorFactura::create([
                    'numero' => ((int) MayorFactura::query()->max('numero')) + 1,
                    'fecha' => $datos['fecha'],
                    'cliente_id' => $cliente->id,
                    'vendedor_empleado_id' => $vendedor->id,
                    'vendedor_nombre' => $vendedor->nombre(),
                    'user_id' => $user->id,
                    'tasa_bcv' => $tasa,
                    'total_usd' => $total,
                    'total_bs' => round($total * $tasa, 2),
                    'estado' => MayorFactura::ESTADO_EMITIDA,
                    'condicion' => $datos['condicion'],
                    'fecha_vencimiento' => $datos['fecha_vencimiento'],
                    'observaciones' => $datos['observaciones'] ?? null,
                ] + $extras);
                $conPresentacion = Schema::hasColumn('mayor_factura_items', 'unidades');
                foreach ($items as $i) {
                    $p = $productos->get($i['producto_id']);
                    $unidadesPor = $catalogo[$i['producto_id']]['unidades'] ?? 1;
                    $factura->items()->create([
                        'producto_id' => $i['producto_id'],
                        'codigo' => $p->codigo,
                        'descripcion' => mb_substr((string) $p->nombre, 0, 300),
                        'cantidad' => $i['cantidad'],
                        'precio_usd' => $i['precio_usd'],
                        'total_usd' => round($i['cantidad'] * $i['precio_usd'], 2),
                    ] + ($conPresentacion ? [
                        'presentacion' => $catalogo[$i['producto_id']]['presentacion'] ?? 'unidad',
                        'unidades_por' => $unidadesPor,
                        'unidades' => $i['cantidad'] * $unidadesPor,
                    ] : []));
                }

                return $factura;
            });
        });
    }

    /**
     * Pago o abono: el monto va en el primer comprobante; los demás comprobantes quedan como soporte.
     * Un abono en efectivo puede no tener comprobante.
     * Devuelve el pago principal (con monto) para emitir la factura/recibo de pago.
     *
     * @param  list<UploadedFile>  $archivos
     * @param  array{fecha?: ?string, forma_pago?: ?string, referencia?: ?string}  $datos
     */
    public function registrarPago(MayorFactura $factura, ?float $monto, array $archivos, array $datos, ?int $userId): ?MayorFacturaPago
    {
        $monto = $monto !== null && $monto > 0 ? round($monto, 2) : null;
        if ($monto === null && $archivos === []) {
            throw ValidationException::withMessages(['monto_usd' => 'Indica el monto del pago o sube el comprobante.']);
        }
        $factura->loadMissing('pagos');
        if ($monto !== null && $monto > $factura->saldoUsd() + 0.009) {
            throw ValidationException::withMessages(['monto_usd' => 'El pago ($'.number_format($monto, 2).') es más que lo que se debe ($'.number_format($factura->saldoUsd(), 2).').']);
        }
        $base = [
            'fecha' => $datos['fecha'] ?? now()->toDateString(),
            'forma_pago' => $datos['forma_pago'] ?? null,
            'referencia' => $datos['referencia'] ?? null,
            'user_id' => $userId,
        ];
        if ($archivos === []) {
            return $factura->pagos()->create($base + ['monto_usd' => $monto]);
        }
        $principal = null;
        foreach (array_values($archivos) as $k => $archivo) {
            $pago = $factura->pagos()->create($base + [
                'url' => $this->subirArchivo($archivo, $factura),
                'nombre_archivo' => mb_substr($archivo->getClientOriginalName(), 0, 200),
                'monto_usd' => $k === 0 ? $monto : null,
            ]);
            if ($k === 0) {
                $principal = $pago;
            }
        }

        return $principal;
    }

    private function subirArchivo(UploadedFile $archivo, MayorFactura $factura): string
    {
        return $this->subirContenido(
            (string) file_get_contents($archivo->getRealPath()),
            (string) $archivo->getClientMimeType(),
            strtolower($archivo->getClientOriginalExtension() ?: 'bin'),
            'mayor_'.$factura->numero,
        );
    }

    public function subirContenido(string $contenido, string $mime, string $extension, string $prefijo): string
    {
        return app(\App\Services\ComprobantesStorage::class)->subirContenido($contenido, $mime, $extension, $prefijo);
    }
}
