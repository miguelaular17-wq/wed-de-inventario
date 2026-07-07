<?php

namespace App\Services;

use App\Models\FinanzasResumen;
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
     *  1. Caché de Laravel (clave diaria).
     *  2. API externa ve.dolarapi.com.
     *  3. Último valor guardado en finanzas_resumen.
     *  4. Valor de emergencia: 1.
     */
    public function getRateForToday(): float
    {
        $cacheKey = 'tasa_bcv_' . date('Y-m-d');

        return Cache::remember($cacheKey, self::TTL_SECONDS, function () {
            return $this->fetchFromApi() ?? $this->fetchFromDatabase() ?? 1.0;
        });
    }

    /**
     * Consulta la API externa.
     * Devuelve el valor o null si falla.
     */
    private function fetchFromApi(): ?float
    {
        try {
            $client = new Client(['timeout' => self::API_TIMEOUT]);
            $response = $client->get(self::API_URL);
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
     * Lee la tasa más reciente almacenada en finanzas_resumen.
     * Sirve de fallback cuando la API no responde.
     */
    private function fetchFromDatabase(): ?float
    {
        $ultimo = FinanzasResumen::orderBy('fecha', 'desc')->first();

        return $ultimo ? (float) $ultimo->tasa_bcv_usd : null;
    }
}
