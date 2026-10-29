<?php

namespace App\Services\Conciliacion;

use App\Models\ConciliacionImportacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Guarda líneas del extracto por bloques con inserción masiva.
 *
 * Duplicados: misma regla que antes (banco, titular, fecha, monto, referencia, descripción, tipo).
 * Reintentos: cada bloque se confirma junto con `ultima_fila`, así un reintento continúa
 * donde quedó sin volver a insertar ni a contar filas.
 */
class ExtractoImporter
{
    private ?bool $trazabilidad = null;

    /** Filas que se dejaron fuera en la última importación (ver seIgnora). */
    private int $ignoradas = 0;

    public function __construct(private ExtractoParser $parser) {}

    public function importarArchivo(ConciliacionImportacion $imp): ConciliacionImportacion
    {
        $disco = Storage::disk('local');
        if (! $imp->archivo_path || ! $disco->exists($imp->archivo_path)) {
            return $this->fallar($imp, 'No se encontró el archivo subido. Vuelve a cargarlo.');
        }

        try {
            $filas = $this->parser->filas($disco->path($imp->archivo_path), $imp->extension, $imp->banco);

            return $this->importarFilas($imp, $filas);
        } catch (FormatoExtractoException $e) {
            $this->fallar($imp, $e->getMessage());
            $this->borrarArchivo($imp);

            return $imp;
        }
        // Cualquier otro error (BD, red) sube para que la cola reintente desde `ultima_fila`.
    }

    /** Mensaje para el usuario cuando se agotaron los reintentos; el detalle técnico queda en el log. */
    public function mensajeFalloInesperado(ConciliacionImportacion $imp): string
    {
        return 'Error inesperado al procesar el archivo (importación #'.$imp->id.'). Se guardaron '
            .$imp->filas_importadas.' movimientos antes del error; puedes volver a subir el mismo archivo y solo se agregará lo que falta.';
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $filas  Salida de ExtractoParser::filas() o equivalente.
     */
    public function importarFilas(ConciliacionImportacion $imp, iterable $filas): ConciliacionImportacion
    {
        $imp->forceFill([
            'estado' => ConciliacionImportacion::PROCESANDO,
            'iniciado_at' => $imp->iniciado_at ?? now(),
            'intentos' => $imp->intentos + 1,
            'mensaje' => null,
        ])->save();

        $chunk = max(50, (int) config('conciliacion.import_chunk', 500));
        $maxFilas = (int) config('conciliacion.import_max_rows', 200000);
        $desde = (int) $imp->ultima_fila;
        $bloque = [];
        $rechazosBloque = [];
        $ultimaFilaBloque = $desde;
        $vistas = 0;
        $this->ignoradas = 0;

        foreach ($filas as $fila) {
            $n = (int) ($fila['fila'] ?? 0);
            if ($n <= $desde) {
                continue;
            }
            if (++$vistas > $maxFilas) {
                throw new FormatoExtractoException("El archivo supera el máximo de {$maxFilas} filas.");
            }
            $ultimaFilaBloque = $n;

            if (($fila['tipo'] ?? '') === 'rechazo') {
                $rechazosBloque[] = ['fila' => $n, 'motivo' => (string) $fila['motivo']];
            } elseif (self::seIgnora((string) $imp->banco, $fila['datos']['descripcion'] ?? null)) {
                $this->ignoradas++;
            } else {
                $bloque[] = ['fila' => $n] + $fila['datos'];
            }

            if (count($bloque) + count($rechazosBloque) >= $chunk) {
                $this->guardarBloque($imp, $bloque, $rechazosBloque, $ultimaFilaBloque);
                $bloque = [];
                $rechazosBloque = [];
            }
        }

        if ($bloque !== [] || $rechazosBloque !== [] || $ultimaFilaBloque > (int) $imp->ultima_fila) {
            $this->guardarBloque($imp, $bloque, $rechazosBloque, $ultimaFilaBloque);
        }

        return $this->finalizar($imp->refresh());
    }

    /**
     * Movimientos del extracto que no se guardan: en Mercantil, «CANCELACION RESUMEN DE VENTAS Y RETENCIO»
     * (el abono de los cierres de punto de venta) no se concilia en esta cuenta.
     */
    public static function seIgnora(string $banco, ?string $descripcion): bool
    {
        return str_contains(mb_strtoupper($banco), 'MERCANTIL')
            && str_starts_with(mb_strtoupper(trim((string) $descripcion)), 'CANCELACION RESUMEN DE VENTAS');
    }

    public function fallar(ConciliacionImportacion $imp, string $mensaje): ConciliacionImportacion
    {
        $imp->forceFill([
            'estado' => ConciliacionImportacion::FALLIDA,
            'mensaje' => mb_substr($mensaje, 0, 1000),
            'finalizado_at' => now(),
        ])->save();

        return $imp;
    }

    public function borrarArchivo(ConciliacionImportacion $imp): void
    {
        if ($imp->archivo_path) {
            Storage::disk('local')->delete($imp->archivo_path);
            $imp->forceFill(['archivo_path' => null])->save();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $bloque
     * @param  list<array{fila: int, motivo: string}>  $rechazos
     */
    private function guardarBloque(ConciliacionImportacion $imp, array $bloque, array $rechazos, int $ultimaFila): void
    {
        DB::transaction(function () use ($imp, $bloque, $rechazos, $ultimaFila) {
            $this->bloquearCuenta($imp->banco, $imp->titular);

            $fresca = ConciliacionImportacion::query()->lockForUpdate()->findOrFail($imp->id);
            if ($ultimaFila <= (int) $fresca->ultima_fila) {
                return; // Otro proceso ya guardó este bloque.
            }

            [$nuevas, $duplicadas] = $this->filtrarDuplicados($imp, $bloque);
            if ($nuevas !== []) {
                DB::table('conciliacion_lineas')->insert($nuevas);
            }

            $maxErrores = (int) config('conciliacion.import_max_errores', 200);
            $guardados = $fresca->rechazos ?? [];
            foreach ($rechazos as $r) {
                if (count($guardados) >= $maxErrores) {
                    break;
                }
                $guardados[] = $r;
            }

            $fresca->forceFill([
                'filas_leidas' => $fresca->filas_leidas + count($bloque) + count($rechazos),
                'filas_importadas' => $fresca->filas_importadas + count($nuevas),
                'filas_duplicadas' => $fresca->filas_duplicadas + $duplicadas,
                'filas_rechazadas' => $fresca->filas_rechazadas + count($rechazos),
                'rechazos' => $guardados,
                'ultima_fila' => $ultimaFila,
            ])->save();
        });

        $imp->refresh();
    }

    /**
     * @param  list<array<string, mixed>>  $bloque
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function filtrarDuplicados(ConciliacionImportacion $imp, array $bloque): array
    {
        if ($bloque === []) {
            return [[], 0];
        }

        $banco = strtoupper(trim($imp->banco));
        $titular = strtoupper(trim($imp->titular));
        // Fecha y monto son parte de la clave de duplicado: basta comparar contra esas combinaciones.
        $fechas = array_values(array_unique(array_column($bloque, 'fecha')));
        $montos = array_values(array_unique(array_column($bloque, 'monto')));

        $existentes = [];
        DB::table('conciliacion_lineas')
            ->whereRaw('LOWER(TRIM(banco)) = ?', [strtolower($banco)])
            ->whereRaw("UPPER(TRIM(COALESCE(titular, ''))) = ?", [$titular])
            ->whereIn('fecha', $fechas)
            ->whereIn('monto', $montos)
            ->select(['fecha', 'monto', 'referencia', 'descripcion', 'tipo'])
            ->get()
            ->each(function ($fila) use (&$existentes, $banco, $titular) {
                $existentes[$this->clave($banco, $titular, $fila->fecha, $fila->monto, $fila->referencia, $fila->descripcion, $fila->tipo)] = true;
            });

        $conTrazabilidad = $this->tieneTrazabilidad();
        $ahora = now();
        $nuevas = [];
        $duplicadas = 0;
        foreach ($bloque as $linea) {
            $clave = $this->clave($banco, $titular, $linea['fecha'], $linea['monto'], $linea['referencia'], $linea['descripcion'], $linea['tipo']);
            if (isset($existentes[$clave])) {
                $duplicadas++;

                continue;
            }
            $existentes[$clave] = true;

            $fila = [
                'session_id' => $imp->session_id ?? null,
                'banco' => $banco,
                'titular' => $titular,
                'fecha' => $linea['fecha'],
                'descripcion' => $linea['descripcion'],
                'referencia' => $linea['referencia'],
                'monto' => $linea['monto'],
                'estado' => 'pendiente',
                'tipo' => $linea['tipo'],
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
            if ($conTrazabilidad) {
                $fila['importacion_id'] = $imp->id;
                $fila['fila_origen'] = $linea['fila'];
            }
            $nuevas[] = $fila;
        }

        return [$nuevas, $duplicadas];
    }

    /** Misma clave que FinanzasController::claveExtracto para no cambiar qué se considera repetido. */
    public function clave($banco, $titular, $fecha, $monto, $referencia, $descripcion, $tipo): string
    {
        $dia = $fecha instanceof \DateTimeInterface ? $fecha->format('Y-m-d') : substr((string) $fecha, 0, 10);

        return implode('|', [
            strtoupper(trim((string) $banco)),
            strtoupper(trim((string) $titular)),
            $dia,
            number_format(round((float) $monto, 2), 2, '.', ''),
            trim((string) $referencia),
            trim((string) $descripcion),
            trim((string) $tipo),
        ]);
    }

    private function finalizar(ConciliacionImportacion $imp): ConciliacionImportacion
    {
        if ($imp->filas_leidas === 0) {
            $this->fallar($imp, 'No se encontró ninguna fila con fecha y monto. Revisa que el archivo sea del banco '.$imp->banco.' y que tenga el formato habitual.');
            $this->borrarArchivo($imp);

            return $imp;
        }

        $estado = $imp->filas_rechazadas > 0
            ? ConciliacionImportacion::COMPLETADA_CON_PENDIENTES
            : ConciliacionImportacion::COMPLETADA;

        $mensaje = "Se registraron {$imp->filas_importadas} movimientos.";
        if ($imp->filas_duplicadas > 0) {
            $mensaje .= " {$imp->filas_duplicadas} ya estaban cargados.";
        }
        if ($imp->filas_rechazadas > 0) {
            $mensaje .= " {$imp->filas_rechazadas} filas rechazadas.";
        }
        if ($this->ignoradas > 0) {
            $mensaje .= " {$this->ignoradas} «CANCELACION RESUMEN DE VENTAS» de Mercantil no se guardaron.";
        }

        $imp->forceFill([
            'estado' => $estado,
            'mensaje' => $mensaje,
            'finalizado_at' => now(),
        ])->save();
        $this->borrarArchivo($imp);

        return $imp;
    }

    /** Evita que dos importaciones de la misma cuenta deduzcan e inserten a la vez. */
    private function bloquearCuenta(string $banco, string $titular): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        $llave = crc32('conciliacion|'.strtoupper(trim($banco)).'|'.strtoupper(trim($titular)));
        DB::select('SELECT pg_advisory_xact_lock(?)', [$llave]);
    }

    private function tieneTrazabilidad(): bool
    {
        return $this->trazabilidad ??= Schema::hasColumn('conciliacion_lineas', 'importacion_id');
    }
}
