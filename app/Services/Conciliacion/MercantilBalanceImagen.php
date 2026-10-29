<?php

namespace App\Services\Conciliacion;

use Illuminate\Support\Facades\Http;

/**
 * Captura del portal de Mercantil "Balance de Transacciones en el período" (una fila por lote:
 * Importe Bruto, Importe de Descuento, Retención ISLR, Importe Abonado y el Total General), leída con Gemini.
 * Antes de usarla se comprueban las cuentas de la propia imagen, para no conciliar con un número mal leído.
 */
class MercantilBalanceImagen
{
    private const PROMPT = 'Esta imagen es el "Balance de Transacciones en el periodo" del portal de comercios del Banco Mercantil. '
        .'Tiene una fila por Lote y una fila "Total General". Por cada lote toma: el número de Lote; de las columnas de la derecha '
        .'(después de "Nro. Total Tx"): "Importe Bruto", "Importe de Descuento", "Retencion ISLR" e "Importe Abonado". '
        .'Del "Total General" toma los mismos cuatro importes. Los números usan coma para miles y punto para decimales (1,308,438.05). '
        .'Responde SOLO un objeto JSON: {"lotes":[{"lote":"278","bruto":823680.51,"descuento":16473.61,"retencion":0,"abonado":807206.90}],'
        .'"total":{"bruto":0,"descuento":0,"retencion":0,"abonado":0}}. Números sin separador de miles y con punto decimal.';

    /**
     * @return array{lotes: list<array{lote: string, bruto: float, descuento: float, retencion: float, abonado: float}>, total: array<string, float>}
     */
    public function leer(string $path, string $mime): array
    {
        $apiKey = env('GEMINI_API_KEY');
        if (! $apiKey) {
            throw new \RuntimeException('Falta configurar GEMINI_API_KEY para leer imágenes.');
        }
        $payload = [
            'contents' => [['parts' => [
                ['text' => self::PROMPT],
                ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode((string) file_get_contents($path))]],
            ]]],
            'generationConfig' => ['response_mime_type' => 'application/json', 'temperature' => 0],
        ];
        $respuesta = null;
        for ($intento = 0; $intento < 3; $intento++) {
            try {
                // La clave va en el encabezado: en la URL aparecería en cualquier mensaje de error.
                $respuesta = Http::timeout(90)->withHeaders(['x-goog-api-key' => $apiKey])
                    ->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent', $payload);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                throw new \RuntimeException(str_contains($e->getMessage(), 'SSL certificate')
                    ? 'No se pudo conectar con Gemini: este servidor no tiene configurados los certificados SSL (curl.cainfo en php.ini).'
                    : 'No se pudo conectar con Gemini. Intenta de nuevo en unos minutos.');
            }
            if ($respuesta->successful() || ! in_array($respuesta->status(), [429, 503], true)) {
                break;
            }
            sleep(3);
        }
        if (! $respuesta->successful()) {
            throw new \RuntimeException('Gemini no respondió: '.($respuesta->json('error.message') ?? $respuesta->status()));
        }
        $texto = trim(str_replace(['```json', '```'], '', (string) $respuesta->json('candidates.0.content.parts.0.text')));

        return $this->desdeJson(json_decode($texto, true));
    }

    /**
     * Normaliza y valida lo leído. Público para probarlo sin llamar a la IA.
     *
     * @return array{lotes: list<array{lote: string, bruto: float, descuento: float, retencion: float, abonado: float}>, total: array<string, float>}
     */
    public function desdeJson(mixed $json): array
    {
        if (! is_array($json) || ! is_array($json['lotes'] ?? null) || $json['lotes'] === []) {
            throw new \RuntimeException('No se encontró la tabla de lotes en la imagen. Debe ser el «Balance de Transacciones en el período».');
        }
        $num = fn ($v) => round((float) str_replace(',', '', (string) ($v ?? 0)), 2);
        $lotes = [];
        foreach ($json['lotes'] as $l) {
            $lote = ltrim(preg_replace('/\D/', '', (string) ($l['lote'] ?? '')) ?? '', '0');
            if ($lote === '' || $num($l['bruto'] ?? 0) <= 0) {
                continue;
            }
            $fila = ['lote' => $lote, 'bruto' => $num($l['bruto']), 'descuento' => $num($l['descuento'] ?? 0),
                'retencion' => $num($l['retencion'] ?? 0), 'abonado' => $num($l['abonado'] ?? 0)];
            if (abs($fila['bruto'] - $fila['descuento'] - $fila['retencion'] - $fila['abonado']) > 0.02) {
                throw new \RuntimeException(sprintf(
                    'Lote %s: lo leído no cuadra (bruto %s − descuento %s − retención %s ≠ abonado %s). Sube una captura más nítida.',
                    $lote, number_format($fila['bruto'], 2, ',', '.'), number_format($fila['descuento'], 2, ',', '.'),
                    number_format($fila['retencion'], 2, ',', '.'), number_format($fila['abonado'], 2, ',', '.'),
                ));
            }
            $lotes[] = $fila;
        }
        $total = array_map($num, (array) ($json['total'] ?? []));
        $suma = round(array_sum(array_column($lotes, 'bruto')), 2);
        if (($total['bruto'] ?? 0) > 0 && abs($suma - $total['bruto']) > 0.02) {
            throw new \RuntimeException(sprintf(
                'La suma de los lotes leídos (Bs %s) no da el Total General de la imagen (Bs %s): falta o sobra algún lote. Sube la captura completa.',
                number_format($suma, 2, ',', '.'), number_format($total['bruto'], 2, ',', '.'),
            ));
        }

        return ['lotes' => $lotes, 'total' => $total];
    }
}
