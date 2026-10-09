<?php

namespace App\Services;

use App\Models\ConciliacionLinea;
use Carbon\Carbon;

class BankReconciliationMatcher
{
    /** @var array<string, array{0:string,1:string}> */
    private array $partesCache = [];

    /** @var array<string, array<int, array<int, object>>> */
    private array $egresosPorMonto = [];

    /** @var array<string, array<string, array<int, object>>> */
    private array $egresosPorRef = [];

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

        // Códigos de banco (0134 Banesco, 0105 Mercantil…) en PagoMóvil / transferencias
        // no son números de lote POS.
        if ($this->esCodigoBancoVenezuela($digits)) {
            return false;
        }

        // BNC: lote en referencia ("487") o con ceros ("0487") frente a texto "487 POS:..."
        // Exigir ≥3 dígitos para no cruzar montos/refs cortos (p.ej. lote "5").
        if (strlen($digits) >= 3) {
            $patronDigitos = '/(?:^|[^0-9])0*'.$digits.'(?![0-9])/';
            if (preg_match($patronDigitos, $haystack) === 1) {
                return true;
            }
        }

        if (strlen($digits) >= 4) {
            $hayDigits = $this->soloDigitos($haystack);
            if ($hayDigits !== '' && str_contains($hayDigits, $digits)) {
                return true;
            }
        }

        // Códigos alfanuméricos (no usar stripos en lotes solo-dígitos:
        // "116" pegaba dentro de refs de PagoMóvil tipo V019647116).
        if ($lote !== $digits && strlen($lote) >= 3 && stripos($haystack, $lote) !== false) {
            return true;
        }

        return false;
    }

    /**
     * Códigos de compensación / banco en Venezuela (sin ceros a la izquierda).
     * Aparecen en conceptos tipo "PAGOMOVIL OTROS BANCOS 0134 …".
     */
    public function esCodigoBancoVenezuela(string $digits): bool
    {
        $digits = ltrim(preg_replace('/\D+/', '', $digits) ?? '', '0');
        if ($digits === '') {
            return false;
        }

        static $codigos = [
            '102', '104', '105', '108', '114', '115', '128', '134', '137', '138',
            '146', '151', '156', '157', '163', '168', '169', '171', '172', '174',
            '175', '177', '191',
        ];

        return in_array($digits, $codigos, true);
    }

    public function esPagoMovil(?string $descripcion): bool
    {
        $desc = mb_strtolower(trim((string) $descripcion), 'UTF-8');
        if ($desc === '') {
            return false;
        }

        $needles = [
            'pagomovil',
            'pago movil',
            'pago móvil',
            'pago-movil',
            'p. movil',
            'p.móvil',
        ];
        foreach ($needles as $needle) {
            if (str_contains($desc, $needle)) {
                return true;
            }
        }

        return false;
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
     * Cuentas que comparten la misma cuenta bancaria física.
     * Formato: 'BANCO|TITULAR_CLAVE' => 'BANCO|TITULAR_CLAVE_CANONICO'
     * Las dos entradas se mostrarán en una sola tarjeta de conciliación.
     */
    private array $cuentaAliases = [
        // Mercantil JRZ y Mercantil GRUPO JENU son la misma cuenta física.
        'MERCANTIL|JRZ' => 'MERCANTIL|JENU',
    ];

    /**
     * Clave estable para agrupar tarjetas: VENEZUELA|JRZ unifica "JRZ" y "GRUPO JRZ".
     * Aplica además el mapa de aliases para unificar cuentas físicas iguales.
     */
    public function claveCuenta(?string $banco, ?string $titular): string
    {
        [$b, $t] = $this->partesCuenta($banco, $titular);

        $clave = $b.'|'.$this->titularClave($t);

        return $this->cuentaAliases[$clave] ?? $clave;
    }

    /**
     * Forma canónica del titular para agrupar alias (sin prefijos GRUPO / L.S.).
     */
    public function titularClave(?string $titular): string
    {
        $t = strtoupper(trim((string) $titular));
        if ($t === '') {
            return '';
        }

        $t = preg_replace('/^GRUPO\s+/u', '', $t) ?? $t;
        $t = preg_replace('/^L\.?\s*S\.?\s+/u', '', $t) ?? $t;

        return trim($t);
    }

    /**
     * Prefiere la etiqueta más completa al unificar tarjetas ("GRUPO JRZ" sobre "JRZ").
     */
    public function titularPreferido(?string $actual, ?string $candidato): string
    {
        $a = strtoupper(trim((string) $actual));
        $c = strtoupper(trim((string) $candidato));
        if ($c === '') {
            return $a;
        }
        if ($a === '') {
            return $c;
        }
        if (strlen($c) > strlen($a)) {
            return $c;
        }

        return $a;
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
        $cacheKey = $banco."\0".$titular;
        if (array_key_exists($cacheKey, $this->partesCache)) {
            return $this->partesCache[$cacheKey];
        }

        $resuelto = $this->resolverPartesCuenta($banco, $titular);

        return $this->partesCache[$cacheKey] = $resuelto;
    }

    /**
     * Índice por banco + monto y por banco + referencia, para no comparar
     * cada línea del extracto contra todos los egresos.
     *
     * @param  iterable<mixed>  $flujos
     */
    public function indexarEgresos(iterable $flujos): void
    {
        $this->egresosPorMonto = [];
        $this->egresosPorRef = [];

        foreach ($flujos as $flujo) {
            if ($this->esTraslado($flujo)) {
                continue;
            }
            $this->agregarEgresoAlIndice($flujo);
        }
    }

    /**
     * @return array<int, object>
     */
    public function candidatosEgreso(ConciliacionLinea $linea): array
    {
        [$banco] = $this->partesCuenta($linea->banco, $linea->titular);
        $out = [];
        $monto = abs((float) $linea->monto);
        $probes = [(int) round($monto * 100)];
        if ($this->esBancoVenezuela($linea->banco) && $monto >= 0.01) {
            foreach ([0.02, 0.015] as $fee) {
                $base = (int) round(($monto / (1 - $fee)) * 100);
                for ($delta = -5; $delta <= 5; $delta++) {
                    $probes[] = $base + $delta;
                }
            }
        }
        foreach ($probes as $cents) {
            foreach ($this->egresosPorMonto[$banco][$cents] ?? [] as $id => $flujo) {
                $out[$id] = $flujo;
            }
        }

        $ref = $this->soloDigitos((string) $linea->referencia);
        if (strlen($ref) >= 4) {
            foreach ($this->egresosPorRef[$banco][$ref] ?? [] as $id => $flujo) {
                $out[$id] = $flujo;
            }
            if (strlen($ref) >= 8) {
                foreach ($this->egresosPorRef[$banco][substr($ref, -8)] ?? [] as $id => $flujo) {
                    $out[$id] = $flujo;
                }
            }
        }

        return $out;
    }

    public function retirarEgreso(object $flujo): void
    {
        $id = (int) ($flujo->id ?? 0);
        if ($id === 0) {
            return;
        }
        [$banco] = $this->partesCuenta($flujo->banco ?? null, $flujo->titular ?? null);
        foreach ($this->egresosPorMonto[$banco] ?? [] as $cents => $items) {
            unset($this->egresosPorMonto[$banco][$cents][$id]);
        }
        foreach ($this->egresosPorRef[$banco] ?? [] as $ref => $items) {
            unset($this->egresosPorRef[$banco][$ref][$id]);
        }
    }

    private function agregarEgresoAlIndice(object $flujo): void
    {
        [$banco] = $this->partesCuenta($flujo->banco ?? null, $flujo->titular ?? null);
        $id = (int) ($flujo->id ?? spl_object_id($flujo));
        foreach ([$flujo->monto_bs ?? null, $flujo->monto_usd ?? null, $flujo->monto ?? null] as $monto) {
            if ($monto === null || abs((float) $monto) < 0.004) {
                continue;
            }
            $cents = (int) round(abs((float) $monto) * 100);
            $this->egresosPorMonto[$banco][$cents][$id] = $flujo;
        }
        $ref = $this->soloDigitos((string) ($flujo->referencia ?? ''));
        if (strlen($ref) >= 4) {
            $this->egresosPorRef[$banco][$ref][$id] = $flujo;
            if (strlen($ref) >= 8) {
                $this->egresosPorRef[$banco][substr($ref, -8)][$id] = $flujo;
            }
        }
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolverPartesCuenta(string $banco, string $titular): array
    {
        $known = ['BANCAMIGA', 'BANCARIBE', 'BANESCO', 'MERCANTIL', 'VENEZUELA', 'TESORO', 'BBVA', 'BNC', 'PROVINCIAL'];

        foreach ($known as $nombre) {
            if ($banco === $nombre || str_starts_with($banco, $nombre.' ')) {
                $resto = trim(substr($banco, strlen($nombre)));
                if ($titular === '' && $resto !== '') {
                    $titular = $resto;
                }

                return [$nombre, $titular];
            }
        }

        // "BANCO DE VENEZUELA" → VENEZUELA (sin usar "BANCO DE" como titular)
        $norm = $this->normalizarBanco($banco);
        foreach ($known as $nombre) {
            if ($norm === $nombre || str_starts_with($norm, $nombre.' ')) {
                $resto = trim(substr($norm, strlen($nombre)));
                if ($titular === '' && $resto !== '') {
                    $titular = $resto;
                }

                return [$nombre, $titular];
            }
        }

        return [$norm, $titular];
    }

    /**
     * Variantes de nombre de banco para filtros SQL (VENEZUELA ↔ BANCO DE VENEZUELA).
     *
     * @return list<string>
     */
    public function variantesBanco(?string $banco): array
    {
        [$canon] = $this->partesCuenta($banco, '');
        $raw = strtoupper(trim((string) $banco));

        return array_values(array_unique(array_filter([
            $canon,
            $raw,
            'BANCO '.$canon,
            'BANCO DE '.$canon,
        ], fn ($v) => $v !== '')));
    }

    public function textoBanco(ConciliacionLinea $linea): string
    {
        return trim(($linea->referencia ?? '').' '.($linea->descripcion ?? ''));
    }

    public function coincideLotePunto(ConciliacionLinea $linea, object $ingreso): bool
    {
        // PagoMóvil trae códigos de banco (0134…) que no son nº de lote POS.
        if ($this->esPagoMovil($linea->descripcion)) {
            return false;
        }

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
        $montoExacto = $this->mismosMontos($linea->monto, $ingreso->monto ?? 0);

        if ($loteEnTexto || $loteEnReferencia) {
            if ($montoExacto) {
                return true;
            }

            // Sin monto exacto solo si el lote aparece en el texto/ref (Banesco L.xxx / BNC ref).
            return $this->fechaCercana($linea->fecha, $ingreso->fecha, 5);
        }

        // BDV LIQ.TARJETA / liquidaciones POS: NO cruzar aquí por bruto−2%.
        // Esos abonos se concilian con el Excel "medios de pago" (Monto Neto).
        return false;
    }

    /**
     * Conciliación POS BDV vía "Conciliación medios de pago":
     * el extracto trae LIQ.* con Monto Neto; el Excel trae N° Lote + Monto Neto.
     */
    public function coincideLiqMediosPago(ConciliacionLinea $linea, object $ingreso): bool
    {
        if (! $this->esLiquidacionPuntoVenta($linea->descripcion)) {
            return false;
        }
        if ($this->esPagoMovil($linea->descripcion)) {
            return false;
        }
        if (! $this->mismoBanco($linea->banco, $ingreso->banco ?? null)) {
            return false;
        }
        if (! $this->mismoTitular($linea->titular, $ingreso->titular ?? null, $linea->banco, $ingreso->banco ?? null)) {
            return false;
        }

        // Monto del lote debe ser el Neto del Excel (igual al abono LIQ del banco).
        if (! $this->mismosMontos($linea->monto, $ingreso->monto ?? 0)) {
            return false;
        }

        return $this->fechaCercana($linea->fecha, $ingreso->fecha, 5);
    }

    /**
     * BDV suele abonar el lote menos comisión POS (típicamente 2%, a veces 1.5%).
     * También aplica a cargos/egresos en Venezuela cuando el extracto ya viene neto
     * y en flujo_cajas está el monto bruto (sin descuento).
     */
    public function montoLoteNetoBdv(float|int|string|null $montoBanco, float|int|string|null $montoLote): bool
    {
        $banco = round(abs((float) $montoBanco), 2);
        $lote = round(abs((float) $montoLote), 2);
        if ($lote < 0.01 || $banco < 0.01) {
            return false;
        }

        foreach ([0.02, 0.015] as $fee) {
            $neto = round($lote * (1 - $fee), 2);
            if (abs($neto - $banco) < 0.05) {
                return true;
            }
            // Variante: comisión redondeada aparte (bruto − round(bruto×fee, 2))
            $netoAlt = round($lote - round($lote * $fee, 2), 2);
            if (abs($netoAlt - $banco) < 0.05) {
                return true;
            }
        }

        return false;
    }

    public function esBancoVenezuela(?string $banco): bool
    {
        [$canon] = $this->partesCuenta($banco, null);

        return $canon === 'VENEZUELA';
    }

    /**
     * Liquidaciones de punto de venta en extractos BDV / similares:
     * no traen el número de lote, solo el concepto de liquidación.
     */
    public function esLiquidacionPuntoVenta(?string $descripcion): bool
    {
        $desc = mb_strtolower(trim((string) $descripcion), 'UTF-8');
        if ($desc === '') {
            return false;
        }

        $needles = [
            'liq.tarjeta',
            'liq tarjeta',
            'liquidacion t/',
            'liquidación t/',
            'liquidacion t.',
            'debito maestro bdv',
            'débito maestro bdv',
            'debito electron bd',
            'débito electron bd',
            't/credito vs/mc',
            't/crédito vs/mc',
            'pos:',
        ];

        foreach ($needles as $needle) {
            if (str_contains($desc, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Abonos de lote POS visibles en "LIQ del banco sin lote":
     * BDV LIQ.* / POS: y también Banesco tipo "TDB … L.000058".
     */
    public function esAbonoLotePuntoVenta(?string $descripcion, ?string $referencia = null): bool
    {
        if ($this->esPagoMovil($descripcion)) {
            return false;
        }

        if ($this->esLiquidacionPuntoVenta($descripcion)) {
            return true;
        }

        $texto = trim(($referencia ?? '').' '.($descripcion ?? ''));
        if ($texto === '') {
            return false;
        }

        // Banesco / similares: L.000058 o Lote 58 en la descripción.
        if (preg_match('/(?:^|[^0-9A-Z])L\.?\s*0*\d{2,}(?![0-9])/i', $texto) === 1) {
            return true;
        }
        if (preg_match('/(?:lote|lot)\s*\.?\s*0*\d{2,}(?![0-9])/i', $texto) === 1) {
            return true;
        }

        return false;
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
        $esLiqPos = $this->esLiquidacionPuntoVenta($linea->descripcion);
        foreach ($ingresos as $ingreso) {
            $tipo = (string) ($ingreso->tipo ?? '');
            if ($tipo === 'punto_venta') {
                // LIQ BDV solo contra lotes de medios de pago (Monto Neto exacto).
                $ok = $esLiqPos
                    ? $this->coincideLiqMediosPago($linea, $ingreso)
                    : $this->coincideLotePunto($linea, $ingreso);
            } else {
                // PagoMóvil / depósitos: nunca un LIQ POS.
                if ($esLiqPos) {
                    continue;
                }
                $ok = $this->coincideIngresoTesoreria($linea, $ingreso);
            }
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
            } elseif ($lado !== null) {
                // Monto + mismo día sin ref (p.ej. BNC)
                $puntos += 15;
            }
        } elseif ($this->referenciasCruzan($linea->referencia, $flujo->referencia ?? null)) {
            $puntos += 30;
        }
        if ($this->mismosMontos($linea->monto, $flujo->monto_bs ?? $flujo->monto ?? null)) {
            $puntos += 20;
        } elseif ($this->esBancoVenezuela($linea->banco)
            && $this->montoLoteNetoBdv($linea->monto, $flujo->monto_bs ?? $flujo->monto ?? null)) {
            $puntos += 18;
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
        if ($this->loteIgualReferencia($linea->referencia, $ingreso->lote_referencia ?? null)) {
            $puntos += 25;
        }
        if ($this->referenciasCruzan($linea->referencia, $ingreso->lote_referencia ?? null)) {
            $puntos += 15;
        }
        if ($this->mismosMontos($linea->monto, $ingreso->monto ?? 0)) {
            $puntos += 40;
        } elseif ($this->montoLoteNetoBdv($linea->monto, $ingreso->monto ?? 0)) {
            $puntos += 35;
        }
        if ($this->fechaCercana($linea->fecha, $ingreso->fecha, 0)) {
            $puntos += 10;
        } elseif ($this->fechaCercana($linea->fecha, $ingreso->fecha, 1)) {
            $puntos += 6;
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
            if ($monto === null) {
                continue;
            }
            if ($this->mismosMontos($montoLinea, $monto)) {
                $montoOk = true;
                break;
            }
            // Venezuela: extracto neto (−2%) vs monto bruto en BDD
            if ($this->esBancoVenezuela($linea->banco) && $this->montoLoteNetoBdv($montoLinea, $monto)) {
                $montoOk = true;
                break;
            }
        }
        if (! $montoOk) {
            return false;
        }

        return $this->fechaCercana($linea->fecha, $flujo->fecha);
    }

    /**
     * Un gasto recién copiado del extracto (sin motivo ni comprobante) no debe
     * quedarse con la conciliación si ya existe el egreso real del mismo día.
     */
    public function reemplazoDeGastoVacio(object $linea, object $vinculado, iterable $candidatos): ?object
    {
        if ($this->esTraslado($vinculado)) {
            return null;
        }
        if (trim((string) ($vinculado->motivo ?? '')) !== '') {
            return null;
        }
        if (trim((string) ($vinculado->comprobante_url ?? '')) !== '') {
            return null;
        }

        foreach ($candidatos as $candidato) {
            if ((int) ($candidato->id ?? 0) === (int) ($vinculado->id ?? 0)) {
                continue;
            }
            if ((bool) ($candidato->es_conciliado ?? false)) {
                continue;
            }
            if (trim((string) ($candidato->motivo ?? '')) === '') {
                continue;
            }
            if (! $this->fechaCercana($linea->fecha ?? null, $candidato->fecha ?? null, 0)) {
                continue;
            }
            if (! $this->coincideEgreso($linea instanceof ConciliacionLinea ? $linea : new ConciliacionLinea((array) $linea), $candidato)) {
                continue;
            }

            return $candidato;
        }

        return null;
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
            if ($this->referenciaTrasladoCompletaCoincide($linea->referencia, $flujo->referencia ?? null)) {
                return true;
            }
            // BNC / otros: a veces solo cuadra el monto (la ref del extracto no es la misma).
            // Exige mismo día para no cruzar traslados de fechas distintas.
            return $this->fechaCercana($linea->fecha, $flujo->fecha, 0);
        }

        if ($this->referenciaTrasladoUltimosCuatroCoincide($linea->referencia, $flujo->referencia ?? null)) {
            return true;
        }

        return $this->fechaCercana($linea->fecha, $flujo->fecha, 0);
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
            if ($monto === null) {
                continue;
            }
            if ($this->mismosMontos($linea->monto, $monto)) {
                return true;
            }
            if ($this->esBancoVenezuela($linea->banco) && $this->montoLoteNetoBdv($linea->monto, $monto)) {
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

    public function normalizarBanco(?string $banco): string
    {
        $banco = strtoupper(trim((string) $banco));
        $banco = preg_replace('/\s+/', ' ', $banco) ?? $banco;
        // "BANCO DE VENEZUELA" / "BANCO VENEZUELA" → "VENEZUELA"
        $banco = preg_replace('/^BANCO\s+(DE\s+)?/', '', $banco) ?? $banco;

        return trim($banco);
    }
}
