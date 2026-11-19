<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\CuadreCaja;
use App\Models\CuadreCajaConteo;
use App\Models\CuadreCajaLote;
use App\Models\CuadreCajaPago;
use App\Models\TesoreriaIngreso;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuadre de caja: lo que cobró cada caja en el día según Suite contra lo que
 * Tesorería contó a mano de cada forma de pago. Diferencia = Tesorería − Suite (en USD).
 */
class CuadreCajaService
{
    /** Código de cuenta de la línea que junta todos los puntos de venta de una forma de pago. */
    public const CUENTA_PUNTOS = 'PUNTOS';

    public const ESTADOS = [
        CuadreCaja::PENDIENTE => 'Pendiente',
        CuadreCaja::PROCESADO => 'Procesado',
        CuadreCaja::CERRADO => 'Cerrado',
    ];

    /** @var array<string, list<array<string, mixed>>> */
    private array $formasPorSede = [];

    /**
     * Dashboard Tesorería: filas = formas de pago; columnas = sedes (o cajas si hay sede);
     * más «Ingreso tesorería» (global o de la sede) y «Diferencia» (tesorería − Suite).
     *
     * @return array{
     *   modo: string,
     *   columnas: list<array{clave:string,etiqueta:string}>,
     *   filas: list<array<string,mixed>>,
     *   totales: array<string,mixed>
     * }
     */
    public function matrizDashboard(Carbon $desde, Carbon $hasta, ?string $sede = null): array
    {
        $vacio = [
            'modo' => $sede ? 'cajas' : 'sedes',
            'columnas' => [],
            'filas' => [],
            'totales' => ['por_columna' => [], 'suite' => 0.0, 'tesoreria' => 0.0, 'diferencia' => 0.0],
        ];
        if (! Schema::hasTable('cuadres_caja')) {
            return $vacio;
        }

        $sede = $sede ? strtoupper(trim($sede)) : null;
        $query = CuadreCaja::query()
            ->with(['pagos', 'conteos', 'lotes'])
            ->whereBetween('fecha_cierre', [$desde->toDateString(), $hasta->toDateString().' 23:59:59']);
        if ($sede) {
            $query->where('sede', $sede);
        }
        $cierres = $query->orderBy('sede')->orderBy('codigo_caja')->orderBy('id')->get();
        if ($cierres->isEmpty()) {
            return $vacio;
        }

        $modo = $sede ? 'cajas' : 'sedes';
        $etiquetas = config('inventario.display', []);
        $metodosCuadre = config('cuadre_caja.metodos_cuadre', []);
        $filas = $this->filasBaseDashboard($sede ?: 'DORAL');
        $clavesCol = [];

        $cajasOcultas = ['CAJA COBRANZA', 'CAJA RRHH'];

        foreach ($cierres as $cierre) {
            if ($modo === 'sedes') {
                $col = strtoupper(trim((string) $cierre->sede));
                $etiquetaCol = $etiquetas[$col] ?? $col;
            } else {
                $col = trim((string) ($cierre->codigo_caja ?: $cierre->descripcion_caja ?: 'SIN CAJA'));
                $etiquetaCol = trim((string) ($cierre->descripcion_caja ?: $cierre->codigo_caja ?: 'Sin caja'));
                $nombreCaja = mb_strtoupper($etiquetaCol !== '' ? $etiquetaCol : $col, 'UTF-8');
                if (in_array($nombreCaja, $cajasOcultas, true)) {
                    continue;
                }
            }
            if ($col === '') {
                $col = 'SIN';
                $etiquetaCol = 'Sin caja';
            }
            $clavesCol[$col] = $etiquetaCol;

            foreach ($this->lineas($cierre) as $linea) {
                if (($linea['regla'] ?? null) === 'no_suma') {
                    continue;
                }
                $metodo = (string) ($linea['codigo_metodo'] ?? '');
                if ($metodosCuadre !== [] && ! in_array($metodo, $metodosCuadre, true)) {
                    continue;
                }
                $forma = mb_strtoupper(trim((string) ($linea['forma_pago'] ?: $metodo ?: 'OTRO')), 'UTF-8');
                if (str_contains($forma, 'CAJA PRINCIPAL')) {
                    continue;
                }

                // Puntos de venta: una sola fila global (como en el arqueo).
                $esPos = ! empty($linea['punto_venta']) || ! empty($linea['agrupada'])
                    || (($linea['codigo_cuenta'] ?? '') === self::CUENTA_PUNTOS);
                $clave = $esPos
                    ? $this->clave($metodo, self::CUENTA_PUNTOS)
                    : (string) ($linea['clave'] ?? $this->clave($metodo, $linea['codigo_cuenta'] ?? ''));

                // Solo catálogo del cuadre de esa sede (o DORAL si es consolidado): no sumar cuentas sueltas de otros cierres.
                if (! isset($filas[$clave])) {
                    continue;
                }

                $suite = (float) ($linea['suite_usd'] ?? 0);
                $tesoreria = $linea['tesoreria_usd'] !== null ? (float) $linea['tesoreria_usd'] : 0.0;
                // EFECTIVO DEVOLUCION: resta en Suite (no es cobro) y no se duplica en Tesorería del consolidado.
                $esDevolucion = ($linea['regla'] ?? null) === 'devolucion'
                    || $metodo === strtoupper((string) config('cuadre_caja.metodo_devolucion_efectivo', 'NDC1'));
                if ($esDevolucion) {
                    $suite = $suite > 0 ? -$suite : $suite;
                    $tesoreria = 0.0;
                }
                $filas[$clave]['por_columna'][$col] = round(($filas[$clave]['por_columna'][$col] ?? 0) + $suite, 2);
                $filas[$clave]['suite'] = round($filas[$clave]['suite'] + $suite, 2);
                $filas[$clave]['tesoreria'] = round($filas[$clave]['tesoreria'] + $tesoreria, 2);
            }
        }

        $filas = $this->ordenarComoSuite(array_values($filas), $sede ?: 'DORAL');
        $filas = array_column($filas, null, 'clave');

        if ($modo === 'sedes') {
            $preferidas = config('inventario.sedes_gerencial', []);
            uksort($clavesCol, function (string $a, string $b) use ($preferidas) {
                $ia = array_search($a, $preferidas, true);
                $ib = array_search($b, $preferidas, true);
                if ($ia === false && $ib === false) {
                    return strnatcasecmp($a, $b);
                }
                if ($ia === false) {
                    return 1;
                }
                if ($ib === false) {
                    return -1;
                }

                return $ia <=> $ib;
            });
        } else {
            ksort($clavesCol, SORT_NATURAL | SORT_FLAG_CASE);
        }

        $columnas = [];
        foreach ($clavesCol as $clave => $etiqueta) {
            $columnas[] = ['clave' => $clave, 'etiqueta' => $etiqueta];
        }

        $lista = [];
        $totCol = array_fill_keys(array_keys($clavesCol), 0.0);
        $totSuite = 0.0;
        $totTesoreria = 0.0;
        foreach ($filas as $fila) {
            $dif = round($fila['tesoreria'] - $fila['suite'], 2);
            $lista[] = $fila + ['diferencia' => $dif];
            foreach ($fila['por_columna'] as $col => $monto) {
                $totCol[$col] = round(($totCol[$col] ?? 0) + $monto, 2);
            }
            $totSuite = round($totSuite + $fila['suite'], 2);
            $totTesoreria = round($totTesoreria + $fila['tesoreria'], 2);
        }

        return [
            'modo' => $modo,
            'columnas' => $columnas,
            'filas' => $lista,
            'totales' => [
                'por_columna' => $totCol,
                'suite' => $totSuite,
                'tesoreria' => $totTesoreria,
                'diferencia' => round($totTesoreria - $totSuite, 2),
            ],
        ];
    }

    /**
     * @return array{filas: list<array<string, mixed>>, totales: array<string, float|int>, ultimaCarga: array<string, string>}
     */
    public function consolidado(Carbon $desde, Carbon $hasta, ?string $sede = null, ?string $cajera = null, ?string $estado = null): array
    {
        if (! Schema::hasTable('cuadres_caja')) {
            return ['filas' => [], 'totales' => $this->totales([]), 'ultimaCarga' => []];
        }

        $query = CuadreCaja::query()
            ->with(['pagos', 'conteos', 'lotes.ingreso', 'conciliador'])
            ->whereBetween('fecha_cierre', [$desde->toDateString(), $hasta->toDateString().' 23:59:59']);
        if ($sede) {
            $query->where('sede', strtoupper(trim($sede)));
        }
        $cajera = trim((string) $cajera);
        if ($cajera !== '') {
            $like = '%'.mb_strtoupper($cajera, 'UTF-8').'%';
            $cedulas = Cliente::query()->whereRaw('UPPER(nombre) LIKE ?', [$like])->pluck('cedula')
                ->flatMap(fn ($c) => [$c, 'V'.$c, 'E'.$c])->all();
            $query->where(fn ($q) => $q->whereRaw('UPPER(usuario_nombre) LIKE ?', [$like])
                ->orWhereRaw('UPPER(usuario) LIKE ?', [$like])
                ->orWhereIn('usuario', $cedulas));
        }
        if ($estado === CuadreCaja::PENDIENTE) {
            $query->whereNull('conciliado_at');
        } elseif ($estado === CuadreCaja::CERRADO) {
            $query->where('estado_tesoreria', CuadreCaja::CERRADO);
        } elseif ($estado === CuadreCaja::PROCESADO) {
            $query->whereNotNull('conciliado_at')->where('estado_tesoreria', '<>', CuadreCaja::CERRADO);
        }

        $cierres = $query->orderBy('fecha_cierre')->orderBy('sede')->orderBy('codigo_caja')->orderBy('id_cierre')->get();
        $this->completarNombres($cierres);
        $filas = $cierres
            ->map(fn (CuadreCaja $c) => ['cierre' => $c] + $this->resumen($c))
            ->all();

        return [
            'filas' => $filas,
            'totales' => $this->totales($filas),
            'ultimaCarga' => $this->ultimaCarga(),
        ];
    }

    /**
     * Suite no siempre manda el nombre de la cajera, solo su cédula (a veces con "V"):
     * se toma el de su ficha de empleado. No se guarda; el sincronizador puede traerlo después.
     *
     * @param  iterable<CuadreCaja>  $cierres
     */
    public function completarNombres(iterable $cierres): void
    {
        $sinNombre = collect($cierres)->filter(fn (CuadreCaja $c) => ! $c->usuario_nombre && $c->usuario);
        if ($sinNombre->isEmpty()) {
            return;
        }

        $nombres = Cliente::query()
            ->whereIn('cedula', $sinNombre->map(fn (CuadreCaja $c) => $this->cedula($c->usuario))->unique()->values())
            ->pluck('nombre', 'cedula');

        foreach ($sinNombre as $cierre) {
            $nombre = $nombres[$this->cedula($cierre->usuario)] ?? null;
            if ($nombre) {
                $cierre->usuario_nombre = $nombre;
            }
        }
    }

    /**
     * Una línea por forma de pago y cuenta: lo de Suite y lo que contó Tesorería.
     *
     * @return list<array<string, mixed>>
     */
    public function lineas(CuadreCaja $cuadre): array
    {
        $factor = $cuadre->factor_cambio > 0 ? $cuadre->factor_cambio : null;
        $lineas = [];

        foreach ($cuadre->pagos as $pago) {
            /** @var CuadreCajaPago $pago */
            $clave = $this->clave($pago->codigo_metodo, $pago->codigo_cuenta);
            $lineas[$clave] ??= $this->lineaVacia($clave, $pago->codigo_metodo, $pago->codigo_cuenta, $pago->forma_pago, $pago->cuenta, $pago->en_dolares);
            $lineas[$clave]['suite'] += $pago->ingresos;
            $lineas[$clave]['suite_usd'] += $pago->ingresos_usd;
        }

        foreach ($cuadre->conteos as $conteo) {
            /** @var CuadreCajaConteo $conteo */
            $clave = $this->clave($conteo->codigo_metodo, $conteo->codigo_cuenta);
            $lineas[$clave] ??= $this->lineaVacia($clave, $conteo->codigo_metodo, $conteo->codigo_cuenta, $conteo->forma_pago, $conteo->cuenta, $conteo->en_dolares);
            $lineas[$clave]['tesoreria'] = $conteo->monto;
        }

        // Las formas de pago de la pantalla de cierre de Suite, aunque la cajera no haya declarado nada en ellas.
        foreach ($this->formasDelCuadre((string) $cuadre->sede) as $forma) {
            $lineas[$forma['clave']] ??= $this->lineaVacia($forma['clave'], $forma['codigo_metodo'], $forma['codigo_cuenta'], $forma['forma_pago'], $forma['cuenta'], $forma['en_dolares']);
        }

        foreach ($cuadre->lotes as $lote) {
            /** @var CuadreCajaLote $lote */
            $clave = $this->clave($lote->codigo_metodo, $lote->codigo_cuenta);
            if ($lote->codigo_cuenta === self::CUENTA_PUNTOS) {
                $lineas[$clave] ??= $this->lineaVacia($clave, $lote->codigo_metodo, self::CUENTA_PUNTOS, null, null, false);
            }
            if (isset($lineas[$clave])) {
                $lineas[$clave]['lotes'][] = [
                    'banco' => $lote->banco,
                    'titular' => $lote->titular,
                    'lote' => $lote->lote,
                    'monto' => round((float) $lote->monto, 2),
                    'conciliado' => (bool) $lote->ingreso?->es_conciliado,
                ];
            }
        }
        $lineas = $this->agruparPuntosDeVenta($lineas);
        $lineas = $this->aplicarReglasSuite($lineas, $cuadre, $factor);

        foreach ($lineas as &$linea) {
            $linea['suite'] = round($linea['suite'], 2);
            $linea['suite_usd'] = round($linea['suite_usd'], 2);
            $regla = $linea['regla'] ?? null;
            if ($regla === 'devolucion') {
                // Solo lectura: lo contado es lo devuelto, y entra entero a la diferencia.
                $linea['tesoreria'] = $linea['suite'];
                $linea['tesoreria_usd'] = $linea['suite_usd'];
                $linea['diferencia_usd'] = $linea['suite_usd'];

                continue;
            }
            if ($regla === 'no_suma') {
                continue;
            }
            if ($linea['tesoreria'] !== null) {
                $usd = $linea['en_dolares'] ? $linea['tesoreria'] : ($factor ? $linea['tesoreria'] / $factor : 0.0);
                $linea['tesoreria_usd'] = round($usd, 2);
            } elseif ($cuadre->conciliado()) {
                $linea['tesoreria_usd'] = 0.0;
            }
            if ($linea['tesoreria_usd'] !== null) {
                $linea['diferencia_usd'] = round($linea['tesoreria_usd'] - $linea['suite_usd'], 2);
            }
        }
        unset($linea);

        return $this->ordenarComoSuite(array_values($lineas), (string) $cuadre->sede);
    }

    /**
     * Reglas de las formas de pago de Suite (config cuadre_caja): la nota de crédito no suma y el efectivo
     * devuelto se calcula de las devoluciones de la cajera y suma a lo contado.
     *
     * @param  array<string, array<string, mixed>>  $lineas
     * @return array<string, array<string, mixed>>
     */
    private function aplicarReglasSuite(array $lineas, CuadreCaja $cuadre, ?float $factor): array
    {
        $noSuman = array_map('strtoupper', config('cuadre_caja.metodos_no_suman', []));
        $metodoDevolucion = strtoupper((string) config('cuadre_caja.metodo_devolucion_efectivo', ''));

        $notaCreditoBs = 0.0;
        foreach ($lineas as &$l) {
            $metodo = strtoupper((string) $l['codigo_metodo']);
            if (in_array($metodo, $noSuman, true)) {
                $l['regla'] = 'no_suma';
                $notaCreditoBs += $l['en_dolares'] ? $l['suite'] * (float) $factor : $l['suite'];
            }
        }
        unset($l);

        if ($metodoDevolucion === '') {
            return $lineas;
        }
        $clave = $this->clave($metodoDevolucion, '');
        $devueltoBs = $this->devolucionesBs($cuadre);
        if ($devueltoBs !== null && ! isset($lineas[$clave]) && $devueltoBs - $notaCreditoBs > 0.009) {
            $lineas[$clave] = $this->lineaVacia($clave, $metodoDevolucion, '', 'EFECTIVO DEVOLUCION', null, false);
        }
        if (isset($lineas[$clave])) {
            $linea = &$lineas[$clave];
            $linea['regla'] = 'devolucion';
            // Si Suite no lo mandó como cobro: devoluciones del día − lo que se pagó con nota de crédito.
            if (abs($linea['suite']) < 0.009 && $devueltoBs !== null) {
                $bs = max(0.0, round($devueltoBs - $notaCreditoBs, 2));
                $linea['suite'] = $bs;
                $linea['suite_usd'] = $factor ? round($bs / $factor, 2) : 0.0;
            }
            unset($linea);
        }

        return $lineas;
    }

    /** Total en Bs de las devoluciones registradas por la cajera de la caja ese día (ventas_documentos DEV). */
    private function devolucionesBs(CuadreCaja $cuadre): ?float
    {
        if (! Schema::hasTable('ventas_documentos') || ! Schema::hasColumn('ventas_documentos', 'usuario') || ! $cuadre->usuario || ! $cuadre->fecha_cierre) {
            return null;
        }
        $cedula = $this->cedula((string) $cuadre->usuario);
        $usuarios = array_values(array_unique(array_filter([(string) $cuadre->usuario, $cedula, 'V'.$cedula, 'E'.$cedula])));

        return (float) DB::table('ventas_documentos')
            ->where('sede', $cuadre->sede)
            ->whereDate('fecha', $cuadre->fecha_cierre->toDateString())
            ->where('tipo_documento', 'DEV')
            ->where(fn ($q) => $q->whereNull('estado')->orWhereRaw("LOWER(estado) = 'registrado'"))
            ->whereIn('usuario', $usuarios)
            ->sum('total_neto_bs');
    }

    /**
     * Todos los puntos de venta en Bs de una misma forma de pago (TARJ. DÉBITO) van en una sola línea,
     * sea cual sea el banco: se suman lo de Suite, lo contado y los lotes. Cada lote conserva su banco y
     * titular, que es con lo que se concilia. La cuenta de la línea es la del punto principal (banco por defecto).
     *
     * @param  array<string, array<string, mixed>>  $lineas
     * @return array<string, array<string, mixed>>
     */
    private function agruparPuntosDeVenta(array $lineas): array
    {
        $grupos = [];
        foreach ($lineas as $clave => $l) {
            if ($l['punto_venta'] && ! $l['en_dolares']) {
                $grupos[$l['codigo_metodo']][$clave] = $l;
            }
        }
        foreach ($grupos as $metodo => $grupo) {
            $reales = array_filter($grupo, fn ($l) => $l['codigo_cuenta'] !== self::CUENTA_PUNTOS);
            $principal = collect($reales ?: $grupo)->sortByDesc(fn ($l) => $l['suite'])->first();
            $contados = array_filter($grupo, fn ($l) => $l['tesoreria'] !== null);
            $claveGrupo = $this->clave((string) $metodo, self::CUENTA_PUNTOS);
            $forma = collect($grupo)->pluck('forma_pago')->first(fn ($f) => $f && $f !== $metodo) ?? $principal['forma_pago'];

            foreach (array_keys($grupo) as $c) {
                unset($lineas[$c]);
            }
            $lineas[$claveGrupo] = [
                'clave' => $claveGrupo,
                'codigo_metodo' => (string) $metodo,
                'codigo_cuenta' => self::CUENTA_PUNTOS,
                'forma_pago' => $forma,
                'cuenta' => $principal['cuenta'],
                'en_dolares' => false,
                'suite' => array_sum(array_column($grupo, 'suite')),
                'suite_usd' => array_sum(array_column($grupo, 'suite_usd')),
                'tesoreria' => $contados ? array_sum(array_column($contados, 'tesoreria')) : null,
                'tesoreria_usd' => null,
                'diferencia_usd' => null,
                'punto_venta' => true,
                'regla' => null,
                'agrupada' => true,
                'origenes' => array_values(array_map(fn ($l) => $l['codigo_cuenta'], $reales)),
                'orden_clave' => $principal['clave'],
                'lotes' => array_merge(...array_values(array_map(fn ($l) => $l['lotes'], $grupo))),
            ];
        }

        return $lineas;
    }

    /**
     * Formas de pago fijas del cuadre de una sede (las de la pantalla de cierre de Suite).
     *
     * @return list<array{clave:string, codigo_metodo:string, codigo_cuenta:string, forma_pago:?string, cuenta:?string, en_dolares:bool, punto_venta:bool, lotes:array}>
     */
    public function formasDelCuadre(string $sede): array
    {
        $lista = config('cuadre_caja.formas_cuadre.'.strtoupper($sede));
        if (is_array($lista) && $lista !== []) {
            $formas = [];
            foreach ($lista as $clave => [$forma, $cuenta, $enDolares]) {
                [$metodo, $codigoCuenta] = array_pad(explode('|', $clave, 2), 2, '');
                $formas[] = [
                    'clave' => $clave,
                    'codigo_metodo' => $metodo,
                    'codigo_cuenta' => $codigoCuenta,
                    'forma_pago' => $forma,
                    'cuenta' => $cuenta,
                    'en_dolares' => (bool) $enDolares,
                    'punto_venta' => $this->esPuntoDeVenta($metodo, $forma, $cuenta),
                    'lotes' => [],
                ];
            }

            return $formas;
        }

        $metodos = config('cuadre_caja.metodos_cuadre', []);

        return array_values(array_filter($this->formasDePago($sede), fn ($f) => in_array($f['codigo_metodo'], $metodos, true)));
    }

    /**
     * Filas del dashboard = mismas del arqueo (formas_cuadre), con puntos de venta en una sola fila global.
     *
     * @return array<string, array<string, mixed>>
     */
    private function filasBaseDashboard(string $sede): array
    {
        $noSuman = config('cuadre_caja.metodos_no_suman', []);
        $filas = [];
        $posHecho = [];

        foreach ($this->formasDelCuadre($sede) as $f) {
            $metodo = (string) $f['codigo_metodo'];
            if (in_array($metodo, $noSuman, true)) {
                continue;
            }
            $forma = mb_strtoupper(trim((string) ($f['forma_pago'] ?: $metodo)), 'UTF-8');
            if (! empty($f['punto_venta']) && empty($f['en_dolares'])) {
                if (isset($posHecho[$metodo])) {
                    continue;
                }
                $posHecho[$metodo] = true;
                $clave = $this->clave($metodo, self::CUENTA_PUNTOS);
                $filas[$clave] = [
                    'clave' => $clave,
                    'orden_clave' => (string) $f['clave'],
                    'codigo_metodo' => $metodo,
                    'codigo_cuenta' => self::CUENTA_PUNTOS,
                    'forma_pago' => $forma,
                    'forma' => $forma,
                    'cuenta' => 'Todos los puntos de venta',
                    'por_columna' => [],
                    'suite' => 0.0,
                    'tesoreria' => 0.0,
                ];

                continue;
            }

            $clave = (string) $f['clave'];
            $cuenta = trim((string) ($f['cuenta'] ?? ''));
            $filas[$clave] = [
                'clave' => $clave,
                'orden_clave' => $clave,
                'codigo_metodo' => $metodo,
                'codigo_cuenta' => (string) ($f['codigo_cuenta'] ?? ''),
                'forma_pago' => $forma,
                'forma' => $forma,
                'cuenta' => $cuenta !== '' ? $cuenta : null,
                'por_columna' => [],
                'suite' => 0.0,
                'tesoreria' => 0.0,
            ];
        }

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function ordenarComoSuite(array $lineas, string $sede = ''): array
    {
        $fijas = array_flip(array_keys(config('cuadre_caja.formas_cuadre.'.strtoupper($sede), [])));
        $metodos = array_flip(config('cuadre_caja.orden_metodos', []));
        $cuentas = array_flip(config('cuadre_caja.orden_cuentas', []));
        $fuera = PHP_INT_MAX;
        $orden = fn ($l) => [
            $fijas[$l['orden_clave'] ?? $l['clave']] ?? $fuera,
            $metodos[$l['codigo_metodo']] ?? $fuera, mb_strtoupper((string) $l['forma_pago']),
            $cuentas[$l['codigo_cuenta'] ?? ''] ?? (($l['codigo_cuenta'] ?? '') === '' ? -1 : $fuera), (string) $l['cuenta'],
        ];

        usort($lineas, fn ($a, $b) => $orden($a) <=> $orden($b));

        return $lineas;
    }

    /** @return array{ingresos_usd: float, tesoreria_usd: float|null, diferencia_usd: float|null, lineas: list<array<string, mixed>>} */
    public function resumen(CuadreCaja $cuadre): array
    {
        $lineas = $this->lineas($cuadre);
        // La nota de crédito no suma; el efectivo devuelto solo suma a lo contado (no a lo declarado).
        $declaradas = array_filter($lineas, fn ($l) => ($l['regla'] ?? null) === null);
        $contadas = array_filter($lineas, fn ($l) => ($l['regla'] ?? null) !== 'no_suma');
        $ingresos = round(array_sum(array_column($declaradas, 'suite_usd')), 2);
        if (! $cuadre->conciliado()) {
            return ['lineas' => $lineas, 'ingresos_usd' => $ingresos, 'tesoreria_usd' => null, 'diferencia_usd' => null];
        }
        $tesoreria = round(array_sum(array_map(fn ($l) => $l['tesoreria_usd'] ?? 0.0, $contadas)), 2);

        return [
            'lineas' => $lineas,
            'ingresos_usd' => $ingresos,
            'tesoreria_usd' => $tesoreria,
            'diferencia_usd' => round($tesoreria - $ingresos, 2),
        ];
    }

    /**
     * Formas de pago con cuenta que ya aparecieron en los cierres de la sede, para que Tesorería
     * pueda anotar una que la cajera no declaró.
     *
     * @return list<array{clave:string, codigo_metodo:string, codigo_cuenta:string, forma_pago:?string, cuenta:?string, en_dolares:bool, punto_venta:bool, lotes:array}>
     */
    public function formasDePago(string $sede): array
    {
        return $this->formasPorSede[$sede] ??= CuadreCajaPago::query()
            ->join('cuadres_caja as c', 'c.id', '=', 'cuadre_caja_pagos.cuadre_caja_id')
            ->where('c.sede', $sede)
            ->whereNotNull('cuadre_caja_pagos.codigo_metodo')
            ->select('cuadre_caja_pagos.codigo_metodo', 'cuadre_caja_pagos.codigo_cuenta', 'cuadre_caja_pagos.en_dolares')
            ->selectRaw('MAX(cuadre_caja_pagos.forma_pago) as forma_pago, MAX(cuadre_caja_pagos.cuenta) as cuenta')
            ->groupBy('cuadre_caja_pagos.codigo_metodo', 'cuadre_caja_pagos.codigo_cuenta', 'cuadre_caja_pagos.en_dolares')
            ->orderByRaw('MAX(cuadre_caja_pagos.forma_pago)')
            ->get()
            ->map(fn ($r) => [
                'clave' => $this->clave($r->codigo_metodo, $r->codigo_cuenta),
                'codigo_metodo' => (string) $r->codigo_metodo,
                'codigo_cuenta' => (string) ($r->codigo_cuenta ?? ''),
                'forma_pago' => $r->forma_pago,
                'cuenta' => $r->cuenta,
                'en_dolares' => (bool) $r->en_dolares,
                'punto_venta' => $this->esPuntoDeVenta($r->codigo_metodo, $r->forma_pago, $r->cuenta),
                'lotes' => [],
            ])
            ->unique('clave')
            ->values()
            ->all();
    }

    /**
     * Guarda lo que contó Tesorería y deja la caja procesada: se puede reabrir para corregirla.
     *
     * Los puntos de venta se cuentan por lotes: su monto es la suma de los lotes y cada lote queda en Tesorería.
     *
     * @param  array<string, string|float|int|null>  $montos  clave (metodo|cuenta) => monto en la moneda de la forma de pago
     * @param  array<string, list<array{lote?: mixed, monto?: mixed}>>  $lotes  clave => lotes del punto de venta
     */
    public function procesar(CuadreCaja $cuadre, array $montos, array $lotes, string $observacion, User $user): void
    {
        if (! $cuadre->pendiente()) {
            throw new \DomainException($cuadre->cerrado()
                ? 'Esta caja ya está cerrada y no se puede cambiar.'
                : 'Esta caja ya está procesada. Reábrela para cambiarla.');
        }

        DB::transaction(function () use ($cuadre, $montos, $lotes, $observacion, $user) {
            $this->guardarConteos($cuadre, $montos, $lotes, $user);
            $this->marcar($cuadre, CuadreCaja::PROCESADO, null, $observacion, $user);
            app(TesoreriaCajaEfectivoService::class)->sincronizarDesdeCuadre($cuadre->fresh(['conteos']), $user);
        });
    }

    /**
     * Cierre definitivo con motivo. Desde pendiente guarda también los montos y lotes; desde procesada los deja como están.
     *
     * @param  array<string, string|float|int|null>|null  $montos
     * @param  array<string, list<array{lote?: mixed, monto?: mixed}>>  $lotes
     */
    public function cerrar(CuadreCaja $cuadre, ?array $montos, array $lotes, string $motivo, string $observacion, User $user): void
    {
        if ($cuadre->cerrado()) {
            throw new \DomainException('Esta caja ya está cerrada.');
        }
        if (! array_key_exists($motivo, config('cuadre_caja.motivos_cierre', []))) {
            throw new \DomainException('Elige un motivo de cierre de la lista.');
        }

        DB::transaction(function () use ($cuadre, $montos, $lotes, $motivo, $observacion, $user) {
            if ($cuadre->pendiente() && $montos !== null) {
                $this->guardarConteos($cuadre, $montos, $lotes, $user);
            }
            $this->marcar($cuadre, CuadreCaja::CERRADO, $motivo, $observacion, $user);
            app(TesoreriaCajaEfectivoService::class)->sincronizarDesdeCuadre($cuadre->fresh(['conteos']), $user);
        });
    }

    /** Vuelve a dejar pendiente una caja procesada. Una cerrada no se reabre. */
    public function reabrir(CuadreCaja $cuadre, User $user): void
    {
        if (! $cuadre->procesado()) {
            throw new \DomainException($cuadre->cerrado()
                ? 'Una caja cerrada no se puede reabrir.'
                : 'Esta caja ya está abierta.');
        }

        DB::transaction(function () use ($cuadre, $user) {
            app(TesoreriaCajaEfectivoService::class)->quitarDesdeCuadre($cuadre);
            $cuadre->forceFill([
                'estado_tesoreria' => CuadreCaja::PENDIENTE,
                'motivo_cierre' => null,
                'conciliado_at' => null,
                'conciliado_por' => null,
                'reabierto_at' => now(),
                'reabierto_por' => $user->id,
            ])->save();
        });
    }

    private function marcar(CuadreCaja $cuadre, string $estado, ?string $motivo, string $observacion, User $user): void
    {
        $cuadre->forceFill([
            'estado_tesoreria' => $estado,
            'motivo_cierre' => $motivo,
            'observacion_tesoreria' => trim($observacion) ?: null,
            'conciliado_at' => now(),
            'conciliado_por' => $user->id,
        ])->save();
        $cuadre->unsetRelation('conteos');
    }

    /**
     * Un monto vacío borra el conteo de esa forma de pago.
     *
     * @param  array<string, string|float|int|null>  $montos
     * @param  array<string, list<array{lote?: mixed, monto?: mixed}>>  $lotes
     */
    private function guardarConteos(CuadreCaja $cuadre, array $montos, array $lotes, User $user): void
    {
        $conocidas = collect($this->lineas($cuadre))->keyBy('clave')
            ->union(collect($this->formasDelCuadre((string) $cuadre->sede))->keyBy('clave'))
            ->union(collect($this->formasDePago($cuadre->sede))->keyBy('clave'));

        $lotesPorLinea = [];
        foreach ($conocidas as $clave => $linea) {
            $tieneLotes = $linea['lotes'] ?? [];
            if (! $linea['punto_venta'] || (! array_key_exists($clave, $montos) && ! array_key_exists($clave, $lotes) && ! $tieneLotes)) {
                continue;
            }
            $lista = $this->normalizarLotes($lotes[$clave] ?? [], $linea, (string) $cuadre->sede);
            $lotesPorLinea[$clave] = $lista;
            $montos[$clave] = $lista ? array_sum(array_column($lista, 'monto')) : '';
        }

        foreach ($montos as $clave => $monto) {
            $linea = $conocidas->get($clave);
            if (! $linea) {
                continue;
            }
            $buscar = [
                'cuadre_caja_id' => $cuadre->id,
                'codigo_metodo' => (string) $linea['codigo_metodo'],
                'codigo_cuenta' => (string) ($linea['codigo_cuenta'] ?? ''),
            ];
            // La línea que junta los puntos reemplaza lo que se había contado punto por punto.
            if (! empty($linea['origenes'])) {
                CuadreCajaConteo::query()->where('cuadre_caja_id', $cuadre->id)
                    ->where('codigo_metodo', (string) $linea['codigo_metodo'])
                    ->whereIn('codigo_cuenta', $linea['origenes'])->delete();
            }
            if ($monto === null || trim((string) $monto) === '') {
                CuadreCajaConteo::query()->where($buscar)->delete();

                continue;
            }
            CuadreCajaConteo::query()->updateOrCreate($buscar, [
                'forma_pago' => $linea['forma_pago'],
                'cuenta' => ($linea['agrupada'] ?? false) ? 'PUNTOS DE VENTA' : $linea['cuenta'],
                'en_dolares' => (bool) $linea['en_dolares'],
                'monto' => round((float) $monto, 2),
                'user_id' => $user->id,
            ]);
        }

        foreach ($lotesPorLinea as $clave => $lista) {
            $this->sincronizarLotes($cuadre, $conocidas->get($clave), $lista, $user);
        }
        $cuadre->unsetRelation('lotes');
    }

    /**
     * @param  list<array{banco?: mixed, lote?: mixed, monto?: mixed}>  $lotes
     * @param  array<string, mixed>  $linea
     * @return list<array{banco: string, lote: string, monto: float}>
     */
    private function normalizarLotes(array $lotes, array $linea, string $sede): array
    {
        [$bancoCuenta, $titularCuenta] = $this->bancoTitularPuntoVenta($linea['cuenta'], $sede);
        $bancos = config('cuadre_caja.bancos_punto_venta', []);
        $titulares = config('cuadre_caja.titulares_punto_venta', []);
        $out = [];
        foreach ($lotes as $l) {
            $numero = trim((string) ($l['lote'] ?? ''));
            $monto = $l['monto'] ?? null;
            if ($numero === '' && ($monto === null || trim((string) $monto) === '')) {
                continue;
            }
            if ($numero === '') {
                throw new \DomainException('Falta el número de un lote de '.$linea['forma_pago'].($linea['cuenta'] && empty($linea['agrupada']) ? ' · '.$linea['cuenta'] : '').'.');
            }
            if (! is_numeric($monto) || (float) $monto <= 0) {
                throw new \DomainException("El lote {$numero} necesita un monto mayor que cero.");
            }
            $banco = strtoupper(trim((string) ($l['banco'] ?? ''))) ?: $bancoCuenta;
            if (! in_array($banco, $bancos, true) && $banco !== $bancoCuenta) {
                throw new \DomainException("Elige el banco del lote {$numero}.");
            }
            $titular = strtoupper(trim((string) ($l['titular'] ?? ''))) ?: $titularCuenta;
            if (! in_array($titular, $titulares, true) && $titular !== $titularCuenta) {
                throw new \DomainException("Elige el titular del lote {$numero}.");
            }
            $llave = $banco.'|'.$titular.'|'.mb_strtoupper($numero, 'UTF-8');
            if (isset($out[$llave])) {
                throw new \DomainException("El lote {$numero} de {$banco} · {$titular} está repetido en ".(empty($linea['agrupada']) && $linea['cuenta'] ? $linea['cuenta'] : $linea['forma_pago']).'.');
            }
            $out[$llave] = ['banco' => $banco, 'titular' => $titular, 'lote' => $numero, 'monto' => round((float) $monto, 2)];
        }

        return array_values($out);
    }

    /**
     * Deja los lotes del cuadre y sus lotes POS en Tesorería iguales a la lista. Un lote que el banco ya
     * concilió no se quita ni cambia de monto. Si Tesorería ya lo había cargado a mano, se enlaza ese.
     *
     * @param  array<string, mixed>  $linea
     * @param  list<array{banco: string, titular: string, lote: string, monto: float}>  $lista
     */
    private function sincronizarLotes(CuadreCaja $cuadre, array $linea, array $lista, User $user): void
    {
        [$bancoCuenta, $titularCuenta] = $this->bancoTitularPuntoVenta($linea['cuenta'], (string) $cuadre->sede);
        $fecha = $cuadre->fecha_cierre->toDateString();
        $descripcion = trim('Cuadre de caja '.$cuadre->sede.' · '.($cuadre->descripcion_caja ?: $cuadre->codigo_caja)
            .' · '.($cuadre->usuario_nombre ?: $cuadre->usuario));

        $cuentas = array_values(array_unique(array_merge([(string) ($linea['codigo_cuenta'] ?? '')], $linea['origenes'] ?? [])));
        $existentes = CuadreCajaLote::query()
            ->with('ingreso')
            ->where('cuadre_caja_id', $cuadre->id)
            ->where('codigo_metodo', (string) $linea['codigo_metodo'])
            ->whereIn('codigo_cuenta', $cuentas)
            ->get()
            ->keyBy(fn (CuadreCajaLote $l) => ($l->banco ?: $bancoCuenta).'|'.($l->titular ?: $titularCuenta).'|'.mb_strtoupper($l->lote, 'UTF-8'));
        $nuevos = collect($lista)->keyBy(fn ($l) => $l['banco'].'|'.$l['titular'].'|'.mb_strtoupper($l['lote'], 'UTF-8'));

        foreach ($existentes as $llave => $existente) {
            if ($nuevos->has($llave)) {
                continue;
            }
            if ($existente->ingreso?->es_conciliado) {
                throw new \DomainException("El lote {$existente->lote} ya está conciliado con el banco: no se puede quitar.");
            }
            $existente->ingreso?->delete();
            $existente->delete();
        }

        foreach ($nuevos as $llave => $nuevo) {
            $registro = $existentes->get($llave) ?? new CuadreCajaLote([
                'cuadre_caja_id' => $cuadre->id,
                'codigo_metodo' => (string) $linea['codigo_metodo'],
                'codigo_cuenta' => (string) ($linea['codigo_cuenta'] ?? ''),
            ]);
            $banco = $nuevo['banco'];
            $titular = $nuevo['titular'];
            $ingreso = $registro->ingreso ?? $this->loteTesoreriaSinEnlazar($banco, $titular, $fecha, $nuevo['lote']);

            if ($ingreso && $ingreso->es_conciliado && abs((float) $ingreso->monto - $nuevo['monto']) > 0.004) {
                throw new \DomainException("El lote {$nuevo['lote']} ya está conciliado con el banco por Bs "
                    .number_format((float) $ingreso->monto, 2, ',', '.').': no se puede cambiar el monto.');
            }
            if ($ingreso) {
                $ingreso->update(['monto' => $nuevo['monto']]);
            } else {
                $ingreso = TesoreriaIngreso::create([
                    'tipo' => 'punto_venta',
                    'banco' => $banco,
                    'titular' => $titular,
                    'fecha' => $fecha,
                    'monto' => $nuevo['monto'],
                    'lote_referencia' => $nuevo['lote'],
                    'descripcion' => $descripcion,
                    'user_id' => $user->id,
                ]);
            }

            $registro->fill([
                'codigo_cuenta' => (string) ($linea['codigo_cuenta'] ?? ''),
                'banco' => $banco,
                'titular' => $titular,
                'lote' => $nuevo['lote'],
                'monto' => $nuevo['monto'],
                'tesoreria_ingreso_id' => $ingreso->id,
                'user_id' => $user->id,
            ])->save();
        }
    }

    private function loteTesoreriaSinEnlazar(string $banco, string $titular, string $fecha, string $lote): ?TesoreriaIngreso
    {
        return TesoreriaIngreso::query()
            ->where('tipo', 'punto_venta')
            ->where('banco', $banco)
            ->where('titular', $titular)
            ->whereDate('fecha', $fecha)
            ->whereRaw('UPPER(TRIM(lote_referencia)) = ?', [mb_strtoupper(trim($lote), 'UTF-8')])
            ->whereNotIn('id', CuadreCajaLote::query()->whereNotNull('tesoreria_ingreso_id')->select('tesoreria_ingreso_id'))
            ->first();
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, float|int>
     */
    public function totales(array $filas): array
    {
        $conciliadas = array_filter($filas, fn (array $f) => $f['diferencia_usd'] !== null);
        $diferencias = array_column($conciliadas, 'diferencia_usd');

        return [
            'cierres' => count($filas),
            'pendientes' => count($filas) - count($conciliadas),
            'ingresos_usd' => round(array_sum(array_column($filas, 'ingresos_usd')), 2),
            'tesoreria_usd' => round(array_sum(array_map(fn ($f) => $f['tesoreria_usd'] ?? 0.0, $conciliadas)), 2),
            'diferencia_usd' => round(array_sum($diferencias), 2),
            'faltantes_usd' => round(array_sum(array_filter($diferencias, fn ($d) => $d < 0)), 2),
            'sobrantes_usd' => round(array_sum(array_filter($diferencias, fn ($d) => $d > 0)), 2),
        ];
    }

    public function clave(?string $metodo, ?string $cuenta): string
    {
        return strtoupper(trim((string) $metodo)).'|'.strtoupper(trim((string) $cuenta));
    }

    private function cedula(string $usuario): string
    {
        return (string) preg_replace('/\D/', '', $usuario);
    }

    /** @return array<string, mixed> */
    private function lineaVacia(string $clave, ?string $metodo, ?string $cuenta, ?string $forma, ?string $nombreCuenta, bool $enDolares): array
    {
        return [
            'clave' => $clave,
            'codigo_metodo' => (string) $metodo,
            'codigo_cuenta' => (string) ($cuenta ?? ''),
            'forma_pago' => $forma ?: $metodo,
            'cuenta' => $nombreCuenta,
            'en_dolares' => $enDolares,
            'suite' => 0.0,
            'suite_usd' => 0.0,
            'tesoreria' => null,
            'tesoreria_usd' => null,
            'diferencia_usd' => null,
            'punto_venta' => $cuenta === self::CUENTA_PUNTOS || $this->esPuntoDeVenta($metodo, $forma, $nombreCuenta),
            'lotes' => [],
            'regla' => null,
        ];
    }

    /**
     * Cobro con tarjeta en una cuenta de punto de venta ("BANESCO P.V. JRZ"). Lo que Suite manda por
     * transferencia a esa misma cuenta no es un lote del punto.
     */
    public function esPuntoDeVenta(?string $metodo, ?string $formaPago, ?string $cuenta): bool
    {
        $extra = array_map(fn ($c) => mb_strtoupper(trim((string) $c), 'UTF-8'), config('cuadre_caja.cuentas_punto_venta', []));
        if (! preg_match('/\bP\s*\.\s*V\b\.?|\bPUNTO DE VENTA\b/iu', (string) $cuenta)
            && ! in_array(mb_strtoupper(trim((string) $cuenta), 'UTF-8'), $extra, true)) {
            return false;
        }
        $forma = mb_strtoupper((string) $formaPago, 'UTF-8');

        return ! str_starts_with(strtoupper(trim((string) $metodo)), 'TRF')
            && ! str_contains($forma, 'TRANSF')
            && ! str_contains($forma, 'PAGO M');
    }

    /**
     * Banco y titular del lote POS en Tesorería: "BANESCO P.V. JRZ" → BANESCO / JRZ.
     *
     * @return array{0: string, 1: string}
     */
    public function bancoTitularPuntoVenta(?string $cuenta, string $sede): array
    {
        $sinPv = trim((string) preg_replace('/\bP\s*\.\s*V\b\.?|\bPUNTO DE VENTA\b/iu', ' ', (string) $cuenta));
        $sinPv = (string) preg_replace('/\s+/', ' ', $sinPv);
        [$banco, $titular] = app(BankReconciliationMatcher::class)->partesCuenta($sinPv, '');

        return [$banco, $titular !== '' ? $titular : strtoupper(trim($sede))];
    }

    /** @return array<string, string> sede => fecha y hora de la última subida */
    private function ultimaCarga(): array
    {
        return CuadreCaja::query()
            ->selectRaw('sede, MAX(updated_at) as ultima')
            ->groupBy('sede')
            ->pluck('ultima', 'sede')
            ->map(fn ($v) => Carbon::parse($v)->format('d/m/Y H:i'))
            ->all();
    }
}
