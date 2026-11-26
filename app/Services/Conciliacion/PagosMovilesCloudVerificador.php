<?php

namespace App\Services\Conciliacion;

use App\Models\ConciliacionLinea;
use App\Models\TesoreriaIngreso;
use App\Services\BankReconciliationMatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reporte de pago móvil de Cloud Mai (Banesco / BDV Venezuela):
 * «REPORTE ESPECIFICO/MENSUAL DE BANESCO|BDV» con NRO, FECHA, REFERENCIA PAGO, MONTO…
 * Cada cobro se busca en los abonos de pago móvil de la cuenta banco+titular elegida.
 */
class PagosMovilesCloudVerificador
{
    /** Días que puede tardar el abono en aparecer después del pago (fin de semana). */
    public const DIAS_ABONO = 4;

    public function __construct(
        private ExtractoParser $parser,
        private BankReconciliationMatcher $matcher,
    ) {}

    /**
     * @return list<array{nro: string, fecha: string, ref: string, monto: float, emisor: string, caja: string, cajero: string, localidad: string, tipo: string, ref_banco: string}>
     */
    public function leer(string $path, string $ext): array
    {
        $norm = fn ($v) => mb_strtoupper(trim(preg_replace('/\s+/u', ' ', (string) $v) ?? ''), 'UTF-8');
        $cols = null;
        $out = [];
        foreach ($this->parser->filasCrudas($path, $ext) as $fila) {
            $fila = array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('d/m/Y H:i:s') : trim((string) $v), array_values((array) $fila));
            if ($cols === null) {
                $celdas = array_map($norm, $fila);
                $c = function (array $nombres) use ($celdas): ?int {
                    foreach ($nombres as $n) {
                        $i = array_search($n, $celdas, true);
                        if ($i !== false) {
                            return (int) $i;
                        }
                    }

                    return null;
                };
                $cols = [
                    'nro' => $c(['NRO', 'PAGO']),
                    'fecha' => $c(['FECHA']),
                    'ref' => $c(['REFERENCIA PAGO', 'REF', 'REFERENCIA']),
                    'monto' => $c(['MONTO', 'IMPORTE']),
                    'emisor' => $c(['BANCO EMISOR', 'BANCO']),
                    'caja' => $c(['TERMINAL']),
                    'cajero' => $c(['CAJERO']),
                    'localidad' => $c(['LOCALIDAD']),
                    'tipo' => $c(['TIPO']),
                    'ref_banco' => $c(['REFERENCIA BANCO']),
                ];
                if (in_array(null, [$cols['fecha'], $cols['ref'], $cols['monto']], true)) {
                    $cols = null;
                }

                continue;
            }
            $v = fn (string $k) => $cols[$k] === null ? '' : ($fila[$cols[$k]] ?? '');
            $nro = preg_replace('/\D/', '', $v('nro')) ?? '';
            $fecha = $this->fecha($v('fecha'));
            $ref = trim($v('ref'));
            $monto = (float) ($this->parser->normalizarMonto($v('monto')) ?? 0);
            if ($fecha === null || $ref === '' || $monto <= 0) {
                continue;
            }
            // Filas de encabezado repetidas o totales.
            if ($nro === '' && ! preg_match('/\d/', $ref)) {
                continue;
            }
            $out[] = [
                'nro' => $nro !== '' ? $nro : (string) (count($out) + 1),
                'fecha' => $fecha,
                'ref' => $ref,
                'monto' => round($monto, 2),
                'emisor' => $v('emisor'),
                'caja' => $v('caja'),
                'cajero' => $v('cajero'),
                'localidad' => $v('localidad'),
                'tipo' => $v('tipo'),
                'ref_banco' => $v('ref_banco'),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $pagos
     * @return array{items: list<array<string, mixed>>, desde: ?string, hasta: ?string, abonos: int, banco: string, titular: string}
     */
    public function verificar(array $pagos, string $banco, string $titular): array
    {
        $fechas = array_filter(array_column($pagos, 'fecha'));
        if ($fechas === []) {
            return ['items' => [], 'desde' => null, 'hasta' => null, 'abonos' => 0, 'banco' => $banco, 'titular' => $titular];
        }
        $desde = date('Y-m-d', strtotime(min($fechas)));
        $hasta = date('Y-m-d', strtotime(max($fechas) . ' +' . self::DIAS_ABONO . ' days'));

        $lineas = ConciliacionLinea::query()
            ->where('banco', $banco)
            ->where('titular', $titular)
            ->whereBetween('fecha', [$desde, $hasta])
            ->where(fn ($q) => $q->where('tipo', 'abono')->orWhere(fn ($w) => $w->whereNull('tipo')->where('monto', '>', 0)))
            ->get(['id', 'banco', 'titular', 'fecha', 'referencia', 'descripcion', 'monto', 'tipo', 'estado', 'flujo_caja_id', 'tesoreria_ingreso_id']);

        $porMonto = $this->porMonto($lineas);
        $usadas = [];
        $items = [];
        foreach ($pagos as $p) {
            $items[] = $this->clasificar($p, $porMonto, $usadas);
        }

        return [
            'items' => $items,
            'desde' => $desde,
            'hasta' => $hasta,
            'abonos' => $lineas->count(),
            'banco' => $banco,
            'titular' => $titular,
        ];
    }

    /**
     * @param  array<string, mixed>  $p
     * @param  array<int, list<ConciliacionLinea>>  $porMonto
     * @param  array<int, true>  $usadas
     * @return array<string, mixed>
     */
    private function clasificar(array $p, array $porMonto, array &$usadas): array
    {
        $cent = (int) round($p['monto'] * 100);
        $candidatos = $porMonto[$cent] ?? [];
        $refOk = fn (ConciliacionLinea $l) => $this->matcher->referenciasCruzan($l->referencia, $p['ref'])
            || $this->matcher->referenciaPagoContenida($l->referencia, $p['ref']);
        $enVentana = function (ConciliacionLinea $l) use ($p): bool {
            $pago = strtotime($p['fecha']);
            $abono = strtotime($l->fecha->format('Y-m-d'));
            $diff = ($abono - $pago) / 86400;

            return $diff >= 0 && $diff <= self::DIAS_ABONO;
        };

        $pendientes = array_values(array_filter($candidatos, fn ($l) => ! isset($usadas[$l->id])
            && $l->estado === 'pendiente'
            && $l->tesoreria_ingreso_id === null
            && $l->flujo_caja_id === null
            && $refOk($l)
            && $enVentana($l)
            && $this->matcher->esPagoMovil($l->descripcion)));

        usort($pendientes, fn ($a, $b) => [$a->fecha->timestamp, $a->id] <=> [$b->fecha->timestamp, $b->id]);
        if ($pendientes !== []) {
            $l = $pendientes[0];
            $usadas[$l->id] = true;

            return $p + ['estado' => 'conciliar', 'linea' => $l, 'nota' => null];
        }

        $ya = array_values(array_filter($candidatos, fn ($l) => $refOk($l) && $enVentana($l) && (
            $l->estado === 'conciliado' || $l->tesoreria_ingreso_id !== null || $l->flujo_caja_id !== null
        )));
        if ($ya !== []) {
            return $p + ['estado' => 'ya_conciliado', 'linea' => $ya[0], 'nota' => 'El abono ya está ligado a otro registro.'];
        }

        $parecido = array_values(array_filter($candidatos, fn ($l) => ! isset($usadas[$l->id]) && $enVentana($l)));
        if ($parecido !== []) {
            return $p + [
                'estado' => 'revisar',
                'linea' => $parecido[0],
                'nota' => 'Hay un abono del mismo monto en la ventana de fechas, pero la referencia no cuadra.',
            ];
        }

        return $p + ['estado' => 'no_encontrado', 'linea' => null, 'nota' => null];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{hechos: int, ocupados: int}
     */
    public function aplicar(array $items, string $archivo, ?int $userId): array
    {
        $hechos = 0;
        $ocupados = 0;
        foreach ($items as $it) {
            if (($it['estado'] ?? '') !== 'conciliar' || ! ($it['linea'] ?? null)) {
                continue;
            }
            $this->aplicarUno($it, $archivo, $userId) ? $hechos++ : $ocupados++;
        }

        return ['hechos' => $hechos, 'ocupados' => $ocupados];
    }

    private function aplicarUno(array $it, string $archivo, ?int $userId): bool
    {
        try {
            return DB::transaction(function () use ($it, $archivo, $userId) {
                /** @var ConciliacionLinea $l */
                $l = $it['linea'];
                $ingreso = TesoreriaIngreso::create([
                    'tipo' => 'banco',
                    'banco' => $l->banco,
                    'titular' => $l->titular,
                    'fecha' => $l->fecha->format('Y-m-d'),
                    'monto' => abs((float) $l->monto),
                    'lote_referencia' => $it['ref'],
                    'descripcion' => mb_substr(sprintf(
                        'Pago móvil Cloud #%s · ref %s · %s · %s · %s',
                        $it['nro'], $it['ref'], $it['caja'], $it['cajero'], $archivo
                    ), 0, 255),
                    'user_id' => $userId,
                    'es_conciliado' => true,
                ]);
                $n = DB::table('conciliacion_lineas')->where('id', $l->id)->where('estado', 'pendiente')
                    ->whereNull('flujo_caja_id')->whereNull('tesoreria_ingreso_id')
                    ->when(Schema::hasColumn('conciliacion_lineas', 'compra_divisa_id'), fn ($q) => $q->whereNull('compra_divisa_id'))
                    ->update(['estado' => 'conciliado', 'tesoreria_ingreso_id' => $ingreso->id, 'updated_at' => now()]);
                if ($n !== 1) {
                    throw new VinculoOcupadoException;
                }

                return true;
            });
        } catch (VinculoOcupadoException) {
            return false;
        }
    }

    /** @return array<int, list<ConciliacionLinea>> */
    private function porMonto(Collection $lineas): array
    {
        $out = [];
        foreach ($lineas as $l) {
            $out[(int) round(abs((float) $l->monto) * 100)][] = $l;
        }

        return $out;
    }

    private function fecha(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        return $this->parser->normalizarFecha($raw);
    }
}
