<?php

namespace App\Services;

use App\Models\Cliente;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reportes de la facturadora (Suite) armados con lo que sube el sincronizador de cada sede:
 * ventas_documentos (una fila por factura), ventas_pagos (cada cobro de caja) y cuadres_caja (cierres).
 *
 * Cada reporte devuelve columnas, filas y totales ya formateados: la misma vista y el mismo PDF sirven a todos.
 */
class ReportesSuiteService
{
    public const REPORTES = [
        'facturas' => 'Reporte general de facturas',
        'pagos' => 'Reporte general de pagos',
        'descuentos' => 'Ventas con descuentos',
        'creditos' => 'Ventas a crédito',
        'cuadre' => 'Consolidado - Cuadre de caja',
    ];

    public const ORIGENES = [
        'FACVEN' => 'Factura de venta',
        'DEVVEN' => 'Devolución de venta',
        'CXC' => 'Cobro de cuenta por cobrar',
    ];

    /**
     * @param  array<string, mixed>  $f  sede, desde, hasta y los filtros de cada reporte
     * @return array{columnas: list<array{0:string,1:string}>, filas: list<array{celdas: list<string>, negativo?: bool}>, totales: list<string>|null, total_filas: int}
     */
    public function generar(string $reporte, array $f): array
    {
        return match ($reporte) {
            'facturas' => $this->facturas($f),
            'pagos' => $this->pagos($f),
            'descuentos' => $this->descuentos($f),
            'creditos' => $this->creditos($f),
            'cuadre' => $this->cuadre($f),
        };
    }

    /** @return array<string, array<string, string>> opciones de los filtros (método, cuenta, motivo, usuario…) */
    public function opciones(string $reporte, array $f): array
    {
        $opciones = [];
        if (in_array($reporte, ['pagos', 'descuentos'], true) && Schema::hasTable('ventas_pagos')) {
            $opciones['metodo'] = $this->pagosBase($f)
                ->whereNotNull('p.codigo_metodo')
                ->selectRaw('p.codigo_metodo as clave, MAX(p.forma_pago) as etiqueta')
                ->groupBy('p.codigo_metodo')->orderBy('etiqueta')->pluck('etiqueta', 'clave')
                ->map(fn ($e, $c) => $e ?: $c)->all();
        }
        if ($reporte === 'pagos' && Schema::hasTable('ventas_pagos')) {
            $opciones['cuenta'] = $this->pagosBase($f)
                ->whereNotNull('p.codigo_cuenta')->where('p.codigo_cuenta', '<>', '')
                ->selectRaw('p.codigo_cuenta as clave, MAX(p.cuenta) as etiqueta')
                ->groupBy('p.codigo_cuenta')->orderBy('etiqueta')->pluck('etiqueta', 'clave')
                ->map(fn ($e, $c) => $e ?: $c)->all();
            $origenes = $this->pagosBase($f)->whereNotNull('p.origen')->distinct()->pluck('p.origen');
            $opciones['tipo_documento'] = $origenes->mapWithKeys(fn ($o) => [$o => self::ORIGENES[$o] ?? $o])->all();
            $usuarios = $this->pagosBase($f)->whereNotNull('p.usuario')->distinct()->pluck('p.usuario')->all();
            $nombres = $this->nombresPorCedula($usuarios);
            $opciones['usuario'] = collect($usuarios)->mapWithKeys(fn ($u) => [$u => $nombres[$u] ?? $u])->sort()->all();
        }
        if ($reporte === 'descuentos' && $this->hayColumna('ventas_documentos', 'motivo_descuento')) {
            $opciones['motivo'] = $this->documentosBase($f)
                ->whereNotNull('d.motivo_descuento')->distinct()->orderBy('d.motivo_descuento')
                ->pluck('d.motivo_descuento', 'd.motivo_descuento')->all();
        }
        if ($reporte === 'cuadre' && Schema::hasTable('cuadres_caja')) {
            $opciones['usuario'] = $this->cuadresBase($f)
                ->selectRaw('usuario, MAX(usuario_nombre) as nombre')->groupBy('usuario')->orderBy('nombre')
                ->pluck('nombre', 'usuario')->map(fn ($n, $u) => $n ?: $u)->all();
        }

        return $opciones;
    }

    private function facturas(array $f): array
    {
        $q = $this->documentosBase($f)->where('d.tipo_documento', 'FAC');
        $this->filtrarCliente($q, $f['cliente'] ?? null);
        $docs = $q->orderBy('d.fecha')->orderBy('d.numero_documento')->get();

        $filas = $docs->map(fn ($d) => ['celdas' => [
            $this->fecha($d->fecha), $d->numero_documento, $d->identificacion ?: ($d->codigo_cliente ?: '—'), $d->cliente ?: '—',
            $this->num($d->factor_cambio), $d->numero_fiscal ?: '0',
            $this->num($d->total_neto_bs), $this->num($d->total_neto_usd),
        ], 'documento' => $d->numero_documento, 'sede' => $d->sede])->all();

        return [
            'columnas' => [['Fecha', 'l'], ['N° Doc', 'l'], ['N° Ident.', 'l'], ['Descripción', 'l'], ['Tasa', 'r'], ['N° Fiscal', 'r'], ['Total factura Bs', 'r'], ['Total factura $', 'r']],
            'filas' => $filas,
            'totales' => ['Total', count($filas).' facturas', '', '', '', '', $this->num($docs->sum('total_neto_bs')), $this->num($docs->sum('total_neto_usd'))],
            'total_filas' => count($filas),
            'detalle' => true,
        ];
    }

    private function pagos(array $f): array
    {
        if (! Schema::hasTable('ventas_pagos')) {
            return $this->vacio('Todavía no se suben los pagos de caja: actualiza el sincronizador de la sede.');
        }
        $q = $this->pagosBase($f)
            ->leftJoin('ventas_documentos as d', function ($j) {
                $j->on('d.sede', '=', 'p.sede')->on('d.numero_documento', '=', 'p.numero_documento')->where('d.tipo_documento', 'FAC');
            })
            ->select('p.*', 'd.identificacion', 'd.codigo_cliente', 'd.cliente', 'd.numero_fiscal', 'd.total_neto_usd');
        foreach (['metodo' => 'p.codigo_metodo', 'cuenta' => 'p.codigo_cuenta', 'usuario' => 'p.usuario', 'tipo_documento' => 'p.origen'] as $filtro => $columna) {
            if (! empty($f[$filtro])) {
                $q->where($columna, $f[$filtro]);
            }
        }
        $this->filtrarCliente($q, $f['cliente'] ?? null);
        $pagos = $q->orderBy('p.fecha')->orderBy('p.id_movimiento')->get();

        $filas = $pagos->map(function ($p) {
            $signo = strtoupper((string) $p->tipo_movimiento) === 'EGRESO' ? -1 : 1;
            $metodo = trim(($p->forma_pago ?: $p->codigo_metodo).($p->cuenta ? ' '.$p->cuenta : ''));

            return ['celdas' => [
                $this->fechaHora($p->fecha), $p->numero_documento ?: '—', $p->identificacion ?: ($p->codigo_cliente ?: '—'),
                $metodo ?: '—', $this->num($p->tasa), $p->numero_fiscal ?: '0',
                $this->num($signo * (float) $p->monto_bs), $this->num($signo * (float) $p->monto_usd),
                $p->total_neto_usd !== null ? $this->num($p->total_neto_usd) : '—',
            ], 'negativo' => $signo < 0];
        })->all();
        $total = fn (string $campo) => $pagos->sum(fn ($p) => (strtoupper((string) $p->tipo_movimiento) === 'EGRESO' ? -1 : 1) * (float) $p->{$campo});

        return [
            'columnas' => [['Fecha', 'l'], ['N° Doc', 'l'], ['N° Identificación', 'l'], ['Método pago', 'l'], ['Tasa', 'r'], ['N° Fiscal', 'r'], ['Monto Bs', 'r'], ['Monto $', 'r'], ['Total factura $', 'r']],
            'filas' => $filas,
            'totales' => ['Total', count($filas).' pagos', '', '', '', '', $this->num($total('monto_bs')), $this->num($total('monto_usd')), ''],
            'total_filas' => count($filas),
        ];
    }

    private function descuentos(array $f): array
    {
        $q = $this->documentosBase($f)->where('d.tipo_documento', 'FAC')->where('d.total_descuento_usd', '>', 0.004);
        if (! empty($f['motivo']) && $this->hayColumna('ventas_documentos', 'motivo_descuento')) {
            $q->where('d.motivo_descuento', $f['motivo']);
        }
        if (! empty($f['metodo']) && Schema::hasTable('ventas_pagos')) {
            $q->whereExists(fn ($e) => $e->selectRaw('1')->from('ventas_pagos as p')
                ->whereColumn('p.sede', 'd.sede')->whereColumn('p.numero_documento', 'd.numero_documento')
                ->where('p.codigo_metodo', $f['metodo']));
        }
        $docs = $q->orderBy('d.fecha')->orderBy('d.numero_documento')->get();
        $metodos = $this->metodosPorDocumento($docs);

        $filas = $docs->map(function ($d) use ($metodos) {
            $bruto = (float) $d->total_neto_usd + (float) $d->total_descuento_usd;

            return ['celdas' => [
                $this->fecha($d->fecha), $d->numero_documento, $d->identificacion ?: ($d->codigo_cliente ?: '—'), $d->cliente ?: '—',
                $this->num($d->factor_cambio), $d->motivo_descuento ?? '—', $d->observacion ?? '—',
                $this->num($bruto), $this->num($d->total_descuento_usd), $this->num($d->total_neto_usd),
                $metodos[$d->sede.'|'.$d->numero_documento] ?? '—',
            ]];
        })->all();

        return [
            'columnas' => [['Fecha', 'l'], ['N° Doc', 'l'], ['Cliente', 'l'], ['Razón social', 'l'], ['Tasa', 'r'], ['Motivo', 'l'], ['Observación', 'l'], ['Total fact. $', 'r'], ['Total desc. $', 'r'], ['Total pagado $', 'r'], ['Método', 'l']],
            'filas' => $filas,
            'totales' => ['Total', count($filas).' facturas', '', '', '', '', '',
                $this->num($docs->sum(fn ($d) => (float) $d->total_neto_usd + (float) $d->total_descuento_usd)),
                $this->num($docs->sum('total_descuento_usd')), $this->num($docs->sum('total_neto_usd')), ''],
            'total_filas' => count($filas),
        ];
    }

    private function creditos(array $f): array
    {
        if (! $this->hayColumna('ventas_documentos', 'credito')) {
            return $this->vacio('Todavía no se suben los datos de crédito: actualiza el sincronizador de la sede.');
        }
        $q = $this->documentosBase($f);
        if (($f['solo_devoluciones'] ?? '') === '1') {
            $q->where('d.tipo_documento', 'DEV')->where('d.total_credito_usd', '<>', 0);
        } else {
            $q->where(fn ($w) => $w->where(fn ($fac) => $fac->where('d.tipo_documento', 'FAC')->where('d.credito', true))
                ->orWhere(fn ($dev) => $dev->where('d.tipo_documento', 'DEV')->where('d.total_credito_usd', '<>', 0)));
        }
        $this->filtrarCliente($q, $f['cliente'] ?? null);
        $docs = $q->orderBy('d.fecha')->orderBy('d.numero_documento')->get();
        $nombres = $this->nombresPorCedula($docs->pluck('autorizado_credito')->filter()->all());

        $filas = $docs->map(function ($d) use ($nombres) {
            $esDev = $d->tipo_documento === 'DEV';
            $signo = $esDev ? -1 : 1;
            $credito = $signo * abs((float) $d->total_credito_usd);
            $abonado = $d->saldo_credito_usd !== null ? $credito - $signo * abs((float) $d->saldo_credito_usd) : null;

            return ['celdas' => [
                $this->fecha($d->fecha), $d->numero_documento, $d->identificacion ?: ($d->codigo_cliente ?: '—'), $d->cliente ?: '—',
                $this->num($signo * abs((float) $d->total_neto_usd)), $this->num($d->factor_cambio),
                $abonado !== null ? $this->num($abonado) : '—', $this->num($credito),
                $d->autorizado_credito ? ($nombres[$d->autorizado_credito] ?? $d->autorizado_credito) : '',
            ], 'negativo' => $esDev];
        })->all();

        $credito = $docs->sum(fn ($d) => ($d->tipo_documento === 'DEV' ? -1 : 1) * abs((float) $d->total_credito_usd));
        $saldo = $docs->sum(fn ($d) => ($d->tipo_documento === 'DEV' ? -1 : 1) * abs((float) ($d->saldo_credito_usd ?? 0)));

        return [
            'columnas' => [['Fecha', 'l'], ['N° Doc', 'l'], ['Cliente', 'l'], ['Descripción', 'l'], ['Total fact. $', 'r'], ['Tasa', 'r'], ['Abonado', 'r'], ['Total cred. $', 'r'], ['Autorizado', 'l']],
            'filas' => $filas,
            'totales' => ['Total', count($filas).' documentos', '', '', '', '', $this->num($credito - $saldo), $this->num($credito), ''],
            'total_filas' => count($filas),
        ];
    }

    /**
     * Consolidado de cuadres como se cuadró cada caja en la web: el cuadre es lo que contó Tesorería menos lo
     * declarado en Suite (solo cuando ya se procesó o cerró). Facturas y venta salen del cierre de Suite y, si
     * todavía no llegaron, de los cobros de la caja.
     */
    private function cuadre(array $f): array
    {
        $q = \App\Models\CuadreCaja::query()
            ->with(['pagos', 'conteos', 'lotes.ingreso'])
            ->when(! empty($f['sede']), fn ($q) => $q->where('sede', $f['sede']))
            ->whereBetween('fecha_cierre', [$f['desde'], $f['hasta']]);
        if (! empty($f['usuario'])) {
            $q->where('usuario', $f['usuario']);
        }
        $cuadres = $q->orderBy('fecha_cierre')->orderBy('codigo_caja')->get();
        $servicio = app(CuadreCajaService::class);
        $servicio->completarNombres($cuadres);
        $faltan = $cuadres->filter(fn ($c) => ! $c->usuario_nombre && $c->usuario)->pluck('usuario')->unique()->all();
        if ($faltan) {
            $nombres = $this->nombresPorCedula($faltan);
            $cuadres->each(function ($c) use ($nombres) {
                $c->usuario_nombre = $c->usuario_nombre ?: ($nombres[$c->usuario] ?? null);
            });
        }
        $conTotal = ($f['mostrar_total'] ?? '1') === '1';
        $facturasPorCaja = $this->facturasPorCaja($f);
        $estados = CuadreCajaService::ESTADOS;

        $sumFacturas = 0;
        $sumVenta = 0.0;
        $sumCuadre = 0.0;
        $filas = [];
        foreach ($cuadres as $c) {
            $resumen = $servicio->resumen($c);
            $clave = $c->sede.'|'.$c->fecha_cierre?->toDateString().'|'.$c->codigo_caja.'|'.$c->usuario;
            $facturas = $c->facturas_suite ?? ($facturasPorCaja[$clave] ?? null);
            $venta = $c->total_venta_usd !== null ? (float) $c->total_venta_usd : $resumen['ingresos_usd'];
            $diferencia = $resumen['diferencia_usd'];
            $sumFacturas += (int) $facturas;
            $sumVenta += $venta;
            $sumCuadre += (float) $diferencia;

            $filas[] = ['celdas' => array_values(array_filter([
                $this->fecha($c->fecha_cierre), $c->usuario ?: '—', $c->usuario_nombre ?: '—',
                $c->observacion_tesoreria ?: ($c->observacion_suite ?? ''), $c->codigo_caja ?: '—', $this->num($c->factor_cambio),
                $facturas !== null ? (string) $facturas : '—',
                $conTotal ? $this->num($venta) : null,
                $estados[$c->estadoTesoreria()] ?? $c->estadoTesoreria(),
                $diferencia !== null ? $this->num($diferencia) : '—',
            ], fn ($v) => $v !== null)), 'negativo' => $diferencia !== null && $diferencia < -0.004];
        }

        $columnas = [['Fecha', 'l'], ['Usuario', 'l'], ['Descripción', 'l'], ['Observación', 'l'], ['Caja', 'l'], ['Tasa', 'r'], ['N° facturas', 'r']];
        if ($conTotal) {
            $columnas[] = ['Total venta', 'r'];
        }
        $columnas[] = ['Estado', 'l'];
        $columnas[] = ['Cuadre de caja', 'r'];
        $totales = ['Total', count($filas).' cierres', '', '', '', '', (string) $sumFacturas];
        if ($conTotal) {
            $totales[] = $this->num($sumVenta);
        }
        $totales[] = '';
        $totales[] = $this->num($sumCuadre);

        return ['columnas' => $columnas, 'filas' => $filas, 'totales' => $totales, 'total_filas' => count($filas)];
    }

    /** @return array<string, int> «sede|fecha|caja|usuario» => facturas cobradas en esa caja ese día */
    private function facturasPorCaja(array $f): array
    {
        if (! Schema::hasTable('ventas_pagos')) {
            return [];
        }

        return $this->pagosBase($f)
            ->where('p.origen', 'FACVEN')
            ->whereNotNull('p.numero_documento')
            ->selectRaw('p.sede, DATE(p.fecha) as dia, p.codigo_caja, p.usuario, COUNT(DISTINCT p.numero_documento) as n')
            ->groupBy('p.sede', DB::raw('DATE(p.fecha)'), 'p.codigo_caja', 'p.usuario')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->sede.'|'.substr((string) $r->dia, 0, 10).'|'.$r->codigo_caja.'|'.$r->usuario => (int) $r->n])
            ->all();
    }

    /** Renglones de una factura para el botón «Detalle». */
    public function detalleFactura(string $sede, string $numero): Collection
    {
        return DB::table('ventas_detalle')
            ->where('sede', $sede)->where('tipo_documento', 'FAC')->where('numero_documento', $numero)
            ->orderBy('item_numero')
            ->get(['codigo_producto', 'nombre_producto', 'cantidad', 'precio_neto']);
    }

    private function documentosBase(array $f)
    {
        return DB::table('ventas_documentos as d')
            ->when(! empty($f['sede']), fn ($q) => $q->where('d.sede', $f['sede']))
            ->whereBetween('d.fecha', [$f['desde'], $f['hasta']])
            ->where(fn ($q) => $q->whereNull('d.estado')->orWhereRaw("LOWER(d.estado) = 'registrado'"));
    }

    private function pagosBase(array $f)
    {
        return DB::table('ventas_pagos as p')
            ->when(! empty($f['sede']), fn ($q) => $q->where('p.sede', $f['sede']))
            ->where('p.fecha', '>=', $f['desde'].' 00:00:00')
            ->where('p.fecha', '<=', $f['hasta'].' 23:59:59');
    }

    private function cuadresBase(array $f)
    {
        return DB::table('cuadres_caja')
            ->when(! empty($f['sede']), fn ($q) => $q->where('sede', $f['sede']))
            ->whereBetween('fecha_cierre', [$f['desde'], $f['hasta']]);
    }

    private function filtrarCliente($q, ?string $cliente): void
    {
        $cliente = trim((string) $cliente);
        if ($cliente === '') {
            return;
        }
        $like = '%'.mb_strtoupper($cliente, 'UTF-8').'%';
        $q->where(fn ($w) => $w->whereRaw('UPPER(d.cliente) LIKE ?', [$like])
            ->orWhereRaw('UPPER(d.identificacion) LIKE ?', [$like])
            ->orWhereRaw('UPPER(d.codigo_cliente) LIKE ?', [$like]));
    }

    /** @return array<string, string> sede|documento => «EFECTIVO $, TARJ. DÉBITO» */
    private function metodosPorDocumento(Collection $docs): array
    {
        if ($docs->isEmpty() || ! Schema::hasTable('ventas_pagos')) {
            return [];
        }

        return DB::table('ventas_pagos')
            ->whereIn('numero_documento', $docs->pluck('numero_documento')->unique()->all())
            ->whereIn('sede', $docs->pluck('sede')->unique()->all())
            ->get(['sede', 'numero_documento', 'forma_pago', 'codigo_metodo'])
            ->groupBy(fn ($p) => $p->sede.'|'.$p->numero_documento)
            ->map(fn ($g) => $g->map(fn ($p) => $p->forma_pago ?: $p->codigo_metodo)->filter()->unique()->implode(', '))
            ->all();
    }

    /**
     * Cédula de un usuario de Suite → nombre (de los cuadres de caja o de la ficha del cliente/empleado).
     *
     * @param  iterable<string>  $cedulas
     * @return array<string, string>
     */
    public function nombresPorCedula(iterable $cedulas): array
    {
        $cedulas = collect($cedulas)->filter()->map(fn ($c) => trim((string) $c))->unique()->values();
        if ($cedulas->isEmpty()) {
            return [];
        }
        $nombres = Schema::hasTable('cuadres_caja')
            ? DB::table('cuadres_caja')->whereIn('usuario', $cedulas->all())->whereNotNull('usuario_nombre')
                ->pluck('usuario_nombre', 'usuario')->all()
            : [];
        $faltan = $cedulas->reject(fn ($c) => isset($nombres[$c]));
        if ($faltan->isNotEmpty()) {
            $porCedula = Cliente::query()
                ->whereIn('cedula', $faltan->map(fn ($c) => preg_replace('/\D/', '', $c))->filter()->all())
                ->pluck('nombre', 'cedula');
            foreach ($faltan as $c) {
                $nombre = $porCedula[preg_replace('/\D/', '', $c)] ?? null;
                if ($nombre) {
                    $nombres[$c] = $nombre;
                }
            }
        }

        return $nombres;
    }

    private function hayColumna(string $tabla, string $columna): bool
    {
        return Schema::hasTable($tabla) && Schema::hasColumn($tabla, $columna);
    }

    private function vacio(string $aviso): array
    {
        return ['columnas' => [], 'filas' => [], 'totales' => null, 'total_filas' => 0, 'aviso' => $aviso];
    }

    private function num(mixed $n): string
    {
        $v = round((float) $n, 2);

        return ($v < 0 ? '-' : '').number_format(abs($v), 2, ',', '.');
    }

    private function fecha(mixed $valor): string
    {
        return $valor ? \Carbon\Carbon::parse($valor)->format('d/m/Y') : '—';
    }

    private function fechaHora(mixed $valor): string
    {
        return $valor ? \Carbon\Carbon::parse($valor)->format('d/m/Y h:i a') : '—';
    }
}
