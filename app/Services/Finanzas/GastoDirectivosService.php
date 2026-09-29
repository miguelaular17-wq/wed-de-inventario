<?php

namespace App\Services\Finanzas;

use App\Models\FlujoCaja;
use App\Models\HistorialCobranza;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GastoDirectivosService
{
    /**
     * Personas a mostrar. Varios nombres pueden compartir el mismo código de cliente
     * (p. ej. MARIA NUÑES / MARIA ISABEL NUÑEZ → V24525502).
     *
     * @var array<int, array{codigo:string,nombre:string,aliases:array<int,string>}>
     */
    public const PERSONAS = [
        [
            'codigo' => 'V24703210',
            'nombre' => 'JOSE LEONARDO JEREZ',
            'aliases' => ['JOSE LEONARDO JEREZ'],
        ],
        [
            'codigo' => 'V24525502',
            'nombre' => 'MARIA ISABEL NUÑEZ',
            'aliases' => [
                'MARIA ISABEL NUÑEZ',
                'MARIA ISABEL',
                'MARIA NUÑES',
                'MARIA NUNES',
                'MARIA NUÑEZ',
                'MARIA NUNEZ',
            ],
        ],
    ];

    /** @var array<string, string> codigo => etiqueta */
    public const CONCEPTOS = [
        '093' => 'GASTOS DIRECTIVO',
        '047' => 'REPUESTOS VEHICULOS DIRECTIVO',
        '041' => 'SUELDO Y SALARIO MARIA NUÑEZ',
        '040' => 'SUELDOS Y SALARIO DIRECTIVO',
        '039' => 'GASTOS INTERNET DIRECTIVO',
        '034' => 'COMBUSTIBLE VEHICULOS DIRECTIVO',
        '016' => 'MANT. Y REP. VEHICULOS DIRECTIVO',
    ];

    /**
     * @return array{
     *   desde:string,
     *   hasta:string,
     *   personas: Collection<int, array>,
     *   egresos: Collection<int, FlujoCaja>,
     *   egresos_por_concepto: Collection<int, array>,
     *   totales: array<string, float|int>,
     * }
     */
    public function resumen(string $desde, string $hasta): array
    {
        $personas = $this->personasConFacturas($desde, $hasta);
        $egresos = $this->egresosEnRango($desde, $hasta);
        $egresosPorConcepto = $this->agruparEgresosPorConcepto($egresos);

        $totUsd = round((float) $egresos->sum(fn (FlujoCaja $m) => (float) $m->monto_usd), 2);
        $totBs = round((float) $egresos->sum(fn (FlujoCaja $m) => (float) $m->monto_bs), 2);
        $totDif = round((float) $egresos->sum(fn (FlujoCaja $m) => (float) ($m->diferencial_cambiario ?? 0)), 2);
        $saldoCobranza = round((float) $personas->sum('saldo'), 2);
        $montoFacturas = round((float) $personas->sum('monto'), 2);

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'personas' => $personas,
            'egresos' => $egresos,
            'egresos_por_concepto' => $egresosPorConcepto,
            'totales' => [
                'egresos_usd' => $totUsd,
                'egresos_bs' => $totBs,
                'egresos_dif' => $totDif,
                'egresos_count' => $egresos->count(),
                'cobranza_saldo' => $saldoCobranza,
                'facturas_monto' => $montoFacturas,
                'facturas' => (int) $personas->sum('documentos'),
                'personas' => $personas->count(),
            ],
        ];
    }

    /**
     * Facturas de ventas_documentos a nombre de cada directivo (rango de fechas)
     * + saldo pendiente de cobranza (snapshot actual).
     *
     * @return Collection<int, array{
     *   codigo:string,
     *   nombre:string,
     *   saldo:float,
     *   monto:float,
     *   documentos:int,
     *   facturas:Collection<int, array>
     * }>
     */
    public function personasConFacturas(string $desde, string $hasta): Collection
    {
        $saldosCobranza = $this->saldosCobranzaPorCodigo();
        $saldosPorDoc = $this->saldosCobranzaPorDocumento();
        $docsVentas = $this->ventasDocumentosEnRango($desde, $hasta);
        $itemsPorDoc = $this->lineasVentaPorDocumentos($docsVentas);

        $out = collect();

        foreach (self::PERSONAS as $persona) {
            $aliasesNorm = collect($persona['aliases'])
                ->map(fn ($a) => $this->normalizarNombre($a))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $docsPersona = $docsVentas->filter(function ($doc) use ($aliasesNorm) {
                return in_array($this->normalizarNombre((string) ($doc->cliente ?? '')), $aliasesNorm, true);
            });

            $facturas = $docsPersona->map(function ($doc) use ($itemsPorDoc, $saldosPorDoc) {
                $tipo = strtoupper(trim((string) $doc->tipo_documento));
                $numero = (string) $doc->numero_documento;
                $sede = strtoupper(trim((string) $doc->sede));
                $clave = $sede.'|'.$tipo.'|'.$numero;
                $signo = $tipo === 'DEV' ? -1 : 1;
                $monto = round($signo * abs((float) $doc->total_neto_usd), 2);
                $saldoPend = $saldosPorDoc->get($clave);

                return [
                    'numero' => $numero,
                    'tipo' => $tipo !== '' ? $tipo : 'FAC',
                    'fecha' => $doc->fecha ? Carbon::parse($doc->fecha)->toDateString() : null,
                    'sede' => (string) ($doc->sede ?? '—'),
                    'nombre' => (string) ($doc->cliente ?? ''),
                    'monto' => $monto,
                    'saldo' => $saldoPend !== null ? round((float) $saldoPend, 2) : null,
                    'estatus' => (string) ($doc->estado ?? ''),
                    'dias' => null,
                    'items' => $itemsPorDoc->get($clave, collect()),
                ];
            })
                ->sortByDesc('fecha')
                ->values();

            $cobranza = $saldosCobranza->get($persona['codigo'], ['saldo' => 0.0, 'documentos' => 0]);

            $out->push([
                'codigo' => $persona['codigo'],
                'nombre' => $persona['nombre'],
                'saldo' => round((float) $cobranza['saldo'], 2),
                'monto' => round((float) $facturas->sum('monto'), 2),
                'documentos' => $facturas->count(),
                'facturas' => $facturas,
            ]);
        }

        return $out;
    }

    /**
     * @return Collection<string, array{saldo:float,documentos:int}>
     */
    private function saldosCobranzaPorCodigo(): Collection
    {
        $map = collect();
        foreach (collect(self::PERSONAS)->pluck('codigo')->unique() as $codigo) {
            $map[$codigo] = ['saldo' => 0.0, 'documentos' => 0];
        }

        if (! Schema::connection('pgsql')->hasTable('historial_cobranzas')) {
            return $map;
        }

        $ultima = HistorialCobranza::query()->max('fecha_registro');
        if (! $ultima) {
            return $map;
        }

        $codigos = $map->keys()->all();
        $rows = HistorialCobranza::cuentasOperativas()
            ->where('fecha_registro', $ultima)
            ->whereIn('codigo_cliente', $codigos)
            ->get();

        foreach ($codigos as $codigo) {
            $deCodigo = $rows->where('codigo_cliente', $codigo);
            $facturas = $this->agruparFacturasCobranza($deCodigo);
            $map[$codigo] = [
                'saldo' => round((float) $facturas->sum('saldo'), 2),
                'documentos' => $facturas->count(),
            ];
        }

        return $map;
    }

    /**
     * Clave sede|tipo|numero => saldo pendiente.
     *
     * @return Collection<string, float>
     */
    private function saldosCobranzaPorDocumento(): Collection
    {
        if (! Schema::connection('pgsql')->hasTable('historial_cobranzas')) {
            return collect();
        }

        $ultima = HistorialCobranza::query()->max('fecha_registro');
        if (! $ultima) {
            return collect();
        }

        $codigos = collect(self::PERSONAS)->pluck('codigo')->unique()->values()->all();
        $rows = HistorialCobranza::cuentasOperativas()
            ->where('fecha_registro', $ultima)
            ->whereIn('codigo_cliente', $codigos)
            ->get();

        return $this->agruparFacturasCobranza($rows)
            ->mapWithKeys(function (array $fac) {
                $sede = strtoupper(trim((string) $fac['sede']));
                $tipo = strtoupper(trim((string) $fac['tipo']));
                $numero = trim((string) $fac['numero']);

                return [$sede.'|'.$tipo.'|'.$numero => (float) $fac['saldo']];
            });
    }

    /**
     * @return Collection<int, object>
     */
    private function ventasDocumentosEnRango(string $desde, string $hasta): Collection
    {
        if (! Schema::hasTable('ventas_documentos') || ! Schema::hasColumn('ventas_documentos', 'cliente')) {
            return collect();
        }

        $aliases = collect(self::PERSONAS)->pluck('aliases')->flatten()->unique()->values();

        $query = DB::table('ventas_documentos')
            ->whereBetween('fecha', [$desde, $hasta])
            ->whereIn(DB::raw('UPPER(tipo_documento)'), ['FAC', 'DEV'])
            ->where(function ($q) use ($aliases) {
                foreach ($aliases as $alias) {
                    $q->orWhereRaw('UPPER(TRIM(cliente)) = ?', [mb_strtoupper(trim($alias), 'UTF-8')]);
                    // Variantes sin tilde / ñ
                    $q->orWhereRaw(
                        "UPPER(TRANSLATE(TRIM(cliente), 'ÁÉÍÓÚÄËÏÖÜÑáéíóúäëïöüñ', 'AEIOUAEIOUNAEIOUAEIOUN')) = ?",
                        [$this->normalizarNombre($alias)]
                    );
                }
            })
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        return $query->get([
            'sede',
            'tipo_documento',
            'numero_documento',
            'fecha',
            'estado',
            'total_neto_usd',
            'cliente',
        ]);
    }

    /**
     * @param  Collection<int, object>  $docs
     * @return Collection<string, Collection<int, array{detalle:string,cantidad:float,precio:float,total:float}>>
     */
    private function lineasVentaPorDocumentos(Collection $docs): Collection
    {
        if ($docs->isEmpty() || ! Schema::hasTable('ventas_detalle')) {
            return collect();
        }

        $pares = $docs->map(fn ($d) => [
            'sede' => (string) $d->sede,
            'tipo' => strtoupper(trim((string) $d->tipo_documento)),
            'numero' => (string) $d->numero_documento,
        ])->unique(fn ($p) => $p['sede'].'|'.$p['tipo'].'|'.$p['numero'])->values();

        if ($pares->isEmpty()) {
            return collect();
        }

        $query = DB::table('ventas_detalle')
            ->where(function ($q) use ($pares) {
                foreach ($pares as $p) {
                    $q->orWhere(function ($qq) use ($p) {
                        $qq->where('sede', $p['sede'])
                            ->whereRaw('UPPER(tipo_documento) = ?', [$p['tipo']])
                            ->where('numero_documento', $p['numero']);
                    });
                }
            });

        if (Schema::hasColumn('ventas_detalle', 'anulado')) {
            $query->where(function ($q) {
                $q->where('anulado', false)->orWhereNull('anulado');
            });
        }

        $lineas = $query->get([
            'sede',
            'tipo_documento',
            'numero_documento',
            'nombre_producto',
            'cantidad',
            'precio_venta',
            'precio_neto',
        ]);

        return $lineas->groupBy(function ($row) {
            return strtoupper(trim((string) $row->sede)).'|'
                .strtoupper(trim((string) $row->tipo_documento)).'|'
                .trim((string) $row->numero_documento);
        })->map(function (Collection $items) {
            return $items->map(function ($row) {
                $precio = (float) ($row->precio_neto ?? 0) > 0
                    ? (float) $row->precio_neto
                    : (float) ($row->precio_venta ?? 0);
                $cant = (float) ($row->cantidad ?? 0);

                return [
                    'detalle' => trim((string) ($row->nombre_producto ?? '')),
                    'cantidad' => $cant,
                    'precio' => $precio,
                    'total' => round($cant * $precio, 2),
                ];
            })->filter(fn ($i) => $i['detalle'] !== '')->values();
        });
    }

    /**
     * @param  Collection<int, HistorialCobranza>  $rows
     * @return Collection<int, array{numero:string,tipo:string,sede:string,saldo:float}>
     */
    private function agruparFacturasCobranza(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return collect();
        }

        return $rows->groupBy(function ($row) {
            $num = trim((string) ($row->numero_documento ?: $row->id_documento ?: $row->factura_padre));

            return $num !== '' ? $num : ('id-'.$row->id);
        })->map(function (Collection $lineas) {
            $cabecera = $lineas->first(function ($row) {
                return (float) ($row->monto_neto ?? 0) > 0
                    || (float) ($row->saldo ?? 0) > 0
                    || (float) ($row->total_factura ?? 0) > 0
                    || (float) ($row->saldo_pendiente ?? 0) > 0;
            }) ?? $lineas->first();

            return [
                'numero' => (string) ($cabecera->numero_documento ?: $cabecera->id_documento ?: '—'),
                'tipo' => strtoupper(trim((string) ($cabecera->tipo_cxc ?? 'FAC'))) ?: 'FAC',
                'sede' => (string) ($cabecera->sede_nombre ?? ''),
                'saldo' => round((float) ($cabecera->saldo_pendiente ?? $cabecera->saldo ?? 0), 2),
            ];
        })->values();
    }

    private function normalizarNombre(string $nombre): string
    {
        $n = mb_strtoupper(trim($nombre), 'UTF-8');
        $n = strtr($n, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'Ä' => 'A', 'Ë' => 'E', 'Ï' => 'I', 'Ö' => 'O', 'Ü' => 'U',
            'Ñ' => 'N',
        ]);
        $n = preg_replace('/\s+/', ' ', $n) ?? $n;

        return $n;
    }

    /**
     * @return Collection<int, FlujoCaja>
     */
    public function egresosEnRango(string $desde, string $hasta): Collection
    {
        if (! Schema::hasTable('flujo_cajas')) {
            return collect();
        }

        $query = FlujoCaja::query()
            ->where('tipo', 'egreso')
            ->whereBetween('fecha', [$desde, $hasta])
            ->where(function ($q) {
                foreach (array_keys(self::CONCEPTOS) as $code) {
                    $q->orWhere('tipo_gasto', 'like', $code.' - %')
                        ->orWhere('tipo_gasto', 'like', $code.'-%');
                }
            })
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if (Schema::hasColumn('flujo_cajas', 'oculto')) {
            $query->where(function ($q) {
                $q->where('oculto', false)->orWhereNull('oculto');
            });
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, FlujoCaja>  $egresos
     * @return Collection<int, array{codigo:string,label:string,count:int,usd:float,bs:float}>
     */
    public function agruparEgresosPorConcepto(Collection $egresos): Collection
    {
        $grouped = collect();

        foreach (self::CONCEPTOS as $codigo => $label) {
            $filas = $egresos->filter(function (FlujoCaja $m) use ($codigo) {
                $tg = strtoupper(trim((string) $m->tipo_gasto));

                return str_starts_with($tg, $codigo.' -') || str_starts_with($tg, $codigo.'-');
            });

            $grouped->push([
                'codigo' => $codigo,
                'label' => $label,
                'tipo_gasto' => $codigo.' - '.$label,
                'count' => $filas->count(),
                'usd' => round((float) $filas->sum(fn (FlujoCaja $m) => (float) $m->monto_usd), 2),
                'bs' => round((float) $filas->sum(fn (FlujoCaja $m) => (float) $m->monto_bs), 2),
            ]);
        }

        return $grouped->sortByDesc('usd')->values();
    }

    public function parseFecha(?string $raw, string $fallback): string
    {
        try {
            return $raw ? Carbon::parse($raw)->toDateString() : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
