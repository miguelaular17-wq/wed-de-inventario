<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class InterconectadosSmsService
{
    /**
     * @return array{ok:bool, telefono:string, respuesta:string}
     */
    public function enviar(string $telefono, string $texto, string $user, string $password): array
    {
        $numero = $this->normalizar($telefono);
        if ($numero === null) {
            return ['ok' => false, 'telefono' => trim($telefono), 'respuesta' => 'Número inválido.'];
        }
        if ($user === '' || $password === '') {
            return ['ok' => false, 'telefono' => $numero, 'respuesta' => 'Faltan el usuario y la clave de la API.'];
        }

        try {
            $response = Http::timeout(25)
                ->withOptions(['allow_redirects' => false])
                ->get(rtrim((string) config('sms.url'), '/'), [
                    'PhoneNumber' => $numero,
                    'text' => $texto,
                    'user' => $user,
                    'password' => $password,
                ]);
        } catch (\Throwable) {
            return ['ok' => false, 'telefono' => $numero, 'respuesta' => 'No se pudo conectar con la API.'];
        }

        $body = trim($response->body());
        $fallo = $body === '' || preg_match('/error|invalid|fail|incorrect/i', $body) === 1;

        return [
            'ok' => $response->successful() && ! $fallo,
            'telefono' => $numero,
            'respuesta' => $body !== '' ? $body : 'Sin respuesta del proveedor.',
        ];
    }

    public function normalizar(string $telefono): ?string
    {
        $digitos = preg_replace('/\D+/', '', $telefono) ?? '';
        if (str_starts_with($digitos, '58') && strlen($digitos) === 12) {
            $digitos = '0'.substr($digitos, 2);
        }
        if (strlen($digitos) === 10 && str_starts_with($digitos, '4')) {
            $digitos = '0'.$digitos;
        }
        if (strlen($digitos) !== 11 || ! str_starts_with($digitos, '04')) {
            return null;
        }

        return $digitos;
    }

    /**
     * @return list<string>
     */
    public function separarNumeros(string $lista): array
    {
        $partes = preg_split('/[\s,;]+/', trim($lista)) ?: [];
        $out = [];
        foreach ($partes as $parte) {
            if ($parte !== '') {
                $out[] = $parte;
            }
        }

        return array_values(array_unique($out));
    }
}
