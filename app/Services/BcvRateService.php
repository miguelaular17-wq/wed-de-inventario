<?php

namespace App\Services;

use App\Models\FinanzasResumen;
use App\Services\Profiler;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BcvRateService
{
    /**
     * TTL del caché: 12 horas.
     * La tasa BCV se publica una vez al día, por lo que 12 horas es apropiado.
     * NOTA: el código original usaba 60 * 12 = 720 s (12 minutos). Corregido a 60 * 60 * 12.
     */
    private const TTL_SECONDS = 60 * 60 * 12;

    /**
     * Timeout para la llamada HTTP a la API externa.
     * Se mantiene en 5 s igual que el código original.
     */
    private const API_TIMEOUT = 5;

    private const API_URL = 'https://ve.dolarapi.com/v1/dolares/oficial';

    /**
     * Retorna la tasa BCV del día actual.
     * Orden de resolución:
     *  1. Tasa del flujo de caja de hoy (finanzas_resumen.tasa_bcv_usd).
     *  2. Caché de Laravel (clave diaria, API).
     *  3. API externa ve.dolarapi.com.
     *  4. Último valor guardado en finanzas_resumen (cualquier fecha).
     *  5. Valor de emergencia: 1.
     */
    public function getRateForToday(): float
    {
        Profiler::start('BcvRateService::getRateForToday');

        $fromFlujoHoy = $this->fetchFromDatabase(date('Y-m-d'));
        if ($fromFlujoHoy !== null) {
            Profiler::stop('BcvRateService::getRateForToday');
            return $fromFlujoHoy;
        }

        $cacheKey = 'tasa_bcv_' . date('Y-m-d');

        $result = Cache::remember($cacheKey, self::TTL_SECONDS, function () {
            return $this->fetchFromApi() ?? $this->fetchFromDatabase() ?? 1.0;
        });
        Profiler::stop('BcvRateService::getRateForToday');
        return (float) $result;
    }

    public function forgetTodayCache(): void
    {
        Cache::forget('tasa_bcv_' . date('Y-m-d'));
    }

    /**
     * Consulta la API externa.
     * Devuelve el valor o null si falla.
     */
    private function fetchFromApi(): ?float
    {
        try {
            $client = new Client(['timeout' => self::API_TIMEOUT]);
            Profiler::start('BcvRateService::fetchFromApi HTTP');
            $response = $client->get(self::API_URL);
            Profiler::stop('BcvRateService::fetchFromApi HTTP');
            $data = json_decode($response->getBody(), true);

            if (isset($data['promedio']) && $data['promedio'] > 0) {
                return round((float) $data['promedio'], 2);
            }
        } catch (\Exception $e) {
            Log::warning('BcvRateService: API no disponible — ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Lee la tasa en finanzas_resumen (flujo de caja).
     * Con $fecha: solo ese día. Sin $fecha: el registro más reciente.
     */
    private function fetchFromDatabase(?string $fecha = null): ?float
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('finanzas_resumen')) {
            return null;
        }

        Profiler::start('BcvRateService::fetchFromDatabase');
        $query = FinanzasResumen::query()->where('tasa_bcv_usd', '>', 0);
        if ($fecha !== null) {
            $query->whereDate('fecha', $fecha);
            $row = $query->first();
        } else {
            $row = $query->orderByDesc('fecha')->first();
        }
        $result = $row ? (float) $row->tasa_bcv_usd : null;
        Profiler::stop('BcvRateService::fetchFromDatabase');

        return $result;
    }
}
