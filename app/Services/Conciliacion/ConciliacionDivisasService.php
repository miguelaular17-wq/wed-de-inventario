<?php

namespace App\Services\Conciliacion;

use App\Models\ConciliacionDivisaLinea;
use App\Models\ConciliacionDivisaPago;
use App\Models\FlujoCaja;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Conciliación de cuentas en divisas (Binance, Mercantil Panamá, Wells Fargo / Citizens por Zelle…).
 * El extracto se carga con un mapeo de columnas elegido por el usuario (se recuerda por cuenta)
 * y se empareja en USD con los egresos de flujo de caja de esa cuenta.
 */
class ConciliacionDivisasService
{
    /** Bancos cuyos egresos se registran en USD (nombre del banco en flujo de caja). */
    public const PATRONES_BANCO = ['binance', 'panam', 'wells', 'citizens', 'amerant', 'zelle', 'facebank', 'regions', 'first horizon'];

    private const COMISION = [
        'fee', 'comision', 'comisión', 'commission', 'service charge', 'monthly service', 'maintenance',
        'mantenimiento', 'cargo por servicio', 'wire charge', 'cargo mensual', 'overdraft',
    ];

    /** Traslados entre billeteras de la misma cuenta (no son cobros ni pagos). */
    private const INTERNOS = '/transfer funds to (spot|funding)|binance convert|transferencia interna|internal transfer/i';

    public const FORMATOS_FECHA = [
        'auto' => 'Automático',
        'd/m/Y' => 'DD/MM/AAAA',
        'm/d/Y' => 'MM/DD/AAAA (EE.UU.)',
        'Y-m-d' => 'AAAA-MM-DD',
    ];

    public function __construct(
        private ExtractoParser $parser,
    ) {}

    /**
     * Cuentas en divisas: las que tienen egresos en flujo de caja o movimientos ya cargados.
     *
     * @return list<array{banco: string, titular: string}>
     */
    public function cuentas(): array
    {
        $like = function ($q) {
            $q->where(function ($w) {
                foreach (self::PATRONES_BANCO as $p) {
                    $w->orWhereRaw('LOWER(banco) LIKE ?', ['%' . $p . '%']);
                }
            });
        };

        $deFlujo = FlujoCaja::query()->tap($like)
            ->whereNotNull('banco')->whereNotNull('titular')
            ->select('banco', 'titular')->distinct()->get();
        $cargadas = Schema::hasTable('conciliacion_divisas_lineas')
            ? ConciliacionDivisaLinea::query()->select('banco', 'titular')->distinct()->get()
            : collect();

        return $deFlujo->concat($cargadas)
            ->map(fn ($c) => ['banco' => trim((string) $c->banco), 'titular' => trim((string) $c->titular)])
            ->filter(fn ($c) => $c['banco'] !== '' && $c['titular'] !== '')
            ->unique(fn ($c) => mb_strtoupper($c['banco'] . '|' . $c['titular']))
            ->sortBy(fn ($c) => mb_strtoupper($c['banco'] . ' ' . $c['titular']))
            ->values()->all();
    }

    /** @return list<list<string>> primeras filas del archivo, para elegir columnas */
    public function muestra(string $path, string $ext, int $max = 25): array
    {
        $out = [];
        foreach ($this->parser->filasCrudas($path, $ext) as $fila) {
            $out[] = array_map(fn ($v) => $this->texto($v), (array) $fila);
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function formatoGuardado(string $banco, string $titular): ?array
    {
        if (! Schema::hasTable('conciliacion_divisas_formatos')) {
            return null;
        }
        $row = DB::table('conciliacion_divisas_formatos')
            ->whereRaw('UPPER(banco) = ? AND UPPER(titular) = ?', [mb_strtoupper($banco), mb_strtoupper($titular)])
            ->value('mapeo');

        return $row ? json_decode((string) $row, true) : null;
    }

    /**
     * @param  array{fila_cabecera: int, fecha: int, descripcion: list<int>, referencia: ?int, monto: ?int, debito: ?int, credito: ?int, formato_fecha: string, invertir_signo: bool}  $mapeo
     * @return array{leidas: int, nuevas: int, duplicadas: int, rechazadas: list<string>}
     */
    public function importar(string $path, string $ext, string $banco, string $titular, array $mapeo, string $archivo, ?int $userId): array
    {
        if ($mapeo['monto'] === null && $mapeo['debito'] === null && $mapeo['credito'] === null) {
            throw ValidationException::withMessages(['mapeo' => 'Elige la columna del monto (o las de débito y crédito).']);
        }

        $filas = [];
        $rechazadas = [];
        $ocurrencias = [];
        $leidas = 0;
        $conHora = Schema::hasColumn('conciliacion_divisas_lineas', 'hora');
        foreach ($this->parser->filasCrudas($path, $ext) as $i => $fila) {
            if ($i <= $mapeo['fila_cabecera']) {
                continue;
            }
            $fila = array_map(fn ($v) => $this->texto($v, true), (array) $fila);
            if (implode('', $fila) === '') {
                continue;
            }
            $leidas++;
            $n = $i + 1;

            $fecha = $this->fecha($fila[$mapeo['fecha']] ?? '', $mapeo['formato_fecha']);
            $monto = $this->monto($fila, $mapeo);
            if ($fecha === null || $monto === null) {
                if (count($rechazadas) < 15) {
                    $rechazadas[] = "Fila {$n}: " . ($fecha === null ? 'fecha no reconocida' : 'monto no reconocido')
                        . ' («' . mb_substr(implode(' · ', array_filter($fila)), 0, 90) . '»)';
                }

                continue;
            }
            if (abs($monto) < 0.005) {
                continue;
            }

            $desc = trim(implode(' · ', array_filter(array_map(fn ($c) => $fila[$c] ?? '', $mapeo['descripcion']))));
            $ref = $mapeo['referencia'] !== null ? trim($fila[$mapeo['referencia']] ?? '') : '';
            $hora = $conHora ? $this->hora($fila[$mapeo['fecha']] ?? '') : null;
            $base = implode('|', [mb_strtoupper($banco), mb_strtoupper($titular), $fecha . ($hora ? ' ' . $hora : ''), number_format($monto, 2, '.', ''), $ref, mb_strtoupper($desc)]);
            $ocurrencias[$base] = ($ocurrencias[$base] ?? 0) + 1;

            $filas[] = [
                'banco' => $banco,
                'titular' => $titular,
                'fecha' => $fecha,
            ] + ($conHora ? ['hora' => $hora] : []) + [
                'descripcion' => mb_substr($desc !== '' ? $desc : '(sin descripción)', 0, 500),
                'referencia' => $ref !== '' ? mb_substr($ref, 0, 120) : null,
                'monto' => round($monto, 2),
                'tipo' => $monto < 0 ? 'cargo' : 'abono',
                'estado' => 'pendiente',
                'huella' => hash('sha256', $base . '|' . $ocurrencias[$base]),
                'archivo' => mb_substr($archivo, 0, 255),
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $nuevas = 0;
        foreach (array_chunk($filas, 300) as $chunk) {
            $nuevas += DB::table('conciliacion_divisas_lineas')->insertOrIgnore($chunk);
        }

        DB::table('conciliacion_divisas_formatos')->updateOrInsert(
            ['banco' => $banco, 'titular' => $titular],
            ['mapeo' => json_encode($mapeo), 'updated_at' => now(), 'created_at' => now()],
        );

        return ['leidas' => $leidas, 'nuevas' => $nuevas, 'duplicadas' => count($filas) - $nuevas, 'rechazadas' => $rechazadas];
    }

    /**
     * Empareja los movimientos pendientes con los egresos (y traslados entrantes) de la cuenta.
     *
     * @return array{conciliadas: int, comisiones: int, internos: int}
     */
    public function conciliar(string $banco, string $titular, string $desde, string $hasta): array
    {
        $internos = $this->ignorarInternos($banco, $titular, $desde, $hasta);
        $lineas = $this->lineas($banco, $titular, $desde, $hasta)->where('estado', 'pendiente')->values();
        $egresos = $this->egresosDisponibles($banco, $titular, $desde, $hasta);
        $entrantes = $this->trasladosEntrantes($banco, $titular, $desde, $hasta);

        $dig = fn ($r) => preg_replace('/\D/', '', (string) $r);
        $refDe = function ($linea, $flujo) use ($dig) {
            $rf = $dig($flujo->referencia);
            if (strlen($rf) < 6) {
                return false;
            }
            $rl = $dig($linea->referencia) . ' ' . $dig($linea->descripcion);

            return str_contains($rl, $rf) || (strlen($dig($linea->referencia)) >= 6 && str_ends_with($rf, $dig($linea->referencia)));
        };
        $montoOk = fn ($linea, $flujo) => abs(abs((float) $linea->monto) - abs((float) $flujo->monto_usd)) <= 0.01;
        $refsEgresos = $egresos->concat($entrantes)->map(fn ($f) => $dig($f->referencia))->filter(fn ($r) => strlen($r) >= 6)->flip();
        $tieneRefDeOtro = function ($linea, $flujo) use ($dig, $refsEgresos) {
            $rl = $dig($linea->referencia);

            return strlen($rl) >= 6 && isset($refsEgresos[$rl]) && $rl !== $dig($flujo->referencia);
        };

        $usados = [];
        $pares = [];
        $candidatosDe = fn ($linea) => $linea->esCargo() ? $egresos : $entrantes;

        // 1) Referencia + monto. 2) Monto exacto + fecha ±3 días.
        foreach (['ref', 'monto'] as $pasada) {
            foreach ($lineas as $linea) {
                if (isset($pares[$linea->id])) {
                    continue;
                }
                $mejor = null;
                foreach ($candidatosDe($linea) as $f) {
                    if (isset($usados[$f->id]) || ! $montoOk($linea, $f)) {
                        continue;
                    }
                    $dias = abs($linea->fecha->diffInDays(Carbon::parse($f->fecha), false));
                    $ok = $pasada === 'ref'
                        ? $refDe($linea, $f) && $dias <= 10
                        : $dias <= 3 && ! $tieneRefDeOtro($linea, $f);
                    if ($ok && ($mejor === null || $dias < $mejor[1])) {
                        $mejor = [$f, $dias];
                    }
                }
                if ($mejor) {
                    $usados[$mejor[0]->id] = true;
                    $pares[$linea->id] = $mejor[0];
                }
            }
        }

        $conciliadas = 0;
        foreach ($pares as $lineaId => $flujo) {
            $conciliadas += $this->vincular($lineaId, (int) $flujo->id) ? 1 : 0;
        }

        $comisiones = 0;
        foreach ($lineas as $linea) {
            if (! isset($pares[$linea->id]) && $linea->esCargo() && $this->esComision($linea->descripcion)) {
                $comisiones += ConciliacionDivisaLinea::query()->whereKey($linea->id)->where('estado', 'pendiente')
                    ->update(['estado' => 'comision', 'updated_at' => now()]);
            }
        }

        return ['conciliadas' => $conciliadas, 'comisiones' => $comisiones, 'internos' => $internos];
    }

    /**
     * Movimientos entre billeteras de la misma cuenta (Binance Funding ↔ Spot, conversiones USDC/USDT):
     * no son cobros ni pagos, salen en pares y se apartan como ignorados.
     */
    public function ignorarInternos(string $banco, string $titular, string $desde, string $hasta): int
    {
        $n = 0;
        foreach ($this->lineas($banco, $titular, $desde, $hasta)->where('estado', 'pendiente') as $linea) {
            if (preg_match(self::INTERNOS, (string) $linea->descripcion) === 1) {
                $n += ConciliacionDivisaLinea::query()->whereKey($linea->id)->where('estado', 'pendiente')
                    ->update(['estado' => 'ignorado', 'updated_at' => now()]);
            }
        }

        return $n;
    }

    /**
     * Lee el reporte de pagos recibidos (Cloud: NRO, MONTO, LOCALIDAD, FECHA CONFIRMADO, TERMINAL, USUARIO, PAGADOR…).
     * Las columnas se reconocen por el nombre de la cabecera.
     *
     * @return array{leidas: int, nuevas: int, duplicadas: int, rechazadas: list<string>, desde: ?string, hasta: ?string}
     */
    public function importarPagos(string $path, string $ext, string $banco, string $titular, string $archivo, ?int $userId): array
    {
        $cols = null;
        $filas = [];
        $rechazadas = [];
        $ocurrencias = [];
        $leidas = 0;
        foreach ($this->parser->filasCrudas($path, $ext) as $i => $fila) {
            $fila = array_map(fn ($v) => $this->texto($v, true), (array) $fila);
            if ($cols === null) {
                $cols = $this->columnasPagos($fila);
                continue;
            }
            if (implode('', $fila) === '') {
                continue;
            }
            $leidas++;
            [$fecha, $fechaHora] = $this->fechaHora($fila[$cols['fecha']] ?? '');
            $monto = $this->parser->normalizarMonto($fila[$cols['monto']] ?? '');
            if ($fecha === null || $monto === null || (float) $monto <= 0) {
                if (count($rechazadas) < 15 && trim(implode('', $fila)) !== '') {
                    $rechazadas[] = 'Fila ' . ($i + 1) . ': ' . ($fecha === null ? 'fecha no reconocida' : 'monto no reconocido')
                        . ' («' . mb_substr(implode(' · ', array_filter($fila)), 0, 90) . '»)';
                }

                continue;
            }
            $dato = fn (string $c) => isset($cols[$c]) ? (mb_substr(trim($fila[$cols[$c]] ?? ''), 0, 160) ?: null) : null;
            $base = implode('|', [mb_strtoupper($banco), mb_strtoupper($titular), $dato('nro'), $fechaHora ?? $fecha, $monto, mb_strtoupper((string) $dato('pagador'))]);
            $ocurrencias[$base] = ($ocurrencias[$base] ?? 0) + 1;
            $filas[] = [
                'banco' => $banco,
                'titular' => $titular,
                'nro' => $dato('nro'),
                'fecha' => $fecha,
                'fecha_hora' => $fechaHora,
                'monto' => round((float) $monto, 2),
                'localidad' => $dato('localidad'),
                'terminal' => $dato('terminal'),
                'usuario' => $dato('usuario'),
                'pagador' => $dato('pagador'),
                'referencia' => $dato('referencia'),
                'huella' => hash('sha256', $base . '|' . $ocurrencias[$base]),
                'archivo' => mb_substr($archivo, 0, 255),
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if ($cols === null) {
            throw ValidationException::withMessages(['archivo' => 'No se encontró la cabecera del reporte de pagos (columnas MONTO y FECHA).']);
        }

        $nuevas = 0;
        foreach (array_chunk($filas, 300) as $chunk) {
            $nuevas += DB::table('conciliacion_divisas_pagos')->insertOrIgnore($chunk);
        }
        $fechas = array_column($filas, 'fecha');

        return [
            'leidas' => $leidas, 'nuevas' => $nuevas, 'duplicadas' => count($filas) - $nuevas, 'rechazadas' => $rechazadas,
            'desde' => $fechas ? min($fechas) : null, 'hasta' => $fechas ? max($fechas) : null,
        ];
    }

    /**
     * Cruza cada cobro del reporte de pagos con su abono en el extracto: mismo monto y la hora más cercana.
     * El banco registra el abono primero y la caja lo confirma segundos o minutos después, así que se prueba
     * primero en ventanas cortas y solo después en las largas; dentro de cada pasada gana la pareja más cercana.
     *
     * @return array{cruzados: int, sin_abono: int}
     */
    public function cruzarPagos(string $banco, string $titular, string $desde, string $hasta): array
    {
        if (! Schema::hasTable('conciliacion_divisas_pagos')) {
            return ['cruzados' => 0, 'sin_abono' => 0];
        }
        $pagos = $this->pagos($banco, $titular, $desde, $hasta)->whereNull('linea_id')->values();
        $ocupadas = ConciliacionDivisaPago::query()->whereNotNull('linea_id')->pluck('linea_id')->flip();
        $lineas = $this->lineas($banco, $titular, date('Y-m-d', strtotime($desde . ' -1 day')), date('Y-m-d', strtotime($hasta . ' +1 day')))
            ->where('estado', 'pendiente')
            ->filter(fn ($l) => ! $l->esCargo() && ! isset($ocupadas[$l->id]))
            ->values();

        $porMonto = [];
        foreach ($lineas as $k => $l) {
            $porMonto[(int) round(abs($l->monto) * 100)][] = $k;
        }
        $tsLinea = fn ($l) => $l->hora ? strtotime($l->fecha->format('Y-m-d') . ' ' . $l->hora) : null;
        $dig = fn ($r) => ltrim(preg_replace('/\D/', '', (string) $r), '0');

        $pasadas = ['ref', [-120, 900], [-600, 7200], [-1800, 43200], 'dia'];
        $libresPago = array_fill_keys(array_keys($pagos->all()), true);
        $libresLinea = array_fill_keys(array_keys($lineas->all()), true);
        $pares = [];
        foreach ($pasadas as $pasada) {
            $cands = [];
            foreach ($libresPago as $pk => $_) {
                $p = $pagos[$pk];
                $tp = $p->fecha_hora?->getTimestamp();
                foreach ($porMonto[(int) round($p->monto * 100)] ?? [] as $lk) {
                    if (! isset($libresLinea[$lk])) {
                        continue;
                    }
                    $l = $lineas[$lk];
                    $tl = $tsLinea($l);
                    if ($pasada === 'ref') {
                        $rp = $dig($p->referencia);
                        $ok = strlen($rp) >= 4 && (str_contains($dig($l->referencia) . ' ' . $dig($l->descripcion), $rp));
                        $score = $tp && $tl ? abs($tp - $tl) : 0;
                    } elseif ($pasada === 'dia') {
                        $dias = abs($l->fecha->diffInDays($p->fecha, false));
                        $ok = ($tp === null || $tl === null) ? $dias <= 1 : false;
                        $score = $dias;
                    } else {
                        if ($tp === null || $tl === null) {
                            continue;
                        }
                        $dt = $tp - $tl;
                        $ok = $dt >= $pasada[0] && $dt <= $pasada[1];
                        // Abono antes que la confirmación de caja: es lo normal y pesa menos.
                        $score = $dt >= 0 ? $dt : 3 * abs($dt);
                    }
                    if ($ok) {
                        $cands[] = [$score, $pk, $lk];
                    }
                }
            }
            usort($cands, fn ($a, $b) => $a[0] <=> $b[0]);
            foreach ($cands as [, $pk, $lk]) {
                if (isset($libresPago[$pk], $libresLinea[$lk])) {
                    unset($libresPago[$pk], $libresLinea[$lk]);
                    $pares[] = [$pagos[$pk]->id, $lineas[$lk]->id];
                }
            }
        }

        $cruzados = 0;
        foreach ($pares as [$pagoId, $lineaId]) {
            $cruzados += $this->vincularPago($pagoId, $lineaId) ? 1 : 0;
        }

        return ['cruzados' => $cruzados, 'sin_abono' => $pagos->count() - $cruzados];
    }

    public function vincularPago(int $pagoId, int $lineaId): bool
    {
        try {
            return DB::transaction(function () use ($pagoId, $lineaId) {
                if (ConciliacionDivisaPago::query()->where('linea_id', $lineaId)->exists()) {
                    return false;
                }
                $ok = ConciliacionDivisaLinea::query()->whereKey($lineaId)->whereIn('estado', ['pendiente', 'ignorado'])->whereNull('flujo_caja_id')
                    ->update(['estado' => 'conciliado', 'updated_at' => now()]);
                if ($ok !== 1) {
                    return false;
                }
                if (ConciliacionDivisaPago::query()->whereKey($pagoId)->whereNull('linea_id')->update(['linea_id' => $lineaId, 'updated_at' => now()]) !== 1) {
                    // Otro proceso cruzó el pago: se deshace también el movimiento.
                    throw new VinculoOcupadoException;
                }

                return true;
            });
        } catch (VinculoOcupadoException) {
            return false;
        }
    }

    /**
     * Reconoce los documentos conocidos por su cabecera, para no pedir el mapeo de columnas:
     * historial de transacciones de Binance (extracto) y reporte de pagos de caja de Cloud.
     *
     * @param  list<list<string>>  $muestra
     * @return array{tipo: 'extracto'|'pagos', nombre: string, mapeo?: array<string, mixed>}|null
     */
    public function detectarFormato(array $muestra): ?array
    {
        $norm = fn ($s) => mb_strtolower(trim(strtr((string) $s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u'])));
        foreach ($muestra as $i => $fila) {
            $celdas = array_map($norm, $fila);
            $col = function (array $nombres) use ($celdas): ?int {
                foreach ($nombres as $n) {
                    $j = array_search($n, $celdas, true);
                    if ($j !== false) {
                        return (int) $j;
                    }
                }

                return null;
            };

            $fecha = $col(['tiempo', 'time', 'date(utc)']);
            $monto = $col(['cambio', 'change']);
            $operacion = $col(['operacion', 'operation']);
            if ($fecha !== null && $monto !== null && $operacion !== null) {
                return [
                    'tipo' => 'extracto',
                    'nombre' => 'historial de Binance',
                    'mapeo' => [
                        'fila_cabecera' => $i,
                        'fecha' => $fecha,
                        'descripcion' => array_values(array_filter([$col(['cuenta', 'account']), $operacion, $col(['observacion', 'observaciones', 'remark'])], fn ($c) => $c !== null)),
                        'referencia' => null,
                        'monto' => $monto,
                        'debito' => null,
                        'credito' => null,
                        'formato_fecha' => 'auto',
                        'invertir_signo' => false,
                    ],
                ];
            }

            $pagos = $this->columnasPagos($fila);
            if ($pagos !== null && (isset($pagos['pagador']) || isset($pagos['terminal']) || in_array('fecha confirmado', $celdas, true))) {
                return ['tipo' => 'pagos', 'nombre' => 'reporte de pagos de caja'];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $fila
     * @return array<string, int>|null
     */
    private function columnasPagos(array $fila): ?array
    {
        $nombres = [
            'nro' => ['NRO', 'NRO.', 'N°', 'NUMERO', '#'],
            'monto' => ['MONTO', 'MONTO USD', 'AMOUNT', 'TOTAL', 'IMPORTE'],
            'fecha' => ['FECHA CONFIRMADO', 'FECHA CONFIRMACION', 'FECHA DE CONFIRMACION', 'FECHA Y HORA', 'FECHA PAGO', 'FECHA'],
            'localidad' => ['LOCALIDAD', 'SEDE', 'TIENDA', 'SUCURSAL'],
            'terminal' => ['TERMINAL', 'CAJA'],
            'usuario' => ['USUARIO', 'CAJERO', 'VENDEDOR'],
            'pagador' => ['PAGADOR', 'CLIENTE', 'NOMBRE', 'TITULAR'],
            'referencia' => ['REFERENCIA', 'REF', 'REF.', 'CODIGO', 'ORDER ID', 'ID TRANSACCION'],
        ];
        $norm = fn ($s) => mb_strtoupper(trim(strtr((string) $s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U'])));
        $celdas = array_map($norm, $fila);
        $cols = [];
        foreach ($nombres as $campo => $opciones) {
            foreach ($opciones as $op) {
                $j = array_search($op, $celdas, true);
                if ($j !== false) {
                    $cols[$campo] = (int) $j;
                    break;
                }
            }
        }

        return isset($cols['monto'], $cols['fecha']) ? $cols : null;
    }

    /** @return array{0: ?string, 1: ?string} [Y-m-d, Y-m-d H:i:s|null] */
    private function fechaHora(string $raw): array
    {
        $raw = trim(preg_replace('/\s+/', ' ', $raw) ?? '');
        if ($raw === '') {
            return [null, null];
        }
        if (is_numeric($raw) && (float) $raw > 20000) {
            $c = Carbon::createFromTimestampUTC((int) round(((float) $raw - 25569) * 86400));

            return [$c->format('Y-m-d'), $c->format('H:i:s') !== '00:00:00' ? $c->format('Y-m-d H:i:s') : null];
        }
        $norm = preg_replace('/\s*([ap])\.?\s*m\.?$/i', ' $1M', $raw) ?? $raw;
        foreach (['d/m/Y g:i:s A', 'd/m/Y g:i A', 'd/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'd-m-Y H:i:s'] as $f) {
            $d = \DateTime::createFromFormat('!' . $f, strtoupper($norm));
            if ($d && $d->format('Y') >= 2000) {
                return [$d->format('Y-m-d'), $d->format('Y-m-d H:i:s')];
            }
        }
        $solo = $this->fecha($raw, 'auto');

        return [$solo, null];
    }

    /** Hora de la celda de fecha del extracto ("2026-08-03 14:59:48", "08/03/2026 2:59 PM" o serial de Excel). */
    private function hora(string $raw): ?string
    {
        $raw = trim($raw);
        if (is_numeric($raw) && (float) $raw > 20000) {
            $seg = (int) round(fmod((float) $raw, 1) * 86400);

            return $seg > 0 ? gmdate('H:i:s', $seg) : null;
        }
        if (! preg_match('/(\d{1,2}):(\d{2})(?::(\d{2}))?\s*([ap])?\.?\s*m?\.?/i', $raw, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $ap = strtolower($m[4] ?? '');
        if ($ap === 'p' && $h < 12) {
            $h += 12;
        } elseif ($ap === 'a' && $h === 12) {
            $h = 0;
        }

        return $h <= 23 ? sprintf('%02d:%02d:%02d', $h, (int) $m[2], (int) ($m[3] ?? 0)) : null;
    }

    /**
     * Archivos que aportan filas al período: de dónde sale cada lado de la conciliación.
     *
     * @return list<array{archivo: string, filas: int, desde: string, hasta: string, subido: ?string}>
     */
    private function documentosCargados(string $tabla, string $banco, string $titular, string $desde, string $hasta): array
    {
        return DB::table($tabla)
            ->whereRaw('UPPER(TRIM(banco)) = ? AND UPPER(TRIM(titular)) = ?', [mb_strtoupper(trim($banco)), mb_strtoupper(trim($titular))])
            ->whereBetween('fecha', [$desde, $hasta])
            ->groupBy('archivo')
            ->selectRaw('archivo, COUNT(*) AS filas, MIN(fecha) AS desde, MAX(fecha) AS hasta, MAX(created_at) AS subido')
            ->orderByRaw('MAX(created_at) DESC')
            ->get()
            ->map(fn ($r) => [
                'archivo' => (string) ($r->archivo ?: '(sin nombre)'),
                'filas' => (int) $r->filas,
                'desde' => substr((string) $r->desde, 0, 10),
                'hasta' => substr((string) $r->hasta, 0, 10),
                'subido' => $r->subido ? substr((string) $r->subido, 0, 16) : null,
            ])->all();
    }

    /** @return Collection<int, ConciliacionDivisaPago> */
    private function pagos(string $banco, string $titular, string $desde, string $hasta): Collection
    {
        return ConciliacionDivisaPago::query()
            ->whereRaw('UPPER(TRIM(banco)) = ? AND UPPER(TRIM(titular)) = ?', [mb_strtoupper(trim($banco)), mb_strtoupper(trim($titular))])
            ->whereBetween('fecha', [$desde, $hasta])
            ->orderBy('fecha_hora')->orderBy('id')
            ->get();
    }

    public function vincular(int $lineaId, int $flujoId): bool
    {
        return DB::transaction(function () use ($lineaId, $flujoId) {
            $ocupado = ConciliacionDivisaLinea::query()->where('flujo_caja_id', $flujoId)->where('id', '!=', $lineaId)->exists();
            if ($ocupado) {
                return false;
            }
            $ok = ConciliacionDivisaLinea::query()->whereKey($lineaId)->whereIn('estado', ['pendiente', 'comision', 'ignorado'])
                ->update(['estado' => 'conciliado', 'flujo_caja_id' => $flujoId, 'updated_at' => now()]);
            if ($ok !== 1) {
                return false;
            }
            DB::table('flujo_cajas')->where('id', $flujoId)->where(fn ($q) => $q->whereNull('categoria_egreso')->orWhere('categoria_egreso', '!=', 'traslados'))
                ->update(['es_conciliado' => true, 'updated_at' => now()]);

            return true;
        });
    }

    public function desvincular(int $lineaId): void
    {
        DB::transaction(function () use ($lineaId) {
            $linea = ConciliacionDivisaLinea::query()->lockForUpdate()->find($lineaId);
            if (! $linea) {
                return;
            }
            $flujoId = $linea->flujo_caja_id;
            $linea->update(['estado' => 'pendiente', 'flujo_caja_id' => null]);
            if (Schema::hasTable('conciliacion_divisas_pagos')) {
                ConciliacionDivisaPago::query()->where('linea_id', $lineaId)->update(['linea_id' => null, 'updated_at' => now()]);
            }
            if ($flujoId && ! ConciliacionDivisaLinea::query()->where('flujo_caja_id', $flujoId)->exists()) {
                DB::table('flujo_cajas')->where('id', $flujoId)->where(fn ($q) => $q->whereNull('categoria_egreso')->orWhere('categoria_egreso', '!=', 'traslados'))
                    ->update(['es_conciliado' => false, 'updated_at' => now()]);
            }
        });
    }

    public function marcar(int $lineaId, string $estado): void
    {
        $linea = ConciliacionDivisaLinea::query()->find($lineaId);
        if (! $linea) {
            return;
        }
        if ($linea->estado === 'conciliado') {
            $this->desvincular($lineaId);
        }
        ConciliacionDivisaLinea::query()->whereKey($lineaId)->update(['estado' => $estado, 'updated_at' => now()]);
    }

    /**
     * Datos de la pantalla para una cuenta y período.
     *
     * @return array<string, mixed>
     */
    public function resumen(string $banco, string $titular, string $desde, string $hasta): array
    {
        $conPagos = Schema::hasTable('conciliacion_divisas_pagos');
        $lineas = $this->lineas($banco, $titular, $desde, $hasta)->load($conPagos ? ['flujoCaja', 'pago'] : ['flujoCaja']);
        $vinculados = ConciliacionDivisaLinea::query()->whereNotNull('flujo_caja_id')->pluck('flujo_caja_id')->flip();
        $sinPareja = $this->egresosDelPeriodo($banco, $titular, $desde, $hasta)
            ->reject(fn ($f) => isset($vinculados[$f->id]))
            ->values();
        $pagos = $conPagos ? $this->pagos($banco, $titular, $desde, $hasta) : collect();
        // Cobro registrado dos veces en caja: mismo monto y pagador con minutos de diferencia; el banco recibió uno solo.
        foreach ($pagos->whereNull('linea_id') as $p) {
            $p->duplicado_de = $pagos->first(fn ($o) => $o->id !== $p->id && abs($o->monto - $p->monto) < 0.005
                && $p->pagador && mb_strtoupper(trim((string) $o->pagador)) === mb_strtoupper(trim($p->pagador))
                && $o->fecha_hora && $p->fecha_hora && abs($o->fecha_hora->getTimestamp() - $p->fecha_hora->getTimestamp()) <= 900)?->nro;
        }

        return [
            'consolidados' => $lineas->where('estado', 'conciliado')->values(),
            'pendientes' => $lineas->where('estado', 'pendiente')->values(),
            'comisiones' => $lineas->where('estado', 'comision')->values(),
            'ignorados' => $lineas->where('estado', 'ignorado')->values(),
            'egresos_sin_pareja' => $sinPareja,
            'documentos' => [
                'banco' => $this->documentosCargados('conciliacion_divisas_lineas', $banco, $titular, $desde, $hasta),
                'cobros' => $conPagos ? $this->documentosCargados('conciliacion_divisas_pagos', $banco, $titular, $desde, $hasta) : [],
            ],
            'pagos_total' => $pagos->count(),
            'pagos_cruzados' => $pagos->whereNotNull('linea_id')->count(),
            'pagos_sin_abono' => $pagos->whereNull('linea_id')->values(),
            'total_lineas' => $lineas->count(),
        ];
    }

    /** @return Collection<int, object> egresos libres de la cuenta para emparejar a mano */
    public function egresosParaManual(string $banco, string $titular, string $desde, string $hasta): Collection
    {
        return $this->egresosDisponibles($banco, $titular, $desde, $hasta)
            ->concat($this->trasladosEntrantes($banco, $titular, $desde, $hasta));
    }

    /** @return Collection<int, ConciliacionDivisaLinea> */
    private function lineas(string $banco, string $titular, string $desde, string $hasta): Collection
    {
        return ConciliacionDivisaLinea::query()
            ->whereRaw('UPPER(TRIM(banco)) = ? AND UPPER(TRIM(titular)) = ?', [mb_strtoupper(trim($banco)), mb_strtoupper(trim($titular))])
            ->whereBetween('fecha', [$desde, $hasta])
            ->orderBy('fecha')->orderBy('id')
            ->get();
    }

    /** @return Collection<int, object> */
    private function egresosDelPeriodo(string $banco, string $titular, string $desde, string $hasta): Collection
    {
        return $this->baseEgresos($desde, $hasta)
            ->whereRaw('UPPER(TRIM(banco)) = ? AND UPPER(TRIM(titular)) = ?', [mb_strtoupper(trim($banco)), mb_strtoupper(trim($titular))])
            ->whereBetween('fecha', [$desde, $hasta])
            ->get();
    }

    /** @return Collection<int, object> */
    private function egresosDisponibles(string $banco, string $titular, string $desde, string $hasta): Collection
    {
        $vinculados = ConciliacionDivisaLinea::query()->whereNotNull('flujo_caja_id')->pluck('flujo_caja_id')->all();

        return $this->baseEgresos(date('Y-m-d', strtotime($desde . ' -10 days')), date('Y-m-d', strtotime($hasta . ' +10 days')))
            ->whereRaw('UPPER(TRIM(banco)) = ? AND UPPER(TRIM(titular)) = ?', [mb_strtoupper(trim($banco)), mb_strtoupper(trim($titular))])
            ->when($vinculados !== [], fn ($q) => $q->whereNotIn('id', $vinculados))
            ->get();
    }

    /** @return Collection<int, object> traslados que llegan a esta cuenta (abonos) */
    private function trasladosEntrantes(string $banco, string $titular, string $desde, string $hasta): Collection
    {
        if (! Schema::hasColumn('flujo_cajas', 'banco_receptor')) {
            return collect();
        }
        $vinculados = ConciliacionDivisaLinea::query()->whereNotNull('flujo_caja_id')->pluck('flujo_caja_id')->all();

        return $this->baseEgresos(date('Y-m-d', strtotime($desde . ' -10 days')), date('Y-m-d', strtotime($hasta . ' +10 days')))
            ->where('categoria_egreso', 'traslados')
            ->whereRaw('UPPER(TRIM(banco_receptor)) = ? AND UPPER(TRIM(titular_receptor)) = ?', [mb_strtoupper(trim($banco)), mb_strtoupper(trim($titular))])
            ->when($vinculados !== [], fn ($q) => $q->whereNotIn('id', $vinculados))
            ->get();
    }

    private function baseEgresos(string $desde, string $hasta)
    {
        return DB::table('flujo_cajas')
            ->where('tipo', 'egreso')
            ->whereBetween('fecha', [$desde, $hasta])
            ->when(Schema::hasColumn('flujo_cajas', 'anulado'), fn ($q) => $q->where(fn ($w) => $w->where('anulado', false)->orWhereNull('anulado')))
            ->where(fn ($q) => $q->whereNotNull('monto_usd')->where('monto_usd', '!=', 0))
            ->select(['id', 'fecha', 'banco', 'titular', 'referencia', 'monto_usd', 'motivo', 'categoria_egreso', 'banco_receptor', 'titular_receptor'])
            ->orderBy('fecha');
    }

    public function esComision(?string $descripcion): bool
    {
        $d = mb_strtolower((string) $descripcion, 'UTF-8');
        foreach (self::COMISION as $kw) {
            if (preg_match('/\b' . preg_quote($kw, '/') . '\b/u', $d)) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<string>  $fila */
    private function monto(array $fila, array $mapeo): ?float
    {
        $num = function ($v) {
            $v = trim((string) $v);
            if ($v === '') {
                return 0.0;
            }
            $t = $this->parser->normalizarMonto($v);

            return $t === null ? null : (float) $t;
        };

        if ($mapeo['monto'] !== null) {
            $m = $num($fila[$mapeo['monto']] ?? '');
        } else {
            $deb = $mapeo['debito'] !== null ? $num($fila[$mapeo['debito']] ?? '') : 0.0;
            $cre = $mapeo['credito'] !== null ? $num($fila[$mapeo['credito']] ?? '') : 0.0;
            if ($deb === null || $cre === null) {
                return null;
            }
            $m = abs($cre) - abs($deb);
        }
        if ($m === null) {
            return null;
        }

        return ! empty($mapeo['invertir_signo']) ? -$m : $m;
    }

    private function fecha(string $raw, string $formato): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (is_numeric($raw) && (float) $raw > 20000) {
            return Carbon::createFromTimestampUTC(((float) $raw - 25569) * 86400)->format('Y-m-d');
        }
        $solo = preg_split('/[ T]/', $raw)[0];
        if ($formato !== 'auto') {
            $f = str_replace('/', preg_match('/-/', $solo) ? '-' : '/', $formato);
            foreach ([$f, str_replace('Y', 'y', $f)] as $intento) {
                $d = \DateTime::createFromFormat('!' . $intento, $solo);
                if ($d && $d->format('Y') >= 2000) {
                    return $d->format('Y-m-d');
                }
            }

            return null;
        }

        return $this->parser->normalizarFecha($raw);
    }

    private function texto(mixed $v, bool $conHora = false): string
    {
        if ($v instanceof \DateTimeInterface) {
            return $v->format($conHora && $v->format('H:i:s') !== '00:00:00' ? 'Y-m-d H:i:s' : 'Y-m-d');
        }

        return trim(preg_replace('/\s+/u', ' ', (string) $v) ?? '');
    }
}
