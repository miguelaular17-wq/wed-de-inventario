<?php

namespace App\Services\Finanzas;

use App\Models\FlujoCaja;
use App\Models\HistorialCobranza;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class GastoDirectivosService
{
    /** @var array<string, string> codigo_cliente => nombre */
    public const PERSONAS = [
        'V24703210' => 'JOSE LEONARDO JEREZ',
        'V24525502' => 'MARIA NUÑEZ',
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
        $personas = $this->cobranzaPersonas();
        $egresos = $this->egresosEnRango($desde, $hasta);
        $egresosPorConcepto = $this->agruparEgresosPorConcepto($egresos);

        $totUsd = round((float) $egresos->sum(fn (FlujoCaja $m) => (float) $m->monto_usd), 2);
        $totBs = round((float) $egresos->sum(fn (FlujoCaja $m) => (float) $m->monto_bs), 2);
        $totDif = round((float) $egresos->sum(fn (FlujoCaja $m) => (float) ($m->diferencial_cambiario ?? 0)), 2);
        $saldoCobranza = round((float) $personas->sum('saldo'), 2);

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
                'personas' => $personas->count(),
            ],
        ];
    }

    /**
     * @return Collection<int, array{codigo:string,nombre:string,saldo:float,documentos:int,docs:Collection}>
     */
    public function cobranzaPersonas(): Collection
    {
        $out = collect();

        if (! Schema::connection('pgsql')->hasTable('historial_cobranzas')) {
            foreach (self::PERSONAS as $codigo => $nombre) {
                $out->push([
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'saldo' => 0.0,
                    'documentos' => 0,
                    'docs' => collect(),
                ]);
            }

            return $out;
        }

        $ultima = HistorialCobranza::query()->max('fecha_registro');

        foreach (self::PERSONAS as $codigo => $nombreDefault) {
            $docs = collect();
            $saldo = 0.0;
            $nombre = $nombreDefault;

            if ($ultima) {
                $docs = HistorialCobranza::cuentasOperativas()
                    ->where('fecha_registro', $ultima)
                    ->where('codigo_cliente', $codigo)
                    ->orderBy('fecha_emision')
                    ->get();

                $saldo = round((float) $docs->sum('saldo'), 2);
                $nombreDb = trim((string) optional($docs->first())->nombre_cliente);
                if ($nombreDb !== '') {
                    $nombre = $nombreDb;
                }
            }

            $out->push([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'saldo' => $saldo,
                'documentos' => $docs->count(),
                'docs' => $docs,
            ]);
        }

        return $out;
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
