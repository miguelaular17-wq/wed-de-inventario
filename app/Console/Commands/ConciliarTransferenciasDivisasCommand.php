<?php

namespace App\Console\Commands;

use App\Services\Conciliacion\TransferenciasDivisasImporter;
use Illuminate\Console\Command;

class ConciliarTransferenciasDivisasCommand extends Command
{
    protected $signature = 'conciliacion:transferencias-divisas
        {pdf : Ruta del PDF de transferencias (Dashboard | Cloud Mai Services)}
        {--banco=MERCANTIL : Banco del extracto}
        {--titular=GRUPO JENU : Titular de la cuenta}
        {--desde= : Fecha mínima de los débitos (por defecto, 3 días antes de la primera transferencia)}
        {--hasta= : Fecha máxima de los débitos (por defecto, 3 días después de la última)}
        {--rehacer-por-monto : Deshace primero las compras emparejadas solo por monto (sin su referencia) para volver a emparejarlas}
        {--aplicar : Guarda las compras de divisas y concilia los débitos (sin esto solo muestra el resultado)}';

    protected $description = 'Empareja las transferencias en divisas de un PDF con los débitos pendientes del banco y las registra como compras de divisas conciliadas.';

    public function handle(TransferenciasDivisasImporter $importer): int
    {
        ini_set('memory_limit', '1G');
        $pdf = (string) $this->argument('pdf');
        if (! is_file($pdf)) {
            $this->error("No existe el archivo: {$pdf}");

            return self::FAILURE;
        }

        $transferencias = $importer->leerPdf($pdf);
        $totalUsd = array_sum(array_column($transferencias, 'monto_usd'));
        $this->info(sprintf('PDF: %d transferencias · $%s', count($transferencias), number_format($totalUsd, 2)));

        $banco = (string) $this->option('banco');
        $titular = (string) $this->option('titular');
        $fechas = array_column($transferencias, 'fecha');
        $desde = $this->option('desde') ?: ($fechas ? date('Y-m-d', strtotime(min($fechas) . ' -3 days')) : null);
        $hasta = $this->option('hasta') ?: ($fechas ? date('Y-m-d', strtotime(max($fechas) . ' +3 days')) : null);

        $simular = [];
        if ($this->option('rehacer-por-monto')) {
            $porMonto = $importer->comprasPorMonto($banco, $titular, $desde, $hasta);
            $this->warn(sprintf('Compras emparejadas solo por monto que se rehacen: %d', count($porMonto)));
            $antes = [];
            foreach ($porMonto as $c) {
                foreach ($c['lineas'] as $l) {
                    $antes[$l->id] = $c['motivo'];
                }
            }

            if ($this->option('aplicar')) {
                $this->info('Deshechas: ' . $importer->deshacerCompras(array_column($porMonto, 'id')));
            } else {
                $simular = array_column($porMonto, 'id');
            }
        }

        $r = $importer->emparejar($transferencias, $banco, $titular, $desde, $hasta, $simular);

        if ($this->option('rehacer-por-monto')) {
            $this->table(['Débito', 'Bs.', 'Antes', 'Ahora'], collect($r['pares'])
                ->flatMap(fn ($p) => array_map(fn ($l) => [$l, $p], array_merge([$p['linea']], $p['extras'] ?? [])))
                ->filter(fn ($x) => isset($antes[$x[0]->id]) || $x[1]['como'] === 'monto_fecha')
                ->map(fn ($x) => [
                    $x[0]->referencia,
                    number_format(abs((float) $x[0]->monto), 2),
                    isset($antes[$x[0]->id]) ? mb_substr(preg_replace('/^Transferencia /', '', $antes[$x[0]->id]), 0, 40) : '(pendiente)',
                    '#' . $x[1]['t']['nro'] . ' ' . mb_substr($x[1]['t']['titular'], 0, 22) . ' ref ' . $x[1]['t']['referencia'] . ' · ' . $x[1]['como'],
                ])->values()->all());
        }

        $porComo = collect($r['pares'])->countBy('como');
        $this->line(sprintf('Débitos pendientes revisados: %d', $r['lineas_pendientes']));
        $this->table(['Cómo', 'Transferencias'], [
            ['Ya registradas en una corrida anterior', count($r['ya_registradas'] ?? [])],
            ['Referencia igual', $porComo['referencia'] ?? 0],
            ['Últimos dígitos de la referencia + monto', $porComo['referencia_final'] ?? 0],
            ['Monto exacto + fecha (±3 días)', $porComo['monto_fecha'] ?? 0],
            ['Misma referencia, otra tasa (±5 %)', $porComo['referencia_otra_tasa'] ?? 0],
            ['Pagado en varios débitos del mismo día', $porComo['pago_partido'] ?? 0],
            ['Sin pareja', count($r['sin_pareja'])],
        ]);
        $this->line(sprintf(
            'Emparejadas: $%s · Bs. %s',
            number_format(collect($r['pares'])->sum(fn ($p) => $p['t']['monto_usd']), 2),
            number_format(collect($r['pares'])->sum(fn ($p) => abs((float) $p['linea']->monto) + array_sum(array_map(fn ($x) => abs((float) $x->monto), $p['extras'] ?? []))), 2),
        ));

        if ($r['sin_pareja'] !== []) {
            $this->warn('Sin pareja en el banco:');
            $this->table(['#', 'Fecha', 'Ref', 'Banco', 'Titular', 'USD', 'Tasa', 'Bs esperado'], array_map(fn ($t) => [
                $t['nro'], $t['fecha'], $t['referencia'], $t['banco'], mb_substr($t['titular'], 0, 28),
                number_format($t['monto_usd'], 2), $t['tasa'], number_format($t['monto_bs'], 2),
            ], $r['sin_pareja']));
        }

        if (! $this->option('aplicar')) {
            $this->comment('Prueba: no se guardó nada. Agrega --aplicar para registrar las compras de divisas y conciliar.');

            return self::SUCCESS;
        }

        $hechas = $importer->aplicar($r['pares']);
        $this->info("Listo: {$hechas} compras de divisas registradas y conciliadas.");

        return self::SUCCESS;
    }
}
