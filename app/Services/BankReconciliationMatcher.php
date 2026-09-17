<?php

namespace App\Services;

use App\Models\ConciliacionLinea;
use Carbon\Carbon;

class BankReconciliationMatcher
{
    public function mismosMontos(float|int|string|null $a, float|int|string|null $b): bool
    {
        return abs($this->aCentavos($a) - $this->aCentavos($b)) < 0.005;
    }

    public function haystackTieneLote(string $haystack, ?string $lote): bool
    {
        $lote = trim((string) $lote);
        if ($lote === '') {
            return false;
        }

        $digits = $this->soloDigitos($lote);
        if ($digits === '' || $digits === '0') {
            return false;
        }

        $patronLote = '/(?:^|[^0-9A-Z])L\.?\s*0*'.$digits.'(?![0-9])/i';
        if (preg_match($patronLote, $haystack) === 1) {
            return true;
        }

        $patronPalabra = '/(?:lote|lot)\s*\.?\s*0*'.$digits.'(?![0-9])/i';
        if (preg_match($patronPalabra, $haystack) === 1) {
            return true;
        }

        // BNC: lote en referencia ("487") o con ceros ("0487") frente a texto "487 POS:..."
        $patronDigitos = '/(?:^|[^0-9])0*'.$digits.'(?![0-9])/';
        if (preg_match($patronDigitos, $haystack) === 1) {
            return true;
        }

        if (strlen($digits) >= 4) {
            $hayDigits = $this->soloDigitos($haystack);
            if ($hayDigits !== '' && str_contains($hayDigits, $digits)) {
                return true;
            }
        }

        return strlen($lote) >= 3 && stripos($haystack, $lote) !== false;
    }

    public function referenciasCruzan(?string $a, ?string $b): bool
    {
        $a = trim((string) $a);
        $b = trim((string) $b);
        if ($a === '' || $b === '') {
            return false;
        }

        if (strcasecmp($a, $b) === 0) {
            return true;
        }

        $da = $this->soloDigitos($a);
        $db = $this->soloDigitos($b);
        if ($da !== '' && $da === $db && strlen($da) >= 4) {
            return true;
        }

        if (strlen($da) >= 6 && strlen($db) >= 6 && (str_contains($da, $db) || str_contains($db, $da))) {
            return true;
        }

        $cola = 8;
        if (strlen($da) >= $cola && strlen($db) >= $cola) {
            return substr($da, -$cola) === substr($db, -$cola);
        }

        return false;
    }

    public function fechaCercana($fechaA, $fechaB, int $dias = 3): bool
    {
        try {
            return abs(Carbon::parse($fechaA)->diffInDays(Carbon::parse($fechaB))) <= $dias;
        } catch (\Throwable) {
            return false;
        }
    }

    public function mismoBanco(?string $a, ?string $b): bool
    {
        [$bancoA] = $this->partesCuenta($a, null);
        [$bancoB] = $this->partesCuenta($b, null);
        if ($bancoA === '' || $bancoB === '') {
            return $bancoA === $bancoB;
        }

        return $bancoA === $bancoB;
    }

    public function mismoTitular(?string $lineaTitular, ?string $registroTitular, ?string $lineaBanco = null, ?string $registroBanco = null): bool
    {
        [, $titLinea] = $this->partesCuenta($lineaBanco, $lineaTitular);
        [, $titReg] = $this->partesCuenta($registroBanco, $registroTitular);
        $titLinea = strtolower($titLinea);
        $titReg = strtolower($titReg);

        if ($titLinea === '' || $titReg === '' || $titLinea === $titReg) {
            return true;
        }

        // "GRUPO JRZ" vs "JRZ", "L.S. CASHEA" vs "CASHEA"
        if (str_contains($titLinea, $titReg) || str_contains($titReg, $titLinea)) {
            return true;
        }

        $tokA = preg_split('/\s+/', $titLinea) ?: [];
        $tokB = preg_split('/\s+/', $titReg) ?: [];
        $lastA = (string) end($tokA);
        $lastB = (string) end($tokB);

        return $lastA !== '' && $lastA === $lastB;
    }

    /**
     * Tesorería guarda a veces "BANESCO DORAL" en banco y titular vacío.
     *
     * @return array{0:string,1:string}
     */
    public function partesCuenta(?string $banco, ?string $titular): array
    {
        $banco = strtoupper(trim((string) $banco));
        $titular = strtoupper(trim((string) $titular));
        $known = ['BANCAMIGA', 'BANCARIBE', 'BANESCO', 'MERCANTIL', 'VENEZUELA', 'TESORO', 'BBVA', 'BNC', 'PROVINCIAL'];
        foreach ($known as $nombre) {
            if ($banco === $nombre || str_starts_with($banco, $nombre.' ') || str_contains($banco, $nombre)) {
                $resto = trim(str_replace($nombre, '', $banco));
                if ($titular === '' && $resto !== '') {
                    $titular = $resto;
                }

                return [$nombre, $titular];
            }
        }

        return [$this->normalizarBanco($banco), $titular];
    }

    public function textoBanco(ConciliacionLinea $linea): string
    {
        return trim(($linea->referencia ?? '').' '.($linea->descripcion ?? ''));
    }

    public function coincideLotePunto(ConciliacionLinea $linea, object $ingreso): bool
    {
        if (! $this->mismoBanco($linea->banco, $ingreso->banco ?? null)) {
            return false;
        }
        if (! $this->mismoTitular($linea->titular, $ingreso->titular ?? null, $linea->banco, $ingreso->banco ?? null)) {
            return false;
        }

        $texto = $this->textoBanco($linea);
        $lote = (string) ($ingreso->lote_referencia ?? '');
        $loteEnTexto = $this->haystackTieneLote($texto, $lote);
        $loteEnReferencia = $this->loteIgualReferencia($linea->referencia, $lote);
        if (! $loteEnTexto && ! $loteEnReferencia) {
            return false;
        }

        if ($this->mismosMontos($linea->monto, $ingreso->monto ?? 0)) {
            return true;
        }

        return $this->fechaCercana($linea->fecha, $ingreso->fecha, 5);
    }

    /**
     * En BNC el lote del punto suele ser la referencia del abono (487),
     * mientras la descripción trae el terminal: "POS: 860963370 ...".
     */
    public function loteIgualReferencia(?string $referenciaBanco, ?string $lote): bool
    {
        $a = $this->soloDigitos((string) $referenciaBanco);
        $b = $this->soloDigitos((string) $lote);

        return $a !== '' && $a === $b;
    }

    public function coincideIngresoTesoreria(ConciliacionLinea $linea, object $ingreso): bool
    {
        if (! $this->mismoBanco($linea->banco, $ingreso->banco ?? null)) {
            return false;
        }
        if (! $this->mismosMontos($linea->monto, $ingreso->monto ?? 0)) {
            return false;
        }

        $refIngreso = (string) ($ingreso->lote_referencia ?? '');
        if ($refIngreso !== '' && $this->referenciasCruzan($linea->referencia, $refIngreso)) {
            return true;
        }
        if ($this->haystackTieneLote($this->textoBanco($linea), $refIngreso)) {
            return true;
        }

        return $this->mismoTitular($linea->titular, $ingreso->titular ?? null, $linea->banco, $ingreso->banco ?? null)
            && $this->fechaCercana($linea->fecha, $ingreso->fecha);
    }

    public function mejorEgreso(ConciliacionLinea $linea, iterable $flujos): ?object
    {
        $candidatos = [];
        foreach ($flujos as $flujo) {
            if ($this->coincideEgreso($linea, $flujo)) {
                $candidatos[] = $flujo;
            }
        }
        if ($candidatos === []) {
            return null;
        }

        usort($candidatos, function ($a, $b) use ($linea) {
            return $this->puntajeEgreso($linea, $b) <=> $this->puntajeEgreso($linea, $a);
        });

        return $candidatos[0];
    }

    public function mejorIngresoTesoreria(ConciliacionLinea $linea, iterable $ingresos): ?object
    {
        $candidatos = [];
        foreach ($ingresos as $ingreso) {
            $ok = (($ingreso->tipo ?? '') === 'punto_venta')
                ? $this->coincideLotePunto($linea, $ingreso)
                : $this->coincideIngresoTesoreria($linea, $ingreso);
            if ($ok) {
                $candidatos[] = $ingreso;
            }
        }
        if ($candidatos === []) {
            return null;
        }

        usort($candidatos, function ($a, $b) use ($linea) {
            return $this->puntajeTesoreria($linea, $b) <=> $this->puntajeTesoreria($linea, $a);
        });

        return $candidatos[0];
    }

    public function mejorCompraDivisa(ConciliacionLinea $linea, iterable $compras): ?object
    {
        $candidatos = [];
        foreach ($compras as $compra) {
            if ($this->coincideCompraDivisa($linea, $compra)) {
                $candidatos[] = $compra;
            }
        }
        if ($candidatos === []) {
            return null;
        }

        usort($candidatos, function ($a, $b) use ($linea) {
            $pa = $this->referenciasCruzan($linea->referencia, $a->referencia ?? null) ? 20 : 0;
            $pb = $this->referenciasCruzan($linea->referencia, $b->referencia ?? null) ? 20 : 0;
            if ($this->fechaCercana($linea->fecha, $a->fecha, 0)) {
                $pa += 10;
            }
            if ($this->fechaCercana($linea->fecha, $b->fecha, 0)) {
                $pb += 10;
            }

            return $pb <=> $pa;
        });

        return $candidatos[0];
    }

    public function coincideCompraDivisa(ConciliacionLinea $linea, object $compra): bool
    {
        if (! $linea->esCargo()) {
            return false;
        }
        if (! $this->mismoBanco($linea->banco, $compra->banco ?? null)) {
            return false;
        }
        if (! $this->mismoTitular($linea->titular, $compra->titular ?? null, $linea->banco, $compra->banco ?? null)) {
            return false;
        }
        if (! $this->mismosMontos($linea->monto, $compra->monto_bs ?? 0)) {
            return false;
        }
        if ($this->referenciasCruzan($linea->referencia, $compra->referencia ?? null)) {
            return true;
        }

        return $this->fechaCercana($linea->fecha, $compra->fecha);
    }

    private function puntajeEgreso(ConciliacionLinea $linea, object $flujo): int
    {
        $puntos = 0;
        if ($this->esTraslado($flujo)) {
            $lado = $this->ladoTraslado($linea, $flujo);
            if ($lado === 'salida' && $this->referenciaTrasladoCompletaCoincide($linea->referencia, $flujo->referencia ?? null)) {
                $puntos += 50;
            } elseif ($lado === 'entrada' && $this->referenciaTrasladoUltimosCuatroCoincide($linea->referencia, $flujo->referencia ?? null)) {
                $puntos += 40;
            }
        } elseif ($this->referenciasCruzan($linea->referencia, $flujo->referencia ?? null)) {
            $puntos += 30;
        }
        if ($this->fechaCercana($linea->fecha, $flujo->fecha, 0)) {
            $puntos += 10;
        } elseif ($this->fechaCercana($linea->fecha, $flujo->fecha)) {
            $puntos += 4;
        }

        return $puntos;
    }

    private function puntajeTesoreria(ConciliacionLinea $linea, object $ingreso): int
    {
        $puntos = (($ingreso->tipo ?? '') === 'punto_venta') ? 20 : 0;
        if ($this->haystackTieneLote($this->textoBanco($linea), $ingreso->lote_referencia ?? null)) {
            $puntos += 30;
        }
        if ($this->referenciasCruzan($linea->referencia, $ingreso->lote_referencia ?? null)) {
            $puntos += 15;
        }
        if ($this->fechaCercana($linea->fecha, $ingreso->fecha, 0)) {
            $puntos += 10;
        }

        return $puntos;
    }

    public function coincideEgreso(ConciliacionLinea $linea, object $flujo): bool
    {
        if ($this->esTraslado($flujo)) {
            return $this->coincideTraslado($linea, $flujo);
        }

        if (! $this->mismoBanco($linea->banco, $flujo->banco ?? null)) {
            return false;
        }
        if (! $this->mismoTitular($linea->titular, $flujo->titular ?? null, $linea->banco, $flujo->banco ?? null)) {
            return false;
        }

        if ($this->referenciasCruzan($linea->referencia, $flujo->referencia ?? null)) {
            return true;
        }

        $montoLinea = abs((float) $linea->monto);
        $montosFlujo = [
            $flujo->monto_bs ?? null,
            $flujo->monto_usd ?? null,
            $flujo->monto ?? null,
        ];
        $montoOk = false;
        foreach ($montosFlujo as $monto) {
            if ($monto !== null && $this->mismosMontos($montoLinea, $monto)) {
                $montoOk = true;
                break;
            }
        }
        if (! $montoOk) {
            return false;
        }

        return $this->fechaCercana($linea->fecha, $flujo->fecha);
    }

    public function esTraslado(object $flujo): bool
    {
        return strtolower(trim((string) ($flujo->categoria_egreso ?? ''))) === 'traslados';
    }

    public function ladoTraslado(ConciliacionLinea $linea, object $flujo): ?string
    {
        if (! $this->esTraslado($flujo)) {
            return null;
        }

        if (
            $linea->esCargo()
            && $this->mismoBanco($linea->banco, $flujo->banco ?? null)
            && $this->mismoTitular($linea->titular, $flujo->titular ?? null, $linea->banco, $flujo->banco ?? null)
        ) {
            return 'salida';
        }

        if (
            $linea->esAbono()
            && $this->mismoBanco($linea->banco, $flujo->banco_receptor ?? null)
            && $this->mismoTitular(
                $linea->titular,
                $flujo->titular_receptor ?? null,
                $linea->banco,
                $flujo->banco_receptor ?? null
            )
        ) {
            return 'entrada';
        }

        return null;
    }

    public function coincideTraslado(ConciliacionLinea $linea, object $flujo): bool
    {
        $lado = $this->ladoTraslado($linea, $flujo);
        if ($lado === null || ! $this->montoCoincideConFlujo($linea, $flujo)) {
            return false;
        }
        if (! $this->fechaCercana($linea->fecha, $flujo->fecha)) {
            return false;
        }

        if ($lado === 'salida') {
            return $this->referenciaTrasladoCompletaCoincide($linea->referencia, $flujo->referencia ?? null);
        }

        return $this->referenciaTrasladoUltimosCuatroCoincide($linea->referencia, $flujo->referencia ?? null);
    }

    public function referenciaTrasladoCompletaCoincide(?string $referenciaBanco, ?string $referenciaTraslado): bool
    {
        $banco = $this->normalizarReferencia($referenciaBanco);
        $traslado = $this->normalizarReferencia($referenciaTraslado);

        return $banco !== '' && $traslado !== '' && $banco === $traslado;
    }

    public function referenciaTrasladoUltimosCuatroCoincide(?string $referenciaBanco, ?string $referenciaTraslado): bool
    {
        $banco = $this->soloDigitos((string) $referenciaBanco);
        $traslado = $this->soloDigitos((string) $referenciaTraslado);
        if (strlen($banco) < 4 || strlen($traslado) < 4) {
            return false;
        }

        return substr($banco, -4) === substr($traslado, -4);
    }

    private function montoCoincideConFlujo(ConciliacionLinea $linea, object $flujo): bool
    {
        foreach ([$flujo->monto_bs ?? null, $flujo->monto ?? null] as $monto) {
            if ($monto !== null && $this->mismosMontos($linea->monto, $monto)) {
                return true;
            }
        }

        return false;
    }

    private function aCentavos(float|int|string|null $valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }
        if (is_string($valor)) {
            $valor = str_replace(['Bs.', 'Bs', ' '], '', $valor);
            if (preg_match('/^-?[\d.]+,\d{1,2}$/', $valor)) {
                $valor = str_replace('.', '', $valor);
                $valor = str_replace(',', '.', $valor);
            } else {
                $valor = str_replace(',', '', $valor);
            }
        }

        return round(abs((float) $valor), 2);
    }

    private function soloDigitos(string $valor): string
    {
        $digits = preg_replace('/\D+/', '', $valor) ?? '';

        return ltrim($digits, '0');
    }

    private function normalizarReferencia(?string $valor): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]+/i', '', trim((string) $valor)) ?? '');
    }

    private function normalizarBanco(?string $banco): string
    {
        $banco = strtoupper(trim((string) $banco));
        $banco = str_replace(['BANCO DE ', 'BANCO ', ' DE VENEZUELA'], ['', '', ' VENEZUELA'], $banco);

        return trim($banco);
    }
}
