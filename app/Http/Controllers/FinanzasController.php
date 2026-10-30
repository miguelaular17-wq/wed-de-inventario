<?php
namespace App\Http\Controllers;

use App\Models\FlujoCaja;
use App\Models\CuentaPorPagar;
use App\Models\ConciliacionBancaria;
use App\Models\ConciliacionCierre;
use App\Models\GastoFijoPago;
use App\Models\GastoFijoConfig;
use App\Models\GastoFijoOculto;
use App\Models\GastoFijoCustom;
use App\Models\Nomina\NominaEmpleado;
use Illuminate\Http\Request;
use App\Services\Profiler;

use App\Services\BcvRateService;
use App\Services\GastoFijoPendienteService;
use App\Services\TodoTicketPago;

class FinanzasController extends Controller
{
            public function __construct(
        private BcvRateService $bcvRate,
        private GastoFijoPendienteService $gastosFijosPendientes,
    ) {}

    /** @var array<string, array<string, true>> */
    /** @var list<array<string, mixed>> */
    private array $bufferExtracto = [];

    /** @var array<string, mixed>|null */
    private ?array $bufferContexto = null;

    /** Cuentas del sistema más las agregadas en Finanzas → Configuración. */
    private function getCuentas()
    {
        return \App\Models\FinanzasCatalogo::combinarCuentas(self::cuentasBase());
    }

    /** @return list<array{banco: string, titular: string, categoria: string}> */
    public static function cuentasBase(): array
    {
        return [
            ['banco' => 'Banesco', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Banesco', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Banesco', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Banesco', 'titular' => 'Nunes', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Banesco', 'titular' => 'Grupo JENU', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Banesco', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Banesco', 'titular' => 'Euronissi', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'BNC', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'BNC', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'BNC', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'BNC', 'titular' => 'L.S. Cashea', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Mercantil', 'titular' => 'Grupo JENU', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Mercantil', 'titular' => 'JRZ', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'BBVA', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'BBVA', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Bancaribe', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Bancaribe', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Bancamiga', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO'],
            ['banco' => 'Mercantil', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Mercantil', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Tesoro', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Tesoro', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Tesoro', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Tesoro', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Venezuela', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Venezuela', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Venezuela', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Venezuela', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Bancaribe', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Bancaribe', 'titular' => 'Euronissi', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Bancamiga', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'BBVA', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'BNC', 'titular' => 'Doral Cashea', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'BNC', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'BNC', 'titular' => 'Euronissi', 'categoria' => 'BANCA NACIONAL - BAJO MOVIMIENTO'],
            ['banco' => 'Banesco', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Banesco', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Banesco', 'titular' => 'Nunes', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Banesco', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Banesco', 'titular' => 'Grupo JENU', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Banesco', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'Tipo A José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'Tipo B José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'Tarjeta José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'L.S. Cashea', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BNC', 'titular' => 'Doral Cashea', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Mercantil', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Mercantil', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Mercantil', 'titular' => 'Grupo JENU', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BBVA', 'titular' => 'José Jerez (USD)', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BBVA', 'titular' => 'José Jerez (EUR)', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'BBVA', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Bancaribe', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Bancaribe', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Bancaribe', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Bancaribe', 'titular' => 'Curazao', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Bancaribe', 'titular' => 'Puerto Rico', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Tesoro', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Tesoro', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Tesoro', 'titular' => 'Grupo JRZ', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Tesoro', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Tesoro', 'titular' => 'Tarjeta José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Venezuela', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Venezuela', 'titular' => 'LNACEH', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Bancamiga', 'titular' => 'Doral', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Bancamiga', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Facebank', 'titular' => 'José Jerez', 'categoria' => 'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA'],
            ['banco' => 'Mercantil Panamá', 'titular' => 'José Jerez', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Binance', 'titular' => 'Grupo JENU', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Wells Fargo Investment', 'titular' => 'Doral', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Amerant Investment', 'titular' => 'Doral', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Citizens Money Market', 'titular' => 'Doral', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Citizens Checking', 'titular' => 'Doral', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Regions Investment', 'titular' => 'Doral', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'First Horizon Investment', 'titular' => 'Doral', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Citizens Checking', 'titular' => 'Nunes Store', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Citizens Savings', 'titular' => 'Nunes Store', 'categoria' => 'BANCA INTERNACIONAL / BILLETERAS'],
            ['banco' => 'Venezuela', 'titular' => 'Edward Mavo', 'categoria' => 'TARJETAS INTERNACIONALES DE TERCEROS'],
            ['banco' => 'Venezuela', 'titular' => 'María Núñez', 'categoria' => 'TARJETAS INTERNACIONALES DE TERCEROS'],
            ['banco' => 'Venezuela', 'titular' => 'Dayana López', 'categoria' => 'TARJETAS INTERNACIONALES DE TERCEROS'],
            ['banco' => 'Venezuela', 'titular' => 'José Semeco', 'categoria' => 'TARJETAS INTERNACIONALES DE TERCEROS'],
        ];
    }

    private function getTasaBcvDelDia(): float
    {
        return $this->bcvRate->getRateForToday();
    }

    /** Canoniza nombre de banco (p.ej. BANCO DE VENEZUELA → VENEZUELA). */
    /** Orden cronológico (más viejo primero); a igual fecha, por referencia. */
    private function claveOrdenFecha(array $row): string
    {
        $fecha = $row['fecha'] ?? null;
        $dia = $fecha instanceof \DateTimeInterface ? $fecha->format('Y-m-d') : substr((string) $fecha, 0, 10);

        return $dia.'|'.str_pad((string) ($row['referencia'] ?? ''), 30, '0', STR_PAD_LEFT);
    }

    private function canonizarBanco(?string $banco): string
    {
        return app(\App\Services\BankReconciliationMatcher::class)
            ->partesCuenta($banco, '')[0];
    }

    /**
     * Filtra por banco aceptando alias (VENEZUELA = BANCO DE VENEZUELA).
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    private function filtrarPorBanco($query, ?string $bancoFiltro, string $columna = 'banco')
    {
        if (! $bancoFiltro) {
            return $query;
        }

        $variantes = array_map(
            'strtolower',
            app(\App\Services\BankReconciliationMatcher::class)->variantesBanco($bancoFiltro)
        );

        return $query->where(function ($q) use ($variantes, $columna) {
            foreach ($variantes as $v) {
                $q->orWhereRaw("LOWER(TRIM({$columna})) = ?", [$v]);
            }
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    private function filtrarPorTitular($query, ?string $titular, string $columna = 'titular')
    {
        $titular = strtoupper(trim((string) $titular));
        if ($titular === '') {
            return $query;
        }

        $clave = app(\App\Services\BankReconciliationMatcher::class)->titularClave($titular);
        $variantes = array_values(array_unique(array_filter([
            $titular,
            $clave,
            $clave !== '' ? 'GRUPO '.$clave : '',
            $clave !== '' ? 'L.S. '.$clave : '',
        ])));

        return $query->where(function ($q) use ($variantes, $columna) {
            foreach ($variantes as $variante) {
                $q->orWhereRaw("UPPER(TRIM(COALESCE({$columna}, ''))) = ?", [$variante]);
            }
        });
    }

    /**
     * Una fila por movimiento. Si el extracto se subió varias veces, se queda
     * la conciliada y se ignoran las copias pendientes.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function idsExtractoVisibles(string $desde, string $hasta, string $banco, ?string $titular = null)
    {
        $variantes = array_map(
            'strtolower',
            app(\App\Services\BankReconciliationMatcher::class)->variantesBanco($banco)
        );
        if ($variantes === []) {
            return collect();
        }

        $marcas = implode(',', array_fill(0, count($variantes), '?'));
        $titularSql = '';
        $titularBindings = [];
        $titular = strtoupper(trim((string) $titular));
        if ($titular !== '') {
            $clave = app(\App\Services\BankReconciliationMatcher::class)->titularClave($titular);
            $titulares = array_values(array_unique(array_filter([
                $titular,
                $clave,
                $clave !== '' ? 'GRUPO '.$clave : '',
                $clave !== '' ? 'L.S. '.$clave : '',
            ])));
            $titularSql = ' AND UPPER(TRIM(COALESCE(titular, \'\'))) IN ('.implode(',', array_fill(0, count($titulares), '?')).')';
            $titularBindings = $titulares;
        }
        $filas = \Illuminate\Support\Facades\DB::select(
            "SELECT DISTINCT ON (
                    UPPER(TRIM(banco)),
                    UPPER(TRIM(COALESCE(titular, ''))),
                    fecha::date,
                    ROUND(monto::numeric, 2),
                    COALESCE(referencia, ''),
                    COALESCE(descripcion, ''),
                    COALESCE(tipo, '')
                ) id
             FROM conciliacion_lineas
             WHERE fecha >= ? AND fecha <= ?
               AND LOWER(TRIM(banco)) IN ({$marcas}){$titularSql}
             ORDER BY
                UPPER(TRIM(banco)),
                UPPER(TRIM(COALESCE(titular, ''))),
                fecha::date,
                ROUND(monto::numeric, 2),
                COALESCE(referencia, ''),
                COALESCE(descripcion, ''),
                COALESCE(tipo, ''),
                CASE WHEN estado = 'conciliado' THEN 0 ELSE 1 END,
                id",
            array_merge([$desde, $hasta], $variantes, $titularBindings)
        );

        return collect($filas)->pluck('id')->map(fn ($id) => (int) $id)->values();
    }

    /**
     * Imágenes, PDF de Bancaribe y bancos leídos por IA: las líneas se acumulan por archivo
     * y se guardan juntas con el importador por bloques (misma regla de duplicados).
     */
    private function guardarLineaExtracto(array $datos): bool
    {
        $this->bufferExtracto[] = $datos;

        return true;
    }

    /**
     * @param  list<\App\Models\ConciliacionImportacion>  $importaciones
     */
    private function volcarBufferExtracto(array &$importaciones): void
    {
        $lineas = $this->bufferExtracto;
        $ctx = $this->bufferContexto;
        $this->bufferExtracto = [];
        if ($lineas === [] || $ctx === null) {
            return;
        }

        $imp = \App\Models\ConciliacionImportacion::create([
            'user_id' => auth()->id(),
            'session_id' => $ctx['session_id'],
            'banco' => $ctx['banco'],
            'titular' => $ctx['titular'],
            'archivo_nombre' => mb_substr((string) $ctx['nombre'], 0, 255),
            'archivo_hash' => $ctx['hash'],
            'extension' => $ctx['ext'],
            'estado' => \App\Models\ConciliacionImportacion::PENDIENTE,
        ]);

        $parser = app(\App\Services\Conciliacion\ExtractoParser::class);
        $filas = (function () use ($lineas, $parser) {
            foreach ($lineas as $i => $d) {
                $fecha = $parser->normalizarFecha($d['fecha'] ?? '');
                $monto = $parser->normalizarMonto($d['monto'] ?? null);
                if ($fecha === null) {
                    yield ['tipo' => 'rechazo', 'fila' => $i + 1, 'motivo' => 'Fecha inválida'];

                    continue;
                }
                if ($monto === null || ltrim($monto, '-') === '0.00') {
                    yield ['tipo' => 'rechazo', 'fila' => $i + 1, 'motivo' => 'Sin monto'];

                    continue;
                }
                yield ['tipo' => 'linea', 'fila' => $i + 1, 'datos' => [
                    'fecha' => $fecha,
                    'descripcion' => mb_substr(trim((string) ($d['descripcion'] ?? '')), 0, 255),
                    'referencia' => mb_substr(trim((string) ($d['referencia'] ?? '')), 0, 255),
                    'monto' => ltrim($monto, '-'),
                    'tipo' => ($d['tipo'] ?? '') === 'cargo' ? 'cargo' : 'abono',
                ]];
            }
        })();

        try {
            $importaciones[] = app(\App\Services\Conciliacion\ExtractoImporter::class)->importarFilas($imp, $filas);
        } catch (\Throwable $e) {
            report($e);
            $importer = app(\App\Services\Conciliacion\ExtractoImporter::class);
            $importaciones[] = $importer->fallar($imp->refresh(), $importer->mensajeFalloInesperado($imp));
        }
    }

    private function registrarImportacion(\Illuminate\Http\UploadedFile $file, string $ext, string $banco, string $titular, string $sessionId): \App\Models\ConciliacionImportacion
    {
        $path = $file->storeAs('conciliaciones', \Illuminate\Support\Str::uuid()->toString().'.'.$ext, 'local');

        return \App\Models\ConciliacionImportacion::create([
            'user_id' => auth()->id(),
            'session_id' => $sessionId,
            'banco' => $banco,
            'titular' => $titular,
            'archivo_nombre' => mb_substr($file->getClientOriginalName(), 0, 255),
            'archivo_path' => $path,
            'archivo_hash' => hash_file('sha256', $file->getRealPath()) ?: null,
            'extension' => $ext,
            'estado' => \App\Models\ConciliacionImportacion::PENDIENTE,
        ]);
    }

    /**
     * Procesa (o encola) las importaciones creadas y arma el mensaje para el usuario.
     *
     * @param  list<\App\Models\ConciliacionImportacion>  $importaciones
     */
    private function responderImportaciones(array $importaciones)
    {
        $async = (bool) config('conciliacion.import_async');
        $enCola = 0;
        foreach ($importaciones as $i => $imp) {
            if ($imp->terminada()) {
                continue;
            }
            if ($async) {
                \App\Jobs\ProcesarImportacionExtracto::dispatch($imp->id)
                    ->onQueue((string) config('conciliacion.import_queue', 'conciliaciones'));
                $enCola++;

                continue;
            }
            try {
                \App\Jobs\ProcesarImportacionExtracto::dispatchSync($imp->id);
            } catch (\Throwable $e) {
                report($e);
                $importer = app(\App\Services\Conciliacion\ExtractoImporter::class);
                $importer->fallar($imp->refresh(), $importer->mensajeFalloInesperado($imp));
                $importer->borrarArchivo($imp);
            }
            $importaciones[$i] = $imp->refresh();
        }

        $ids = array_map(fn ($imp) => $imp->id, $importaciones);
        $importadas = array_sum(array_map(fn ($imp) => (int) $imp->filas_importadas, $importaciones));
        $duplicadas = array_sum(array_map(fn ($imp) => (int) $imp->filas_duplicadas, $importaciones));
        $rechazadas = array_sum(array_map(fn ($imp) => (int) $imp->filas_rechazadas, $importaciones));
        $conciliadas = array_sum(array_map(fn ($imp) => (int) ($imp->filas_conciliadas ?? 0), $importaciones));
        $fallidas = array_values(array_filter($importaciones, fn ($imp) => $imp->estado === \App\Models\ConciliacionImportacion::FALLIDA));

        if ($enCola > 0) {
            $mensaje = "Se recibieron {$enCola} archivo(s). Se están procesando en segundo plano; el avance aparece abajo.";
        } else {
            $mensaje = "Archivos procesados correctamente. Se registraron {$importadas} transacciones.";
            if ($duplicadas > 0) {
                $mensaje .= " {$duplicadas} ya estaban cargadas y no se volvieron a guardar.";
            }
            if ($conciliadas > 0) {
                $mensaje .= " {$conciliadas} se conciliaron automáticamente.";
            }
            if ($rechazadas > 0) {
                $mensaje .= " {$rechazadas} filas rechazadas (ver detalle abajo).";
            }
        }

        $redirect = redirect()->route('finanzas.conciliaciones')->with('importaciones_ids', $ids);
        if ($fallidas !== [] && $enCola === 0 && $importadas === 0) {
            return $redirect->with('error', $fallidas[0]->mensaje ?: 'No se pudo procesar el archivo.');
        }

        return $redirect->with('success', $mensaje);
    }

    private function syncSaldoInicialDisponibilidad($resumen)
    {
        // El saldo inicial es TOTAL DISPONIBILIDAD (TASA BCV)
        // que equivale a la suma de reporte_usd de ALTO y BAJO movimiento
        $saldoCalculado = \App\Models\CuentaBancaria::where('mostrar_en_principal', true)->sum('reporte_usd');

        if ($resumen->saldo_inicial != $saldoCalculado) {
            $resumen->saldo_inicial = $saldoCalculado;
            $resumen->save();
        }
        return $resumen;
    }

    public function syncTotalesSalidas($fecha)
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('finanzas_resumen')) {
            return;
        }
        $resumen = \App\Models\FinanzasResumen::where('fecha', $fecha)->first();
        if (!$resumen) return;

        $egresos = \App\Models\FlujoCaja::where('fecha', $fecha)
            ->where('tipo', 'egreso')
            ->where('oculto', false)
            ->vigentes()
            ->where(function($q) {
                $q->whereNull('categoria_egreso')
                  ->orWhereNotIn('categoria_egreso', ['traslados', 'egreso_divisas']);
            })
            ->get();

        $tasa_bcv = $resumen->tasa_bcv_usd > 0 ? $resumen->tasa_bcv_usd : 1;

        // Egresos Realizados (ignorar los que son nulos o vacíos en categoria)
        $egresos_realizados = $egresos->where('categoria_egreso', 'egreso_realizado');
        $resumen->total_egresos_usd = $egresos_realizados->sum('monto_usd');
        $resumen->total_egresos_bs_usd = $egresos_realizados->sum('monto_bs') / $tasa_bcv;

        // Otros Egresos
        $otros_egresos = $egresos->where('categoria_egreso', 'otros_egresos');
        $resumen->total_otros_usd = $otros_egresos->sum('monto_usd');
        $resumen->total_otros_bs_usd = $otros_egresos->sum('monto_bs') / $tasa_bcv;

        // Mantener las existentes por si acaso
        $total_usd = $egresos->sum('monto_usd');
        $total_bs = $egresos->sum('monto_bs');
        $total_salidas_usd = $total_bs / $tasa_bcv;
        
        $resumen->total_salidas_usd = $total_usd;
        $resumen->total_salidas_bs_en_usd = $total_salidas_usd;
        
        // Queda del dia anterior
        $resumen->queda_dia_anterior = $resumen->saldo_inicial - $total_salidas_usd;
        
        // Diferencial cambiario
        $total_diferencial = $egresos->sum('diferencial_cambiario');
        $resumen->porcentaje_total_diferencial = $resumen->saldo_inicial > 0 ? ($total_diferencial / $resumen->saldo_inicial) * 100 : 0;
        
        $resumen->save();
    }

    public function flujoCaja() {
        Profiler::start('FinanzasController::flujoCaja');

        Profiler::start('FinanzasController::flujoCaja query');
        $fecha_desde = request('fecha_desde', request('fecha_filtro', date('Y-m-d')));
        $fecha_hasta = request('fecha_hasta', request('fecha_filtro', date('Y-m-d')));
        $fecha_filtro = $fecha_hasta;

        // Dentro del mismo día, en el orden en que se registraron (sin el id, al recargar salían salteados).
        $movimientos = FlujoCaja::whereBetween('fecha', [$fecha_desde, $fecha_hasta])->where('oculto', false)->vigentes()->orderBy('fecha', 'desc')->orderBy('id')->get();
        Profiler::stop('FinanzasController::flujoCaja query');
        $ingresos = $movimientos->where('tipo', 'ingreso')->values();
        $movimientos = $movimientos->where('tipo', '!=', 'ingreso');
        $egresos_realizados = $movimientos->where('categoria_egreso', 'egreso_realizado');
        $otros_egresos = $movimientos->where('categoria_egreso', 'otros_egresos');
        $traslados = $movimientos->where('categoria_egreso', 'traslados');
        $egresos_divisas = $movimientos->where('categoria_egreso', 'egreso_divisas');
        $compras_divisas = \Illuminate\Support\Facades\Schema::hasTable('compra_divisas')
            ? \App\Models\CompraDivisa::whereBetween('fecha', [$fecha_desde, $fecha_hasta])->orderByDesc('fecha')->orderBy('id')->get()
            : collect();
        
        $cuentas = $this->getCuentas(); // Mantenemos para el dropdown si es necesario o usamos las nuevas
        Profiler::start('FinanzasController::flujoCaja cuentas');
        $cuentasBancarias = \App\Models\CuentaBancaria::where('mostrar_en_principal', true)->orderBy('orden')->get();
        Profiler::stop('FinanzasController::flujoCaja cuentas');
        Profiler::start('FinanzasController::flujoCaja resumen');
        $resumen = \App\Models\FinanzasResumen::firstOrCreate(
            ['fecha' => $fecha_filtro],
            [
                'tasa_bcv_usd' => $this->getTasaBcvDelDia(),
                'saldo_inicial' => 0,
                'queda_dia_anterior' => 0,
                'porcentaje_total_diferencial' => 0
            ]
        );
        if ($fecha_filtro === date('Y-m-d')) {
            $resumen = $this->syncSaldoInicialDisponibilidad($resumen);
        }
        Profiler::stop('FinanzasController::flujoCaja resumen');

        $total_salidas_bs = $egresos_realizados->sum('monto_bs') 
                          + $egresos_realizados->sum('comision') 
                          + $otros_egresos->sum('monto_bs') 
                          + $otros_egresos->sum('comision');
        
        $total_diferencial_cambiario = $egresos_realizados->sum('diferencial_cambiario') 
                                     + $otros_egresos->sum('diferencial_cambiario');
        
        $proveedores = collect(\App\Models\FinanzasCatalogo::beneficiarios(
            \Illuminate\Support\Facades\DB::table('inventario_v2.productos')
                        ->whereNotNull('proveedor')
                        ->where('proveedor', '!=', '')
                        ->distinct()
                        ->orderBy('proveedor')
                ->pluck('proveedor')
        ));
        $tiposGasto = \App\Models\FinanzasCatalogo::tiposGasto();

        $empleadosServicioTecnico = NominaEmpleado::query()
            ->with('cliente:id,nombre,cedula')
            ->where('estado', 'ACTIVO')
            ->where('es_servicio_tecnico', true)
            ->whereHas('cliente')
            ->get()
            ->sortBy(fn (NominaEmpleado $empleado) => $empleado->nombre(), SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $cuentas_por_pagar = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('cuentas_por_pagar')) {
            $cuentas_por_pagar = CuentaPorPagar::query()
                ->with(['pagos' => function ($query) {
                    $query->orderBy('fecha')->orderBy('id');
                }])
                ->orderByRaw("CASE WHEN estado = 'abierta' THEN 0 ELSE 1 END")
                ->orderByDesc('id')
                ->get();
        }
        
        Profiler::start('FinanzasController::flujoCaja Blade render');
        $result = view('finanzas.flujo_caja', compact(
            'movimientos', 
            'ingresos',
            'egresos_realizados', 
            'otros_egresos',
            'traslados',
            'egresos_divisas',
            'compras_divisas',
            'cuentas',
            'cuentasBancarias',
            'resumen',
            'total_salidas_bs',
            'total_diferencial_cambiario',
            'fecha_filtro',
            'fecha_desde',
            'fecha_hasta',
            'proveedores',
            'tiposGasto',
            'empleadosServicioTecnico',
            'cuentas_por_pagar'
        ) + ['soloAvances' => (bool) auth()->user()?->soloAvances()]);
        Profiler::stop('FinanzasController::flujoCaja Blade render');

        Profiler::stop('FinanzasController::flujoCaja');
        return $result;
    }

    public function fetchBcvApi(Request $request)
    {
        try {
            $apiUrl = env('BCV_API_URL', 'https://bcvapi.tech/api/v1/dolar');
            $apiKey = env('BCV_API_KEY');

            $headers = [];
            if ($apiKey) {
                $headers['Authorization'] = $apiKey;
            }

            $client = new \GuzzleHttp\Client(['timeout' => 5]);
            $response = $client->get($apiUrl, [
                'headers' => $headers
            ]);

            $data = json_decode($response->getBody(), true);
            
            // Suponiendo formato de BCVAPI.tech, ajustamos los campos. 
            // Buscamos algo parecido a "rate", "tasa", "value", etc.
            $rate = null;
            $updatedAt = date('Y-m-d H:i');

            if (isset($data['dolar'])) $rate = $data['dolar'];
            elseif (isset($data['rate'])) $rate = $data['rate'];
            elseif (isset($data['tasa'])) $rate = $data['tasa'];
            elseif (isset($data['promedio'])) $rate = $data['promedio'];
            elseif (isset($data['price'])) $rate = $data['price'];
            elseif (isset($data['dolar_oficial'])) $rate = $data['dolar_oficial'];
            else {
                // If the response is a direct number
                if (is_numeric($data)) $rate = $data;
                // Otherwise maybe it's in a nested object or "data" key
                elseif (isset($data['data']['rate'])) $rate = $data['data']['rate'];
            }

            if (isset($data['updated_at'])) $updatedAt = $data['updated_at'];
            elseif (isset($data['fecha'])) $updatedAt = $data['fecha'];

            if ($rate) {
                return response()->json([
                    'success' => true,
                    'tasa' => round((float)$rate, 2),
                    'fecha_actualizacion' => $updatedAt
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No se encontró la tasa en la respuesta de la API.',
                'debug' => $data
            ], 400);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Error consultando BCV API: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'API no disponible o error de conexión.'
            ]);
        }
    }

    public function storeEgreso(Request $request)
    {
        if ($request->user()?->soloAvances() && $request->input('categoria_egreso') !== 'otros_egresos') {
            return back()->withInput()->with('error', 'Solo puedes registrar «Otros egresos (avances y cambios)».');
        }
        try {
            // Normalizar campos numéricos: convertir "35.750,00" o "35750,00" → "35750.00"
            $numericFields = [
                'monto_usd', 'tasa_cambio', 'diferencial_cambiario', 'monto_bs', 'comision',
                'monto_total_gasto',
                'tt_recarga', 'tt_comision', 'tt_iva', 'tt_ret_islr', 'tt_ret_iva',
                'tt_ret_1x1000', 'tt_ret_resp_social', 'tt_ret_isae',
            ];
            $normalized = [];
            foreach ($numericFields as $field) {
                $val = $request->input($field);
                if (is_string($val) && $val !== '') {
                    if (strpos($val, '.') !== false && strpos($val, ',') !== false) {
                        $val = str_replace('.', '', $val);
                        $val = str_replace(',', '.', $val);
                    } else {
                        $val = str_replace(',', '.', $val);
                    }
                    $normalized[$field] = is_numeric($val) ? $val : null;
                }
            }
            if (!empty($normalized)) {
                $request->merge($normalized);
            }

            $data = $request->validate([
                'categoria_egreso' => 'required|in:egreso_realizado,otros_egresos,traslados,egreso_divisas,compra_divisas,ingresos',
                'banco_titular' => 'required|string',
                'banco_titular_receptor' => 'nullable|string',
                'referencia' => 'nullable|string|max:255',
                'monto_usd' => 'nullable|numeric',
                'tasa_cambio' => 'nullable|numeric',
                'diferencial_cambiario' => 'nullable|numeric',
                'monto_bs' => 'nullable|numeric',
                'comision' => 'nullable|numeric',
                'tipo_gasto' => 'nullable|string',
                'motivo' => 'nullable|string',
                'sede' => 'nullable|string',
                'beneficiario' => 'nullable|string',
                'nomina_empleado_id' => 'nullable|integer|exists:nomina_empleados,id',
                'placa_vehiculo' => 'nullable|string',
                'fecha' => 'required|date|before_or_equal:today',
                'desglose_cedula' => 'nullable|array',
                'desglose_monto' => 'nullable|array',
                'desglose_monto_usd' => 'nullable|array',
                'desglose_sede' => 'nullable|array',
                'desglose_tipo_gasto' => 'nullable|array',
                'gasto_fijo_id' => 'nullable|integer|exists:gastos_fijos,id',
                'monto_pagado_gf' => 'nullable|numeric|min:0',
                'es_todoticket' => 'nullable|boolean',
                'tt_recarga' => 'nullable|numeric',
                'tt_comision' => 'nullable|numeric',
                'tt_iva' => 'nullable|numeric',
                'tt_ret_islr' => 'nullable|numeric',
                'tt_ret_iva' => 'nullable|numeric',
                'tt_ret_1x1000' => 'nullable|numeric',
                'tt_ret_resp_social' => 'nullable|numeric',
                'tt_ret_isae' => 'nullable|numeric',
                'monto_total_gasto' => 'nullable|numeric|min:0',
                'es_cuenta_por_pagar' => 'nullable|boolean',
                'cuenta_por_pagar_id' => 'nullable|integer',
            ]);

            $empleadoServicioTecnico = null;
            if (($data['tipo_gasto'] ?? null) === '058 - SERVICIO TECNICO (GARANTIAS)') {
                $empleadoServicioTecnico = NominaEmpleado::query()
                    ->where('estado', 'ACTIVO')
                    ->where('es_servicio_tecnico', true)
                    ->with('cliente')
                    ->find($data['nomina_empleado_id'] ?? 0);

                if (! $empleadoServicioTecnico) {
                    return back()
                        ->withInput()
                        ->with('error', 'Selecciona el empleado de Servicio Técnico al que corresponde este egreso.');
                }

                $data['beneficiario'] = $empleadoServicioTecnico->nombre();
            }

            if ($data['categoria_egreso'] === 'traslados') {
                if (empty($data['banco_titular_receptor'])) {
                    return back()->with('error', 'El Banco Receptor es obligatorio para los traslados.');
                }
                if ($data['banco_titular'] === $data['banco_titular_receptor']) {
                    return back()->with('error', 'El Banco Emisor y el Banco Receptor no pueden ser exactamente iguales.');
                }
                if (empty($data['motivo'])) {
                    return back()->with('error', 'El Motivo es obligatorio para los traslados.');
                }
                if (empty($data['monto_bs']) || $data['monto_bs'] <= 0) {
                    return back()->with('error', 'El Monto debe ser mayor a cero.');
                }
            }

            if ($data['categoria_egreso'] === 'compra_divisas') {
                if (empty($data['monto_bs']) || $data['monto_bs'] <= 0) {
                    return back()->withInput()->with('error', 'El Monto BS de la compra de divisas debe ser mayor a cero.');
                }
            }

            $cuentaInfo = explode('|', $data['banco_titular']);
            $banco = $cuentaInfo[0] ?? null;
            $titular = $cuentaInfo[1] ?? null;
            $categoria_cuenta = $cuentaInfo[2] ?? null;

            if ($data['categoria_egreso'] === 'ingresos') {
                return $this->guardarIngreso($request, $data, $banco, $titular, $categoria_cuenta);
            }

            if ($data['categoria_egreso'] === 'compra_divisas') {
                $comprobanteUrl = null;
                if ($request->hasFile('comprobante')) {
                    $comprobanteUrl = $this->uploadComprobante($request->file('comprobante'), $data['referencia'] ?? null);
                }

                \App\Models\CompraDivisa::create([
                    'fecha' => $data['fecha'],
                    'banco' => $banco,
                    'titular' => $titular,
                    'categoria_cuenta' => $categoria_cuenta,
                    'referencia' => $data['referencia'] ?? null,
                    'concepto' => $data['motivo'] ?? 'COMPRA DE DIVISAS',
                    'motivo' => $data['motivo'] ?? 'COMPRA INTERVENCION ELECTRONIC',
                    'monto_bs' => $data['monto_bs'],
                    'monto_usd' => $data['monto_usd'] ?? null,
                    'tasa_cambio' => $data['tasa_cambio'] ?? null,
                    'es_conciliado' => false,
                    'comprobante_url' => $comprobanteUrl,
                    'comprobantes' => $comprobanteUrl ? [$comprobanteUrl] : null,
                ]);

                return redirect()
                    ->route('finanzas.flujo_caja', ['fecha_desde' => $data['fecha'], 'fecha_hasta' => $data['fecha']])
                    ->with('success', 'Compra de divisas registrada.');
            }

            $banco_receptor = null;
            $titular_receptor = null;
            
            if ($data['categoria_egreso'] === 'traslados') {
                if (!empty($data['banco_titular_receptor'])) {
                    $cuentaReceptorInfo = explode('|', $data['banco_titular_receptor']);
                    $banco_receptor = $cuentaReceptorInfo[0] ?? null;
                    $titular_receptor = $cuentaReceptorInfo[1] ?? null;
                }
            } else {
                if (!empty($data['beneficiario'])) {
                    $titular_receptor = $data['beneficiario'];
                }
            }

            $resumen = \App\Models\FinanzasResumen::where('fecha', date('Y-m-d'))->first();
            $tasa_bcv = $resumen ? ($resumen->tasa_bcv_usd ?: 1) : 1;
            
            $monto_usd = $data['monto_usd'] ?? 0;
            $monto_bs = $data['monto_bs'] ?? 0;
            $comision = $data['comision'] ?? 0;
            $esTodoticket = $request->boolean('es_todoticket');
            $detalleTodoticket = null;

            if ($esTodoticket) {
                $detalleTodoticket = TodoTicketPago::normalizarDetalle([
                    'recarga' => $data['tt_recarga'] ?? 0,
                    'comision' => $data['tt_comision'] ?? 0,
                    'iva' => $data['tt_iva'] ?? 0,
                    'ret_islr' => $data['tt_ret_islr'] ?? 0,
                    'ret_iva' => $data['tt_ret_iva'] ?? 0,
                    'ret_1x1000' => $data['tt_ret_1x1000'] ?? 0,
                    'ret_resp_social' => $data['tt_ret_resp_social'] ?? 0,
                    'ret_isae' => $data['tt_ret_isae'] ?? 0,
                ]);
                $monto_bs = $detalleTodoticket['total_real'];
                $comision = $detalleTodoticket['comision'];
                if (empty($data['tipo_gasto'])) {
                    $data['tipo_gasto'] = '100 - TODOTICKET';
                }
                if (empty($data['motivo'])) {
                    $data['motivo'] = 'Pago TodoTicket — Total Real Bs '.number_format($monto_bs, 2, ',', '.');
                }
                $tasaForm = (float) ($data['tasa_cambio'] ?? 0);
                $tasaUsd = $tasaForm > 0 ? $tasaForm : (float) $tasa_bcv;
                if ($tasaUsd > 0) {
                    $monto_usd = round($monto_bs / $tasaUsd, 2);
                    $data['tasa_cambio'] = $tasaUsd;
                }
                if ($monto_bs <= 0) {
                    return back()->with('error', 'El Total Real de TodoTicket debe ser mayor a cero. Completa recarga, comisión, IVA y retenciones.');
                }
            }
            
            $diferencial_cambiario = array_key_exists('diferencial_cambiario', $data) && $data['diferencial_cambiario'] !== null 
                                     ? $data['diferencial_cambiario'] 
                                     : null;
                                     
            $tasa_cambio = $data['tasa_cambio'] ?? null;
            $calc_usd = $monto_usd > 0 ? $monto_usd : ($tasa_cambio > 0 ? round($monto_bs / $tasa_cambio, 2) : null);

            if ($empleadoServicioTecnico && (! $calc_usd || $calc_usd <= 0)) {
                return back()
                    ->withInput()
                    ->with('error', 'El egreso 058 debe tener un monto USD o un monto Bs con tasa de cambio para descontarlo de la comisión.');
            }

            if ($esTodoticket) {
                $diferencial_cambiario = 0;
            } elseif ($diferencial_cambiario === null && $data['categoria_egreso'] !== 'traslados' && $data['categoria_egreso'] !== 'egreso_divisas' && $tasa_bcv > 0) {
                $diferencial_cambiario = (($calc_usd * $tasa_bcv) - $monto_bs) / $tasa_bcv;
            }
            $diferencial_cambiario = $diferencial_cambiario ?: 0;

            $comprobante_url = null;
            $comprobantes_arr = [];
            if ($request->hasFile('comprobantes')) {
                foreach ($request->file('comprobantes') as $file) {
                    $url = $this->uploadComprobante($file, $data['referencia'] ?? null);
                    if ($url) {
                        $comprobantes_arr[] = $url;
                        if (!$comprobante_url) $comprobante_url = $url; // first one as legacy field
                    }
                }
            } elseif ($request->hasFile('comprobante')) {
                // Backwards compat: single file
                $comprobante_url = $this->uploadComprobante($request->file('comprobante'), $data['referencia'] ?? null);
                if ($comprobante_url) $comprobantes_arr[] = $comprobante_url;
            }

            $desglose = null;
            if (!empty($data['desglose_monto'])) {
                $desglose = [];
                foreach ($data['desglose_monto'] as $index => $monto_val) {
                    $cedula = $data['desglose_cedula'][$index] ?? '';
                    $monto_desglose = $monto_val ?? 0;
                    if ($cedula || $monto_desglose) {
                        $desglose[] = [
                            'cedula' => $cedula,
                            'sede' => $data['desglose_sede'][$index] ?? '',
                            'tipo_gasto' => $data['desglose_tipo_gasto'][$index] ?? '',
                            'monto' => (float)$monto_desglose,
                            'monto_usd' => (float)($data['desglose_monto_usd'][$index] ?? 0),
                        ];
                    }
                }
                if (empty($desglose)) {
                    $desglose = null;
                }
            }

            $cuentaPorPagarId = null;
            if (\Illuminate\Support\Facades\Schema::hasTable('cuentas_por_pagar')) {
                [$cuentaPorPagarId, $errorCuenta] = $this->prepararCuentaPorPagar(
                    $request,
                    $data,
                    (float) ($calc_usd ?? 0),
                    (float) ($monto_bs ?? 0),
                    $titular_receptor
                );
                if ($errorCuenta) {
                    return redirect()->back()->withInput()->with('error', $errorCuenta);
                }
            }

            \Illuminate\Support\Facades\DB::transaction(function () use (
                $data, $banco, $titular, $categoria_cuenta, $banco_receptor, $titular_receptor,
                $calc_usd, $tasa_cambio, $diferencial_cambiario, $monto_bs, $comision,
                $empleadoServicioTecnico, $esTodoticket, $detalleTodoticket,
                $comprobante_url, $comprobantes_arr, $desglose, &$cuentaPorPagarId
            ) {
                if ($cuentaPorPagarId === 0) {
                    $nueva = $this->crearCuentaPorPagar($data, (float) ($calc_usd ?? 0), (float) ($monto_bs ?? 0), $titular_receptor);
                    $cuentaPorPagarId = $nueva->id;
                }

                $payload = [
                    'fecha'                 => $data['fecha'],
                    'tipo'                  => 'egreso',
                    'categoria_egreso'      => $data['categoria_egreso'],
                    'banco'                 => $banco,
                    'titular'               => $titular,
                    'categoria_cuenta'      => $categoria_cuenta,
                    'banco_receptor'        => $banco_receptor,
                    'titular_receptor'      => $titular_receptor,
                    'referencia'            => $data['referencia'] ?? null,
                    'monto_usd'             => $calc_usd ?? 0,
                    'tasa_cambio'           => $tasa_cambio ?? 0,
                    'diferencial_cambiario' => $diferencial_cambiario ?? 0,
                    'monto_bs'              => $monto_bs ?? 0,
                    'comision'              => $comision,
                    'tipo_gasto'            => $data['tipo_gasto'] ?? null,
                    'nomina_empleado_id'     => $empleadoServicioTecnico?->id,
                    'es_todoticket'         => $esTodoticket,
                    'detalle_todoticket'    => $detalleTodoticket,
                    'motivo'                => $data['motivo'] ?? null,
                    'sede'                  => $this->sedeEgresoServicioTecnico($data, $empleadoServicioTecnico),
                    'placa_vehiculo'        => $data['placa_vehiculo'] ?? null,
                    'comprobante_url'       => $comprobante_url,
                    'comprobantes'          => !empty($comprobantes_arr) ? $comprobantes_arr : null,
                    'desglose'              => $desglose,
                ];

                if ($cuentaPorPagarId && \Illuminate\Support\Facades\Schema::hasColumn('flujo_cajas', 'cuenta_por_pagar_id')) {
                    $payload['cuenta_por_pagar_id'] = $cuentaPorPagarId;
                }

                FlujoCaja::create($payload);

                if ($cuentaPorPagarId) {
                    CuentaPorPagar::query()->find($cuentaPorPagarId)?->recalcular();
                }

                if (!empty($data['gasto_fijo_id'])) {
                    $gastoFijo = \App\Models\GastoFijo::query()
                        ->with(['pagos' => function ($q) {
                            $q->where('anio', (int) date('Y'));
                        }])
                        ->find((int) $data['gasto_fijo_id']);
                    $mesIdx = (int) date('n') - 1;
                    if ($gastoFijo) {
                        $periodos = $this->gastosFijosPendientes->periodosPendientes(
                            (string) $gastoFijo->fecha,
                            (float) $gastoFijo->costo,
                            $gastoFijo->pagos
                        );
                        if ($periodos !== []) {
                            $mesIdx = (int) $periodos[0]['mes_idx'];
                        }
                    }
                    $montoPagado = !empty($data['monto_pagado_gf']) ? (float) $data['monto_pagado_gf'] : ($calc_usd ?? 0);
                    GastoFijoPago::updateOrCreate(
                        [
                            'gasto_fijo_id' => (int) $data['gasto_fijo_id'],
                            'mes_idx'       => $mesIdx,
                            'anio'          => (int) date('Y'),
                        ],
                        [
                            'monto'     => $montoPagado,
                            'pagado'    => true,
                            'pagado_at' => now(),
                        ]
                    );
                }
            });

            $this->syncTotalesSalidas($data['fecha']);

            if ((int) ($data['cuenta_por_pagar_id'] ?? 0) > 0) {
                return redirect()
                    ->route('finanzas.cuentas_por_pagar')
                    ->with('success', 'Pago de cuenta por pagar registrado correctamente.');
            }

            return redirect()->back()->with('success', 'Egreso registrado correctamente.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error registrando egreso: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error del sistema: ' . $e->getMessage());
        }
    }

    /**
     * Dinero que entra a una cuenta (cobro, reintegro, préstamo recibido…). Suma al saldo del sistema
     * en la conciliación y se empareja con el abono del extracto; no cuenta como salida del día.
     */
    private function guardarIngreso(Request $request, array $data, ?string $banco, ?string $titular, ?string $categoriaCuenta)
    {
        $montoBs = (float) ($data['monto_bs'] ?? 0);
        $montoUsd = (float) ($data['monto_usd'] ?? 0);
        $tasa = (float) ($data['tasa_cambio'] ?? 0);
        if ($montoBs <= 0 && $montoUsd <= 0) {
            return back()->withInput()->with('error', 'El monto del ingreso debe ser mayor a cero.');
        }
        if ($montoUsd <= 0 && $tasa > 0) {
            $montoUsd = round($montoBs / $tasa, 2);
        }
        if (empty($data['motivo'])) {
            return back()->withInput()->with('error', 'Escribe el motivo del ingreso.');
        }

        $comprobantes = [];
        foreach ((array) $request->file('comprobantes', []) as $file) {
            if ($url = $this->uploadComprobante($file, $data['referencia'] ?? null)) {
                $comprobantes[] = $url;
            }
        }

        FlujoCaja::create([
            'fecha' => $data['fecha'],
            'tipo' => 'ingreso',
            'categoria_egreso' => 'ingresos',
            'banco' => $banco,
            'titular' => $titular,
            'categoria_cuenta' => $categoriaCuenta,
            'titular_receptor' => $data['beneficiario'] ?? null,
            'referencia' => $data['referencia'] ?? null,
            'monto_usd' => $montoUsd,
            'tasa_cambio' => $tasa,
            'diferencial_cambiario' => 0,
            'monto_bs' => $montoBs,
            'comision' => 0,
            'motivo' => $data['motivo'],
            'sede' => $data['sede'] ?? null,
            'comprobante_url' => $comprobantes[0] ?? null,
            'comprobantes' => $comprobantes ?: null,
        ]);

        return redirect()->back()->with('success', 'Ingreso registrado correctamente.');
    }

    /**
     * @return array{0: int|null, 1: string|null}  id 0 = crear en transacción, null = no aplica
     */
    private function prepararCuentaPorPagar(Request $request, array $data, float $calcUsd, float $montoBs, ?string $beneficiario): array
    {
        $cuentaId = (int) ($data['cuenta_por_pagar_id'] ?? 0);
        $marcar = $request->boolean('es_cuenta_por_pagar');

        if ($cuentaId <= 0 && ! $marcar) {
            return [null, null];
        }

        if ($cuentaId > 0) {
            $cuenta = CuentaPorPagar::query()->find($cuentaId);
            if (! $cuenta) {
                return [null, 'La cuenta por pagar no existe.'];
            }
            if (! $cuenta->estaAbierta()) {
                return [null, 'Esa cuenta por pagar ya está saldada.'];
            }
            $pago = $this->montoPagoContraCuenta($cuenta, $calcUsd, $montoBs);
            if ($pago === null) {
                return [null, 'Indica el monto del pago en la misma moneda de la cuenta ('.$cuenta->moneda.').'];
            }
            if ($pago <= 0) {
                return [null, 'El pago debe ser mayor a cero.'];
            }
            if ($pago - (float) $cuenta->saldo > 0.01) {
                return [null, 'El pago ('.number_format($pago, 2, ',', '.').') supera el saldo pendiente ('.number_format((float) $cuenta->saldo, 2, ',', '.').').'];
            }

            return [$cuenta->id, null];
        }

        $moneda = $calcUsd > 0 ? 'USD' : 'BS';
        $pago = $moneda === 'USD' ? $calcUsd : $montoBs;
        if ($pago <= 0) {
            return [null, 'Para abrir una cuenta por pagar el abono de hoy debe ser mayor a cero.'];
        }

        $total = (float) ($data['monto_total_gasto'] ?? 0);
        if ($total <= 0) {
            return [null, 'Indica el monto total del gasto para registrar la cuenta por pagar.'];
        }
        if ($total + 0.01 < $pago) {
            return [null, 'El monto total del gasto no puede ser menor al pago de hoy.'];
        }

        return [0, null];
    }

    private function crearCuentaPorPagar(array $data, float $calcUsd, float $montoBs, ?string $beneficiario): CuentaPorPagar
    {
        $moneda = $calcUsd > 0 ? 'USD' : 'BS';
        $total = (float) ($data['monto_total_gasto'] ?? 0);

        return CuentaPorPagar::query()->create([
            'fecha' => $data['fecha'],
            'beneficiario' => $beneficiario ?: ($data['beneficiario'] ?? null),
            'tipo_gasto' => $data['tipo_gasto'] ?? null,
            'motivo' => $data['motivo'] ?? null,
            'sede' => $data['sede'] ?? null,
            'moneda' => $moneda,
            'monto_total' => $total,
            'monto_pagado' => 0,
            'saldo' => $total,
            'estado' => 'abierta',
            'created_by' => auth()->id(),
        ]);
    }

    private function montoPagoContraCuenta(CuentaPorPagar $cuenta, float $calcUsd, float $montoBs): ?float
    {
        if (strtoupper((string) $cuenta->moneda) === 'BS') {
            return $montoBs > 0 ? $montoBs : null;
        }

        return $calcUsd > 0 ? $calcUsd : null;
    }

    private function uploadComprobante($file, ?string $referencia): ?string
    {
        $ref = !empty($referencia) ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $referencia) : uniqid();
        $fileName = 'comprobante_' . $ref . '_' . date('Ymd_His') . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $fileContent = file_get_contents($file->getRealPath());

        $supabaseUrl = env('SUPABASE_URL');
        $supabaseKey = env('SUPABASE_KEY');

        if ($supabaseUrl && $supabaseKey) {
            $supabaseUrl = rtrim($supabaseUrl, '/');
            $uploadUrl = "{$supabaseUrl}/storage/v1/object/comprobantes/{$fileName}";

            $response = \Illuminate\Support\Facades\Http::withoutVerifying()->withHeaders([
                'Authorization' => "Bearer {$supabaseKey}",
                'Content-Type' => $file->getClientMimeType(),
            ])->withBody($fileContent, $file->getClientMimeType())->post($uploadUrl);

            if ($response->successful()) {
                return "{$supabaseUrl}/storage/v1/object/public/comprobantes/{$fileName}";
            } else {
                throw new \Exception("Error en Supabase: " . $response->body());
            }
        }
        return null;
    }

    private function sedeEgresoServicioTecnico(array $data, ?NominaEmpleado $empleado): ?string
    {
        $sede = trim((string) ($data['sede'] ?? ''));
        if ($sede !== '') {
            return strtoupper($sede);
        }

        $sedeEmpleado = trim((string) ($empleado?->sede ?? ''));

        return $sedeEmpleado !== '' ? strtoupper($sedeEmpleado) : null;
    }

    public function updateEgreso(Request $request, $id)
    {
        try {
            $egreso = FlujoCaja::findOrFail($id);
            if ($request->user()?->soloAvances() && $egreso->categoria_egreso !== 'otros_egresos') {
                abort(403, 'Solo puedes editar «Otros egresos (avances y cambios)».');
            }

        // Normalizar campos numéricos: convertir "35.750,00" o "35750,00" → "35750.00"
        $numericFields = ['monto_usd', 'tasa_cambio', 'diferencial_cambiario', 'monto_bs', 'comision'];
        $normalized = [];
        foreach ($numericFields as $field) {
            $val = $request->input($field);
            if (is_string($val) && $val !== '') {
                if (strpos($val, '.') !== false && strpos($val, ',') !== false) {
                    $val = str_replace('.', '', $val);
                    $val = str_replace(',', '.', $val);
                } else {
                    $val = str_replace(',', '.', $val);
                }
                $normalized[$field] = is_numeric($val) ? $val : null;
            }
        }
        if (!empty($normalized)) {
            $request->merge($normalized);
        }

        $data = $request->validate([
            'banco_titular'          => 'required|string',
            'banco_titular_receptor' => 'nullable|string',
            'referencia'             => 'nullable|string|max:255',
            'monto_usd'              => 'nullable|numeric',
            'tasa_cambio'            => 'nullable|numeric',
            'monto_bs'               => 'nullable|numeric',
            'comision'               => 'nullable|numeric',
            'tipo_gasto'             => 'nullable|string',
            'nomina_empleado_id'      => 'nullable|integer|exists:nomina_empleados,id',
            'motivo'                 => 'nullable|string',
            'beneficiario'           => 'nullable|string|max:255',
            'sede'                   => 'nullable|string',
            'placa_vehiculo'         => 'nullable|string',
            'fecha'                  => 'required|date|before_or_equal:today',
            'desglose_beneficiario'  => 'nullable|array',
            'desglose_cedula'        => 'nullable|array',
            'desglose_monto'         => 'nullable|array',
            'desglose_monto_usd'     => 'nullable|array',
            'comprobantes_eliminar'  => 'nullable|array',
            'diferencial_cambiario'  => 'nullable|numeric',
        ]);

        $cuentaInfo = explode('|', $data['banco_titular']);
        $banco = $cuentaInfo[0] ?? null;
        $titular = $cuentaInfo[1] ?? null;
        $categoria_cuenta = $cuentaInfo[2] ?? null;

        $banco_receptor = null;
        $titular_receptor = null;
        $empleadoServicioTecnico = null;
        if (!empty($data['banco_titular_receptor'])) {
            $cuentaReceptorInfo = explode('|', $data['banco_titular_receptor']);
            $banco_receptor = $cuentaReceptorInfo[0] ?? null;
            $titular_receptor = $cuentaReceptorInfo[1] ?? null;
        }
        if (($data['tipo_gasto'] ?? null) === '058 - SERVICIO TECNICO (GARANTIAS)') {
            $empleadoServicioTecnico = NominaEmpleado::query()
                ->where('estado', 'ACTIVO')
                ->where('es_servicio_tecnico', true)
                ->with('cliente')
                ->find($data['nomina_empleado_id'] ?? $egreso->nomina_empleado_id);
            if (! $empleadoServicioTecnico) {
                return back()->withInput()->with('error', 'Selecciona el empleado de Servicio Técnico al que corresponde este egreso.');
            }
            $titular_receptor = $empleadoServicioTecnico->nombre();
        } elseif ($egreso->categoria_egreso !== 'traslados') {
            $banco_receptor = null;
            $titular_receptor = trim((string) ($data['beneficiario'] ?? '')) ?: null;
        }

        $resumen = \App\Models\FinanzasResumen::where('fecha', $data['fecha'])->first();
        $tasa_bcv = $resumen ? ($resumen->tasa_bcv_usd ?: 1) : 1;
        $monto_usd = $data['monto_usd'] ?: 0;
        $monto_bs  = $data['monto_bs'] ?: 0;
        
        $diferencial_cambiario = array_key_exists('diferencial_cambiario', $data) && $data['diferencial_cambiario'] !== null 
                                 ? $data['diferencial_cambiario'] 
                                 : $egreso->diferencial_cambiario;

        $calc_usd = $monto_usd > 0 ? $monto_usd : (isset($data['tasa_cambio']) && $data['tasa_cambio'] > 0 ? round($monto_bs / $data['tasa_cambio'], 2) : null);
        if ($empleadoServicioTecnico && (! $calc_usd || $calc_usd <= 0)) {
            return back()
                ->withInput()
                ->with('error', 'El egreso 058 debe tener un monto USD o un monto Bs con tasa de cambio para descontarlo de la comisión.');
        }

        // Manage comprobantes array: start from existing
        $comprobantes = $egreso->comprobantes ?? [];

        // Migrate old single comprobante_url if not yet in array
        if ($egreso->comprobante_url && !in_array($egreso->comprobante_url, $comprobantes)) {
            array_unshift($comprobantes, $egreso->comprobante_url);
        }

        // Remove comprobantes the user flagged for deletion
        if (!empty($data['comprobantes_eliminar'])) {
            $comprobantes = array_values(array_filter($comprobantes, fn($url) => !in_array($url, $data['comprobantes_eliminar'])));
        }

        // Upload new comprobantes
        if ($request->hasFile('comprobantes_nuevos')) {
            foreach ($request->file('comprobantes_nuevos') as $file) {
                $url = $this->uploadComprobante($file, $data['referencia'] ?? $egreso->referencia);
                if ($url) {
                    $comprobantes[] = $url;
                }
            }
        }

        // Desglose
        $desglose = null;
        if (!empty($data['desglose_beneficiario'])) {
            $desglose = [];
            foreach ($data['desglose_beneficiario'] as $index => $beneficiario) {
                $cedula = $data['desglose_cedula'][$index] ?? '';
                $monto_desglose = $data['desglose_monto'][$index] ?? 0;
                if ($beneficiario || $cedula || $monto_desglose) {
                    $desglose[] = [
                        'beneficiario' => $beneficiario,
                        'cedula'       => $cedula,
                        'monto'        => (float) $monto_desglose,
                        'monto_usd'    => (float) ($data['desglose_monto_usd'][$index] ?? 0),
                    ];
                }
            }
            if (empty($desglose)) $desglose = null;
        }

        $old_fecha = $egreso->fecha;

        $egreso->update([
            'fecha'                 => $data['fecha'],
            'banco'                 => $banco,
            'titular'               => $titular,
            'categoria_cuenta'      => $categoria_cuenta,
            'banco_receptor'        => $banco_receptor,
            'titular_receptor'      => $titular_receptor,
            'referencia'            => $data['referencia'] ?? null,
            'monto_usd'             => $calc_usd ?? 0,
            'tasa_cambio'           => $data['tasa_cambio'] ?? 0,
            'diferencial_cambiario' => $diferencial_cambiario ?? 0,
            'monto_bs'              => $data['monto_bs'] ?? 0,
            'comision'              => $data['comision'] ?? 0,
            'tipo_gasto'            => $data['tipo_gasto'] ?? null,
            'nomina_empleado_id'     => $empleadoServicioTecnico?->id,
            'motivo'                => $data['motivo'] ?? null,
            'sede'                  => $this->sedeEgresoServicioTecnico($data, $empleadoServicioTecnico),
            'placa_vehiculo'        => $data['placa_vehiculo'] ?? null,
            'comprobante_url'       => $comprobantes[0] ?? $egreso->comprobante_url,
            'comprobantes'          => empty($comprobantes) ? null : array_values($comprobantes),
            'desglose'              => $desglose,
        ]);

        if (!empty($egreso->cuenta_por_pagar_id)) {
            CuentaPorPagar::query()->find($egreso->cuenta_por_pagar_id)?->recalcular();
        }

        $this->syncTotalesSalidas($data['fecha']);
        
        if ($old_fecha != $data['fecha']) {
            $this->syncTotalesSalidas($old_fecha);
        }

        return redirect()->back()->with('success', 'Egreso actualizado correctamente.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error actualizando egreso: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al guardar (Es posible que la imagen sea muy pesada o el servidor haya fallado): ' . $e->getMessage());
        }
    }

    public function destroyEgreso($id)
    {
        $categorias = ['egreso_realizado', 'otros_egresos', 'traslados', 'egreso_divisas'];
        if (auth()->user()?->soloAvances()) {
            abort_unless(FlujoCaja::query()->whereKey($id)->where('categoria_egreso', 'otros_egresos')->exists(), 403, 'Solo puedes eliminar «Otros egresos (avances y cambios)».');
        }

        $egreso = FlujoCaja::query()
            ->where(function ($query) use ($categorias) {
                $query->where('tipo', 'egreso')
                    ->where(function ($cat) use ($categorias) {
                        $cat->whereIn('categoria_egreso', $categorias)->orWhereNull('categoria_egreso');
                    })
                    ->orWhere(fn ($ing) => $ing->where('tipo', 'ingreso')->where('categoria_egreso', 'ingresos'));
            })
            ->findOrFail($id);
        $fecha = $egreso->fecha;
        $cuentaPorPagarId = $egreso->cuenta_por_pagar_id ?? null;

        \Illuminate\Support\Facades\DB::transaction(function () use ($egreso) {
            if (\Illuminate\Support\Facades\Schema::hasTable('conciliacion_lineas')) {
                \App\Models\ConciliacionLinea::query()
                    ->where('flujo_caja_id', $egreso->id)
                    ->update([
                        'flujo_caja_id' => null,
                        'estado' => 'pendiente',
                    ]);
            }

            $egreso->delete();
        });

        if ($cuentaPorPagarId) {
            CuentaPorPagar::query()->find($cuentaPorPagarId)?->recalcular();
        }

        $this->syncTotalesSalidas($fecha);

        return redirect()->back()->with('success', 'Movimiento eliminado correctamente.');
    }

    public function destroyCompraDivisa($id)
    {
        $compra = \App\Models\CompraDivisa::findOrFail($id);

        \Illuminate\Support\Facades\DB::transaction(function () use ($compra) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('conciliacion_lineas', 'compra_divisa_id')) {
                \App\Models\ConciliacionLinea::query()
                    ->where('compra_divisa_id', $compra->id)
                    ->update([
                        'compra_divisa_id' => null,
                        'estado' => 'pendiente',
                    ]);
            }
            $compra->delete();
        });

        return redirect()->back()->with('success', 'Compra de divisas eliminada.');
    }

    public function storeEgresosBulk(Request $request)
    {
        if ($request->has('egresos') && is_array($request->input('egresos'))) {
            $egresos = $request->input('egresos');
            $numericFields = ['monto_usd', 'tasa_cambio', 'diferencial_cambiario', 'monto_bs', 'comision'];
            foreach ($egresos as &$egreso) {
                foreach ($numericFields as $field) {
                    if (isset($egreso[$field]) && is_string($egreso[$field]) && $egreso[$field] !== '') {
                        $val = $egreso[$field];
                        if (strpos($val, '.') !== false && strpos($val, ',') !== false) {
                            $val = str_replace('.', '', $val);
                            $val = str_replace(',', '.', $val);
                        } else {
                            $val = str_replace(',', '.', $val);
                        }
                        $egreso[$field] = is_numeric($val) ? $val : null;
                    }
                }
            }
            $request->merge(['egresos' => $egresos]);
        }

        $data = $request->validate([
            'egresos' => 'required|array',
            'egresos.*.categoria_egreso' => 'required|in:egreso_realizado,otros_egresos',
            'egresos.*.banco_titular' => 'required|string',
            'egresos.*.referencia' => 'nullable|string|max:255',
            'egresos.*.monto_usd' => 'nullable|numeric',
            'egresos.*.tasa_cambio' => 'nullable|numeric',
            'egresos.*.diferencial_cambiario' => 'nullable|numeric',
            'egresos.*.comision' => 'nullable|numeric',
            'egresos.*.monto_bs' => 'nullable|numeric',
            'egresos.*.tipo_gasto' => 'nullable|string',
            'egresos.*.motivo' => 'nullable|string',
            'egresos.*.fecha' => 'required|date|before_or_equal:today'
        ]);

        $resumen = \App\Models\FinanzasResumen::where('fecha', date('Y-m-d'))->first();
        $tasa_bcv = $resumen ? ($resumen->tasa_bcv_usd ?: 1) : 1;

        foreach ($data['egresos'] as $egresoData) {
            $cuentaInfo = explode('|', $egresoData['banco_titular']);
            $banco = $cuentaInfo[0] ?? null;
            $titular = $cuentaInfo[1] ?? null;
            $categoria_cuenta = $cuentaInfo[2] ?? null;
            
            $monto_usd = isset($egresoData['monto_usd']) && $egresoData['monto_usd'] !== null ? $egresoData['monto_usd'] : 0;
            $monto_bs = isset($egresoData['monto_bs']) && $egresoData['monto_bs'] !== null ? $egresoData['monto_bs'] : 0;
            
            $diferencial_cambiario = isset($egresoData['diferencial_cambiario']) && $egresoData['diferencial_cambiario'] !== null 
                                     ? $egresoData['diferencial_cambiario'] 
                                     : null;
            
            $calc_usd = $monto_usd > 0 ? $monto_usd : (isset($egresoData['tasa_cambio']) && $egresoData['tasa_cambio'] > 0 ? round($monto_bs / $egresoData['tasa_cambio'], 2) : null);

            if ($diferencial_cambiario === null && $tasa_bcv > 0) {
                $diferencial_cambiario = (($calc_usd * $tasa_bcv) - $monto_bs) / $tasa_bcv;
            }
            $diferencial_cambiario = $diferencial_cambiario ?: 0;

            \App\Models\FlujoCaja::create([
                'fecha' => $egresoData['fecha'],
                'tipo' => 'egreso',
                'categoria_egreso' => $egresoData['categoria_egreso'],
                'banco' => $banco,
                'titular' => $titular,
                'categoria_cuenta' => $categoria_cuenta,
                'referencia' => $egresoData['referencia'] ?? null,
                'monto_usd' => $calc_usd,
                'tasa_cambio' => $egresoData['tasa_cambio'] ?? null,
                'diferencial_cambiario' => $diferencial_cambiario,
                'monto_bs' => $egresoData['monto_bs'] ?? 0,
                'comision' => $egresoData['comision'] ?? 0,
                'tipo_gasto' => $egresoData['tipo_gasto'] ?? null,
                'motivo' => $egresoData['motivo'] ?? null,
                'egreso_divisas' => $egresoData['egreso_divisas'] ?? false,
            ]);
        }

        $fechas_afectadas = collect($data['egresos'])->pluck('fecha')->unique();
        foreach ($fechas_afectadas as $f) {
            $this->syncTotalesSalidas($f);
        }

        return redirect()->back()->with('success', count($data['egresos']) . ' egresos masivos registrados correctamente.');
    }

    public function conciliaciones(Request $request) {
        // Cuentas como BNC · L.S. CASHEA traen ~25.000 movimientos al mes.
        @ini_set('memory_limit', '512M');
        $filterKeys = ['fecha_desde', 'fecha_hasta', 'banco_filtro', 'titular_filtro'];

        $vieneDelFiltro = $request->hasAny($filterKeys);
        if ($vieneDelFiltro) {
            $filtrosConciliacion = [
                'fecha_desde' => $request->input('fecha_desde') ?: null,
                'fecha_hasta' => $request->input('fecha_hasta') ?: null,
                'banco_filtro' => $request->input('banco_filtro') ?: null,
                'titular_filtro' => $request->input('titular_filtro') ?: null,
            ];
            $request->session()->put('conciliaciones.filtros', $filtrosConciliacion);
        } else {
            $filtrosConciliacion = $request->session()->get('conciliaciones.filtros', [
                'fecha_desde' => null,
                'fecha_hasta' => null,
                'banco_filtro' => null,
                'titular_filtro' => null,
            ]);
        }

        $banco_filtro = $filtrosConciliacion['banco_filtro'] ?? null;
        $titular_filtro = $filtrosConciliacion['titular_filtro'] ?? null;

        [$cuentasBancarias, $bancos, $titularesPorBanco] = $this->cuentasConciliacion();

        // 2. Líneas bancarias cargadas
        $fecha_desde = ! empty($filtrosConciliacion['fecha_desde'])
            ? \Carbon\Carbon::parse($filtrosConciliacion['fecha_desde'])->format('Y-m-d')
            : null;
        $fecha_hasta = ! empty($filtrosConciliacion['fecha_hasta'])
            ? \Carbon\Carbon::parse($filtrosConciliacion['fecha_hasta'])->format('Y-m-d')
            : null;

        if (! $vieneDelFiltro || ! $fecha_desde || ! $fecha_hasta || ! $banco_filtro) {
            return view('finanzas.conciliaciones', [
                'lineas' => collect(),
                'bancos' => $bancos,
                'cuentasBancarias' => $cuentasBancarias,
                'egresos_ayer' => collect(),
                'bancosActivos' => collect(),
                'data_por_banco' => [],
                'titularesPorBanco' => $titularesPorBanco,
                'filtrosConciliacion' => $filtrosConciliacion,
                'fecha_desde' => $fecha_desde,
                'fecha_hasta' => $fecha_hasta,
                'cierresMarcados' => collect(),
                'requiereFiltro' => true,
                'importacionesRecientes' => $this->importacionesRecientes(),
            ]);
        }

        // Reparaciones de vínculos antes de elegir qué copia de cada movimiento se muestra.
        $matcherReparar = app(\App\Services\BankReconciliationMatcher::class);
        $this->desvincularComisionEmparejadaConEgreso($matcherReparar, $fecha_desde, $fecha_hasta);
        $this->reasignarEgresoALaReferenciaExacta($matcherReparar, $fecha_desde, $fecha_hasta);

        $idsAbiertos = $this->idsExtractoVisibles($fecha_desde, $fecha_hasta, $banco_filtro, $titular_filtro);

        $relacionesLinea = [
            'flujoCaja:id,motivo,tipo_gasto,categoria_egreso,banco,titular,banco_receptor,titular_receptor,monto_bs,monto_usd,fecha,referencia',
            'compraDivisa:id,motivo,concepto,banco,titular,monto_bs,monto_usd,fecha,referencia',
            'tesoreriaIngreso:id,tipo,banco,titular,monto,fecha',
        ];
        $cargarLineas = function () use ($idsAbiertos, $relacionesLinea) {
            if ($idsAbiertos->isEmpty()) {
                return collect();
            }

            $lineas = collect();
            foreach ($idsAbiertos->chunk(1000) as $lote) {
                $lineas = $lineas->concat(
                    \App\Models\ConciliacionLinea::query()
                        ->with($relacionesLinea)
                        ->whereIn('id', $lote->all())
                        ->get()
                );
            }

            return $lineas->sortBy('fecha')->values();
        };
        $lineas = $cargarLineas();

        // 3. Motor de emparejamiento automático (acotado para no tumbar la página)
        $conciliadasAhora = app(\App\Services\Conciliacion\ConciliacionAutoMatcher::class)
            ->conciliar($lineas, $fecha_desde, $fecha_hasta, $banco_filtro, $titular_filtro, 500);
        if ($conciliadasAhora > 0) {
                $lineas = $cargarLineas();
        }

        // Repara traslados ya vinculados por salida que quedaron en tránsito
        // (antes se exigían los dos lados para marcar es_conciliado).
        $matcherRepair = app(\App\Services\BankReconciliationMatcher::class);
        $repararDesde = $fecha_desde ?: now()->subDays(14)->format('Y-m-d');
        $repararHasta = $fecha_hasta ?: now()->format('Y-m-d');
        $this->desvincularPagomovilMalEmparejadoConLote($matcherRepair, $repararDesde, $repararHasta);
        $this->marcarTrasladosConSalidaConciliados($matcherRepair, $repararDesde, $repararHasta);
        $this->reasignarGastosVaciosAlEgresoReal($matcherRepair, $repararDesde, $repararHasta);
        app(\App\Services\Conciliacion\ComisionPagoMovilBanesco::class)->marcar($repararDesde, $repararHasta);

        // 4. Clasificar comisiones bancarias y compras de divisas del extracto
        $classifier = app(\App\Services\BankMovementClassifier::class);

        // 5. Egresos del sistema (en tránsito)
        $egresos_query = \App\Models\FlujoCaja::where('tipo', 'egreso')
            ->where('es_conciliado', false)
            ->where('fecha', '>=', $fecha_desde)
            ->where('fecha', '<=', $fecha_hasta);
        if (\Illuminate\Support\Facades\Schema::hasColumn('flujo_cajas', 'anulado')) {
            $egresos_query->where('anulado', false);
        }

        $this->filtrarPorBanco($egresos_query, $banco_filtro);
        $this->filtrarPorTitular($egresos_query, $titular_filtro);
        $egresos_ayer = $egresos_query->orderBy('id')->get();

        // Egresos ya conciliados (incl. manuales) para listarlos en Consolidados
        // cuando no hay línea del extracto vinculada.
        $egresos_conciliados_query = \App\Models\FlujoCaja::where('tipo', 'egreso')
            ->where('es_conciliado', true)
            ->where('fecha', '>=', $fecha_desde)
            ->where('fecha', '<=', $fecha_hasta);
        if (\Illuminate\Support\Facades\Schema::hasColumn('flujo_cajas', 'anulado')) {
            $egresos_conciliados_query->where('anulado', false);
        }
        $this->filtrarPorBanco($egresos_conciliados_query, $banco_filtro);
        $this->filtrarPorTitular($egresos_conciliados_query, $titular_filtro);
        $egresos_conciliados_sistema = $egresos_conciliados_query->orderBy('id')->get();
        $duplicadosTransito = $this->duplicadosEnTransito($egresos_ayer, $fecha_desde, $fecha_hasta, $banco_filtro, $titular_filtro);

        // Movimientos registrados en el sistema durante el período del extracto.
        // El archivo bancario solo se usa para conciliar.
        $periodo_desde = $fecha_desde;
        $periodo_hasta = $fecha_hasta;
        $movimientos_sistema_query = \App\Models\FlujoCaja::query()
            ->where('oculto', false)
            ->vigentes()
            ->select([
                'id', 'tipo', 'fecha', 'banco', 'titular', 'banco_receptor', 'titular_receptor',
                'categoria_egreso', 'tipo_gasto', 'motivo', 'referencia',
                'monto_bs', 'monto_usd', 'comision', 'es_conciliado',
            ]);
        $ingresos_sistema_query = \App\Models\TesoreriaIngreso::query()
            ->select(['id', 'tipo', 'fecha', 'banco', 'titular', 'monto', 'es_conciliado']);
        if ($periodo_desde) {
            $movimientos_sistema_query->where('fecha', '>=', $periodo_desde);
            $ingresos_sistema_query->where('fecha', '>=', $periodo_desde);
        }
        if ($periodo_hasta) {
            $movimientos_sistema_query->where('fecha', '<=', $periodo_hasta);
            $ingresos_sistema_query->where('fecha', '<=', $periodo_hasta);
        }
        if ($banco_filtro) {
            $variantes = array_map(
                'strtolower',
                app(\App\Services\BankReconciliationMatcher::class)->variantesBanco($banco_filtro)
            );
            $movimientos_sistema_query->where(function ($q) use ($variantes) {
                $q->where(function ($banco) use ($variantes) {
                    foreach ($variantes as $v) {
                        $banco->orWhereRaw('LOWER(TRIM(banco)) = ?', [$v]);
                    }
                })->orWhere(function ($receptor) use ($variantes) {
                    foreach ($variantes as $v) {
                        $receptor->orWhereRaw('LOWER(TRIM(banco_receptor)) = ?', [$v]);
                    }
                });
            });
        }
        $this->filtrarPorTitular($movimientos_sistema_query, $titular_filtro);
        $this->filtrarPorTitular($ingresos_sistema_query, $titular_filtro);
        $movimientos_sistema = $movimientos_sistema_query->get();
        $ingresos_sistema = $ingresos_sistema_query->get();
        $matcher = app(\App\Services\BankReconciliationMatcher::class);

        // 6. Construir estructura por banco+titular.
        // "JRZ" y "GRUPO JRZ" son la misma cuenta y salen en una sola tarjeta.
        // José Jerez y terceros Venezuela no aparecen aunque tengan egresos.
        $cuentas = [];
        $calendarioCuentas = app(\App\Services\ConciliacionCalendarioService::class);
        $registrarCuenta = function ($banco, $titular) use (&$cuentas, $matcher, $calendarioCuentas) {
            $clave = $matcher->claveCuenta($banco, $titular);
            [$bancoCanon, $titularCanon] = $matcher->partesCuenta($banco, $titular);
            if ($bancoCanon === '' || $clave === '|') {
                return;
            }
            if (! $calendarioCuentas->cuentaIncluida($bancoCanon, $titularCanon)) {
                return;
            }
            if (! isset($cuentas[$clave])) {
                $cuentas[$clave] = [
                    'banco' => $bancoCanon,
                    'titular' => $titularCanon,
                ];
            } else {
                $cuentas[$clave]['titular'] = $matcher->titularPreferido(
                    $cuentas[$clave]['titular'],
                    $titularCanon
                );
            }
        };

        $lineas->each(function ($l) use ($registrarCuenta) {
            $registrarCuenta($l->banco, $l->titular);
        });
        $egresos_ayer->each(function ($e) use ($registrarCuenta) {
            $registrarCuenta($e->banco, $e->titular);
        });
        $egresos_conciliados_sistema->each(function ($e) use ($registrarCuenta) {
            $registrarCuenta($e->banco, $e->titular);
        });

        ksort($cuentas);
        $bancosActivos = collect(array_keys($cuentas));

        $comprasTransito = \Illuminate\Support\Facades\Schema::hasTable('compra_divisas')
            ? \App\Models\CompraDivisa::query()
                ->where('es_conciliado', false)
                ->where('fecha', '>=', $fecha_desde)
                ->where('fecha', '<=', $fecha_hasta)
                ->tap(fn ($q) => $this->filtrarPorBanco($q, $banco_filtro))
                ->tap(fn ($q) => $this->filtrarPorTitular($q, $titular_filtro))
                ->get()
            : collect();

        $comprasConciliadasSistema = \Illuminate\Support\Facades\Schema::hasTable('compra_divisas')
            ? \App\Models\CompraDivisa::query()
                ->where('es_conciliado', true)
                ->where('fecha', '>=', $fecha_desde)
                ->where('fecha', '<=', $fecha_hasta)
                ->tap(fn ($q) => $this->filtrarPorBanco($q, $banco_filtro))
                ->tap(fn ($q) => $this->filtrarPorTitular($q, $titular_filtro))
                ->get()
            : collect();
        $comprasConciliadasSistema->each(function ($c) use ($registrarCuenta) {
            $registrarCuenta($c->banco, $c->titular);
        });
        // Reconstruir claves por si se agregaron cuentas solo de conciliados manuales.
        ksort($cuentas);
        $bancosActivos = collect(array_keys($cuentas));

        $flujosYaEnExtracto = $lineas->whereNotNull('flujo_caja_id')
            ->pluck('flujo_caja_id')
            ->unique()
            ->flip();
        $comprasYaEnExtracto = $lineas->whereNotNull('compra_divisa_id')
            ->pluck('compra_divisa_id')
            ->unique()
            ->flip();
        $egresos_manuales = $egresos_conciliados_sistema
            ->reject(fn ($e) => isset($flujosYaEnExtracto[$e->id]))
            ->values();
        $compras_manuales = $comprasConciliadasSistema
            ->reject(fn ($c) => isset($comprasYaEnExtracto[$c->id]))
            ->values();

        $data_por_banco = [];
        $tasasQuery = \App\Models\FlujoCaja::query()
            ->where('tipo', 'egreso')
            ->where('oculto', false)
            ->where('tasa_cambio', '>', 0)
            ->when($periodo_desde, fn ($q) => $q->where('fecha', '>=', $periodo_desde))
            ->when($periodo_hasta, fn ($q) => $q->where('fecha', '<=', $periodo_hasta));
        $this->filtrarPorBanco($tasasQuery, $banco_filtro);
        $this->filtrarPorTitular($tasasQuery, $titular_filtro);
        $tasasEgreso = app(\App\Services\ComisionBancariaUsd::class)->tasasPorDia(
            $tasasQuery->get(['fecha', 'banco', 'titular', 'tasa_cambio'])
        );
        foreach ($bancosActivos as $bk_key) {
            $bk = $cuentas[$bk_key]['banco'];
            $tit = $cuentas[$bk_key]['titular'];
            $mismaCuenta = function ($item) use ($matcher, $bk_key) {
                return $matcher->claveCuenta($item->banco ?? null, $item->titular ?? null) === $bk_key;
            };

            // Líneas del banco+titular cargadas. Cargo y devolución se anulan entre sí: no se muestran.
            $lineas_banco = $lineas->filter($mismaCuenta)
                ->reject(fn ($l) => (bool) ($l->devolucion_linea_id ?? null));

            // Separar comisiones, compras de divisas, pago de crédito y normales
            $lineas_comisiones = $lineas_banco->filter(
                fn ($l) => (bool) ($l->forzar_comision ?? false)
                    || $classifier->esComision($l->descripcion, $bk)
            );
            $lineas_compra_divisas = $lineas_banco
                ->diff($lineas_comisiones)
                ->filter(fn ($l) => $classifier->esCompraDivisas($l->descripcion));
            $lineas_pago_credito = $lineas_banco
                ->diff($lineas_comisiones)
                ->diff($lineas_compra_divisas)
                ->filter(fn ($l) => $classifier->esPagoCredito($l->descripcion));
            $lineas_lotes = $lineas_banco->filter(
                fn ($l) => $matcher->esAbonoLotePuntoVenta($l->descripcion, $l->referencia)
            );
            $lineas_normales = $lineas_banco
                ->diff($lineas_comisiones)
                ->diff($lineas_compra_divisas)
                ->diff($lineas_pago_credito)
                ->diff($lineas_lotes);

            // Conciliados
            $conciliados = $lineas_normales->where('estado', 'conciliado')
                ->map(function ($l) use ($matcher) {
                    $motivo = '-';
                    $tipo_gasto = '-';
                    if ($l->flujo_caja_id) {
                        $flujo = $l->flujoCaja;
                        if ($flujo) {
                            $motivo = $flujo->motivo ?: $flujo->concepto;
                            $tipo_gasto = $flujo->tipo_gasto ?: $flujo->categoria_egreso;
                        }
                    } elseif ($l->compra_divisa_id) {
                        $compra = $l->compraDivisa;
                        if ($compra) {
                            $motivo = $compra->motivo ?: ($compra->concepto ?: 'Compra de divisas');
                            $tipo_gasto = 'Compra de divisas';
                        }
                    } elseif ($l->tesoreria_ingreso_id) {
                        $tesoreria = $l->tesoreriaIngreso;
                        if ($tesoreria) {
                            $motivo = ($tesoreria->tipo === 'punto_venta')
                                ? 'Lote Punto de Venta'
                                : ($matcher->esPagoMovil($l->descripcion) ? 'Pago móvil' : 'Ingreso Bancario Tesorería');
                            $tipo_gasto = 'Ingreso de Tesorería';
                        }
                    } elseif (! empty($l->conciliacion_nota)) {
                        $motivo = $l->conciliacion_nota;
                        $tipo_gasto = 'Justificado';
                    }

                    return [
                        'fecha'       => $l->fecha,
                        'referencia'  => $l->referencia,
                        'descripcion' => $l->descripcion,
                        'motivo'      => $motivo,
                        'tipo_gasto'  => $tipo_gasto,
                        'monto'       => $l->monto,
                        'tipo'        => $l->tipo,
                        'manual'      => false,
                    ];
                })->filter()->values();

            $conciliados = $conciliados->concat(
                $lineas_compra_divisas->where('estado', 'conciliado')->map(function ($l) {
                    $motivo = 'Compra de divisas';
                    if ($l->compra_divisa_id && $l->compraDivisa) {
                        $motivo = $l->compraDivisa->motivo ?: ($l->compraDivisa->concepto ?: $motivo);
                    }

                    return [
                        'fecha'       => $l->fecha,
                        'referencia'  => $l->referencia,
                        'descripcion' => $l->descripcion,
                        'motivo'      => $motivo,
                        'tipo_gasto'  => 'Compra de divisas',
                        'monto'       => $l->monto,
                        'tipo'        => $l->tipo,
                        'manual'      => false,
                    ];
                })
            )->values();

            // Conciliados a mano desde En tránsito (sin match del extracto).
            $conciliados = $conciliados->concat(
                $egresos_manuales->filter($mismaCuenta)->map(fn ($e) => [
                    'fecha'       => $e->fecha,
                    'referencia'  => $e->referencia,
                    'descripcion' => $e->concepto,
                    'motivo'      => $e->motivo ?: $e->concepto,
                    'tipo_gasto'  => $e->tipo_gasto ?: $e->categoria_egreso,
                    'monto'       => (float) $e->monto_bs,
                    'tipo'        => 'cargo',
                    'manual'      => true,
                ])
            )->concat(
                $compras_manuales->filter($mismaCuenta)->map(fn ($c) => [
                    'fecha'       => $c->fecha,
                    'referencia'  => $c->referencia,
                    'descripcion' => $c->concepto ?: $c->motivo,
                    'motivo'      => $c->motivo ?: ($c->concepto ?: 'Compra de divisas'),
                    'tipo_gasto'  => 'Compra de divisas',
                    'monto'       => (float) $c->monto_bs,
                    'tipo'        => 'cargo',
                    'manual'      => true,
                ])
            )->sortBy(fn (array $row) => $this->claveOrdenFecha($row))->values();

            $sin_registrar = $lineas_normales->where('estado', 'pendiente')
                ->map(fn ($l) => [
                    'id'          => $l->id,
                    'fecha'       => $l->fecha,
                    'referencia'  => $l->referencia,
                    'descripcion' => $l->descripcion,
                    'monto'       => $l->monto,
                    'tipo'        => $l->tipo,
                    'linea_id'    => $l->id,
                ])->values();

            $compras_divisas_banco = $lineas_compra_divisas->where('estado', 'pendiente')
                ->map(fn ($l) => [
                    'id'          => $l->id,
                    'fecha'       => $l->fecha,
                    'referencia'  => $l->referencia,
                    'descripcion' => $l->descripcion,
                    'monto'       => $l->monto,
                    'tipo'        => $l->tipo,
                    'linea_id'    => $l->id,
                ])->values();

            $pagos_credito = $lineas_pago_credito
                ->map(fn ($l) => [
                    'id'          => $l->id,
                    'fecha'       => $l->fecha,
                    'referencia'  => $l->referencia,
                    'descripcion' => $l->descripcion,
                    'monto'       => $l->monto,
                    'tipo'        => $l->tipo,
                    'estado'      => $l->estado,
                    'linea_id'    => $l->id,
                ])->values();

            $en_transito = $egresos_ayer
                ->filter($mismaCuenta)
                ->map(fn ($e) => [
                    'fecha'      => $e->fecha,
                    'referencia' => $e->referencia,
                    'concepto'   => $e->concepto,
                    'motivo'     => $e->motivo,
                    'titular'    => strtoupper(trim($e->titular ?? '')),
                    'tipo_gasto' => $e->tipo_gasto ?: $e->categoria_egreso,
                    'monto_bs'   => $e->monto_bs,
                    'monto_usd'  => $e->monto_usd,
                    'flujo_id'   => $e->id,
                    'compra_id'  => null,
                    'duplicado_de' => $duplicadosTransito[$e->id] ?? null,
                ])->values();

            $compras_transito = $comprasTransito
                ->filter($mismaCuenta)
                ->map(fn ($c) => [
                    'fecha'      => $c->fecha,
                    'referencia' => $c->referencia,
                    'concepto'   => $c->concepto ?: $c->motivo,
                    'motivo'     => $c->motivo,
                    'titular'    => strtoupper(trim($c->titular ?? '')),
                    'tipo_gasto' => 'Compra de divisas',
                    'monto_bs'   => $c->monto_bs,
                    'monto_usd'  => $c->monto_usd,
                    'flujo_id'   => null,
                    'compra_id'  => $c->id,
                ])->values();
            $en_transito = $en_transito->concat($compras_transito)->values();

            $comisionesUsd = app(\App\Services\ComisionBancariaUsd::class)
                ->convertir($lineas_comisiones, $tasasEgreso, $bk, $tit);
            $comisiones = $comisionesUsd['filas'];
            $total_comisiones_usd = $comisionesUsd['total_usd'];

            $total_conciliados = $conciliados->sum('monto');
            $total_transito = $en_transito->sum('monto_bs');
            $total_sin_registrar = $sin_registrar->sum('monto');
            $total_comisiones = $comisiones->sum('monto');
            $total_compras_divisas = $compras_divisas_banco->sum('monto');
            $total_pagos_credito = $pagos_credito->sum('monto');

            $mis_movimientos = $movimientos_sistema->filter(function ($movimiento) use ($matcher, $bk_key) {
                return $matcher->claveCuenta($movimiento->banco, $movimiento->titular) === $bk_key;
            });
            $mis_traslados_recibidos = $movimientos_sistema->filter(function ($movimiento) use ($matcher, $bk_key) {
                if (($movimiento->categoria_egreso ?? '') !== 'traslados') {
                    return false;
                }

                return $matcher->claveCuenta(
                    $movimiento->banco_receptor,
                    $movimiento->titular_receptor
                ) === $bk_key;
            });
            $mis_ingresos_tesoreria = $ingresos_sistema->filter(function ($ingreso) use ($matcher, $bk_key) {
                return $matcher->claveCuenta($ingreso->banco, $ingreso->titular) === $bk_key;
            });

            $total_cargos_sistema = $mis_movimientos
                ->where('tipo', 'egreso')
                ->sum(fn ($movimiento) => (float) $movimiento->monto_bs + (float) $movimiento->comision);
            $total_abonos_sistema = $mis_movimientos
                ->where('tipo', 'ingreso')
                ->sum('monto_bs')
                + $mis_traslados_recibidos->sum('monto_bs')
                + $mis_ingresos_tesoreria->sum('monto');
            $movimiento_neto_sistema = round($total_abonos_sistema - $total_cargos_sistema, 2);

            $data_por_banco[$bk_key] = array_merge(
                compact(
                    'conciliados',
                    'en_transito',
                    'sin_registrar',
                    'comisiones',
                    'compras_divisas_banco',
                    'pagos_credito',
                    'total_conciliados',
                    'total_transito',
                    'total_sin_registrar',
                    'total_comisiones',
                    'total_comisiones_usd',
                    'total_compras_divisas',
                    'total_pagos_credito',
                    'total_cargos_sistema',
                    'total_abonos_sistema',
                    'movimiento_neto_sistema'
                ),
                [
                    'banco' => $bk,
                    'titular' => $tit,
                    'clave' => $bk_key,
                    'cuenta_digitos' => $this->digitosCuentaPorClave($matcher)[$bk_key] ?? null,
                ]
            );
        }

        $cierresMarcados = collect();
        if ($fecha_desde && $fecha_hasta && \Illuminate\Support\Facades\Schema::hasTable('conciliacion_cierres')) {
            $cierresMarcados = \App\Models\ConciliacionCierre::query()
                ->whereDate('fecha_desde', $fecha_desde)
                ->whereDate('fecha_hasta', $fecha_hasta)
                ->get()
                ->keyBy(fn ($cierre) => $matcher->claveCuenta($cierre->banco, $cierre->titular));
        }

        $requiereFiltro = false;

        return view('finanzas.conciliaciones', compact(
            'lineas', 'bancos', 'cuentasBancarias', 'egresos_ayer',
            'bancosActivos', 'data_por_banco', 'titularesPorBanco',
            'filtrosConciliacion', 'fecha_desde', 'fecha_hasta', 'cierresMarcados',
            'requiereFiltro'
        ) + ['importacionesRecientes' => $this->importacionesRecientes()]);
    }

    /**
     * Últimas importaciones del usuario (para el panel de avance).
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\ConciliacionImportacion>
     */
    private function importacionesRecientes()
    {
        if (! auth()->check() || ! \Illuminate\Support\Facades\Schema::hasTable('conciliacion_importaciones')) {
            return collect();
        }

        return \App\Models\ConciliacionImportacion::query()
            ->where('user_id', auth()->id())
            ->where(function ($q) {
                $q->where('created_at', '>=', now()->subDay())
                    ->orWhereIn('estado', [\App\Models\ConciliacionImportacion::PENDIENTE, \App\Models\ConciliacionImportacion::PROCESANDO]);
            })
            ->latest('id')
            ->limit(5)
            ->get();
    }

    public function estadoImportacion(\App\Models\ConciliacionImportacion $importacion)
    {
        abort_unless($importacion->visiblePara(auth()->user()), 403);

        return response()->json([
            'id' => $importacion->id,
            'archivo' => $importacion->archivo_nombre,
            'banco' => $importacion->banco,
            'titular' => $importacion->titular,
            'estado' => $importacion->estado,
            'estado_texto' => $importacion->etiquetaEstado(),
            'terminada' => $importacion->terminada()
                && ! \Illuminate\Support\Facades\Cache::has(\App\Jobs\ProcesarImportacionExtracto::llaveConciliando($importacion->id)),
            'filas_leidas' => $importacion->filas_leidas,
            'filas_importadas' => $importacion->filas_importadas,
            'filas_duplicadas' => $importacion->filas_duplicadas,
            'filas_rechazadas' => $importacion->filas_rechazadas,
            'filas_conciliadas' => $importacion->filas_conciliadas,
            'filas_pendientes' => $importacion->filas_pendientes,
            'rechazos' => array_slice($importacion->rechazos ?? [], 0, 50),
            'mensaje' => $importacion->mensaje,
        ]);
    }

    public function calendarioConciliaciones(Request $request)
    {
        [, $bancos, $titularesPorBanco] = $this->cuentasConciliacion();

        $calMes = $request->input('cal_mes');
        if (! is_string($calMes) || ! preg_match('/^\d{4}-\d{2}$/', $calMes)) {
            $calMes = now()->format('Y-m');
        }

        $calendarioConciliacion = app(\App\Services\ConciliacionCalendarioService::class)
            ->armar($calMes, $titularesPorBanco);

        return view('finanzas.calendario_conciliaciones', compact(
            'bancos',
            'titularesPorBanco',
            'calendarioConciliacion'
        ));
    }

    public function calendarioConciliacionesPdf(Request $request)
    {
        [, , $titularesPorBanco] = $this->cuentasConciliacion();

        $calMes = $request->input('cal_mes');
        if (! is_string($calMes) || ! preg_match('/^\d{4}-\d{2}$/', $calMes)) {
            $calMes = now()->format('Y-m');
        }

        $cal = app(\App\Services\ConciliacionCalendarioService::class)->armar($calMes, $titularesPorBanco);

        $logo = null;
        $logoRaw = is_file(public_path('logo.png')) ? @file_get_contents(public_path('logo.png')) : false;
        if ($logoRaw) {
            $logo = 'data:image/png;base64,'.base64_encode($logoRaw);
        }

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('finanzas.pdf.calendario_conciliaciones', [
            'cal' => $cal,
            'generadoPor' => $request->user()?->name,
            'logoPath' => $logo,
        ])
            ->setPaper('a4', 'portrait')
            ->download('Calendario_conciliaciones_'.$cal['mes'].'.pdf');
    }

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection, 2: array<string, list<string>>}
     */
    /**
     * Últimos 10 dígitos del número de cuenta, agrupados por la misma clave de conciliación.
     *
     * @return array<string, string>
     */
    private function digitosCuentaPorClave(\App\Services\BankReconciliationMatcher $matcher): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        if (! \Illuminate\Support\Facades\Schema::hasColumn('cuentas_bancarias', 'numero_cuenta')) {
            return $cache = [];
        }

        $cache = [];
        $cuentas = \App\Models\CuentaBancaria::query()
            ->whereNotNull('numero_cuenta')
            ->where('numero_cuenta', '!=', '')
            ->get(['banco', 'titular', 'numero_cuenta']);

        foreach ($cuentas as $cuenta) {
            $digitos = preg_replace('/\D+/', '', (string) $cuenta->numero_cuenta) ?? '';
            if ($digitos === '') {
                continue;
            }
            $clave = $matcher->claveCuenta($cuenta->banco, $cuenta->titular);
            $previo = $cache[$clave] ?? '';
            if (strlen($digitos) < strlen($previo)) {
                continue;
            }
            $cache[$clave] = strlen($digitos) > 10 ? substr($digitos, -10) : $digitos;
        }

        return $cache;
    }

    private function cuentasConciliacion(): array
    {
        $cuentasBancarias = \App\Models\CuentaBancaria::all();
        $bancosPermitidos = ['BANCAMIGA', 'BANCARIBE', 'BANESCO', 'BBVA', 'BNC', 'MERCANTIL', 'TESORO', 'VENEZUELA'];
        $bancos = $cuentasBancarias->pluck('banco')
            ->map(fn ($b) => $this->canonizarBanco($b))
            ->filter(fn ($b) => in_array($b, $bancosPermitidos, true))
            ->unique()->sort()->values();

        $calendario = app(\App\Services\ConciliacionCalendarioService::class);
        $titularesPorBanco = [];
        foreach ($cuentasBancarias as $cuenta) {
            $b = $this->canonizarBanco($cuenta->banco);
            $t = strtoupper(trim($cuenta->titular));
            if (in_array($b, $bancosPermitidos, true) && $t && $calendario->cuentaIncluida($b, $t)) {
                $titularesPorBanco[$b][] = $t;
            }
        }
        foreach ($titularesPorBanco as $b => &$tits) {
            $tits = array_values(array_unique($tits));
            sort($tits);
        }
        unset($tits);

        $bancos = $bancos
            ->filter(fn ($b) => ! empty($titularesPorBanco[$b]))
            ->values();

        return [$cuentasBancarias, $bancos, $titularesPorBanco];
    }

    public function marcarConciliacionPeriodo(Request $request)
    {
        $data = $request->validate([
            'banco' => 'required|string|max:80',
            'titular' => 'required|string|max:120',
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
        ]);

        [$banco, $titular] = app(\App\Services\BankReconciliationMatcher::class)
            ->partesCuenta($data['banco'], $data['titular']);

        if ($banco === '' || $titular === '') {
            return redirect()->route('finanzas.calendario_conciliaciones')
                ->with('error', 'Elige el banco y el titular.');
        }

        $existe = ConciliacionCierre::query()
            ->where('banco', $banco)
            ->where('titular', $titular)
            ->whereDate('fecha_desde', $data['fecha_desde'])
            ->whereDate('fecha_hasta', $data['fecha_hasta'])
            ->exists();

        if (! $existe) {
            ConciliacionCierre::create([
                'banco' => $banco,
                'titular' => $titular,
                'fecha_desde' => $data['fecha_desde'],
                'fecha_hasta' => $data['fecha_hasta'],
                'user_id' => $request->user()?->id,
            ]);
        }

        $mes = \Carbon\Carbon::parse($data['fecha_desde'])->format('Y-m');
        $mensaje = $existe
            ? 'Ese banco, titular y rango ya estaban marcados.'
            : "{$banco} · {$titular} quedó marcado como conciliado.";

        return $this->redirectTrasCierre($request, $mes)->with('success', $mensaje);
    }

    public function marcarSinMovimientos(Request $request)
    {
        $data = $request->validate([
            'banco' => 'required|string|max:80',
            'titular' => 'required|string|max:120',
            'mes' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        [$banco, $titular] = app(\App\Services\BankReconciliationMatcher::class)
            ->partesCuenta($data['banco'], $data['titular']);
        if ($banco === '' || $titular === '') {
            return redirect()->route('finanzas.calendario_conciliaciones', ['cal_mes' => $data['mes']])
                ->with('error', 'Cuenta no válida.');
        }

        $inicio = \Carbon\Carbon::createFromFormat('Y-m-d', $data['mes'].'-01')->startOfMonth();
        ConciliacionCierre::query()->updateOrCreate(
            [
                'banco' => $banco,
                'titular' => $titular,
                'fecha_desde' => $inicio->toDateString(),
                'fecha_hasta' => $inicio->copy()->endOfMonth()->toDateString(),
            ],
            [
                'sin_movimientos' => true,
                'user_id' => $request->user()?->id,
            ]
        );

        return redirect()->route('finanzas.calendario_conciliaciones', ['cal_mes' => $data['mes']])
            ->with('success', "{$banco} · {$titular} quedó como sin movimientos este mes.");
    }

    public function quitarConciliacionPeriodo(Request $request, ConciliacionCierre $cierre)
    {
        $mes = $cierre->fecha_desde->format('Y-m');
        $cierre->delete();

        return $this->redirectTrasCierre($request, $mes)
            ->with('success', 'Se quitó la marca de conciliación.');
    }

    private function redirectTrasCierre(Request $request, string $mes)
    {
        if ($request->input('origen') === 'conciliaciones') {
            return $this->redirectConciliacionesConFiltros($request);
        }

        return redirect()->route('finanzas.calendario_conciliaciones', ['cal_mes' => $mes]);
    }

    /**
     * Egresos en tránsito que repiten otro egreso ya conciliado de la misma cuenta: mismo monto y misma
     * referencia (o, sin referencia, el mismo día). El banco tiene un solo pago y ya lo tomó el otro.
     *
     * @return array<int, array{id: int, motivo: string}> id en tránsito => egreso conciliado que repite
     */
    private function duplicadosEnTransito($transito, string $desde, string $hasta, ?string $banco, ?string $titular): array
    {
        if ($transito->isEmpty()) {
            return [];
        }
        $matcher = app(\App\Services\BankReconciliationMatcher::class);
        $q = \App\Models\FlujoCaja::query()->where('tipo', 'egreso')->where('es_conciliado', true)
            ->where('fecha', '>=', \Carbon\Carbon::parse($desde)->subDays(7)->toDateString())
            ->where('fecha', '<=', \Carbon\Carbon::parse($hasta)->addDays(7)->toDateString());
        if (\Illuminate\Support\Facades\Schema::hasColumn('flujo_cajas', 'anulado')) {
            $q->where('anulado', false);
        }
        $this->filtrarPorBanco($q, $banco);
        $this->filtrarPorTitular($q, $titular);
        $porMonto = $q->get()->groupBy(fn ($f) => number_format(abs((float) $f->monto_bs), 2, '.', ''));

        $dup = [];
        foreach ($transito as $e) {
            $dia = substr((string) ($e->fecha instanceof \DateTimeInterface ? $e->fecha->format('Y-m-d') : $e->fecha), 0, 10);
            $cuenta = $matcher->claveCuenta($e->banco, $e->titular);
            foreach ($porMonto->get(number_format(abs((float) $e->monto_bs), 2, '.', ''), []) as $f) {
                $diaF = substr((string) ($f->fecha instanceof \DateTimeInterface ? $f->fecha->format('Y-m-d') : $f->fecha), 0, 10);
                $sinRef = trim((string) $e->referencia) === '' || trim((string) $f->referencia) === '';
                if ((int) $f->id !== (int) $e->id
                    && $matcher->claveCuenta($f->banco, $f->titular) === $cuenta
                    && ($matcher->referenciasCruzan($e->referencia, $f->referencia) || ($sinRef && $dia === $diaF))) {
                    $dup[$e->id] = ['id' => (int) $f->id, 'motivo' => (string) ($f->motivo ?: $f->concepto)];
                    break;
                }
            }
        }

        return $dup;
    }

    /** Vuelve a conciliaciones con el mismo filtro (fecha/banco/titular) activo. */
    private function redirectConciliacionesConFiltros(?Request $request = null)
    {
        $request = $request ?: request();
        $sessionFiltros = $request->session()->get('conciliaciones.filtros', []);

        $query = array_filter([
            'fecha_desde' => $request->input('fecha_desde') ?: ($sessionFiltros['fecha_desde'] ?? null),
            'fecha_hasta' => $request->input('fecha_hasta') ?: ($sessionFiltros['fecha_hasta'] ?? null),
            'banco_filtro' => $request->input('banco_filtro') ?: ($sessionFiltros['banco_filtro'] ?? null),
            'titular_filtro' => $request->input('titular_filtro') ?: ($sessionFiltros['titular_filtro'] ?? null),
        ], fn ($v) => $v !== null && $v !== '');

        return redirect()->route('finanzas.conciliaciones', $query);
    }


    public function uploadConciliacion(Request $request) {
        $request->validate([
            'file' => 'required|array|min:1',
            // extensions: más fiable que mimes (xlsx a veces llega como zip; csv como octet-stream)
            'file.*' => ['required', 'file', 'max:15360', 'extensions:csv,txt,xlsx,xls,pdf,png,jpg,jpeg'],
            'banco_seleccionado' => 'required|string',
            'titular_seleccionado' => 'required|string',
        ], [
            'file.required' => 'Selecciona al menos un archivo del banco.',
            'file.*.extensions' => 'El archivo debe ser CSV, TXT, Excel (.xlsx/.xls), PDF de Bancaribe o imagen (.png/.jpg/.jpeg).',
            'file.*.max' => 'Cada archivo no puede superar 15 MB.',
        ]);

        $files = $request->file('file');
        $success_count = 0;
        $repetidas = 0;
        $session_id = session()->getId();
        $banco_nombre  = $this->canonizarBanco($request->banco_seleccionado);
        $titular_nombre = strtoupper(trim($request->titular_seleccionado));

        $parserExtracto = app(\App\Services\Conciliacion\ExtractoParser::class);
        $importaciones = [];

        // Helper: limpiar y convertir monto a float
        $parseMonto = function($raw) {
            if ($raw === null || $raw === '') return 0;
            $raw = trim((string)$raw);
            // Formato europeo: 1.234.567,89
            if (preg_match('/^-?[\d\.]+,\d{2}$/', $raw)) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(['$', 'Bs', 'BS', ' '], '', $raw);
                $raw = str_replace(',', '', $raw); // quitar separador de miles en formato americano
            }
            return floatval($raw);
        };

        // Helper: parsear fecha
        $parseFecha = function($fecha_raw) {
            $fecha_raw = trim((string)$fecha_raw);
            if (empty($fecha_raw)) return null;
            $fecha_solo = explode(' ', str_replace('/', '-', $fecha_raw))[0];
            try {
                // Número serial de Excel
                if (is_numeric($fecha_solo) && $fecha_solo > 20000) {
                    $unix_date = ($fecha_solo - 25569) * 86400;
                    return \Carbon\Carbon::createFromTimestampUTC($unix_date)->format('Y-m-d');
                }
                return \Carbon\Carbon::parse($fecha_solo)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        };

        foreach ($files as $file) {
            $this->volcarBufferExtracto($importaciones);
            $ext = strtolower($file->getClientOriginalExtension());
            $all_rows = [];
            $this->bufferContexto = [
                'nombre' => $file->getClientOriginalName(),
                'ext' => $ext,
                'hash' => hash_file('sha256', $file->getRealPath()) ?: null,
                'banco' => $banco_nombre,
                'titular' => $titular_nombre,
                'session_id' => $session_id,
            ];

            // 1. IMÁGENES → IA (Gemini)
            if (in_array($ext, ['png', 'jpg', 'jpeg'])) {
                try {
                    $base64Image = base64_encode(file_get_contents($file->getRealPath()));
                    $mimeType = $file->getClientMimeType();
                    $apiKey = env('GEMINI_API_KEY');
                    if (!$apiKey) continue;

                    $response = \Illuminate\Support\Facades\Http::withHeaders(['x-goog-api-key' => $apiKey])->post(
                        "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent",
                        [
                            'contents' => [[
                                'parts' => [
                                    ['text' => 'Extrae los detalles de las transacciones de este comprobante o estado de cuenta bancario. Responde ÚNICAMENTE con un ARREGLO JSON (sin markdown ni comillas invertidas) donde cada elemento sea un objeto con estas claves exactas: "fecha" (formato YYYY-MM-DD), "referencia" (string), "monto" (número decimal, usando punto para decimales), "descripcion" (string breve).'],
                                    ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64Image]]
                                ]
                            ]],
                            'generationConfig' => ['response_mime_type' => 'application/json']
                        ]
                    );

                    if ($response->successful()) {
                        $jsonText = str_replace(['```json', '```'], '', trim($response->json('candidates.0.content.parts.0.text')));
                        $transacciones = json_decode($jsonText, true);
                        if (is_array($transacciones)) {
                            if (isset($transacciones['fecha'])) $transacciones = [$transacciones];
                            foreach ($transacciones as $data) {
                                if (!isset($data['fecha']) || !isset($data['monto'])) continue;
                                $monto_val = floatval(str_replace(',', '.', (string)$data['monto']));
                                if ($monto_val == 0) continue;
                                $guardo = $this->guardarLineaExtracto([
                                    'session_id'  => $session_id,
                                    'banco'       => $banco_nombre,
                                    'titular'     => $titular_nombre,
                                    'fecha'       => $data['fecha'],
                                    'descripcion' => substr((string)($data['descripcion'] ?? 'Extracción por IA'), 0, 255),
                                    'referencia'  => substr((string)($data['referencia'] ?? ''), 0, 255),
                                    'monto'       => abs($monto_val),
                                    'estado'      => 'pendiente',
                                    'tipo'        => $monto_val < 0 ? 'cargo' : 'abono',
                                ]);
                                if ($guardo) {
                                    $success_count++;
                                } else {
                                    $repetidas++;
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('Error IA imagen: ' . $e->getMessage());
                    continue;
                }
                continue; // siguiente archivo
            }

            // 2. PDF (solo Bancaribe: estado de cuenta con "Detalles de Movimientos")
            if ($ext === 'pdf') {
                if ($banco_nombre !== 'BANCARIBE') {
                    return redirect()->back()->with('error', 'Por ahora solo se aceptan PDF de Bancaribe. Para '.$banco_nombre.' sube el Excel o CSV.');
                }
                try {
                    $movimientos = app(\App\Services\BancaribePdfParser::class)->parse($file->getRealPath());
                } catch (\Throwable $e) {
                    \Log::error('uploadConciliacion PDF Bancaribe: '.$e->getMessage());

                    return redirect()->back()->with('error', 'No se pudo leer el PDF de Bancaribe.');
                }
                foreach ($movimientos as $mov) {
                    $guardo = $this->guardarLineaExtracto([
                        'session_id'  => $session_id,
                        'banco'       => $banco_nombre,
                        'titular'     => $titular_nombre,
                        'fecha'       => $mov['fecha'],
                        'descripcion' => $mov['descripcion'],
                        'referencia'  => $mov['referencia'],
                        'monto'       => $mov['monto'],
                        'estado'      => 'pendiente',
                        'tipo'        => $mov['tipo'],
                    ]);
                    if ($guardo) {
                        $success_count++;
                    } else {
                        $repetidas++;
                    }
                }
                continue;
            }

            // 3. Excel/CSV de un banco con mapeo fijo: importación por bloques (en cola o en la misma petición).
            if (in_array($ext, ['csv', 'txt', 'xlsx', 'xls'], true) && $parserExtracto->soportaBanco($banco_nombre)) {
                $importaciones[] = $this->registrarImportacion($file, $ext, $banco_nombre, $titular_nombre, $session_id);

                continue;
            }

            // 4. LEER ARCHIVO EXCEL / CSV (bancos sin mapeo: la IA ubica las columnas)
            if ($ext === 'xlsx') {
                if ($xlsx = \Shuchkin\SimpleXLSX::parse($file->getRealPath())) {
                    $all_rows = $xlsx->rows();
                }
            } elseif ($ext === 'xls') {
                if (class_exists('\Shuchkin\SimpleXLS') && $xls = \App\Services\Conciliacion\LectorXls::parse($file->getRealPath())) {
                    $all_rows = $xls->rows();
                } elseif ($xlsx = \Shuchkin\SimpleXLSX::parse($file->getRealPath())) {
                    $all_rows = $xlsx->rows();
                }
            } else {
                // CSV — detectar separador real
                $handle = fopen($file->getRealPath(), 'r');
                $csv_semicolon = false;
                while (($line = fgets($handle)) !== false) {
                    $line = rtrim($line, "\r\n");
                    if ($line === '') {
                        $all_rows[] = [];
                        continue;
                    }
                    // Intentar detectar automáticamente si usa ';'
                    $sep = ',';
                    if ($csv_semicolon || (strpos($line, ';') !== false && substr_count($line, ';') > substr_count($line, ','))) {
                        $sep = ';';
                    }
                    // Parsear respetando comillas
                    $row = str_getcsv($line, $sep);
                    // Limpiar comillas residuales
                    $row = array_map(fn($v) => trim($v, " \t\"'"), $row);
                    $all_rows[] = $row;
                }
                fclose($handle);
            }

            if (empty($all_rows)) {
                \Log::warning("uploadConciliacion: archivo vacio o no pudo leerse. Banco=$banco_nombre ext=$ext");
                continue;
            }

            if (! $parserExtracto->soportaBanco($banco_nombre)) {
                // ── FALLBACK: IA (para MERCANTIL u otros desconocidos) ────────
                $apiKey = env('GEMINI_API_KEY') ?? null;
                if (!$apiKey) {
                    return redirect()->back()->with('error', 'Banco desconocido y la clave de API de Gemini no está configurada.');
                }

                $data_rows = array_slice($all_rows, 0, 15);
                $csv_preview = '';
                foreach ($data_rows as $row) {
                    $csv_preview .= implode(' | ', $row) . "\n";
                }

                $prompt = "Analiza las siguientes líneas de un estado de cuenta bancario.\n"
                    . "Identifica en qué fila comienzan los datos reales (0-indexed) y cuáles son los índices de las columnas (0-indexed).\n"
                    . "Responde ÚNICAMENTE con un objeto JSON exacto, sin markdown ni comillas invertidas:\n"
                    . "{\n"
                    . '  "start_row_index": integer,' . "\n"
                    . '  "col_fecha": integer,' . "\n"
                    . '  "col_descripcion": integer,' . "\n"
                    . '  "col_referencia": integer | null,' . "\n"
                    . '  "col_monto": integer | null,' . "\n"
                    . '  "col_cargo": integer | null,' . "\n"
                    . '  "col_abono": integer | null' . "\n"
                    . "}\n\nFilas:\n" . $csv_preview;

                try {
                    $response = \Illuminate\Support\Facades\Http::withHeaders(['x-goog-api-key' => $apiKey])->post(
                        "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent",
                        [
                            'contents' => [[ 'parts' => [['text' => $prompt]] ]],
                            'generationConfig' => ['response_mime_type' => 'application/json']
                        ]
                    );

                    if (!$response->successful()) { continue; }

                    $jsonText = str_replace(['```json', '```'], '', trim($response->json('candidates.0.content.parts.0.text')));
                    $mapping = json_decode($jsonText, true);

                    if (!$mapping || !isset($mapping['col_fecha'])) continue;

                    $col_fecha      = $mapping['col_fecha'];
                    $col_descripcion= $mapping['col_descripcion'] ?? -1;
                    $col_referencia = $mapping['col_referencia'] ?? -1;
                    $col_monto      = $mapping['col_monto'] ?? -1;
                    $col_cargo      = $mapping['col_cargo'] ?? -1;
                    $col_abono      = $mapping['col_abono'] ?? -1;
                    $start_row      = $mapping['start_row_index'] ?? 1;

                    foreach ($all_rows as $current_row => $data) {
                        if ($current_row < $start_row) continue;
                        if (!isset($data[$col_fecha])) continue;

                        $fecha_str = $parseFecha($data[$col_fecha]);
                        if (!$fecha_str) continue;

                        $monto_val = 0;
                        $es_egreso = false;

                        if ($col_monto != -1 && isset($data[$col_monto])) {
                            $monto_val = $parseMonto($data[$col_monto]);
                            $es_egreso = $monto_val < 0;
                        } else {
                            $cargo = ($col_cargo != -1 && isset($data[$col_cargo])) ? $parseMonto($data[$col_cargo]) : 0;
                            $abono = ($col_abono != -1 && isset($data[$col_abono])) ? $parseMonto($data[$col_abono]) : 0;
                            if (abs($cargo) > 0) { $monto_val = abs($cargo); $es_egreso = true; }
                            elseif (abs($abono) > 0) { $monto_val = abs($abono); $es_egreso = false; }
                        }

                        if (abs($monto_val) == 0) continue;

                        try {
                            $guardo = $this->guardarLineaExtracto([
                                'session_id'  => $session_id,
                                'banco'       => $banco_nombre,
                                'titular'     => $titular_nombre,
                                'fecha'       => $fecha_str,
                                'descripcion' => substr((string)($col_descripcion != -1 ? ($data[$col_descripcion] ?? '') : ''), 0, 255),
                                'referencia'  => substr((string)($col_referencia != -1 ? ($data[$col_referencia] ?? '') : ''), 0, 255),
                                'monto'       => abs($monto_val),
                                'estado'      => 'pendiente',
                                'tipo'        => $es_egreso ? 'cargo' : 'abono',
                            ]);
                            if ($guardo) {
                                $success_count++;
                            } else {
                                $repetidas++;
                            }
                        } catch (\Exception $e) {
                            \Log::error("Error fila $current_row IA: " . $e->getMessage());
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('Error IA fallback: ' . $e->getMessage());
                    continue;
                }
            }
        }

        $this->volcarBufferExtracto($importaciones);

        return $this->responderImportaciones($importaciones);
    }



    public function addMissingConciliacion(Request $request) {
        $linea = \App\Models\ConciliacionLinea::findOrFail($request->linea_id);
        
        $banco = $linea->banco ?? 'N/A';
        $titular = 'N/A';
        $categoria_cuenta = null;

        if ($linea->banco) {
            $cuentaQuery = \App\Models\CuentaBancaria::query();
            $cuenta = $this->filtrarPorBanco($cuentaQuery, $linea->banco)->first();
            if ($cuenta) {
                $banco = $cuenta->banco;
                $titular = $cuenta->titular;
                $categoria_cuenta = $cuenta->categoria;
            }
        }

        $flujo = \App\Models\FlujoCaja::create([
            'fecha' => $linea->fecha,
            'concepto' => $linea->descripcion,
            'referencia' => $linea->referencia,
            'monto' => abs($linea->monto),
            'monto_bs' => abs($linea->monto),
            'tipo' => $linea->esCargo() ? 'egreso' : 'ingreso',
            'categoria_egreso' => 'egreso_realizado',
            'banco' => $banco,
            'titular' => $titular,
            'categoria_cuenta' => $categoria_cuenta,
            'es_conciliado' => true
        ]);

        $linea->estado = 'conciliado';
        $linea->flujo_caja_id = $flujo->id;
        $linea->save();

        $this->syncTotalesSalidas($linea->fecha);

        return redirect()->route('finanzas.conciliaciones')->with('success', 'Gasto agregado al sistema y conciliado correctamente.');
    }

    public function ignoreConciliacion(Request $request) {
        $linea = \App\Models\ConciliacionLinea::findOrFail($request->linea_id);
        $linea->estado = 'ignorado';
        $linea->save();
        return redirect()->route('finanzas.conciliaciones')->with('success', 'Línea ignorada.');
    }

    /**
     * Sube "Conciliación medios de pago" (lotes POS BDV) y cruza Monto Neto con LIQ.* del extracto.
     */
    public function uploadMediosPago(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:15360|extensions:xlsx,xls,csv',
            'titular_seleccionado' => 'nullable|string|max:120',
        ], [
            'file.required' => 'Selecciona el Excel de medios de pago.',
            'file.extensions' => 'El archivo debe ser Excel (.xlsx / .xls) o CSV.',
        ]);

        $titular = $request->input('titular_seleccionado');
        $servicio = app(\App\Services\BdvMediosPagoConciliacionService::class);
        $resultado = $servicio->importarYConciliar(
            $request->file('file'),
            'VENEZUELA',
            $titular ?: null
        );

        $msg = sprintf(
            'Medios de pago BDV: %d lotes leídos, %d conciliados con LIQ, %d lotes creados/actualizados.',
            $resultado['lotes'],
            $resultado['conciliados'],
            $resultado['creados']
        );
        if ($resultado['sin_match'] !== []) {
            $msg .= ' Sin match en extracto: '.count($resultado['sin_match']).' lote(s).';
        }

        return redirect()->route('finanzas.conciliaciones.lotes', ['banco_filtro' => 'VENEZUELA'])
            ->with('success', $msg);
    }

    public function lotesPuntoVenta(Request $request)
    {
        $filterKeys = ['fecha_desde', 'fecha_hasta', 'banco_filtro'];
        if ($request->hasAny($filterKeys)) {
            $filtros = [
                'fecha_desde' => $request->input('fecha_desde') ?: null,
                'fecha_hasta' => $request->input('fecha_hasta') ?: null,
                'banco_filtro' => $request->input('banco_filtro') ?: null,
            ];
            $request->session()->put('conciliaciones.lotes.filtros', $filtros);
        } else {
            $filtros = $request->session()->get('conciliaciones.lotes.filtros', [
                'fecha_desde' => null,
                'fecha_hasta' => null,
                'banco_filtro' => null,
            ]);
        }

        [, $bancos, $titularesPorBanco] = $this->cuentasConciliacion();
        $bancoFiltro = $filtros['banco_filtro'] ?? null;
        $fechaDesde = ! empty($filtros['fecha_desde'])
            ? \Carbon\Carbon::parse($filtros['fecha_desde'])->format('Y-m-d')
            : null;
        $fechaHasta = ! empty($filtros['fecha_hasta'])
            ? \Carbon\Carbon::parse($filtros['fecha_hasta'])->format('Y-m-d')
            : null;
        if (! $fechaDesde && ! $fechaHasta) {
            $fechaDesde = now()->startOfMonth()->toDateString();
            $fechaHasta = now()->toDateString();
            $filtros['fecha_desde'] = $fechaDesde;
            $filtros['fecha_hasta'] = $fechaHasta;
        }

        $matcher = app(\App\Services\BankReconciliationMatcher::class);

        $lotesAutoConciliados = app(\App\Services\Conciliacion\LotesPuntoVentaAutoMatcher::class)->conciliar(
            $fechaDesde ?: \Carbon\Carbon::parse($fechaHasta)->subMonth()->toDateString(),
            $fechaHasta ?: now()->toDateString(),
            $bancoFiltro
        );
        // El abono del cierre llega al día siguiente: se mira hasta 5 días después del rango para tomar los lotes del final.
        $mercantilAuto = (! $bancoFiltro || stripos($bancoFiltro, 'MERCANTIL') !== false)
            ? app(\App\Services\Conciliacion\MercantilLotesConciliador::class)->conciliarAutomatico(
                $fechaDesde ?: \Carbon\Carbon::parse($fechaHasta)->subMonth()->toDateString(),
                \Carbon\Carbon::parse($fechaHasta ?: now())->addDays(5)->toDateString(),
            )['conciliados']
            : 0;

        // Solo liquidaciones sin lote ligado y por conciliar. Se recorren por bloques: una cuenta como
        // BNC L.S. CASHEA tiene decenas de miles de movimientos al mes y cargarlos todos agota la memoria.
        $lineasQuery = \App\Models\ConciliacionLinea::query()
            ->select(['id', 'banco', 'titular', 'fecha', 'descripcion', 'referencia', 'monto', 'estado', 'tipo', 'tesoreria_ingreso_id'])
            ->whereNull('tesoreria_ingreso_id')
            ->where('estado', '!=', 'conciliado');
        if ($fechaDesde) {
            $lineasQuery->where('fecha', '>=', $fechaDesde);
        }
        if ($fechaHasta) {
            $lineasQuery->where('fecha', '<=', $fechaHasta);
        }
        $this->filtrarPorBanco($lineasQuery, $bancoFiltro);
        $classifier = app(\App\Services\BankMovementClassifier::class);
        $liquidaciones = collect();
        foreach ($lineasQuery->lazyById(2000) as $linea) {
            // "COMISION BANCO POS: …" (BNC) trae "POS:" pero es el cargo de la comisión, no la liquidación.
            if ($linea->esCargo() || $classifier->esComision($linea->descripcion, $linea->banco)) {
                continue;
            }
            if ($matcher->esAbonoLotePuntoVenta($linea->descripcion, $linea->referencia)) {
                $liquidaciones->push($linea);
            }
        }
        $liquidaciones = $liquidaciones->sortBy(fn ($l) => [$l->fecha?->format('Y-m-d'), $l->id])->values();

        $conDiferidos = \Illuminate\Support\Facades\Schema::hasColumn('tesoreria_ingresos', 'conciliar_en');
        $lotesQuery = \App\Models\TesoreriaIngreso::query()
            ->where('tipo', 'punto_venta')
            ->where(function ($q) use ($fechaDesde, $fechaHasta, $conDiferidos) {
                $q->where(function ($w) use ($fechaDesde, $fechaHasta) {
                    $w->when($fechaDesde, fn ($x) => $x->where('fecha', '>=', $fechaDesde))
                        ->when($fechaHasta, fn ($x) => $x->where('fecha', '<=', $fechaHasta));
                });
                // Lotes del cierre de mes pasados a este período: se concilian aquí con la LIQ del día 1.
                if ($conDiferidos) {
                    $q->orWhere(function ($w) use ($fechaDesde, $fechaHasta) {
                        $w->whereNotNull('conciliar_en')
                            ->when($fechaDesde, fn ($x) => $x->where('conciliar_en', '>=', $fechaDesde))
                            ->when($fechaHasta, fn ($x) => $x->where('conciliar_en', '<=', $fechaHasta));
                    });
                }
            })
            ->orderBy('fecha')
            ->orderBy('id');
        $this->filtrarPorBanco($lotesQuery, $bancoFiltro);
        $lotes = $lotesQuery->get();
        $diaConciliar = fn ($l) => $conDiferidos && $l->conciliar_en ? substr((string) $l->conciliar_en, 0, 10) : null;
        // Pasados al mes siguiente: aquí solo se listan aparte; se cuentan y concilian en el período de conciliar_en.
        $esDiferido = fn ($l) => $diaConciliar($l) && $fechaHasta && $diaConciliar($l) > $fechaHasta;

        $grupos = [];
        $registrar = function ($banco, $titular) use (&$grupos, $matcher) {
            $clave = $matcher->claveCuenta($banco, $titular);
            [$bancoCanon, $titularCanon] = $matcher->partesCuenta($banco, $titular);
            if ($bancoCanon === '' || $clave === '|') {
                return;
            }
            if (! isset($grupos[$clave])) {
                $grupos[$clave] = [
                    'banco' => $bancoCanon,
                    'titular' => $titularCanon,
                ];
            } else {
                $grupos[$clave]['titular'] = $matcher->titularPreferido(
                    $grupos[$clave]['titular'],
                    $titularCanon
                );
            }
        };
        foreach ($liquidaciones as $linea) {
            $registrar($linea->banco, $linea->titular);
        }
        foreach ($lotes as $lote) {
            $registrar($lote->banco, $lote->titular);
        }
        ksort($grupos);

        // Abonos de Mercantil conciliados a medias: en la lista cuenta solo lo que falta.
        $parcialesLiq = app(\App\Services\Conciliacion\MercantilLotesConciliador::class)
            ->parciales($liquidaciones->where('estado', '!=', 'conciliado')->pluck('id')->all())
            ->map(fn ($filas) => [
                'faltan' => $filas->whereNull('tesoreria_ingreso_id')->values(),
                'ligados' => $filas->whereNotNull('tesoreria_ingreso_id')->values(),
                'pendiente' => round((float) $filas->whereNull('tesoreria_ingreso_id')->sum('monto'), 2),
            ]);
        $montoLiq = fn ($l) => isset($parcialesLiq[$l->id]) ? $parcialesLiq[$l->id]['pendiente'] : abs((float) $l->monto);
        // Lotes registrados dos veces: la copia no se concilia nunca; se marca para quitarla.
        $copiasLotes = app(\App\Services\Conciliacion\LotesRepetidos::class)->copias($lotes);

        // Lo que abonó el banco por cada lote conciliado: la diferencia con el neto es la comisión que descontó.
        $idsConciliados = $lotes->where('es_conciliado', true)->pluck('id')->all();
        $abonadoPorLote = $idsConciliados === [] ? collect() : \App\Models\ConciliacionLinea::query()
            ->whereIn('tesoreria_ingreso_id', $idsConciliados)
            ->where('estado', 'conciliado')
            ->get(['tesoreria_ingreso_id', 'monto', 'descripcion'])
            ->groupBy('tesoreria_ingreso_id')
            // La LIQ del balance de Mercantil va por el bruto; lo abonado de verdad viene en su descripción.
            ->map(fn ($l) => round((float) $l->sum(fn ($x) => \App\Services\Conciliacion\MercantilLiqBalance::abonado($x->descripcion) ?? abs((float) $x->monto)), 2));
        $comisionLote = function ($lote) use ($abonadoPorLote) {
            $neto = round(abs((float) $lote->monto), 2);
            $abonado = $abonadoPorLote[$lote->id] ?? null;
            if ($abonado === null) {
                return ['abonado' => null, 'comision' => null, 'pct' => null, 'nota' => 'Sin LIQ propia'];
            }
            // Un abono que cubre varios lotes (cierre de Mercantil) no tiene comisión por lote.
            if ($abonado > $neto * 1.05) {
                return ['abonado' => $abonado, 'comision' => null, 'pct' => null, 'nota' => 'Abono de varios lotes'];
            }
            $comision = round($neto - $abonado, 2);

            return ['abonado' => $abonado, 'comision' => $comision, 'pct' => $neto > 0 ? round($comision / $neto * 100, 2) : 0.0, 'nota' => null];
        };

        $tarjetas = [];
        foreach ($grupos as $meta) {
            $bancoCard = $meta['banco'];
            $titularCard = $meta['titular'];
            $mismo = function ($item) use ($matcher, $bancoCard, $titularCard) {
                return $matcher->mismoBanco($item->banco ?? null, $bancoCard)
                    && $matcher->mismoTitular($item->titular ?? null, $titularCard, $item->banco ?? null, $bancoCard);
            };
            $liqBanco = $liquidaciones->filter($mismo)->values();
            $todosBanco = $lotes->filter($mismo)->values();
            $lotesBanco = $todosBanco->reject($esDiferido)->values();
            $comisiones = $lotesBanco->where('es_conciliado', true)->mapWithKeys(fn ($l) => [$l->id => $comisionLote($l)]);
            $tarjetas[] = [
                'comisiones' => $comisiones,
                'total_comision' => round((float) $comisiones->sum(fn ($c) => (float) ($c['comision'] ?? 0)), 2),
                'banco' => $bancoCard,
                'titular' => $titularCard,
                'conciliados' => $lotesBanco->where('es_conciliado', true)->values(),
                'pendientes' => $lotesBanco->where('es_conciliado', false)->values(),
                'diferidos' => $todosBanco->filter($esDiferido)->values(),
                'liq_pendientes' => $liqBanco->where('estado', '!=', 'conciliado')->values(),
                'total_conciliado' => round((float) $lotesBanco->where('es_conciliado', true)->sum('monto'), 2),
                'total_pendiente' => round((float) $lotesBanco->where('es_conciliado', false)->sum('monto'), 2),
                'total_liq' => round((float) $liqBanco->where('estado', '!=', 'conciliado')->sum($montoLiq), 2),
            ];
        }

        return view('finanzas.conciliaciones_lotes', [
            'filtros' => $filtros,
            'bancos' => $bancos,
            'titularesPorBanco' => $titularesPorBanco,
            'tarjetas' => $tarjetas,
            'totalLotes' => $lotes->reject($esDiferido)->count(),
            'totalConciliados' => $lotes->reject($esDiferido)->where('es_conciliado', true)->count(),
            'totalPendientes' => $lotes->reject($esDiferido)->where('es_conciliado', false)->count(),
            'conDiferidos' => $conDiferidos,
            'filtroDesde' => $fechaDesde,
            'totalLiq' => $liquidaciones->where('estado', '!=', 'conciliado')->count(),
            'lotesAutoConciliados' => $lotesAutoConciliados,
            'mercantilAuto' => $mercantilAuto,
            'parcialesLiq' => $parcialesLiq,
            'copiasLotes' => $copiasLotes,
            'lotesPorId' => $lotes->keyBy('id'),
            'descripcionesLote' => $lotes->pluck('descripcion')->filter()->map(fn ($d) => trim((string) $d))->unique()->sort()->values(),
        ]);
    }

    /**
     * Lote del cierre de mes que el banco abona el día 1: se pasa al mes siguiente (o se devuelve a su mes).
     */
    public function diferirLote(Request $request)
    {
        $data = $request->validate([
            'diferir_id' => ['nullable', 'integer', 'required_without:volver_id'],
            'volver_id' => ['nullable', 'integer'],
        ]);
        if (! \Illuminate\Support\Facades\Schema::hasColumn('tesoreria_ingresos', 'conciliar_en')) {
            return back()->with('error', 'Falta la columna conciliar_en: ejecuta php artisan migrate.');
        }
        $lote = \App\Models\TesoreriaIngreso::query()->where('tipo', 'punto_venta')
            ->findOrFail($data['diferir_id'] ?? $data['volver_id']);
        if ($lote->es_conciliado) {
            return back()->with('error', 'El lote '.$lote->lote_referencia.' ya está conciliado.');
        }
        if (! empty($data['diferir_id'])) {
            $mes = \Carbon\Carbon::parse($lote->fecha)->addMonthNoOverflow()->startOfMonth();
            $lote->update(['conciliar_en' => $mes->toDateString()]);

            return back()->with('success', 'Lote '.$lote->lote_referencia.' del '.\Carbon\Carbon::parse($lote->fecha)->format('d/m/Y')
                .' pasado a '.$mes->locale('es')->translatedFormat('F Y').': se concilia ahí con la LIQ del banco del día 1.');
        }
        $lote->update(['conciliar_en' => null]);

        return back()->with('success', 'Lote '.$lote->lote_referencia.' devuelto a su mes.');
    }

    /**
     * Liquidación del banco que no tiene su lote en Tesorería: se registra el lote desde aquí y queda conciliado con ella.
     */
    public function registrarLoteDesdeLiq(Request $request)
    {
        $data = $request->validate([
            'linea_id' => ['required', 'integer'],
            'lote_referencia' => ['required', 'string', 'max:40'],
            'fecha' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ], [
            'lote_referencia.required' => 'Escribe el número del lote.',
            'monto.gt' => 'El monto neto del lote debe ser mayor a cero.',
        ]);
        $matcher = app(\App\Services\BankReconciliationMatcher::class);
        $linea = \App\Models\ConciliacionLinea::query()->findOrFail($data['linea_id']);
        if ($linea->estado === 'conciliado' || $linea->tesoreria_ingreso_id || $linea->flujo_caja_id) {
            return back()->with('error', 'Esa liquidación ya está conciliada.');
        }
        if ($linea->esCargo() || ! $matcher->esAbonoLotePuntoVenta($linea->descripcion, $linea->referencia)) {
            return back()->with('error', 'Ese movimiento no es una liquidación de punto de venta.');
        }

        [$banco, $titular] = $matcher->partesCuenta($linea->banco, $linea->titular);
        $neto = round((float) $data['monto'], 2);
        $abonado = round(\App\Services\Conciliacion\MercantilLiqBalance::abonado($linea->descripcion) ?? abs((float) $linea->monto), 2);

        // El mismo lote ya registrado en Tesorería (±10 días): no se crea otro encima.
        $numero = fn ($v) => ltrim(preg_replace('/\D/', '', (string) $v) ?? '', '0');
        $dia = \Carbon\Carbon::parse($data['fecha']);
        $existentes = \App\Models\TesoreriaIngreso::query()->where('tipo', 'punto_venta')
            ->whereBetween('fecha', [$dia->copy()->subDays(10)->toDateString(), $dia->copy()->addDays(10)->toDateString()])
            ->orderBy('es_conciliado')->orderBy('fecha')->orderBy('id')->get()
            ->filter(fn ($i) => $numero($i->lote_referencia) === $numero($data['lote_referencia'])
                && $matcher->mismoBanco($i->banco, $linea->banco)
                && $matcher->mismoTitular($i->titular, $linea->titular, $i->banco, $linea->banco));
        $igual = $existentes->first(fn ($i) => abs(abs((float) $i->monto) - $neto) <= 0.05);
        if ($igual && $igual->es_conciliado) {
            return back()->with('error', 'El lote '.$igual->lote_referencia.' del '.\Carbon\Carbon::parse($igual->fecha)->format('d/m/Y')
                .' ya está en Tesorería y conciliado: no se registra otra vez.');
        }
        if ($igual) {
            try {
                \Illuminate\Support\Facades\DB::transaction(function () use ($linea, $igual) {
                    $ahora = now();
                    $ok = \Illuminate\Support\Facades\DB::table('tesoreria_ingresos')->where('id', $igual->id)->where('es_conciliado', false)
                        ->update(['es_conciliado' => true, 'updated_at' => $ahora]) === 1
                        && \Illuminate\Support\Facades\DB::table('conciliacion_lineas')->where('id', $linea->id)
                            ->where('estado', '!=', 'conciliado')->whereNull('tesoreria_ingreso_id')->whereNull('flujo_caja_id')
                            ->update(['estado' => 'conciliado', 'tesoreria_ingreso_id' => $igual->id, 'updated_at' => $ahora]) === 1;
                    if (! $ok) {
                        throw new \RuntimeException('La liquidación o el lote ya los tomó otro proceso.');
                    }
                });
            } catch (\RuntimeException $e) {
                return back()->with('error', $e->getMessage().' Recarga la página.');
            }
            $comision = round($neto - $abonado, 2);

            return back()->with('success', 'El lote '.$igual->lote_referencia.' ya estaba en Tesorería (del '.\Carbon\Carbon::parse($igual->fecha)->format('d/m/Y')
                .'): no se creó otro, se concilió ese con la liquidación'
                .(abs($comision) >= 0.01 ? '. Comisión del banco: Bs. '.number_format($comision, 2, ',', '.') : '').'.');
        }
        if ($existentes->isNotEmpty()) {
            $e = $existentes->first();

            return back()->with('error', 'El lote '.$e->lote_referencia.' ya está en Tesorería del '.\Carbon\Carbon::parse($e->fecha)->format('d/m/Y')
                .' por Bs. '.number_format((float) $e->monto, 2, ',', '.').' (y escribiste Bs. '.number_format($neto, 2, ',', '.')
                .'). Corrige el monto de ese lote en Tesorería en vez de registrar otro: después se concilia solo o con «Conciliar manualmente».');
        }
        $lote = null;
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($data, $linea, $banco, $titular, $neto, &$lote) {
            $lote = \App\Models\TesoreriaIngreso::create([
                'tipo' => 'punto_venta',
                'banco' => $banco,
                'titular' => $titular !== '' ? $titular : null,
                'fecha' => $data['fecha'],
                'monto' => $neto,
                'lote_referencia' => trim($data['lote_referencia']),
                'descripcion' => $data['descripcion'] ?? null,
                'user_id' => auth()->id(),
                'es_conciliado' => true,
            ]);
            $ok = \Illuminate\Support\Facades\DB::table('conciliacion_lineas')->where('id', $linea->id)
                ->where('estado', '!=', 'conciliado')->whereNull('tesoreria_ingreso_id')->whereNull('flujo_caja_id')
                ->update(['estado' => 'conciliado', 'tesoreria_ingreso_id' => $lote->id, 'updated_at' => now()]);
            if ($ok !== 1) {
                throw new \RuntimeException('La liquidación ya la tomó otro proceso.');
            }
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage().' Recarga la página.');
        }

        $comision = round($neto - $abonado, 2);

        return back()->with('success', 'Lote '.$lote->lote_referencia.' registrado en Tesorería y conciliado con la liquidación del '
            .$linea->fecha->format('d/m/Y').' (Bs. '.number_format($abonado, 2, ',', '.').')'
            .(abs($comision) >= 0.01 ? '. Comisión del banco: Bs. '.number_format($comision, 2, ',', '.').' ('.number_format(abs($comision) / $neto * 100, 2, ',', '.').' %)' : '').'.');
    }

    /** Quita de Tesorería las copias de lotes registrados dos veces (las sin conciliar ni ligar). */
    public function quitarLotesRepetidos(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']]);
        $r = app(\App\Services\Conciliacion\LotesRepetidos::class)->quitar(array_map('intval', $data['ids']));

        return redirect()->route('finanzas.conciliaciones.lotes')->with($r['borradas'] > 0 ? 'success' : 'error', $r['borradas'] > 0
            ? "Se quitaron {$r['borradas']} lote(s) repetidos de Tesorería. Queda uno de cada lote (respaldo: {$r['respaldo']})."
            : 'No se quitó nada: esos lotes ya no están repetidos o están ligados a un movimiento del banco.');
    }

    public function manualConciliacion(Request $request)
    {
        $linea = \App\Models\ConciliacionLinea::findOrFail($request->linea_id);
        $linea->estado = 'conciliado';
        $linea->save();

        return redirect()->route('finanzas.conciliaciones')->with('success', 'Línea marcada como conciliada manualmente.');
    }

    /** Marca un movimiento del extracto como comisión bancaria (manual). */
    public function marcarComisionBancaria(Request $request)
    {
        $data = $request->validate([
            'linea_id' => ['required', 'integer'],
        ]);

        $linea = \App\Models\ConciliacionLinea::findOrFail($data['linea_id']);
        if (! \Illuminate\Support\Facades\Schema::hasColumn('conciliacion_lineas', 'forzar_comision')) {
            return back()->with('error', 'Falta migrar la columna forzar_comision. Ejecuta: php artisan migrate');
        }

        $linea->forzar_comision = true;
        $linea->save();

        return $this->redirectConciliacionesConFiltros($request)
            ->with('success', 'Movimiento marcado como comisión bancaria.');
    }

    /**
     * Egresos del sistema que pueden ser el pago que el banco devolvió:
     * misma cuenta y misma referencia que el cargo, ±15 días.
     */
    public function egresosParaDevolucion(Request $request)
    {
        $data = $request->validate([
            'linea_id' => ['required', 'integer'],
            'contraparte_id' => ['required', 'integer'],
        ]);

        try {
            [$cargo] = $this->lineasDevolucion((int) $data['linea_id'], (int) $data['contraparte_id']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        }

        $matcher = app(\App\Services\BankReconciliationMatcher::class);
        $dia = $cargo->fecha->copy()->startOfDay();
        $query = \App\Models\FlujoCaja::query()
            ->where('tipo', 'egreso')
            ->whereBetween('fecha', [$dia->copy()->subDays(15)->toDateString(), $dia->copy()->addDays(15)->toDateString()]);
        if (\Illuminate\Support\Facades\Schema::hasColumn('flujo_cajas', 'anulado')) {
            $query->where('anulado', false);
        }
        $this->filtrarPorBanco($query, $cargo->banco);
        $this->filtrarPorTitular($query, $cargo->titular);

        $candidatos = $query->limit(500)->get()
            ->filter(fn ($f) => $this->egresoCoincideConCargo($matcher, $cargo, $f))
            ->values();
        $vinculados = \App\Models\ConciliacionLinea::query()
            ->whereIn('flujo_caja_id', $candidatos->pluck('id'))
            ->where('estado', 'conciliado')
            ->get(['id', 'flujo_caja_id', 'fecha', 'referencia', 'monto'])
            ->keyBy('flujo_caja_id');

        $egresos = $candidatos
            ->sortBy(fn ($f) => [
                $matcher->referenciasCruzan($cargo->referencia, $f->referencia) ? 0 : 1,
                abs(\Carbon\Carbon::parse($f->fecha)->startOfDay()->diffInDays($dia, false)),
                (int) $f->id,
            ])
            ->map(function ($f) use ($vinculados) {
                $linea = $vinculados->get($f->id);

                return [
                    'id' => $f->id,
                    'fecha' => \Carbon\Carbon::parse($f->fecha)->format('d/m/Y'),
                    'motivo' => $f->motivo ?: ($f->concepto ?? '') ?: '-',
                    'tipo_gasto' => $f->tipo_gasto ?: $f->categoria_egreso,
                    'referencia' => $f->referencia,
                    'monto_bs' => (float) $f->monto_bs,
                    'monto_usd' => $f->monto_usd !== null ? (float) $f->monto_usd : null,
                    'conciliado_con' => $linea
                        ? 'Ya conciliado con el movimiento del banco del '.$linea->fecha->format('d/m/Y').' (ref. '.$linea->referencia.')'
                        : null,
                ];
            })
            ->values();

        return response()->json(['egresos' => $egresos]);
    }

    /**
     * Devolución del banco: el cargo y su abono de devolución quedan conciliados entre sí.
     * Los egresos elegidos (el pago que no llegó a salir) se anulan y dejan de contar en el flujo de caja.
     */
    public function marcarDevolucion(Request $request)
    {
        $data = $request->validate([
            'linea_id' => ['required', 'integer'],
            'contraparte_id' => ['required', 'integer'],
            'anular' => ['nullable', 'array'],
            'anular.*' => ['integer'],
        ]);
        if (! \Illuminate\Support\Facades\Schema::hasColumn('conciliacion_lineas', 'devolucion_linea_id')) {
            return back()->with('error', 'Falta migrar las columnas de devolución. Ejecuta: php artisan migrate');
        }

        $matcher = app(\App\Services\BankReconciliationMatcher::class);
        $anulados = 0;

        \Illuminate\Support\Facades\DB::transaction(function () use ($data, $matcher, &$anulados) {
            [$cargo, $abono] = $this->lineasDevolucion((int) $data['linea_id'], (int) $data['contraparte_id'], true);
            $ahora = now();

            foreach ([[$cargo, $abono], [$abono, $cargo]] as [$linea, $otra]) {
                $ok = \Illuminate\Support\Facades\DB::table('conciliacion_lineas')
                    ->where('id', $linea->id)
                    ->where('estado', 'pendiente')
                    ->whereNull('devolucion_linea_id')
                    ->update(['estado' => 'conciliado', 'devolucion_linea_id' => $otra->id, 'updated_at' => $ahora]);
                if ($ok !== 1) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'devolucion' => 'Otro usuario ya concilió uno de los movimientos. Recarga la página.',
                    ]);
                }
            }

            $ids = array_values(array_unique(array_map('intval', $data['anular'] ?? [])));
            if ($ids === []) {
                return;
            }
            $motivo = mb_substr('Devuelto por el banco: cargo ref. '.($cargo->referencia ?: '—').' del '.$cargo->fecha->format('d/m/Y')
                .', devolución ref. '.($abono->referencia ?: '—').' del '.$abono->fecha->format('d/m/Y'), 0, 255);

            foreach (\App\Models\FlujoCaja::query()->whereIn('id', $ids)->lockForUpdate()->get() as $flujo) {
                $conciliadoConLinea = \App\Models\ConciliacionLinea::query()
                    ->where('flujo_caja_id', $flujo->id)
                    ->where('estado', 'conciliado')
                    ->exists();
                if ($flujo->tipo !== 'egreso' || $flujo->anulado || $conciliadoConLinea
                    || $matcher->claveCuenta($flujo->banco, $flujo->titular) !== $matcher->claveCuenta($cargo->banco, $cargo->titular)
                    || ! $this->egresoCoincideConCargo($matcher, $cargo, $flujo)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'devolucion' => 'El egreso #'.$flujo->id.' no corresponde a ese movimiento del banco o ya está conciliado.',
                    ]);
                }

                $flujo->forceFill([
                    'anulado' => true,
                    'anulado_motivo' => $motivo,
                    'anulado_at' => $ahora,
                    'oculto' => true,
                    'es_conciliado' => true,
                ])->save();
                $anulados++;
            }
        });

        $mensaje = 'Devolución registrada: el cargo y su devolución quedaron conciliados entre sí.';
        if ($anulados > 0) {
            $mensaje .= ' '.$anulados.' egreso(s) marcados como nulos.';
        }

        return $this->redirectConciliacionesConFiltros($request)->with('success', $mensaje);
    }

    /**
     * Valida el par de líneas de una devolución y devuelve [cargo, abono].
     *
     * @return array{0: \App\Models\ConciliacionLinea, 1: \App\Models\ConciliacionLinea}
     */
    private function lineasDevolucion(int $lineaId, int $contraparteId, bool $bloquear = false): array
    {
        $error = fn (string $m) => \Illuminate\Validation\ValidationException::withMessages(['devolucion' => $m]);
        if ($lineaId === $contraparteId) {
            throw $error('Elige el otro movimiento del banco (el cargo o el abono de la devolución).');
        }

        $lineas = \App\Models\ConciliacionLinea::query()
            ->whereIn('id', [$lineaId, $contraparteId])
            ->when($bloquear, fn ($q) => $q->lockForUpdate())
            ->get()
            ->keyBy('id');
        $a = $lineas->get($lineaId);
        $b = $lineas->get($contraparteId);
        if (! $a || ! $b) {
            throw $error('No se encontró uno de los movimientos del banco.');
        }

        $tieneDevolucion = \Illuminate\Support\Facades\Schema::hasColumn('conciliacion_lineas', 'devolucion_linea_id');
        foreach ([$a, $b] as $l) {
            if ($l->estado !== 'pendiente' || $l->flujo_caja_id || $l->tesoreria_ingreso_id || $l->compra_divisa_id
                || ($tieneDevolucion && $l->devolucion_linea_id)) {
                throw $error('Los dos movimientos deben estar pendientes en "Movimientos en el banco".');
            }
        }

        $matcher = app(\App\Services\BankReconciliationMatcher::class);
        if ($matcher->claveCuenta($a->banco, $a->titular) !== $matcher->claveCuenta($b->banco, $b->titular)) {
            throw $error('Los dos movimientos deben ser de la misma cuenta del banco.');
        }
        if ($a->esCargo() === $b->esCargo()) {
            throw $error('Una devolución une un cargo con un abono: elige un movimiento del tipo contrario.');
        }
        if (! $matcher->mismosMontos(abs((float) $a->monto), abs((float) $b->monto))) {
            throw $error('El cargo y la devolución deben tener el mismo monto.');
        }

        return $a->esCargo() ? [$a, $b] : [$b, $a];
    }

    /** Solo el egreso con la misma referencia que el cargo devuelto puede anularse. */
    private function egresoCoincideConCargo(\App\Services\BankReconciliationMatcher $matcher, \App\Models\ConciliacionLinea $cargo, object $flujo): bool
    {
        $digitos = fn ($v) => ltrim(preg_replace('/\D+/', '', (string) $v) ?? '', '0');
        $refCargo = $digitos($cargo->referencia);

        return $refCargo !== '' && $refCargo === $digitos($flujo->referencia ?? null);
    }

    /** Marca un egreso/compra en tránsito como conciliado sin match del extracto. */
    public function marcarTransitoConciliado(Request $request)
    {
        $data = $request->validate([
            'flujo_id' => ['nullable', 'integer'],
            'compra_id' => ['nullable', 'integer'],
        ]);

        $filtros = $request->session()->get('conciliaciones.filtros', []);
        $desde = $request->input('fecha_desde') ?: ($filtros['fecha_desde'] ?? null);
        $hasta = $request->input('fecha_hasta') ?: ($filtros['fecha_hasta'] ?? null);
        $desde = $desde ? \Carbon\Carbon::parse($desde)->toDateString() : null;
        $hasta = $hasta ? \Carbon\Carbon::parse($hasta)->toDateString() : null;
        $autoMatcher = app(\App\Services\Conciliacion\ConciliacionAutoMatcher::class);
        // Sin línea del banco no se da por conciliado: para eso está «Justificar» (deja nota) o «Buscar el error».
        $responder = function (object $mov, ?\App\Models\ConciliacionLinea $linea) use ($request) {
            if ($mov->es_conciliado && ! $linea) {
                return $this->redirectConciliacionesConFiltros($request)->with('error', 'Ese movimiento ya estaba conciliado.');
            }
            if (! $linea) {
                return $this->redirectConciliacionesConFiltros($request)->with('error', sprintf(
                    'No se encontró en el banco un movimiento pendiente de Bs %s en esta cuenta (±15 días). Usa «Justificar» para ligarlo a mano o explicar qué es, o «Buscar el error» para ver dónde quedó el pago.',
                    number_format((float) $mov->monto_bs, 2, ',', '.')
                ));
            }

            return $this->redirectConciliacionesConFiltros($request)->with('success', 'Conciliado con el movimiento del banco del '.$linea->fecha->format('d/m/Y')
                .($linea->referencia ? ' (ref. '.$linea->referencia.')' : '').'.');
        };

        if (! empty($data['flujo_id'])) {
            $flujo = \App\Models\FlujoCaja::findOrFail($data['flujo_id']);

            return $responder($flujo, $flujo->es_conciliado ? null : $autoMatcher->conciliarManual($flujo, false, $desde, $hasta));
        }

        if (! empty($data['compra_id']) && \Illuminate\Support\Facades\Schema::hasTable('compra_divisas')) {
            $compra = \App\Models\CompraDivisa::findOrFail($data['compra_id']);

            return $responder($compra, $compra->es_conciliado ? null : $autoMatcher->conciliarManual($compra, true, $desde, $hasta));
        }

        return back()->with('error', 'No se indicó el movimiento a conciliar.');
    }

    /**
     * Conciliación manual POS: 1 lote sin liquidar + N liquidaciones del extracto.
     * La suma de las LIQ debe coincidir con el neto del lote (±0.05).
     */
    /**
     * Uno o varios grupos (1 lote + N LIQ cada uno). Con varios llegan en «grupos» como JSON:
     * [{"lote": 12, "lineas": [3, 4]}, …]; se concilian uno por uno y se informa cada resultado.
     */
    public function conciliarLoteManual(Request $request)
    {
        $data = $request->validate([
            'grupos' => 'nullable|string|max:20000',
            'tesoreria_ingreso_id' => 'required_without:grupos|nullable|integer',
            'linea_ids' => 'required_without:grupos|nullable|array|min:1',
            'linea_ids.*' => 'integer',
            'aceptar_diferencia' => 'nullable|boolean',
        ]);
        $aceptar = $request->boolean('aceptar_diferencia');

        $grupos = [];
        if (! empty($data['grupos'])) {
            foreach ((array) json_decode($data['grupos'], true) as $g) {
                if (isset($g['lote'], $g['lineas']) && is_array($g['lineas']) && $g['lineas'] !== []) {
                    $grupos[] = [(int) $g['lote'], array_map('intval', $g['lineas'])];
                }
            }
            if ($grupos === []) {
                return back()->with('error', 'No llegó ningún grupo para conciliar.');
            }
        } else {
            $grupos[] = [(int) $data['tesoreria_ingreso_id'], array_map('intval', $data['linea_ids'])];
        }

        $bien = [];
        $mal = [];
        foreach ($grupos as [$loteId, $ids]) {
            [$ok, $mensaje] = $this->conciliarGrupoLote($loteId, $ids, $aceptar);
            $ok ? $bien[] = $mensaje : $mal[] = $mensaje;
        }

        if (count($grupos) === 1) {
            return back()->with($bien ? 'success' : 'error', $bien[0] ?? $mal[0]);
        }
        $texto = count($bien).' de '.count($grupos).' grupo(s) conciliados. '.implode(' ', $bien);
        if ($mal) {
            $texto .= ' No se pudo: '.implode(' ', $mal);
        }

        return back()->with($bien ? 'success' : 'error', $texto);
    }

    /** @return array{0: bool, 1: string} */
    private function conciliarGrupoLote(int $loteId, array $ids, bool $aceptarDiferencia): array
    {
        $lote = \App\Models\TesoreriaIngreso::query()->where('tipo', 'punto_venta')->whereKey($loteId)->first();
        if (! $lote) {
            return [false, 'El lote #'.$loteId.' no existe.'];
        }
        $ref = $lote->lote_referencia ?: ('#'.$lote->id);
        if ($lote->es_conciliado) {
            return [false, 'El lote '.$ref.' ya está conciliado.'];
        }

        $ids = array_values(array_unique($ids));
        $matcher = app(\App\Services\BankReconciliationMatcher::class);

        $lineas = \App\Models\ConciliacionLinea::query()
            ->whereIn('id', $ids)
            ->where('estado', '!=', 'conciliado')
            ->whereNull('tesoreria_ingreso_id')
            ->get();

        if ($lineas->count() !== count($ids)) {
            return [false, 'Lote '.$ref.': una o más liquidaciones ya no están disponibles.'];
        }

        foreach ($lineas as $linea) {
            if (! $matcher->esAbonoLotePuntoVenta($linea->descripcion, $linea->referencia)) {
                return [false, 'Lote '.$ref.': hay un movimiento que no es liquidación de punto de venta.'];
            }
            if (! $matcher->mismoBanco($linea->banco, $lote->banco)) {
                return [false, 'Lote '.$ref.': el banco del lote y de la liquidación no coinciden.'];
            }
            if (! $matcher->mismoTitular($linea->titular, $lote->titular, $linea->banco, $lote->banco)) {
                return [false, 'Lote '.$ref.': el titular del lote y de la liquidación no coinciden.'];
            }
        }

        // Abono de Mercantil conciliado a medias: el lote elegido completa lo que falta.
        $mercantil = app(\App\Services\Conciliacion\MercantilLotesConciliador::class);
        if ($lineas->count() === 1 && $mercantil->parciales([$lineas->first()->id])->isNotEmpty()) {
            return $mercantil->completarConLote($lineas->first(), $lote)
                ? [true, 'Lote '.$ref.' ligado al abono del '.$lineas->first()->fecha->format('d/m/Y').': completa lo que faltaba del cierre.']
                : [false, 'Lote '.$ref.': el monto no es el que falta en ese abono (mira el detalle en «LIQ del banco sin lote»).'];
        }

        $suma = round((float) $lineas->sum(fn ($l) => abs((float) $l->monto)), 2);
        $netoLote = round(abs((float) $lote->monto), 2);
        $diferencia = round($suma - $netoLote, 2);
        $conDiferencia = abs($diferencia) > 0.05;
        if ($conDiferencia && ! $aceptarDiferencia) {
            return [false, 'Lote '.$ref.': la suma de LIQ (Bs. '.number_format($suma, 2, ',', '.').') no coincide con el lote (Bs. '.number_format($netoLote, 2, ',', '.').').'];
        }
        if ($conDiferencia) {
            \Log::info('Lote punto de venta conciliado con diferencia', [
                'tesoreria_ingreso_id' => $lote->id,
                'lote' => $lote->lote_referencia,
                'neto_lote' => $netoLote,
                'suma_liq' => $suma,
                'diferencia' => $diferencia,
                'linea_ids' => $ids,
                'user_id' => auth()->id(),
            ]);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($lote, $lineas) {
            foreach ($lineas as $linea) {
                $linea->estado = 'conciliado';
                $linea->tesoreria_ingreso_id = $lote->id;
                $linea->flujo_caja_id = null;
                $linea->save();
            }
            $lote->es_conciliado = true;
            $lote->save();
        });

        $pct = $netoLote > 0 ? abs($diferencia) / $netoLote * 100 : 0;

        return [true, 'Lote '.$ref.' conciliado manualmente con '.$lineas->count().' liquidación(es)'
            .($conDiferencia ? ', con diferencia de Bs. '.number_format($diferencia, 2, ',', '.').' ('.number_format($pct, 2, ',', '.').' %)' : '').'.'];
    }

    public function reporteConciliacion(Request $request) {
        $banco_filtro = $request->query('banco_filtro');
        
        $query = \App\Models\ConciliacionLinea::where('estado', 'conciliado')->orderBy('fecha', 'desc');
        
        $this->filtrarPorBanco($query, $banco_filtro);
        
        $conciliados = $query->get();
        
        $filename = "reporte_conciliacion_" . ($banco_filtro ? strtolower($banco_filtro) . "_" : "") . date('Y-m-d') . ".csv";
        
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $columns = ['Fecha', 'Banco', 'Descripcion', 'Referencia', 'Monto'];

        $callback = function() use($conciliados, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($conciliados as $c) {
                $row['Fecha']  = $c->fecha;
                $row['Banco']    = $c->banco;
                $row['Descripcion']    = $c->descripcion;
                $row['Referencia']  = $c->referencia;
                $row['Monto']  = $c->monto;

                fputcsv($file, array($row['Fecha'], $row['Banco'], $row['Descripcion'], $row['Referencia'], $row['Monto']));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Mismo criterio que idsExtractoVisibles: un extracto subido varias veces deja copias
     * idénticas; se muestra una sola, prefiriendo la conciliada y luego la más antigua.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\ConciliacionLinea>  $lineas
     * @return \Illuminate\Support\Collection<int, \App\Models\ConciliacionLinea>
     */
    private function sinCopiasExtracto($lineas)
    {
        return $lineas
            ->sortBy(fn ($l) => [$l->estado === 'conciliado' ? 0 : 1, (int) $l->id])
            ->unique(fn ($l) => implode('|', [
                strtoupper(trim((string) $l->banco)),
                strtoupper(trim((string) ($l->titular ?? ''))),
                $l->fecha instanceof \DateTimeInterface ? $l->fecha->format('Y-m-d') : substr((string) $l->fecha, 0, 10),
                number_format(round((float) $l->monto, 2), 2, '.', ''),
                (string) ($l->referencia ?? ''),
                (string) ($l->descripcion ?? ''),
                (string) ($l->tipo ?? ''),
            ]))
            ->sortBy(fn ($l) => [
                $l->fecha instanceof \DateTimeInterface ? $l->fecha->format('Y-m-d') : substr((string) $l->fecha, 0, 10),
                (int) $l->id,
            ])
            ->values();
    }

    /** Por encima de esta cantidad de filas, la sección del PDF se muestra resumida por día. */
    private const PDF_CONCILIACION_LIMITE_DETALLE = 500;

    /**
     * Resumen diario de una sección del PDF; null si la sección es pequeña y va con detalle.
     * Sin campo "tipo" (en tránsito, comisiones) todo cuenta como egreso.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $filas
     * @return \Illuminate\Support\Collection<int, array{fecha:string,cantidad:int,ingresos:float,egresos:float}>|null
     */
    private function resumenPdfPorDia($filas, string $campoMonto = 'monto')
    {
        if ($filas->count() <= self::PDF_CONCILIACION_LIMITE_DETALLE) {
            return null;
        }

        return $filas
            ->groupBy(fn ($r) => $r['fecha'] instanceof \DateTimeInterface
                ? $r['fecha']->format('Y-m-d')
                : substr((string) $r['fecha'], 0, 10))
            ->map(function ($grupo, $dia) use ($campoMonto) {
                $ingresos = 0.0;
                $egresos = 0.0;
                foreach ($grupo as $r) {
                    $monto = abs((float) ($r[$campoMonto] ?? 0));
                    if (($r['tipo'] ?? 'cargo') === 'abono') {
                        $ingresos += $monto;
                    } else {
                        $egresos += $monto;
                    }
                }

                return [
                    'fecha' => (string) $dia,
                    'cantidad' => $grupo->count(),
                    'ingresos' => round($ingresos, 2),
                    'egresos' => round($egresos, 2),
                ];
            })
            ->sortKeys()
            ->values();
    }

    public function reporteBancoPdf(Request $request) {
        $bk_req = strtolower(trim($request->query('banco', '')));
        $tit_req = strtolower(trim($request->query('titular', '')));
        $fecha_desde_filtro = $request->query('fecha_desde');
        $fecha_hasta_filtro = $request->query('fecha_hasta');
        
        if (!$bk_req) {
            return redirect()->back()->with('error', 'Banco no especificado.');
        }

        // Cuentas con miles de movimientos (p. ej. Cashea): dompdf necesita bastante memoria.
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $lineas_query = \App\Models\ConciliacionLinea::query()
            ->select(array_merge([
                'id', 'banco', 'titular', 'fecha', 'descripcion', 'referencia', 'monto', 'estado', 'tipo',
                'forzar_comision', 'flujo_caja_id', 'tesoreria_ingreso_id',
            ], \Illuminate\Support\Facades\Schema::hasColumn('conciliacion_lineas', 'devolucion_linea_id') ? ['devolucion_linea_id'] : [],
                \Illuminate\Support\Facades\Schema::hasColumn('conciliacion_lineas', 'conciliacion_nota') ? ['conciliacion_nota'] : []))
            ->with(['flujoCaja', 'tesoreriaIngreso:id,tipo']);
        $this->filtrarPorBanco($lineas_query, $bk_req);
        if ($fecha_desde_filtro) {
            $lineas_query->whereDate('fecha', '>=', $fecha_desde_filtro);
        } else {
            $lineas_query->where('created_at', '>=', now()->subDays(1)->startOfDay());
        }
        if ($fecha_hasta_filtro) {
            $lineas_query->whereDate('fecha', '<=', $fecha_hasta_filtro);
        }
        $lineas = $this->sinCopiasExtracto($lineas_query->get());

        $egresos_query = \App\Models\FlujoCaja::where('tipo', 'egreso')
            ->where('es_conciliado', false);
        if (\Illuminate\Support\Facades\Schema::hasColumn('flujo_cajas', 'anulado')) {
            $egresos_query->where('anulado', false);
        }
        $this->filtrarPorBanco($egresos_query, $bk_req);
        if ($fecha_desde_filtro) {
            $egresos_query->whereDate('fecha', '>=', $fecha_desde_filtro);
        } else {
            $egresos_query->whereDate('fecha', '>=', now()->subDay()->format('Y-m-d'));
        }
        if ($fecha_hasta_filtro) {
            $egresos_query->whereDate('fecha', '<=', $fecha_hasta_filtro);
        }
        $egresos_ayer = $egresos_query->orderBy('id')->get();

        $classifier = app(\App\Services\BankMovementClassifier::class);
        $matcher = app(\App\Services\BankReconciliationMatcher::class);
        $clavePdf = $tit_req !== '' ? $matcher->claveCuenta($bk_req, $tit_req) : '';

        $lineas_banco = $lineas->filter(function ($l) use ($matcher, $clavePdf, $tit_req) {
            // Cargo y devolución se anulan entre sí: no se muestran.
            if ($l->devolucion_linea_id ?? null) {
                return false;
            }
            if ($tit_req === '') {
                return true;
            }

            return $matcher->claveCuenta($l->banco, $l->titular) === $clavePdf;
        });

        $lineas_comisiones = $lineas_banco->filter(
            fn ($l) => (bool) ($l->forzar_comision ?? false)
                || $classifier->esComision($l->descripcion, $bk_req)
        );
        $lineas_compra_divisas = $lineas_banco
            ->diff($lineas_comisiones)
            ->filter(fn ($l) => $classifier->esCompraDivisas($l->descripcion));
        $lineas_pago_credito = $lineas_banco
            ->diff($lineas_comisiones)
            ->diff($lineas_compra_divisas)
            ->filter(fn ($l) => $classifier->esPagoCredito($l->descripcion));
        // Igual que la pantalla: los abonos de lotes de punto de venta se concilian en la página de lotes.
        $lineas_lotes = $lineas_banco->filter(
            fn ($l) => $matcher->esAbonoLotePuntoVenta($l->descripcion, $l->referencia)
        );
        $lineas_normales = $lineas_banco
            ->diff($lineas_comisiones)
            ->diff($lineas_compra_divisas)
            ->diff($lineas_pago_credito)
            ->diff($lineas_lotes);

        $conciliados = $lineas_normales->where('estado', 'conciliado')
            ->map(function($l) {
                $motivo = '-';
                $tipo_gasto = '-';
                if ($l->flujo_caja_id) {
                    $flujo = $l->flujoCaja;
                    if ($flujo) {
                        $motivo = $flujo->motivo ?: $flujo->concepto;
                        $tipo_gasto = $flujo->tipo_gasto ?: $flujo->categoria_egreso;
                    }
                } elseif ($l->tesoreria_ingreso_id) {
                    $tesoreria = $l->tesoreriaIngreso;
                    if ($tesoreria) {
                        $motivo = ($tesoreria->tipo === 'punto_venta' ? 'Lote Punto de Venta' : 'Ingreso Bancario Tesorería');
                        $tipo_gasto = 'Ingreso de Tesorería';
                    }
                } elseif (! empty($l->conciliacion_nota)) {
                    $motivo = $l->conciliacion_nota;
                    $tipo_gasto = 'Justificado';
                }

                return [
                    'fecha'       => $l->fecha,
                    'referencia'  => $l->referencia,
                    'descripcion' => $l->descripcion,
                    'motivo'      => $motivo,
                    'tipo_gasto'  => $tipo_gasto,
                    'monto'       => $l->monto,
                    'tipo'        => $l->tipo,
                ];
            })->sortBy(fn (array $row) => $this->claveOrdenFecha($row))->values();

        $sin_registrar = $lineas_normales->where('estado', 'pendiente')
            ->map(fn($l) => [
                'id'          => $l->id,
                'fecha'       => $l->fecha,
                'referencia'  => $l->referencia,
                'descripcion' => $l->descripcion,
                'monto'       => $l->monto,
                'tipo'        => $l->tipo,
                'linea_id'    => $l->id,
            ])->values();

        $en_transito = $egresos_ayer
            ->filter(function ($e) use ($matcher, $clavePdf, $tit_req) {
                if ($tit_req === '') {
                    return true;
                }

                return $matcher->claveCuenta($e->banco, $e->titular) === $clavePdf;
            })
            ->map(fn($e) => [
                'fecha'      => $e->fecha,
                'referencia' => $e->referencia,
                'concepto'   => $e->concepto,
                'motivo'     => $e->motivo,
                'titular'    => strtoupper(trim($e->titular ?? '')),
                'tipo_gasto' => $e->tipo_gasto ?: $e->categoria_egreso,
                'monto_bs'   => $e->monto_bs,
                'monto_usd'  => $e->monto_usd,
                'flujo_id'   => $e->id,
            ])->values();

        $tasasEgreso = app(\App\Services\ComisionBancariaUsd::class)->tasasPorDia(
            \App\Models\FlujoCaja::query()
                ->where('tipo', 'egreso')
                ->where('oculto', false)
                ->where('tasa_cambio', '>', 0)
                ->when($fecha_desde_filtro, fn ($q) => $q->whereDate('fecha', '>=', $fecha_desde_filtro))
                ->when($fecha_hasta_filtro, fn ($q) => $q->whereDate('fecha', '<=', $fecha_hasta_filtro))
                ->get(['fecha', 'banco', 'titular', 'tasa_cambio'])
        );
        [$bancoCanon, $titularCanon] = app(\App\Services\BankReconciliationMatcher::class)
            ->partesCuenta($bk_req, $tit_req);
        $comisionesUsd = app(\App\Services\ComisionBancariaUsd::class)
            ->convertir($lineas_comisiones, $tasasEgreso, $bancoCanon, $titularCanon);
        $comisiones = $comisionesUsd['filas'];

        $pagos_credito = $lineas_pago_credito
            ->map(fn ($l) => [
                'fecha'       => $l->fecha,
                'referencia'  => $l->referencia,
                'descripcion' => $l->descripcion,
                'monto'       => $l->monto,
                'tipo'        => $l->tipo,
            ])->values();

        $data = [
            'banco' => strtoupper($bk_req),
            'titular' => strtoupper($tit_req),
            'cuenta_digitos' => $clavePdf !== ''
                ? ($this->digitosCuentaPorClave($matcher)[$clavePdf] ?? null)
                : null,
            'conciliados' => $conciliados,
            'en_transito' => $en_transito,
            'sin_registrar' => $sin_registrar,
            'comisiones' => $comisiones,
            'pagos_credito' => $pagos_credito,
            'total_conciliados' => $conciliados->sum('monto'),
            'total_transito' => $en_transito->sum('monto_bs'),
            'total_sin_registrar' => $sin_registrar->sum('monto'),
            'total_comisiones' => $comisiones->sum('monto'),
            'total_comisiones_usd' => $comisionesUsd['total_usd'],
            'total_pagos_credito' => $pagos_credito->sum('monto'),
            'fecha_desde' => $fecha_desde_filtro,
            'fecha_hasta' => $fecha_hasta_filtro,
            'limite_detalle' => self::PDF_CONCILIACION_LIMITE_DETALLE,
            'por_dia' => [
                'conciliados' => $this->resumenPdfPorDia($conciliados),
                'en_transito' => $this->resumenPdfPorDia($en_transito, 'monto_bs'),
                'sin_registrar' => $this->resumenPdfPorDia($sin_registrar),
                'comisiones' => $this->resumenPdfPorDia($comisiones),
                'pagos_credito' => $this->resumenPdfPorDia($pagos_credito),
            ],
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('finanzas.pdf.reporte_banco', ['data' => $data]);
        $pdf->setPaper('a4', 'landscape');
        
        $filename = 'Reporte_' . strtoupper($bk_req) . ($tit_req ? '_' . str_replace(' ', '_', strtoupper($tit_req)) : '') . '_' . date('Ymd') . '.pdf';
        
        return $pdf->download($filename);
    }
    public function clearConciliacion() {
        $fecha_desde = now()->subDays(1)->startOfDay();
        \App\Models\ConciliacionLinea::where('created_at', '>=', $fecha_desde)->delete();
        return redirect()->route('finanzas.conciliaciones')->with('success', 'Se han borrado los movimientos bancarios cargados.');
    }

    /**
     * Línea de comisión del banco que quedó con un egreso de otro monto (por cruce de referencia)
     * en lugar de la transferencia real. Se libera para que el emparejamiento tome la línea correcta.
     */
    private function desvincularComisionEmparejadaConEgreso(
        \App\Services\BankReconciliationMatcher $matcher,
        string $desde,
        string $hasta,
    ): void {
        $classifier = app(\App\Services\BankMovementClassifier::class);
        $lineas = \App\Models\ConciliacionLinea::query()
            ->where('estado', 'conciliado')
            ->whereNotNull('flujo_caja_id')
            ->whereBetween('fecha', [$desde, $hasta])
            ->with('flujoCaja')
            ->limit(2000)
            ->get();

        foreach ($lineas as $linea) {
            $flujo = $linea->flujoCaja;
            if (! $flujo || $matcher->esTraslado($flujo)) {
                continue;
            }
            if (! $matcher->comisionNoCorrespondeAlEgreso($linea, $flujo, $classifier->esComision($linea->descripcion, $linea->banco))) {
                continue;
            }

            $linea->flujo_caja_id = null;
            $linea->estado = 'pendiente';
            $linea->save();

            $sigueVinculado = \App\Models\ConciliacionLinea::query()
                ->where('flujo_caja_id', $flujo->id)
                ->where('estado', 'conciliado')
                ->exists();
            if (! $sigueVinculado) {
                $flujo->es_conciliado = false;
                $flujo->save();
            }
        }
    }

    /**
     * El banco tiene dos cargos del mismo monto (p. ej. intento devuelto + reenvío) y el egreso quedó
     * en el que no es su referencia. Si hay una línea pendiente con la referencia exacta del egreso,
     * el egreso pasa a esa línea y la otra queda libre (para marcarla como devolución).
     */
    private function reasignarEgresoALaReferenciaExacta(
        \App\Services\BankReconciliationMatcher $matcher,
        string $desde,
        string $hasta,
    ): void {
        $digitos = fn ($v) => ltrim(preg_replace('/\D+/', '', (string) $v) ?? '', '0');
        $lineas = \App\Models\ConciliacionLinea::query()
            ->where('estado', 'conciliado')
            ->whereNotNull('flujo_caja_id')
            ->whereBetween('fecha', [$desde, $hasta])
            ->with('flujoCaja')
            ->limit(2000)
            ->get();

        foreach ($lineas as $linea) {
            $flujo = $linea->flujoCaja;
            $refEgreso = $digitos($flujo?->referencia);
            if (! $flujo || $matcher->esTraslado($flujo) || strlen($refEgreso) < 6 || $digitos($linea->referencia) === $refEgreso) {
                continue;
            }

            $correcta = \App\Models\ConciliacionLinea::query()
                ->where('estado', 'pendiente')
                ->whereNull('flujo_caja_id')
                ->whereNull('tesoreria_ingreso_id')
                ->where('id', '!=', $linea->id)
                ->whereBetween('fecha', [
                    \Carbon\Carbon::parse($flujo->fecha)->subDays(3)->toDateString(),
                    \Carbon\Carbon::parse($flujo->fecha)->addDays(3)->toDateString(),
                ])
                ->whereRaw('TRIM(referencia) LIKE ?', ['%'.$refEgreso])
                ->orderBy('id')
                ->get()
                ->first(fn ($c) => $digitos($c->referencia) === $refEgreso
                    && $c->esCargo()
                    && $matcher->montoIgualEgreso($c, $flujo)
                    && $matcher->claveCuenta($c->banco, $c->titular) === $matcher->claveCuenta($linea->banco, $linea->titular));
            if (! $correcta) {
                continue;
            }

            \Illuminate\Support\Facades\DB::transaction(function () use ($linea, $correcta, $flujo) {
                $ahora = now();
                $tomada = \Illuminate\Support\Facades\DB::table('conciliacion_lineas')
                    ->where('id', $correcta->id)->where('estado', 'pendiente')->whereNull('flujo_caja_id')
                    ->update(['estado' => 'conciliado', 'flujo_caja_id' => $flujo->id, 'updated_at' => $ahora]);
                if ($tomada !== 1) {
                    return;
                }
                \Illuminate\Support\Facades\DB::table('conciliacion_lineas')
                    ->where('id', $linea->id)->where('flujo_caja_id', $flujo->id)
                    ->update(['estado' => 'pendiente', 'flujo_caja_id' => null, 'updated_at' => $ahora]);
            });
        }
    }

    /**
     * PagoMóvil mal cruzado con lote POS (el "0134" del concepto es código de banco).
     */
    private function desvincularPagomovilMalEmparejadoConLote(
        \App\Services\BankReconciliationMatcher $matcher,
        ?string $desde = null,
        ?string $hasta = null,
    ): void {
        $lineas = \App\Models\ConciliacionLinea::query()
            ->whereNotNull('tesoreria_ingreso_id')
            ->where('estado', 'conciliado')
            ->when($desde, fn ($q) => $q->where('fecha', '>=', $desde))
            ->when($hasta, fn ($q) => $q->where('fecha', '<=', $hasta))
            ->when(! $desde && ! $hasta, fn ($q) => $q->where('fecha', '>=', now()->subDays(14)->format('Y-m-d')))
            ->where(function ($q) {
                foreach (['%pagomovil%', '%pago movil%', '%pago móvil%', '%pago-movil%', '%p. movil%', '%p.móvil%'] as $needle) {
                    $q->orWhereRaw('LOWER(descripcion) LIKE ?', [mb_strtolower($needle, 'UTF-8')]);
                }
            })
            ->limit(300)
            ->get();

        if ($lineas->isEmpty()) {
            return;
        }

        $ingresos = \App\Models\TesoreriaIngreso::query()
            ->whereIn('id', $lineas->pluck('tesoreria_ingreso_id')->unique()->filter())
            ->get()
            ->keyBy('id');

        foreach ($lineas as $linea) {
            if (! $matcher->esPagoMovil($linea->descripcion)) {
                continue;
            }
            $ingreso = $ingresos->get($linea->tesoreria_ingreso_id);
            if (! $ingreso || ($ingreso->tipo ?? '') !== 'punto_venta') {
                continue;
            }

            $linea->tesoreria_ingreso_id = null;
            $linea->estado = 'pendiente';
            $linea->save();

            $sigueVinculado = \App\Models\ConciliacionLinea::query()
                ->where('tesoreria_ingreso_id', $ingreso->id)
                ->where('estado', 'conciliado')
                ->exists();
            if (! $sigueVinculado) {
                $ingreso->es_conciliado = false;
                $ingreso->save();
            }
        }
    }

    /**
     * Si la línea quedó atada a un egreso vacío (copia del extracto), pásala al
     * gasto real del mismo día para que salga de "En tránsito".
     */
    private function reasignarGastosVaciosAlEgresoReal(
        \App\Services\BankReconciliationMatcher $matcher,
        ?string $desde = null,
        ?string $hasta = null,
    ): void {
        $desde = $desde ?: now()->subDays(14)->format('Y-m-d');
        $hasta = $hasta ?: now()->format('Y-m-d');
        $lineas = \App\Models\ConciliacionLinea::query()
            ->where('estado', 'conciliado')
            ->whereNotNull('flujo_caja_id')
            ->whereBetween('fecha', [$desde, $hasta])
            ->whereHas('flujoCaja', function ($q) {
                $q->where(function ($q) {
                    $q->whereNull('motivo')->orWhere('motivo', '');
                })->where(function ($q) {
                    $q->whereNull('comprobante_url')->orWhere('comprobante_url', '');
                })->where(function ($q) {
                    $q->whereNull('categoria_egreso')
                        ->orWhereRaw("LOWER(categoria_egreso) <> 'traslados'");
                });
            })
            ->with('flujoCaja')
            ->limit(300)
            ->get();

        if ($lineas->isEmpty()) {
            return;
        }

        $candidatos = \App\Models\FlujoCaja::query()
            ->where('tipo', 'egreso')
            ->where('es_conciliado', false)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->whereNotNull('motivo')
            ->where('motivo', '!=', '')
            ->limit(2000)
            ->get();

        $candidatosPorDiaMonto = [];
        foreach ($candidatos as $candidato) {
            $dia = $candidato->fecha instanceof \DateTimeInterface
                ? $candidato->fecha->format('Y-m-d')
                : substr((string) $candidato->fecha, 0, 10);
            $cents = (int) round(abs((float) ($candidato->monto_bs ?? 0)) * 100);
            $candidatosPorDiaMonto[$dia.'|'.$cents][] = $candidato;
        }

        foreach ($lineas as $linea) {
            $vinculado = $linea->flujoCaja;
            if (! $vinculado) {
                continue;
            }

            $diaLinea = $linea->fecha instanceof \DateTimeInterface
                ? $linea->fecha->format('Y-m-d')
                : substr((string) $linea->fecha, 0, 10);
            $centsLinea = (int) round(abs((float) $linea->monto) * 100);
            $reemplazo = $matcher->reemplazoDeGastoVacio(
                $linea,
                $vinculado,
                $candidatosPorDiaMonto[$diaLinea.'|'.$centsLinea] ?? []
            );
            if (! $reemplazo) {
                continue;
            }

            $linea->flujo_caja_id = $reemplazo->id;
            $linea->save();
            $reemplazo->es_conciliado = true;
            $reemplazo->save();

            $sigueVinculado = \App\Models\ConciliacionLinea::query()
                ->where('flujo_caja_id', $vinculado->id)
                ->where('estado', 'conciliado')
                ->exists();
            if (! $sigueVinculado) {
                $vinculado->delete();
            }

            $candidatos = $candidatos->reject(fn ($candidato) => (int) $candidato->id === (int) $reemplazo->id);
        }
    }

    /**
     * Si el cargo del banco emisor ya está vinculado al traslado, sácalo de "En tránsito".
     * La entrada en el receptor puede conciliarse después; los traslados siguen disponibles
     * en el motor de emparejamiento.
     */
    private function marcarTrasladosConSalidaConciliados(
        \App\Services\BankReconciliationMatcher $matcher,
        ?string $desde = null,
        ?string $hasta = null,
    ): void {
        $traslados = \App\Models\FlujoCaja::query()
            ->where('tipo', 'egreso')
            ->where('categoria_egreso', 'traslados')
            ->where('es_conciliado', false)
            ->when($desde, fn ($q) => $q->where('fecha', '>=', $desde))
            ->when($hasta, fn ($q) => $q->where('fecha', '<=', $hasta))
            ->when(! $desde && ! $hasta, fn ($q) => $q->where('fecha', '>=', now()->subDays(45)->format('Y-m-d')))
            ->limit(500)
            ->get();

        if ($traslados->isEmpty()) {
            return;
        }

        $lineas = \App\Models\ConciliacionLinea::query()
            ->whereIn('flujo_caja_id', $traslados->pluck('id'))
            ->where('estado', 'conciliado')
            ->get()
            ->groupBy('flujo_caja_id');

        foreach ($traslados as $traslado) {
            $tieneSalida = false;
            foreach ($lineas->get($traslado->id, collect()) as $linea) {
                if ($matcher->ladoTraslado($linea, $traslado) === 'salida') {
                    $tieneSalida = true;
                    break;
                }
            }
            if ($tieneSalida) {
                $traslado->es_conciliado = true;
                $traslado->save();
            }
        }
    }

    public function updateCuenta(Request $request, $id) {
        $cuenta = \App\Models\CuentaBancaria::findOrFail($id);
        $field = $request->input('field');
        $value = $request->input('value');
        
        if (in_array($field, ['bs_tc', 'bs_disponibles', 'usd_tc', 'usd_disp', 'reporte_bs', 'reporte_usd', 'reporte_bs_fin', 'reporte_usd_fin'])) {
            // Normalizar: quitar puntos de miles y convertir coma decimal a punto
            if (is_string($value) && $value !== '') {
                if (strpos($value, '.') !== false && strpos($value, ',') !== false) {
                    $value = str_replace('.', '', $value);
                    $value = str_replace(',', '.', $value);
                } else {
                    $value = str_replace(',', '.', $value);
                }
            }
            $value = is_numeric($value) ? (float)$value : 0;
            $cuenta->$field = $value;

            
            // Auto calc USD variables
            $resumen = \App\Models\FinanzasResumen::where('fecha', date('Y-m-d'))->first();
            $tasa = $resumen ? ($resumen->tasa_bcv_usd ?: 1) : 1;
            
            if ($field === 'reporte_bs') {
                $cuenta->bs_disponibles = $value ?: 0;
                $cuenta->reporte_bs_fin = $value ?: 0; // Inicio alimenta Flujo y Fin
                if ($tasa > 0) {
                    $cuenta->usd_disp = round($cuenta->bs_disponibles / $tasa, 2);
                    $cuenta->reporte_usd = $cuenta->usd_disp;
                    $cuenta->reporte_usd_fin = $cuenta->usd_disp;
                }
            } elseif ($field === 'bs_disponibles') {
                $cuenta->reporte_bs_fin = $value ?: 0; // Flujo alimenta Fin
                if ($tasa > 0) {
                    $cuenta->usd_disp = round($cuenta->bs_disponibles / $tasa, 2);
                    $cuenta->reporte_usd_fin = $cuenta->usd_disp;
                }
            } elseif ($field === 'reporte_bs_fin') {
                $cuenta->bs_disponibles = $value ?: 0; // Fin alimenta Flujo
                if ($tasa > 0) {
                    $cuenta->usd_disp = round($cuenta->bs_disponibles / $tasa, 2);
                    $cuenta->reporte_usd_fin = $cuenta->usd_disp;
                }
            } elseif ($field === 'reporte_usd') {
                $cuenta->usd_disp = $value ?: 0;
                $cuenta->reporte_usd_fin = $value ?: 0;
            } elseif ($field === 'reporte_usd_fin') {
                $cuenta->usd_disp = $value ?: 0;
            } elseif ($field === 'usd_disp') {
                $cuenta->reporte_usd_fin = $value ?: 0;
            }
            
            $cuenta->save();
            return response()->json([
                'success' => true,
                'reporte_usd' => $cuenta->reporte_usd,
                'reporte_usd_fin' => $cuenta->reporte_usd_fin,
                'usd_disp' => $cuenta->usd_disp
            ]);
        }
        return response()->json(['success' => false, 'message' => 'Invalid field'], 400);
    }

    public function updateResumen(Request $request, $id) {
        $resumen = \App\Models\FinanzasResumen::findOrFail($id);
        $field = $request->input('field');
        $value = $request->input('value');
        
        $allowed = [
            'tasa_bcv_usd', 'saldo_inicial', 'queda_dia_anterior', 'porcentaje_total_diferencial',
            'tasa_paralelo', 'bloqueado_compra_divisas', 'fondos_no_disponibles',
            'titulos_cobertura_espera', 'titulos_cobertura_aprobados', 'retenido_pagos_planificados',
            'compromisos_pago_bs', 'compromisos_pago_usd', 'solicitudes_cobertura', 'retenido_pagos'
        ];

        if (in_array($field, $allowed)) {
            // Normalizar el valor: quitar puntos de miles y convertir coma decimal a punto
            // Ej: "744,6" → "744.6", "68.500,00" → "68500.00"
            if (is_string($value) && $value !== '') {
                // Si tiene punto Y coma, el punto es separador de miles y la coma es decimal
                if (strpos($value, '.') !== false && strpos($value, ',') !== false) {
                    $value = str_replace('.', '', $value);  // quitar puntos de miles
                    $value = str_replace(',', '.', $value); // coma → punto decimal
                } else {
                    // Solo tiene coma: es decimal
                    $value = str_replace(',', '.', $value);
                }
            }
            $resumen->$field = is_numeric($value) ? (float)$value : 0;
            $resumen->save();

            if ($field === 'tasa_bcv_usd') {
                $this->bcvRate->forgetTodayCache();
            }
            
            if ($field === 'tasa_bcv_usd' && $resumen->tasa_bcv_usd > 0) {
                $cuentas = \App\Models\CuentaBancaria::whereIn('categoria_reporte', [
                    'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO',
                    'BANCA NACIONAL - BAJO MOVIMIENTO'
                ])->get();
                
                foreach ($cuentas as $cuenta) {
                    $cuenta->reporte_usd = round($cuenta->reporte_bs / $resumen->tasa_bcv_usd, 2);
                    $cuenta->reporte_usd_fin = round($cuenta->reporte_bs_fin / $resumen->tasa_bcv_usd, 2);
                    $cuenta->usd_disp = $cuenta->reporte_usd; 
                    $cuenta->save();
                }
            }

            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false, 'message' => 'Invalid field'], 400);
    }

    public function reporteConsolidado()
    {
        $categoriasConsolidado = [
            'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO',
            'BANCA NACIONAL - BAJO MOVIMIENTO',
            'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS OPERATIVOS',
            'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS NO OPERATIVOS',
            'BANCA INTERNACIONAL / BILLETERAS',
            'BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS',
        ];

        $cuentas = \App\Models\CuentaBancaria::whereIn('categoria_reporte', $categoriasConsolidado)
            ->orderBy('orden')
            ->get()
            ->filter(function ($cuenta) {
                $cat = (string) $cuenta->categoria_reporte;
                if (in_array($cat, [
                    'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO',
                    'BANCA NACIONAL - BAJO MOVIMIENTO',
                ], true)) {
                    return (bool) $cuenta->mostrar_en_principal;
                }

                return true;
            })
            ->groupBy('categoria_reporte');
        
        $resumen = \App\Models\FinanzasResumen::firstOrCreate(
            ['fecha' => date('Y-m-d')],
            [
                'tasa_bcv_usd' => $this->getTasaBcvDelDia(),
                'tasa_paralelo' => 738.50,
                'saldo_inicial' => 0,
                'queda_dia_anterior' => 0,
                'porcentaje_total_diferencial' => 0
            ]
        );

        $planificacion = \App\Models\PlanificacionPago::orderBy('orden')->get();

        return view('finanzas.reporte_consolidado', compact('cuentas', 'resumen', 'planificacion'));
    }

    public function updatePlanificacion(Request $request, $id)
    {
        $plan = \App\Models\PlanificacionPago::findOrFail($id);
        $field = $request->input('field');
        $value = $request->input('value');
        $plan->$field = $value;
        $plan->save();
        return response()->json(['success' => true]);
    }

    public function ocrReceipt(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            return response()->json(['error' => 'API Key de Gemini no configurada en .env (GEMINI_API_KEY)'], 500);
        }

        $image = $request->file('image');
        $base64 = base64_encode(file_get_contents($image->getRealPath()));
        $mimeType = $image->getMimeType();

        $prompt = "Eres un asistente financiero experto. Extrae los datos de este recibo o movimiento bancario y devuelve SOLO un objeto JSON, sin formato markdown ni texto adicional. Si no encuentras un dato, envíalo como null. " .
                  "Estructura esperada: " .
                  "{ \"fecha\": \"YYYY-MM-DD\", \"monto_bs\": number, \"referencia\": \"solo números o texto de ref\", \"banco_titular_hint\": \"texto\", \"motivo\": \"texto breve de lo pagado\" }. " .
                  "Asume que los montos son en Bolívares (Bs) a menos que se indique explícitamente lo contrario.";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $base64
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'response_mime_type' => 'application/json'
            ]
        ];

        try {
            $maxRetries = 3;
            $attempt = 0;
            $response = null;

            while ($attempt < $maxRetries) {
                $response = \Illuminate\Support\Facades\Http::withoutVerifying()->timeout(120)
                    ->withHeaders(['Content-Type' => 'application/json', 'x-goog-api-key' => $apiKey])
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent", $payload);

                if ($response->successful()) {
                    break;
                }

                // If error is 503 or 429 (high demand / rate limit), wait and retry
                if (in_array($response->status(), [503, 429])) {
                    $attempt++;
                    if ($attempt < $maxRetries) {
                        sleep(3); // wait 3 seconds before retrying
                        continue;
                    }
                }
                
                // If it's another error or max retries reached
                $errorDetail = $response->json()['error']['message'] ?? json_encode($response->json());
                return response()->json(['error' => 'API de Gemini: ' . $errorDetail], 500);
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
            
            // Clean markdown if present
            $text = trim($text);
            if (str_starts_with($text, '```json')) {
                $text = substr($text, 7);
                if (str_ends_with($text, '```')) {
                    $text = substr($text, 0, -3);
                }
            }

            $json = json_decode($text, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json(['error' => 'Error al decodificar JSON de Gemini', 'raw' => $text], 500);
            }

            return response()->json($json);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Excepción al procesar OCR: ' . $e->getMessage()], 500);
        }
    }

    public function ocrSaldos(Request $request)
    {
        $request->validate(['image' => 'required|image']);
        $image = $request->file('image');
        $apiKey = env('GEMINI_API_KEY');

        if (!$apiKey) {
            return response()->json(['error' => 'API Key de Gemini no configurada'], 500);
        }

        $base64 = base64_encode(file_get_contents($image->getRealPath()));
        $mimeType = $image->getMimeType();

        $prompt = "Eres un asistente financiero experto. Extrae los saldos de cada cuenta bancaria que aparezca en este reporte (Excel o tabla). " .
                  "Devuelve SOLO un ARREGLO JSON de objetos, sin formato markdown ni texto adicional. " .
                  "Estructura esperada por cada cuenta: " .
                  "{ \"banco\": \"texto en mayusculas\", \"titular\": \"texto en mayusculas\", \"bs\": numero }. " .
                  "Busca las columnas de BANCO, TITULAR y BS. " .
                  "Ejemplo: [{\"banco\": \"BANESCO\", \"titular\": \"GRUPO JRZ\", \"bs\": 1500.50}].";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $base64
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'response_mime_type' => 'application/json'
            ]
        ];

        try {
            $maxRetries = 3;
            $attempt = 0;
            $response = null;

            while ($attempt < $maxRetries) {
                $response = \Illuminate\Support\Facades\Http::withoutVerifying()->timeout(120)
                    ->withHeaders(['Content-Type' => 'application/json', 'x-goog-api-key' => $apiKey])
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent", $payload);

                if ($response->successful()) {
                    break;
                }

                if (in_array($response->status(), [503, 429])) {
                    $attempt++;
                    if ($attempt < $maxRetries) {
                        sleep(3);
                        continue;
                    }
                }
                
                $errorDetail = $response->json()['error']['message'] ?? json_encode($response->json());
                return response()->json(['error' => 'API de Gemini: ' . $errorDetail], 500);
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '[]';
            
            $text = trim($text);
            if (str_starts_with($text, '```json')) {
                $text = substr($text, 7);
                if (str_ends_with($text, '```')) {
                    $text = substr($text, 0, -3);
                }
            }

            $json = json_decode($text, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json(['error' => 'Error al decodificar JSON de Gemini', 'raw' => $text], 500);
            }

            return response()->json($json);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Excepción al procesar OCR de saldos: ' . $e->getMessage()], 500);
        }
    }

    public function resetDaily() {
        // 1. Ocultar todos los movimientos de caja de hoy en lugar de borrarlos
        \App\Models\FlujoCaja::where('fecha', date('Y-m-d'))->update(['oculto' => true]);

        // 2. Reiniciar todas las cuentas bancarias a 0, EXCEPTUANDO algunas categorias
        $excluidas = [
            'BANCA NACIONAL / TARJETAS MONEDA EXTRANJERA',
            'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS OPERATIVOS',
            'BANCA NACIONAL MONEDA EXTRANJERA - FONDOS NO OPERATIVOS',
            'BANCA INTERNACIONAL / BILLETERAS',
            'CUENTAS INTERNACIONALES CERRADAS (FONDOS POR LIBERAR)',
            'BANCA INTERNACIONAL - CUENTAS NO OPERATIVAS',
            'TARJETAS INTERNACIONALES DE TERCEROS',
        ];

        \App\Models\CuentaBancaria::whereNotIn('categoria_reporte', $excluidas)
            ->orWhereNull('categoria_reporte')
            ->update([
                'bs_tc' => 0,
                'bs_disponibles' => 0,
                'usd_tc' => 0,
                'usd_disp' => 0,
                'reporte_bs' => 0,
                'reporte_usd' => 0,
                'reporte_bs_fin' => 0,
                'reporte_usd_fin' => 0,
            ]);

        // 3. No borrar el historial (sin truncate). Solo resetear saldos de hoy y resincronizar
        $resumen = \App\Models\FinanzasResumen::where('fecha', date('Y-m-d'))->first();
        $this->syncTotalesSalidas(date('Y-m-d'));

        return redirect()->back()->with('success', 'Disponibilidad y datos iniciales del día de hoy reseteados correctamente.');
    }

    public function gastosFijos()
    {
        $mesActual = (int) date('n'); // 1-12
        $nombresMeses = GastoFijoPendienteService::NOMBRES_MESES;

        $tablas = [
            0 => [
                'titulo' => 'GASTOS FIJOS GRUPO INMOBILIARIO Y DE TRANSPORTE JE NU & ASOCIADOS, C.A. 2026',
                'titulo_corto' => 'GRUPO INMOBILIARIO Y DE TRANSPORTE JE NU & ASOCIADOS, C.A.',
                'tiene_sede' => false,
                'filas' => []
            ],
            1 => [
                'titulo' => 'GASTO FIJOS GRUPO PALACIO DE LOS DETALLES / NUNES STORE / EURONISSI 2026',
                'titulo_corto' => 'GRUPO PALACIO DE LOS DETALLES / NUNES STORE / EURONISSI',
                'tiene_sede' => true,
                'filas' => []
            ],
            2 => [
                'titulo' => 'GASTOS FIJOS DIRECTIVO 2026',
                'titulo_corto' => 'GASTOS FIJOS DIRECTIVO',
                'tiene_sede' => false,
                'filas' => []
            ],
        ];

        $gastos = \App\Models\GastoFijo::where('visible', true)
            ->orderBy('grupo_id')
            ->orderBy('orden')
            ->orderBy('id')
            ->with(['pagos' => function($q) {
                $q->where('anio', (int) date('Y'));
            }])
            ->get();

        foreach ($gastos as $gasto) {
            if (!isset($tablas[$gasto->grupo_id])) continue;

            $meses = array_fill(0, 12, null);
            $pagosMap = $gasto->pagos->keyBy('mes_idx');

            for ($m = 0; $m < 12; $m++) {
                if (isset($pagosMap[$m]) && $pagosMap[$m]->monto !== null) {
                    $meses[$m] = (float) $pagosMap[$m]->monto;
                }
            }

            $tablas[$gasto->grupo_id]['filas'][] = [
                'id' => $gasto->id,
                'sede' => $gasto->sede,
                'servicio' => $gasto->servicio,
                'fecha' => $gasto->fecha,
                'empresa' => $gasto->empresa,
                'costo' => (float) $gasto->costo,
                'meses' => $meses,
                'pagos_models' => $gasto->pagos // Pass the actual models for notifications
            ];
        }

        // ── GENERAR NOTIFICACIONES (siguen visibles hasta marcar pagado) ──
        $notificaciones = [];
        $tablaLabels = ['Grupo Inmobiliario', 'Palacio/Nunes/Euronissi', 'Directivo'];

        foreach ($tablas as $tIdx => $tabla) {
            foreach ($tabla['filas'] as $fila) {
                foreach ($this->gastosFijosPendientes->periodosPendientes((string) $fila['fecha'], (float) $fila['costo'], $fila['pagos_models']) as $periodo) {
                    $notificaciones[] = [
                        'tipo' => $periodo['tipo'],
                        'servicio' => $fila['servicio'],
                        'empresa' => $fila['empresa'],
                        'costo' => $fila['costo'],
                        'fecha' => $fila['fecha'],
                        'dia' => $periodo['dia'],
                        'mes_idx' => $periodo['mes_idx'],
                        'mes_nombre' => $periodo['mes_nombre'],
                        'tabla' => $tablaLabels[$tIdx],
                        'tabla_idx' => $tIdx,
                        'fila_idx' => $fila['id'],
                        'gasto_fijo_id' => $fila['id'],
                        'urgente' => $periodo['urgente'],
                    ];
                }
            }
        }

        usort($notificaciones, function ($a, $b) {
            $urgencia = ($b['urgente'] ?? false) <=> ($a['urgente'] ?? false);
            if ($urgencia !== 0) {
                return $urgencia;
            }

            return ($a['mes_idx'] ?? 0) <=> ($b['mes_idx'] ?? 0);
        });

        return view('finanzas.gastos_fijos', compact('tablas', 'mesActual', 'nombresMeses', 'notificaciones'));
    }

    /**
     * Returns the list of pending gastos fijos (those with active notifications)
     * for the egreso modal dropdown to link payments.
     */
    public function getGastosFijosParaVincular()
    {
        $tablaLabels = [
            0 => 'Grupo Inmobiliario',
            1 => 'Palacio/Nunes/Euronissi',
            2 => 'Directivo',
        ];

        $gastos = \App\Models\GastoFijo::where('visible', true)
            ->orderBy('grupo_id')->orderBy('orden')->orderBy('id')
            ->with(['pagos' => function ($q) {
                $q->where('anio', (int) date('Y'));
            }])
            ->get();

        $pendientes = [];
        foreach ($gastos as $gasto) {
            $periodos = $this->gastosFijosPendientes->periodosPendientes((string) $gasto->fecha, (float) $gasto->costo, $gasto->pagos);
            if ($periodos === []) {
                continue;
            }

            $masUrgente = collect($periodos)->sortByDesc('urgente')->first();

            $pendientes[] = [
                'id'           => $gasto->id,
                'servicio'     => $gasto->servicio,
                'empresa'      => $gasto->empresa,
                'sede'         => $gasto->sede,
                'costo'        => (float) $gasto->costo,
                'grupo_id'     => $gasto->grupo_id,
                'tabla_label'  => $tablaLabels[$gasto->grupo_id] ?? 'Otro',
                'fecha'        => $gasto->fecha,
                'urgente'      => (bool) ($masUrgente['urgente'] ?? false),
                'mes_idx'      => $masUrgente['mes_idx'] ?? ((int) date('n') - 1),
            ];
        }

        return response()->json($pendientes);
    }

    public function agregarGastoFijo(Request $request)
    {
        $request->validate([
            'tabla_idx' => 'required|integer|min:0|max:2',
            'sede'      => 'nullable|string|max:200',
            'servicio'  => 'required|string|max:200',
            'fecha'     => 'nullable|string|max:100',
            'empresa'   => 'nullable|string|max:200',
            'costo'     => 'nullable|numeric|min:0',
        ]);

        $gasto = \App\Models\GastoFijo::create([
            'grupo_id'  => $request->tabla_idx,
            'sede'      => $request->sede ?? '',
            'servicio'  => $request->servicio,
            'fecha'     => $request->fecha ?? '',
            'empresa'   => $request->empresa ?? '',
            'costo'     => $request->costo ?? 0,
            'orden'     => 9999,
            'visible'   => true,
        ]);

        return response()->json(['ok' => true, 'id' => $gasto->id]);
    }

    public function eliminarGastoFijoFila(Request $request)
    {
        $request->validate([
            'gasto_fijo_id' => 'required|integer',
        ]);

        $gasto = \App\Models\GastoFijo::find($request->gasto_fijo_id);
        if ($gasto) {
            $gasto->visible = false;
            $gasto->save();
        }

        return response()->json(['ok' => true]);
    }

    public function updateGastoFijoMonto(Request $request)
    {
        $request->validate([
            'gasto_fijo_id' => 'required|integer',
            'mes_idx' => 'required|integer|min:0|max:11',
            'monto' => 'nullable|numeric|min:0',
        ]);

        $montoVal = $request->monto !== null && $request->monto !== '' ? $request->monto : null;

        $pago = \App\Models\GastoFijoPago::firstOrNew([
            'gasto_fijo_id' => $request->gasto_fijo_id,
            'mes_idx' => $request->mes_idx,
            'anio' => (int) date('Y'),
        ]);

        $pago->monto = $montoVal;

        if ($montoVal !== null) {
            $pago->pagado = true;
            if (!$pago->pagado_at) {
                $pago->pagado_at = now();
            }
        } else {
            $pago->pagado = false;
            $pago->pagado_at = null;
        }

        $pago->save();

        return response()->json(['ok' => true, 'monto' => $pago->monto]);
    }

    public function marcarGastoFijoPagado(Request $request)
    {
        $request->validate([
            'gasto_fijo_id' => 'required|integer',
            'costo' => 'nullable|numeric|min:0',
            'mes_idx' => 'nullable|integer|min:0|max:11',
        ]);

        $mesActual = (int) date('n');
        $mesIdx = $request->filled('mes_idx') ? (int) $request->mes_idx : $mesActual - 1;

        $pago = \App\Models\GastoFijoPago::updateOrCreate(
            [
                'gasto_fijo_id' => $request->gasto_fijo_id,
                'mes_idx' => $mesIdx,
                'anio' => (int) date('Y'),
            ],
            [
                'pagado' => true,
                'pagado_at' => now(),
            ]
        );

        if ($request->has('costo') && $request->costo > 0) {
            $pago->monto = $request->costo;
            $pago->save();
        }

        return response()->json(['ok' => true]);
    }

    public function updateGastoFijoFecha(Request $request)
    {
        $request->validate([
            'gasto_fijo_id' => 'required|integer',
            'fecha' => 'required|string|max:100',
        ]);

        $gasto = \App\Models\GastoFijo::find($request->gasto_fijo_id);
        if ($gasto) {
            $gasto->fecha = $request->fecha;
            $gasto->save();
        }

        return response()->json(['ok' => true]);
    }

    public function updateGastoFijoCosto(Request $request)
    {
        $request->validate([
            'gasto_fijo_id' => 'required|integer',
            'costo' => 'nullable|numeric|min:0',
        ]);

        $gasto = \App\Models\GastoFijo::find($request->gasto_fijo_id);
        if ($gasto) {
            $gasto->costo = $request->costo;
            $gasto->save();
        }

        return response()->json(['ok' => true]);
    }

    public function updateGastoFijoCampo(Request $request)
    {
        $request->validate([
            'gasto_fijo_id' => 'required|integer',
            'campo'         => 'required|in:servicio,empresa',
            'valor'         => 'nullable|string|max:255',
        ]);

        $gasto = \App\Models\GastoFijo::find($request->gasto_fijo_id);
        if ($gasto) {
            $campo = $request->campo;
            $gasto->$campo = $request->valor ?? null;
            $gasto->save();
        }

        return response()->json(['ok' => true]);
    }

    public function reporteFlujoCajaBusqueda(Request $request) {
        $data = $this->datosReporteFlujoCajaBusqueda($request);
        $formato = strtolower((string) $request->query('formato', 'pdf'));
        $base = 'Reporte_Flujo_Caja_'.$data['fecha_desde'].'_al_'.$data['fecha_hasta'];

        if ($formato === 'xlsx') {
            $xlsx = $this->xlsxReporteFlujoCaja($data);

            return response($xlsx, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$base.'.xlsx"',
            ]);
        }

        if ($formato === 'zip') {
            $archivos = [];
            $grupoEgresos = array_values(array_intersect($data['selected_cats'], ['egreso_realizado', 'otros_egresos']));
            $grupoMovimientos = array_values(array_intersect($data['selected_cats'], ['traslados', 'egreso_divisas']));

            if ($grupoEgresos !== []) {
                $archivos[$base.'_egresos.pdf'] = $this->pdfReporteFlujoCaja($data, $grupoEgresos, 'Egresos')->output();
            }
            if ($grupoMovimientos !== []) {
                $dataExcel = $data;
                $dataExcel['selected_cats'] = $grupoMovimientos;
                $archivos[$base.'_traslados_divisas.xlsx'] = $this->xlsxReporteFlujoCaja($dataExcel);
            }

            $zip = \App\Support\SimpleXlsxWriter::zipFiles($archivos);

            return response($zip, 200, [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="'.$base.'_pdf_y_excel.zip"',
            ]);
        }

        $pdf = $this->pdfReporteFlujoCaja($data, $data['selected_cats']);

        return $pdf->download($base.'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function datosReporteFlujoCajaBusqueda(Request $request): array
    {
        $fecha_desde = $request->query('desde', date('Y-m-d'));
        $fecha_hasta = $request->query('hasta', date('Y-m-d'));
        $q = strtolower(trim($request->query('q', '')));
        $ref = $this->normalizarReferenciaBusqueda((string) $request->query('ref', ''));
        $cats_str = $request->query('cats', '');
        $selected_cats = $cats_str ? explode(',', $cats_str) : ['egreso_realizado', 'otros_egresos', 'traslados', 'egreso_divisas', 'compra_divisas'];
        $catsFlujo = array_values(array_intersect($selected_cats, ['egreso_realizado', 'otros_egresos', 'traslados', 'egreso_divisas']));

        $movimientos_query = \App\Models\FlujoCaja::whereBetween('fecha', [$fecha_desde, $fecha_hasta])
                                ->where('oculto', false)
                                ->vigentes()
                                ->when($catsFlujo !== [], fn ($q) => $q->whereIn('categoria_egreso', $catsFlujo), fn ($q) => $q->whereRaw('1 = 0'))
                                ->orderBy('fecha', 'desc')
                                ->orderBy('id');

        $movimientos = $movimientos_query->get();

        if ($q) {
            $movimientos = $movimientos->filter(function($m) use ($q) {
                $banco = strtolower($m->banco ?? '');
                $titular = strtolower($m->titular ?? '');
                $receptor_banco = strtolower($m->banco_receptor ?? '');
                $receptor_titular = strtolower($m->titular_receptor ?? '');
                $motivo = strtolower($m->motivo ?? '');
                $tipo = strtolower($m->tipo_gasto ?? '');
                $referencia = strtolower($m->referencia ?? '');
                $sede = strtolower($m->sede ?? '');
                $placa = strtolower($m->placa_vehiculo ?? '');

                return strpos($banco, $q) !== false ||
                       strpos($titular, $q) !== false ||
                       strpos($receptor_banco, $q) !== false ||
                       strpos($receptor_titular, $q) !== false ||
                       strpos($motivo, $q) !== false ||
                       strpos($tipo, $q) !== false ||
                       strpos($referencia, $q) !== false ||
                       strpos($sede, $q) !== false ||
                       strpos($placa, $q) !== false;
            });
        }
        if ($ref !== '') {
            $movimientos = $movimientos->filter(
                fn ($m) => str_contains($this->normalizarReferenciaBusqueda((string) ($m->referencia ?? '')), $ref)
            );
        }

        $egresos = $movimientos->where('categoria_egreso', 'egreso_realizado');
        $otros = $movimientos->where('categoria_egreso', 'otros_egresos');
        $traslados = $movimientos->where('categoria_egreso', 'traslados');
        $divisas = $movimientos->where('categoria_egreso', 'egreso_divisas');
        $compras = $this->comprasDivisasReporte($fecha_desde, $fecha_hasta, $q, $selected_cats);
        if ($ref !== '') {
            $compras = $compras->filter(
                fn ($c) => str_contains($this->normalizarReferenciaBusqueda((string) ($c->referencia ?? '')), $ref)
            )->values();
        }

        return [
            'fecha_desde' => $fecha_desde,
            'fecha_hasta' => $fecha_hasta,
            'q' => $q,
            'selected_cats' => $selected_cats,
            'egresos' => $egresos,
            'otros' => $otros,
            'traslados' => $traslados,
            'divisas' => $divisas,
            'compras' => $compras,
            'tot_egresos_usd' => $egresos->sum('monto_usd'),
            'tot_egresos_bs' => $egresos->sum('monto_bs'),
            'tot_egresos_dif' => $egresos->sum('diferencial_cambiario'),
            'tot_egresos_com' => $egresos->sum('comision'),
            'tot_otros_usd' => $otros->sum('monto_usd'),
            'tot_otros_bs' => $otros->sum('monto_bs'),
            'tot_otros_dif' => $otros->sum('diferencial_cambiario'),
            'tot_otros_com' => $otros->sum('comision'),
            'tot_traslados_usd' => $traslados->sum('monto_usd'),
            'tot_traslados_bs' => $traslados->sum('monto_bs'),
            'tot_traslados_com' => $traslados->sum('comision'),
            'tot_divisas_usd' => $divisas->sum('monto_usd'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $cats
     */
    private function pdfReporteFlujoCaja(array $data, array $cats, string $tituloExtra = '')
    {
        $payload = $data;
        $payload['selected_cats'] = $cats;
        $payload['titulo_extra'] = $tituloExtra;

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('finanzas.pdf.reporte_flujo_busqueda', ['data' => $payload])
            ->setPaper('a4', 'landscape');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function xlsxReporteFlujoCaja(array $data): string
    {
        $hojas = [];
        $secciones = [
            'egreso_realizado' => ['Egresos Realizados', $data['egresos'], 'egresos'],
            'otros_egresos' => ['Otros Egresos', $data['otros'], 'egresos'],
            'egreso_divisas' => ['Egresos Divisas', $data['divisas'], 'divisas'],
            'compra_divisas' => ['Compra Divisas', $data['compras'] ?? collect(), 'compras'],
            'traslados' => ['Traslados', $data['traslados'], 'traslados'],
        ];

        foreach ($secciones as $cat => [$titulo, $rows, $tipo]) {
            if (! in_array($cat, $data['selected_cats'], true)) {
                continue;
            }
            $cuerpo = match ($tipo) {
                'divisas' => $this->filasExcelDivisas($rows),
                'compras' => $this->filasExcelCompras($rows),
                'traslados' => $this->filasExcelTraslados($rows),
                default => $this->filasExcelEgresos($rows),
            };
            $hojas[$titulo] = count($cuerpo) === 1 ? [$cuerpo[0], ['Sin registros']] : $cuerpo;
        }

        return \App\Support\SimpleXlsxWriter::toString($hojas);
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\CompraDivisa>
     */
    /** Igual que en la pantalla: sin espacios, guiones ni mayúsculas. */
    private function normalizarReferenciaBusqueda(string $valor): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', strtolower(trim($valor)));
    }

    private function comprasDivisasReporte(string $desde, string $hasta, string $q, array $selectedCats)
    {
        if (! in_array('compra_divisas', $selectedCats, true) || ! \Illuminate\Support\Facades\Schema::hasTable('compra_divisas')) {
            return collect();
        }

        $compras = \App\Models\CompraDivisa::query()
            ->whereBetween('fecha', [$desde, $hasta])
            ->orderByDesc('fecha')
            ->orderBy('id')
            ->get();

        if ($q === '') {
            return $compras;
        }

        return $compras->filter(function ($m) use ($q) {
            $texto = strtolower(implode(' ', [
                $m->banco ?? '',
                $m->titular ?? '',
                $m->motivo ?? '',
                $m->concepto ?? '',
                $m->referencia ?? '',
            ]));

            return str_contains($texto, $q);
        })->values();
    }

    /**
     * @param  iterable<int, object>  $rows
     * @return list<list<string|float>>
     */
    private function filasExcelEgresos(iterable $rows): array
    {
        $cuerpo = [[
            'Fecha', 'Banco', 'Titular', 'Beneficiario', 'Tipo gasto', 'Motivo', 'Referencia',
            'Sede', 'Placa', 'USD', 'Tasa cambio', 'Dif. cambiario', 'Bs', 'Comisión',
            'TodoTicket recarga', 'TodoTicket comisión', 'TodoTicket IVA', 'TodoTicket total real',
            'Línea', 'Cédula desglose', 'Sede desglose', 'Tipo gasto desglose', 'USD desglose', 'Bs desglose',
        ]];

        foreach ($rows as $mov) {
            $tt = is_array($mov->detalle_todoticket ?? null) ? $mov->detalle_todoticket : [];
            $cuerpo[] = [
                $this->fechaExcel($mov->fecha ?? null),
                $mov->banco ?: '',
                $mov->titular ?: '',
                $mov->titular_receptor ?: '',
                $mov->tipo_gasto ?: '',
                $mov->motivo ?: '',
                $mov->referencia ?: '',
                $mov->sede ?: '',
                $mov->placa_vehiculo ?: '',
                round((float) $mov->monto_usd, 2),
                round((float) $mov->tasa_cambio, 2),
                round((float) $mov->diferencial_cambiario, 2),
                round((float) $mov->monto_bs, 2),
                round((float) $mov->comision, 2),
                round((float) ($tt['recarga'] ?? 0), 2),
                round((float) ($tt['comision'] ?? 0), 2),
                round((float) ($tt['iva'] ?? 0), 2),
                round((float) ($tt['total_real'] ?? 0), 2),
                'Movimiento',
                '', '', '', '', '',
            ];

            foreach (is_array($mov->desglose ?? null) ? $mov->desglose : [] as $item) {
                $cuerpo[] = [
                    $this->fechaExcel($mov->fecha ?? null),
                    $mov->banco ?: '',
                    $mov->titular ?: '',
                    $mov->titular_receptor ?: '',
                    $mov->tipo_gasto ?: '',
                    $mov->motivo ?: '',
                    $mov->referencia ?: '',
                    $mov->sede ?: '',
                    $mov->placa_vehiculo ?: '',
                    '', '', '', '', '', '', '', '', '',
                    'Desglose',
                    $item['cedula'] ?? '',
                    $item['sede'] ?? '',
                    $item['tipo_gasto'] ?? '',
                    round((float) ($item['monto_usd'] ?? 0), 2),
                    round((float) ($item['monto'] ?? 0), 2),
                ];
            }
        }

        return $cuerpo;
    }

    /**
     * @param  iterable<int, object>  $rows
     * @return list<list<string|float>>
     */
    private function filasExcelDivisas(iterable $rows): array
    {
        $cuerpo = [[
            'Fecha', 'Banco', 'Titular', 'Tipo gasto', 'Motivo', 'Referencia', 'Sede', 'Placa', 'Monto USD',
        ]];
        foreach ($rows as $mov) {
            $cuerpo[] = [
                $this->fechaExcel($mov->fecha ?? null),
                $mov->banco ?: '',
                $mov->titular ?: '',
                $mov->tipo_gasto ?: '',
                $mov->motivo ?: '',
                $mov->referencia ?: '',
                $mov->sede ?: '',
                $mov->placa_vehiculo ?: '',
                round((float) $mov->monto_usd, 2),
            ];
        }

        return $cuerpo;
    }

    /**
     * @param  iterable<int, object>  $rows
     * @return list<list<string|float>>
     */
    private function filasExcelTraslados(iterable $rows): array
    {
        $cuerpo = [[
            'Fecha', 'Banco emisor', 'Titular emisor', 'Banco receptor', 'Titular receptor',
            'Motivo', 'Referencia', 'Comisión', 'Monto USD', 'Monto BS',
        ]];
        foreach ($rows as $mov) {
            $cuerpo[] = [
                $this->fechaExcel($mov->fecha ?? null),
                $mov->banco ?: '',
                $mov->titular ?: '',
                $mov->banco_receptor ?: '',
                $mov->titular_receptor ?: '',
                $mov->motivo ?: '',
                $mov->referencia ?: '',
                round((float) $mov->comision, 2),
                round((float) $mov->monto_usd, 2),
                round((float) $mov->monto_bs, 2),
            ];
        }

        return $cuerpo;
    }

    /**
     * @param  iterable<int, object>  $rows
     * @return list<list<string|float>>
     */
    private function filasExcelCompras(iterable $rows): array
    {
        $cuerpo = [[
            'Fecha', 'Banco', 'Titular', 'Referencia', 'Motivo', 'Tasa cambio', 'Monto BS', 'Monto USD',
        ]];
        foreach ($rows as $mov) {
            $cuerpo[] = [
                $this->fechaExcel($mov->fecha ?? null),
                $mov->banco ?: '',
                $mov->titular ?: '',
                $mov->referencia ?: '',
                ($mov->motivo ?: $mov->concepto) ?: '',
                round((float) ($mov->tasa_cambio ?? 0), 2),
                round((float) $mov->monto_bs, 2),
                round((float) $mov->monto_usd, 2),
            ];
        }

        return $cuerpo;
    }

    private function fechaExcel(mixed $fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '';
        }
        $texto = $fecha instanceof \DateTimeInterface ? $fecha->format('Y-m-d') : (string) $fecha;
        $ts = strtotime($texto);

        return $ts ? date('d/m/Y', $ts) : $texto;
    }

    public function parseArchivoDesglose(Request $request)
    {
        try {
            $request->validate([
                'archivo' => 'required|file|mimes:xlsx,xls,csv,xlsm,txt|max:5120',
            ]);

            $file = $request->file('archivo');
            $ext = strtolower($file->getClientOriginalExtension());
            $path = $file->getRealPath();

            if ($ext === 'txt') {
                $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $result = [];
                $cedulas = [];
                $parsedLines = [];
                foreach ($lines as $line) {
                    if (strlen($line) >= 33) {
                        $cedRawStr = trim(substr($line, 0, 12));
                        $cedRaw = preg_replace('/[^0-9]/', '', $cedRawStr);
                        if (empty($cedRaw)) continue;

                        $montoStr = substr($line, 12, 21);
                        $monto = (float) $montoStr / 100;

                        if ($monto <= 0) continue;

                        $cedulas[] = $cedRaw;
                        $parsedLines[] = [
                            'cedRaw' => $cedRaw,
                            'monto' => $monto
                        ];
                    }
                }

                $clientesDb = \App\Models\Cliente::whereIn('cedula', $cedulas)->get()->keyBy('cedula');

                foreach ($parsedLines as $p) {
                    $cliente = $clientesDb->get($p['cedRaw']);
                    $nombre_mostrar = ($cliente && $cliente->nombre) ? $cliente->nombre : $p['cedRaw'];
                    $result[] = [
                        'cedula' => $nombre_mostrar,
                        'monto' => $p['monto'],
                    ];
                }

                if (empty($result)) {
                    return response()->json(['ok' => false, 'error' => 'No se encontraron datos válidos en el archivo TXT. Asegúrate de que tenga el formato correcto.']);
                }
                return response()->json(['ok' => true, 'data' => $result]);
            }

            $allSheetsRows = [];
            if ($ext === 'csv') {
                $rows = [];
                $handle = fopen($path, 'r');
                while (($data = fgetcsv($handle, 1000, ';')) !== false) {
                    if (count($data) === 1) { // Fallback if comma separated
                        $data = str_getcsv($data[0], ',');
                    }
                    $rows[] = $data;
                }
                fclose($handle);
                $allSheetsRows[] = $rows;
            } elseif ($ext === 'xls') {
                if ($xls = \App\Services\Conciliacion\LectorXls::parse($path)) {
                    foreach ($xls->sheetNames() as $index => $name) {
                        $allSheetsRows[] = $xls->rows($index);
                    }
                } else {
                    return response()->json(['ok' => false, 'error' => \Shuchkin\SimpleXLS::parseError()]);
                }
            } else {
                if ($xlsx = \Shuchkin\SimpleXLSX::parse($path)) {
                    foreach ($xlsx->sheetNames() as $index => $name) {
                        $allSheetsRows[] = $xlsx->rows($index);
                    }
                } else {
                    return response()->json(['ok' => false, 'error' => \Shuchkin\SimpleXLSX::parseError()]);
                }
            }

            if (empty($allSheetsRows)) {
                return response()->json(['ok' => false, 'error' => 'El archivo está vacío o no se pudo leer.']);
            }

            $colCedula = -1;
            $colMonto = -1;
            $startRow = -1;
            $targetRows = [];

            // Buscar en todas las hojas
            foreach ($allSheetsRows as $rows) {
                if (empty($rows)) continue;

                foreach ($rows as $rowIndex => $row) {
                    if ($rowIndex > 50) break; // Check first 50 rows
                    
                    $colCedula = -1;
                    $colMonto = -1;

                    $header = array_map(function($val) { 
                        // Clean whitespace and weird characters
                        $val = strtolower(trim((string)$val));
                        return preg_replace('/\s+/', ' ', $val); 
                    }, $row);

                    foreach ($header as $i => $colName) {
                        if ($colCedula === -1) {
                            if (strpos($colName, 'cédula del beneficiario') !== false || strpos($colName, 'cedula del beneficiario') !== false || $colName === 'cédula' || $colName === 'cedula' || strpos($colName, 'cedula') !== false || strpos($colName, 'beneficiario') !== false) {
                                $colCedula = $i;
                            }
                        }
                        if ($colMonto === -1) {
                            if (strpos($colName, 'monto a abonar') !== false || strpos($colName, 'monto') !== false || strpos($colName, 'abonar') !== false) {
                                $colMonto = $i;
                            }
                        }
                    }
                    if ($colCedula !== -1 && $colMonto !== -1 && $colCedula !== $colMonto) {
                        $startRow = $rowIndex + 1;
                        $targetRows = $rows;
                        break 2; // Break both loops
                    }
                }
                
                // reset if not found in this sheet
                $colCedula = -1;
                $colMonto = -1;
            }

            if ($colCedula === -1 || $colMonto === -1) {
                return response()->json(['ok' => false, 'error' => 'No se encontraron las columnas "Cédula del beneficiario" y "Monto a abonar" en ninguna de las hojas del archivo.']);
            }

            $result = [];
            $cedulas = [];
            for ($i = $startRow; $i < count($targetRows); $i++) {
                $row = $targetRows[$i];
                if (!isset($row[$colCedula])) continue;

                $cedRawStr = (string)$row[$colCedula];
                $cedRaw = preg_replace('/[^0-9]/', '', $cedRawStr);
                if (empty($cedRaw)) continue;

                $cedulas[] = $cedRaw;
            }

            $clientesDb = \App\Models\Cliente::whereIn('cedula', $cedulas)->get()->keyBy('cedula');

            for ($i = $startRow; $i < count($targetRows); $i++) {
                $row = $targetRows[$i];
                if (!isset($row[$colCedula])) continue;
                
                $cedRawStr = (string)$row[$colCedula];
                // Ignorar filas que parezcan encabezados repetidos
                if (strpos(strtolower($cedRawStr), 'cédula') !== false || strpos(strtolower($cedRawStr), 'cedula') !== false) continue;

                $cedRaw = preg_replace('/[^0-9]/', '', $cedRawStr);
                if (empty($cedRaw)) continue;

                $montoStr = (string)($row[$colMonto] ?? '0');
                
                // --- DEBUG LOGGING ---
                \Log::info("Row $i - CedulaRaw: '$cedRawStr' ($cedRaw), MontoRaw: '$montoStr'");

                // Clean monto string: remove symbols, but keep dots and commas
                $montoClean = preg_replace('/[^0-9,-.]/', '', $montoStr);
                // If comma is the decimal separator (e.g. 500,00)
                if (strpos($montoClean, ',') !== false && strpos($montoClean, '.') === false) {
                    $montoClean = str_replace(',', '.', $montoClean);
                } elseif (strpos($montoClean, ',') !== false && strpos($montoClean, '.') !== false) {
                    // If both are present, remove dot (thousands) and change comma to dot
                    $montoClean = str_replace('.', '', $montoClean);
                    $montoClean = str_replace(',', '.', $montoClean);
                }
                $monto = (float)$montoClean;

                \Log::info("Row $i - MontoClean: '$montoClean', MontoFloat: $monto");
                // ---------------------

                if ($monto <= 0) {
                    \Log::info("Row $i - Monto is <= 0. Skipped.");
                    continue;
                }

                $cliente = $clientesDb->get($cedRaw);
                if ($cliente && $cliente->nombre) {
                    $nombre_mostrar = $cliente->nombre;
                } else {
                    $nombre_mostrar = $cedRaw;
                }

                $result[] = [
                    'cedula' => $nombre_mostrar,
                    'monto' => $monto,
                ];
            }

            if (empty($result)) {
                return response()->json(['ok' => false, 'error' => 'Se encontraron las columnas, pero no hay datos válidos (cédulas y montos) para procesar.']);
            }

            return response()->json(['ok' => true, 'data' => $result]);
        } catch (\Exception $e) {
            \Log::error('Error parseando excel desglose: ' . $e->getMessage());
            return response()->json(['ok' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
        }
    }
}
