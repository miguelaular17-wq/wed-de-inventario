<?php

namespace App\Services;

use App\Models\ConciliacionLinea;
use App\Models\TesoreriaIngreso;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Shuchkin\SimpleXLSX;

/**
 * Conciliación de lotes POS Banco de Venezuela vía Excel "Conciliación medios de pago".
 * Cruza Monto Neto del Excel con abonos LIQ.* del extracto (mismo monto).
 */
class BdvMediosPagoConciliacionService
{
    public function __construct(
        private BankReconciliationMatcher $matcher,
    ) {}

    /**
     * @return array{lotes:int, conciliados:int, creados:int, sin_match:array<int, array>}
     */
    public function importarYConciliar(
        UploadedFile|string $file,
        string $banco = 'VENEZUELA',
        ?string $titular = null,
    ): array {
        $lotes = $this->parseExcel($file);

        return $this->aplicarLotes($lotes, $banco, $titular);
    }

    /**
     * @param  array<int, array{fecha:string,lote:string,transacciones:?int,bruto:float,impuesto:float,comision:float,neto:float}>  $lotes
     * @return array{lotes:int, conciliados:int, creados:int, sin_match:array<int, array>}
     */
    public function aplicarLotes(array $lotes, string $banco = 'VENEZUELA', ?string $titular = null): array
    {
        [$bancoCanon, $titularCanon] = $this->matcher->partesCuenta($banco, $titular);
        $lineasLiq = $this->lineasLiqPendientes($bancoCanon, $titularCanon);

        $conciliados = 0;
        $creados = 0;
        $sinMatch = [];

        DB::transaction(function () use ($lotes, $bancoCanon, $titularCanon, &$lineasLiq, &$conciliados, &$creados, &$sinMatch) {
            foreach ($lotes as $lote) {
                $linea = $this->buscarLineaPorNeto($lineasLiq, (float) $lote['neto'], $lote['fecha']);
                if (! $linea) {
                    $sinMatch[] = $lote;
                    continue;
                }

                $titularLote = $titularCanon
                    ?: (trim((string) $linea->titular) !== '' ? (string) $linea->titular : 'GRUPO JRZ');
                if ($this->matcher->titularClave($titularLote) === 'JRZ') {
                    $titularLote = 'GRUPO JRZ';
                }
                $antes = TesoreriaIngreso::query()
                    ->where('tipo', 'punto_venta')
                    ->whereRaw('UPPER(TRIM(banco)) LIKE ?', ['%VENEZUELA%'])
                    ->where('lote_referencia', $lote['lote'])
                    ->whereDate('fecha', $lote['fecha'])
                    ->exists();

                $ingreso = $this->upsertLote($lote, $bancoCanon, $titularLote);
                if (! $antes) {
                    $creados++;
                }

                $linea->estado = 'conciliado';
                $linea->tesoreria_ingreso_id = $ingreso->id;
                $linea->flujo_caja_id = null;
                $linea->save();

                $ingreso->es_conciliado = true;
                $ingreso->save();

                $lineasLiq = $lineasLiq->reject(fn ($l) => (int) $l->id === (int) $linea->id)->values();
                $conciliados++;
            }
        });

        return [
            'lotes' => count($lotes),
            'conciliados' => $conciliados,
            'creados' => $creados,
            'sin_match' => $sinMatch,
        ];
    }

    /**
     * @return array<int, array{fecha:string,lote:string,transacciones:?int,bruto:float,impuesto:float,comision:float,neto:float}>
     */
    public function parseExcel(UploadedFile|string $file): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        if (! is_string($path) || ! is_file($path)) {
            return [];
        }

        $xlsx = SimpleXLSX::parse($path);
        if (! $xlsx) {
            return [];
        }

        return $this->rowsToLotes($xlsx->rows());
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, array{fecha:string,lote:string,transacciones:?int,bruto:float,impuesto:float,comision:float,neto:float}>
     */
    private function rowsToLotes(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $header = array_map(fn ($v) => mb_strtolower(trim((string) $v), 'UTF-8'), $rows[0] ?? []);
        $idxFecha = $this->findCol($header, ['fecha']);
        $idxLote = $this->findCol($header, ['n° lote', 'nº lote', 'no lote', 'n lote', 'lote']);
        $idxTx = $this->findCol($header, ['n° transacciones', 'nº transacciones', 'transacciones']);
        $idxBruto = $this->findCol($header, ['monto bruto', 'bruto']);
        $idxImp = $this->findCol($header, ['impuesto']);
        $idxCom = $this->findCol($header, ['comisión', 'comision']);
        $idxNeto = $this->findCol($header, ['monto neto', 'neto']);

        if ($idxFecha === null || $idxLote === null || $idxNeto === null) {
            $idxFecha = 0;
            $idxLote = 1;
            $idxTx = 2;
            $idxBruto = 3;
            $idxImp = 4;
            $idxCom = 5;
            $idxNeto = 6;
        }

        $out = [];
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $fecha = $this->parseFecha($row[$idxFecha] ?? null);
            $lote = trim((string) ($row[$idxLote] ?? ''));
            $neto = $this->parseMonto($row[$idxNeto] ?? null);
            if ($fecha === null || $lote === '' || $neto === null || $neto < 0.01) {
                continue;
            }

            $out[] = [
                'fecha' => $fecha,
                'lote' => $lote,
                'transacciones' => $idxTx !== null ? (int) ($this->parseMonto($row[$idxTx] ?? 0) ?? 0) : null,
                'bruto' => $idxBruto !== null ? (float) ($this->parseMonto($row[$idxBruto] ?? 0) ?? 0) : 0.0,
                'impuesto' => $idxImp !== null ? (float) ($this->parseMonto($row[$idxImp] ?? 0) ?? 0) : 0.0,
                'comision' => $idxCom !== null ? (float) ($this->parseMonto($row[$idxCom] ?? 0) ?? 0) : 0.0,
                'neto' => round($neto, 2),
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, string>  $needles
     */
    private function findCol(array $header, array $needles): ?int
    {
        foreach ($header as $i => $h) {
            $h = str_replace(['°', 'º'], '', $h);
            foreach ($needles as $n) {
                $n = str_replace(['°', 'º'], '', mb_strtolower($n, 'UTF-8'));
                if ($h === $n || str_contains($h, $n)) {
                    return $i;
                }
            }
        }

        return null;
    }

    private function parseFecha(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if ($raw instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTimeImmutable::createFromInterface($raw))->toDateString();
        }
        if (is_numeric($raw)) {
            // Excel serial date
            $unix = ((float) $raw - 25569) * 86400;
            if ($unix > 0) {
                return Carbon::createFromTimestampUTC((int) $unix)->toDateString();
            }
        }
        $s = trim((string) $raw);
        try {
            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $s)) {
                return Carbon::createFromFormat('d/m/Y', $s)->toDateString();
            }

            return Carbon::parse($s)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseMonto(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }
        $s = trim((string) $raw);
        $s = str_replace(["\xc2\xa0", ' '], '', $s);
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, ',')) {
            $s = str_replace(',', '.', $s);
        }
        $s = preg_replace('/[^0-9.\-]/', '', $s) ?? '';
        if ($s === '' || $s === '-' || $s === '.') {
            return null;
        }

        return (float) $s;
    }

    /**
     * @return Collection<int, ConciliacionLinea>
     */
    private function lineasLiqPendientes(string $banco, ?string $titular): Collection
    {
        $q = ConciliacionLinea::query()
            ->where('estado', 'pendiente')
            ->whereNull('tesoreria_ingreso_id')
            ->where(function ($qq) {
                $qq->where('tipo', 'abono')->orWhere('monto', '>', 0);
            });

        $q->where(function ($qq) use ($banco) {
            foreach ($this->matcher->variantesBanco($banco) as $v) {
                $qq->orWhereRaw('UPPER(TRIM(banco)) = ?', [mb_strtoupper($v, 'UTF-8')]);
            }
        });

        return $q->orderBy('fecha')->orderBy('id')->get()
            ->filter(function (ConciliacionLinea $l) use ($banco, $titular) {
                if (! $this->matcher->esLiquidacionPuntoVenta($l->descripcion)) {
                    return false;
                }
                if (! $titular) {
                    return true;
                }

                return $this->matcher->mismoTitular($l->titular, $titular, $l->banco, $banco);
            })
            ->values();
    }

    /**
     * @param  Collection<int, ConciliacionLinea>  $lineas
     */
    private function buscarLineaPorNeto(Collection $lineas, float $neto, string $fechaLote): ?ConciliacionLinea
    {
        $neto = round($neto, 2);
        $candidatos = $lineas->filter(function (ConciliacionLinea $l) use ($neto) {
            return abs(round(abs((float) $l->monto), 2) - $neto) < 0.02;
        });

        if ($candidatos->isEmpty()) {
            return null;
        }

        $ordenado = $candidatos->sortBy(function (ConciliacionLinea $l) use ($fechaLote) {
            try {
                $diff = abs(Carbon::parse($l->fecha)->diffInDays(Carbon::parse($fechaLote)));
            } catch (\Throwable) {
                $diff = 99;
            }

            return [$diff, $l->id];
        })->values();

        $mejor = $ordenado->first(function (ConciliacionLinea $l) use ($fechaLote) {
            return $this->matcher->fechaCercana($l->fecha, $fechaLote, 5);
        });

        return $mejor ?? $ordenado->first();
    }

    /**
     * @param  array{fecha:string,lote:string,transacciones:?int,bruto:float,impuesto:float,comision:float,neto:float}  $lote
     */
    private function upsertLote(array $lote, string $banco, string $titular): TesoreriaIngreso
    {
        $existente = TesoreriaIngreso::query()
            ->where('tipo', 'punto_venta')
            ->whereRaw('UPPER(TRIM(banco)) LIKE ?', ['%VENEZUELA%'])
            ->where('lote_referencia', $lote['lote'])
            ->whereDate('fecha', $lote['fecha'])
            ->where(function ($q) use ($lote) {
                $q->where('monto', round($lote['neto'], 2))
                    ->orWhere('monto', round($lote['bruto'], 2));
            })
            ->first();

        $desc = sprintf(
            'Medios de pago BDV lote %s · bruto %s · comisión %s · neto %s',
            $lote['lote'],
            number_format($lote['bruto'], 2, '.', ''),
            number_format($lote['comision'], 2, '.', ''),
            number_format($lote['neto'], 2, '.', '')
        );

        if ($existente) {
            $existente->fill([
                'monto' => round($lote['neto'], 2),
                'titular' => $titular,
                'banco' => $banco,
                'descripcion' => $desc,
            ]);
            $existente->save();

            return $existente;
        }

        return TesoreriaIngreso::query()->create([
            'tipo' => 'punto_venta',
            'banco' => $banco,
            'titular' => $titular,
            'fecha' => $lote['fecha'],
            'monto' => round($lote['neto'], 2),
            'lote_referencia' => $lote['lote'],
            'descripcion' => $desc,
            'es_conciliado' => false,
            'user_id' => auth()->id(),
        ]);
    }
}
