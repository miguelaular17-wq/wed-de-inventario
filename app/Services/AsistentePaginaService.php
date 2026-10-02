<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class AsistentePaginaService
{
    /**
     * @param  array{titulo?:string,url?:string,texto?:string,campos?:list<array<string,mixed>>}  $pagina
     * @param  list<array{rol?:string,texto?:string}>  $historial
     * @return array{respuesta:string,rellenar:list<array{clave:string,valor:string}>}
     */
    public function consultar(string $mensaje, array $pagina, array $historial = [], ?UploadedFile $imagen = null, ?array $pendiente = null): array
    {
        $apiKey = (string) env('GEMINI_API_KEY');
        if ($apiKey === '') {
            throw new RuntimeException('Falta GEMINI_API_KEY en el archivo .env.');
        }

        $catalogo = $this->catalogo($pagina['campos'] ?? []);
        $payload = [
            'systemInstruction' => [
                'parts' => [[
                    'text' => 'Eres el asistente de Nexo PD. Respondes en español, corto y claro. '
                        .'Solo usas el texto de la página, la imagen adjunta y la conversación. '
                        .'Si piden rellenar un formulario, devuelve esos campos en rellenar usando la clave exacta del catálogo. Rellenar no guarda. '
                        .'Si piden crear un anticipo o adelanto de nómina, no digas que ya quedó registrado. Devuelve accion crear_adelanto. '
                        .'Si falta el empleado, deja empleado vacío y pregunta a quién. Si falta el monto, deja monto null y pregúntalo. '
                        .'Responde solo JSON: {"respuesta":"texto","rellenar":[{"clave":"campo","valor":"texto"}],"accion":null} '
                        .'accion, cuando aplique: {"tipo":"crear_adelanto","monto":10,"empleado":"nombre o cedula","fecha":"YYYY-MM-DD","motivo":""}.',
                ]],
            ],
            'contents' => $this->contenidos($mensaje, $pagina, $catalogo, $historial, $imagen, $pendiente),
            'generationConfig' => [
                'response_mime_type' => 'application/json',
                'temperature' => 0.2,
            ],
        ];

        try {
            $response = Http::withoutVerifying()
                ->timeout(60)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key='.$apiKey, $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException('No se pudo conectar con el asistente.');
        }

        if (! $response->successful()) {
            $detalle = $response->json('error.message') ?: 'El asistente no respondió.';
            throw new RuntimeException(Str::limit((string) $detalle, 240));
        }

        $texto = (string) ($response->json('candidates.0.content.parts.0.text') ?? '');

        return $this->interpretar($texto, array_keys($catalogo));
    }

    /**
     * @param  list<array<string, mixed>>  $campos
     * @return array<string, array{clave:string,etiqueta:string,tipo:string,valor:string,opciones:list<string>}>
     */
    private function catalogo(array $campos): array
    {
        $catalogo = [];
        foreach (array_slice($campos, 0, 80) as $campo) {
            if (! is_array($campo)) {
                continue;
            }
            $clave = (string) ($campo['clave'] ?? '');
            if (! preg_match('/^[A-Za-z0-9_\-\[\]\.]{1,80}$/', $clave)) {
                continue;
            }
            if (in_array(strtolower((string) ($campo['tipo'] ?? '')), ['password', 'hidden', 'file'], true)) {
                continue;
            }
            $opciones = [];
            foreach (array_slice((array) ($campo['opciones'] ?? []), 0, 30) as $opcion) {
                $opciones[] = Str::limit(trim((string) $opcion), 80, '');
            }
            $catalogo[$clave] = [
                'clave' => $clave,
                'etiqueta' => Str::limit(trim((string) ($campo['etiqueta'] ?? $clave)), 80, ''),
                'tipo' => Str::limit(trim((string) ($campo['tipo'] ?? 'text')), 20, ''),
                'valor' => Str::limit(trim((string) ($campo['valor'] ?? '')), 120, ''),
                'opciones' => array_values(array_filter($opciones)),
            ];
        }

        return $catalogo;
    }

    /**
     * @param  array{titulo?:string,url?:string,texto?:string}  $pagina
     * @param  array<string, array{clave:string,etiqueta:string,tipo:string,valor:string,opciones:list<string>}>  $catalogo
     * @param  list<array{rol?:string,texto?:string}>  $historial
     * @return list<array<string, mixed>>
     */
    private function contenidos(string $mensaje, array $pagina, array $catalogo, array $historial, ?UploadedFile $imagen, ?array $pendiente = null): array
    {
        $contents = [];
        foreach (array_slice($historial, -6) as $turno) {
            $texto = trim((string) ($turno['texto'] ?? ''));
            if ($texto === '') {
                continue;
            }
            $contents[] = [
                'role' => ($turno['rol'] ?? '') === 'asistente' ? 'model' : 'user',
                'parts' => [['text' => Str::limit($texto, 1500, '')]],
            ];
        }

        $lineas = [];
        foreach ($catalogo as $campo) {
            $linea = $campo['clave'].' | '.$campo['etiqueta'].' | '.$campo['tipo'];
            if ($campo['valor'] !== '') {
                $linea .= ' | actual: '.$campo['valor'];
            }
            if ($campo['opciones'] !== []) {
                $linea .= ' | opciones: '.implode(', ', $campo['opciones']);
            }
            $lineas[] = $linea;
        }

        $prompt = "Página: ".Str::limit(trim((string) ($pagina['titulo'] ?? '')), 160, '')
            ."\nRuta: ".Str::limit(trim((string) ($pagina['url'] ?? '')), 180, '')
            ."\n\nTexto visible:\n".Str::limit(trim((string) ($pagina['texto'] ?? '')), 8000, '')
            ."\n\nCampos que puedes rellenar (clave | etiqueta | tipo):\n".($lineas === [] ? '(ninguno)' : implode("\n", $lineas))
            ."\n\nPedido:\n".Str::limit(trim($mensaje), 4000, '');

        if ($imagen) {
            $prompt .= "\n\nHay una imagen adjunta. Úsala como referencia para responder o para rellenar los campos.";
        }
        if (is_array($pendiente) && ($pendiente['tipo'] ?? '') === 'crear_adelanto') {
            $prompt .= "\n\nHay un anticipo pendiente de $".($pendiente['monto'] ?? '?')
                .'. Si el mensaje es un nombre o cédula, devuelve accion crear_adelanto con ese empleado y el mismo monto.';
        }

        $parts = [['text' => $prompt]];
        if ($imagen) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $imagen->getMimeType() ?: 'image/jpeg',
                    'data' => base64_encode((string) file_get_contents($imagen->getRealPath())),
                ],
            ];
        }

        $contents[] = ['role' => 'user', 'parts' => $parts];

        return $contents;
    }

    /**
     * @param  list<string>  $clavesPermitidas
     * @return array{respuesta:string,rellenar:list<array{clave:string,valor:string}>}
     */
    private function interpretar(string $texto, array $clavesPermitidas): array
    {
        $limpio = trim($texto);
        $limpio = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $limpio) ?? $limpio;
        $json = json_decode(trim($limpio), true);

        if (! is_array($json)) {
            return [
                'respuesta' => Str::limit($limpio !== '' ? $limpio : 'No pude leer la respuesta.', 4000, ''),
                'rellenar' => [],
                'accion' => null,
            ];
        }

        $rellenar = [];
        foreach (array_slice((array) ($json['rellenar'] ?? []), 0, 40) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $clave = (string) ($item['clave'] ?? '');
            if (! in_array($clave, $clavesPermitidas, true)) {
                continue;
            }
            $rellenar[] = [
                'clave' => $clave,
                'valor' => Str::limit(trim((string) ($item['valor'] ?? '')), 500, ''),
            ];
        }

        $respuesta = trim((string) ($json['respuesta'] ?? ''));
        if ($respuesta === '') {
            $respuesta = $rellenar === []
                ? 'Listo.'
                : 'Rellené los campos que pude leer. Revísalos antes de guardar.';
        }

        return [
            'respuesta' => Str::limit($respuesta, 4000, ''),
            'rellenar' => $rellenar,
            'accion' => $this->accion($json['accion'] ?? null),
        ];
    }

    /**
     * @return array{tipo:string,monto:?float,empleado:string,fecha:string,motivo:string}|null
     */
    private function accion(mixed $accion): ?array
    {
        if (! is_array($accion) || ($accion['tipo'] ?? '') !== 'crear_adelanto') {
            return null;
        }

        $monto = $accion['monto'] ?? null;
        if (is_string($monto)) {
            $monto = str_replace(',', '.', $monto);
        }

        return [
            'tipo' => 'crear_adelanto',
            'monto' => is_numeric($monto) ? round((float) $monto, 2) : null,
            'empleado' => Str::limit(trim((string) ($accion['empleado'] ?? '')), 120, ''),
            'fecha' => Str::limit(trim((string) ($accion['fecha'] ?? '')), 20, ''),
            'motivo' => Str::limit(trim((string) ($accion['motivo'] ?? '')), 200, ''),
        ];
    }
}
