<?php

namespace App\Jobs;

use App\Models\ConciliacionImportacion;
use App\Models\ConciliacionLinea;
use App\Services\Conciliacion\ConciliacionAutoMatcher;
use App\Services\Conciliacion\ExtractoImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Importa un extracto por bloques y luego corre el emparejamiento automático
 * sobre las líneas nuevas de esa cuenta y período.
 */
class ProcesarImportacionExtracto implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** Debe ser menor que DB_QUEUE_RETRY_AFTER para que la cola no lo relance mientras corre. */
    public int $timeout = 900;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public int $importacionId) {}

    public function uniqueId(): string
    {
        return 'conciliacion-importacion-'.$this->importacionId;
    }

    public function handle(ExtractoImporter $importer, ConciliacionAutoMatcher $autoMatcher): void
    {
        $imp = ConciliacionImportacion::find($this->importacionId);
        if (! $imp || $imp->terminada()) {
            return;
        }

        // La pantalla no debe recargar al terminar la importación si aún falta el emparejamiento.
        $llave = self::llaveConciliando($this->importacionId);
        Cache::put($llave, true, $this->timeout);
        try {
            $imp = $importer->importarArchivo($imp);
            if ($imp->estado === ConciliacionImportacion::FALLIDA) {
                return;
            }

            $this->conciliarNuevas($imp, $autoMatcher);
        } finally {
            Cache::forget($llave);
        }
    }

    public static function llaveConciliando(int $importacionId): string
    {
        return 'conciliacion-importacion-'.$importacionId.'-conciliando';
    }

    public function failed(?Throwable $e): void
    {
        $imp = ConciliacionImportacion::find($this->importacionId);
        if (! $imp) {
            return;
        }
        // Solo tipo y línea: el mensaje de la BD podría incluir datos del extracto.
        Log::error('Importación de extracto fallida', [
            'importacion_id' => $imp->id,
            'banco' => $imp->banco,
            'error' => $e ? get_class($e).' en '.basename($e->getFile()).':'.$e->getLine() : null,
        ]);
        $importer = app(ExtractoImporter::class);
        $importer->fallar($imp, $importer->mensajeFalloInesperado($imp));
        $importer->borrarArchivo($imp);
    }

    /**
     * Corre el emparejamiento en pasadas de 500 hasta que no quedan coincidencias nuevas.
     */
    private function conciliarNuevas(ConciliacionImportacion $imp, ConciliacionAutoMatcher $autoMatcher): void
    {
        $rango = DB::table('conciliacion_lineas')
            ->where('importacion_id', $imp->id)
            ->selectRaw('MIN(fecha) AS desde, MAX(fecha) AS hasta')
            ->first();
        if (! $rango || ! $rango->desde) {
            return;
        }
        $desde = substr((string) $rango->desde, 0, 10);
        $hasta = substr((string) $rango->hasta, 0, 10);
        // Antes del emparejamiento: así ese cargo nunca se liga con un egreso del mismo monto.
        app(\App\Services\Conciliacion\ComisionPagoMovilBanesco::class)->marcar($desde, $hasta);

        // En segundo plano no hace falta el tope de 500 de la pantalla: se revisan todas las líneas
        // nuevas, en bloques por id para que la memoria no crezca con el tamaño del archivo.
        // Una segunda pasada recoge el otro lado de traslados que quedó libre en la primera.
        $total = 0;
        for ($pasada = 0; $pasada < 2; $pasada++) {
            $enPasada = 0;
            ConciliacionLinea::query()
                ->where('importacion_id', $imp->id)
                ->where('estado', 'pendiente')
                ->whereNull('flujo_caja_id')
                ->whereNull('tesoreria_ingreso_id')
                ->whereNull('compra_divisa_id')
                ->chunkById(2000, function ($lineas) use ($autoMatcher, $desde, $hasta, $imp, &$enPasada) {
                    $enPasada += $autoMatcher->conciliar($lineas, $desde, $hasta, $imp->banco, $imp->titular, $lineas->count());
                });
            $total += $enPasada;
            if ($enPasada === 0) {
                break;
            }
        }
        $total += app(\App\Services\Conciliacion\LotesPuntoVentaAutoMatcher::class)
            ->conciliar($desde, $hasta, $imp->banco, $imp->titular);
        if (stripos((string) $imp->banco, 'MERCANTIL') !== false) {
            $total += app(\App\Services\Conciliacion\MercantilLotesConciliador::class)
                ->conciliarAutomatico($desde, $hasta, $imp->titular)['conciliados'];
        }

        $pendientes = DB::table('conciliacion_lineas')
            ->where('importacion_id', $imp->id)
            ->where('estado', 'pendiente')
            ->count();

        $imp->forceFill([
            'filas_conciliadas' => $total,
            'filas_pendientes' => $pendientes,
            'mensaje' => trim(($imp->mensaje ?? '').' Conciliados automáticamente: '.$total.'. Pendientes por conciliar: '.$pendientes.'.'),
        ])->save();
    }
}
