<?php

namespace App\Services;

use Illuminate\Support\Collection;

class CobranzaIndicatorService
{
    public function calcular(Collection $registros, array $codigosPersonales = []): array
    {
        $personales = collect($codigosPersonales)
            ->map(fn ($codigo) => $this->normalizar($codigo))
            ->filter()
            ->flip();
        $estatusTotales = collect(['CRITICO', 'MOROSO', 'RECIENTE', 'APARTADO'])
            ->mapWithKeys(fn ($estatus) => [$estatus => [
                'clientes' => 0,
                'saldo' => 0.0,
                'regulares' => 0,
                'personales' => 0,
                'saldo_regulares' => 0.0,
                'saldo_personales' => 0.0,
            ]])
            ->all();

        $porSede = [];
        $granTotalSaldo = 0.0;
        $granTotalClientes = 0;

        foreach ($registros->groupBy('sede_nombre') as $sede => $registrosSede) {
            $clientes = $registrosSede->groupBy(function ($registro) {
                $codigo = $this->normalizar($registro->codigo_cliente ?? null);

                return $codigo !== ''
                    ? $codigo
                    : 'SIN-CODIGO:'.($registro->id_documento ?? $registro->id);
            });
            $saldoSede = round((float) $registrosSede->sum('saldo'), 2);

            $porSede[] = (object) [
                'sede_nombre' => $sede,
                'total_clientes' => $clientes->count(),
                'total_saldo' => $saldoSede,
            ];
            $granTotalSaldo += $saldoSede;
            $granTotalClientes += $clientes->count();

            foreach ($clientes as $registrosCliente) {
                $primero = $registrosCliente->first();
                $codigo = $this->normalizar($primero->codigo_cliente ?? null);
                $saldoCliente = round((float) $registrosCliente->sum('saldo'), 2);
                $estatus = $this->estatusMasCritico($registrosCliente);
                $esPersonal = $personales->has($codigo);

                $estatusTotales[$estatus]['clientes']++;
                $estatusTotales[$estatus]['saldo'] += $saldoCliente;
                $tipo = $esPersonal ? 'personales' : 'regulares';
                $estatusTotales[$estatus][$tipo]++;
                $estatusTotales[$estatus]['saldo_'.$tipo] += $saldoCliente;
            }
        }

        usort($porSede, fn ($a, $b) => strcmp((string) $a->sede_nombre, (string) $b->sede_nombre));

        $porEstatus = collect($estatusTotales)->map(
            fn (array $totales, string $estatus) => (object) [
                'estatus' => $estatus,
                'total_clientes' => $totales['clientes'],
                'total_saldo' => $totales['saldo'],
                'regulares' => $totales['regulares'],
                'personales' => $totales['personales'],
                'saldo_regulares' => $totales['saldo_regulares'],
                'saldo_personales' => $totales['saldo_personales'],
            ]
        )->values()->all();

        return [
            'por_sede' => $porSede,
            'por_estatus' => $porEstatus,
            'total_saldo' => round($granTotalSaldo, 2),
            'total_clientes' => $granTotalClientes,
        ];
    }

    /**
     * Un deudor por código (global), con saldo total y peores indicadores.
     *
     * @param  list<string>  $codigosPersonales
     * @return array{
     *   deudores: list<object>,
     *   total_deudores: int,
     *   total_saldo: float,
     *   personales: int,
     *   por_estatus: array<string, array{clientes:int,saldo:float}>
     * }
     */
    public function deudoresUnicos(Collection $registros, array $codigosPersonales = []): array
    {
        $personales = collect($codigosPersonales)
            ->map(fn ($codigo) => $this->normalizar($codigo))
            ->filter()
            ->flip();

        $porEstatus = [
            'CRITICO' => ['clientes' => 0, 'saldo' => 0.0],
            'MOROSO' => ['clientes' => 0, 'saldo' => 0.0],
            'RECIENTE' => ['clientes' => 0, 'saldo' => 0.0],
            'APARTADO' => ['clientes' => 0, 'saldo' => 0.0],
        ];

        $docs = $registros->filter(
            fn ($r) => (float) ($r->monto_neto ?? 0) > 0 || (float) ($r->saldo ?? 0) > 0
        );

        $deudores = $docs
            ->groupBy(function ($registro) {
                $codigo = $this->normalizar($registro->codigo_cliente ?? null);

                return $codigo !== ''
                    ? $codigo
                    : 'SIN-CODIGO:'.($registro->id_documento ?? $registro->id);
            })
            ->map(function (Collection $rows) use ($personales) {
                $primero = $rows->first();
                $codigo = $this->normalizar($primero->codigo_cliente ?? null);
                $saldo = round((float) $rows->sum('saldo'), 2);
                $estatus = $this->estatusMasCritico($rows);
                $esPersonal = $codigo !== '' && (
                    $personales->has($codigo)
                    || (bool) ($primero->es_personal ?? false)
                );
                $sedes = $rows->pluck('sede_nombre')
                    ->map(fn ($s) => trim((string) $s))
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values();

                return (object) [
                    'codigo' => $codigo !== '' ? $codigo : (string) ($primero->codigo_cliente ?? '—'),
                    'cliente' => (string) ($primero->nombre_cliente ?? '—'),
                    'saldo' => $saldo,
                    'monto_neto' => round((float) $rows->sum('monto_neto'), 2),
                    'estatus' => $estatus,
                    'es_personal' => $esPersonal,
                    'sedes' => $sedes->implode(', '),
                    'documentos' => $rows->count(),
                    'nota' => $rows->pluck('nota_anclada')->filter()->unique()->implode(' | '),
                ];
            })
            ->filter(fn ($d) => (float) $d->saldo > 0.009)
            ->sortByDesc('saldo')
            ->values();

        foreach ($deudores as $deudor) {
            $estatus = $deudor->estatus;
            if (! isset($porEstatus[$estatus])) {
                $estatus = 'RECIENTE';
            }
            $porEstatus[$estatus]['clientes']++;
            $porEstatus[$estatus]['saldo'] += (float) $deudor->saldo;
        }

        return [
            'deudores' => $deudores->all(),
            'total_deudores' => $deudores->count(),
            'total_saldo' => round((float) $deudores->sum('saldo'), 2),
            'personales' => $deudores->where('es_personal', true)->count(),
            'por_estatus' => $porEstatus,
        ];
    }

    public function estatusMasCritico(Collection $registros): string
    {
        $prioridad = ['APARTADO' => 1, 'RECIENTE' => 2, 'MOROSO' => 3, 'CRITICO' => 4];

        return $registros
            ->map(fn ($registro) => strtoupper(trim((string) ($registro->estatus ?? 'RECIENTE'))))
            ->map(fn ($estatus) => isset($prioridad[$estatus]) ? $estatus : 'RECIENTE')
            ->sortByDesc(fn ($estatus) => $prioridad[$estatus])
            ->first() ?? 'RECIENTE';
    }

    private function normalizar(mixed $valor): string
    {
        return mb_strtoupper(trim((string) $valor), 'UTF-8');
    }
}
