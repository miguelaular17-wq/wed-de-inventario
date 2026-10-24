<?php

namespace App\Services\Conciliacion;

use App\Models\ConciliacionDivisaLinea;
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
        foreach ($this->parser->filasCrudas($path, $ext) as $i => $fila) {
            if ($i <= $mapeo['fila_cabecera']) {
                continue;
            }
            $fila = array_map(fn ($v) => $this->texto($v), (array) $fila);
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
            $base = implode('|', [mb_strtoupper($banco), mb_strtoupper($titular), $fecha, number_format($monto, 2, '.', ''), $ref, mb_strtoupper($desc)]);
            $ocurrencias[$base] = ($ocurrencias[$base] ?? 0) + 1;

            $filas[] = [
                'banco' => $banco,
                'titular' => $titular,
                'fecha' => $fecha,
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
     * @return array{conciliadas: int, comisiones: int}
     */
    public function conciliar(string $banco, string $titular, string $desde, string $hasta): array
    {
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

        return ['conciliadas' => $conciliadas, 'comisiones' => $comisiones];
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
        if ($linea->flujo_caja_id) {
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
        $lineas = $this->lineas($banco, $titular, $desde, $hasta)->load('flujoCaja');
        $vinculados = ConciliacionDivisaLinea::query()->whereNotNull('flujo_caja_id')->pluck('flujo_caja_id')->flip();
        $sinPareja = $this->egresosDelPeriodo($banco, $titular, $desde, $hasta)
            ->reject(fn ($f) => isset($vinculados[$f->id]))
            ->values();

        return [
            'consolidados' => $lineas->where('estado', 'conciliado')->values(),
            'pendientes' => $lineas->where('estado', 'pendiente')->values(),
            'comisiones' => $lineas->where('estado', 'comision')->values(),
            'ignorados' => $lineas->where('estado', 'ignorado')->values(),
            'egresos_sin_pareja' => $sinPareja,
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

    private function texto(mixed $v): string
    {
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }

        return trim(preg_replace('/\s+/u', ' ', (string) $v) ?? '');
    }
}
