<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Str;

class AsistenteAvisoService
{
    /**
     * @param  array{respuesta:string,avisar?:?array}  $resultado
     * @param  array{titulo?:string,url?:string}  $pagina
     * @return array{respuesta:string,avisar?:?array}
     */
    public function enviar(User $remitente, array $resultado, string $mensaje, array $pagina = []): array
    {
        $avisar = is_array($resultado['avisar'] ?? null) ? $resultado['avisar'] : [];
        $destinatario = trim((string) ($avisar['destinatario'] ?? ''));
        if (! $this->esMigue($mensaje) && ! $this->esMigue($destinatario)) {
            return $resultado;
        }

        $miguel = $this->miguel();
        if ($miguel === null) {
            $resultado['respuesta'] = rtrim($resultado['respuesta'])."\n\nNo encontré al usuario Miguel Aular.";

            return $resultado;
        }

        if ($miguel->id === $remitente->id) {
            $resultado['respuesta'] = rtrim($resultado['respuesta'])."\n\nTú eres Miguel Aular. No hace falta enviártelo.";

            return $resultado;
        }

        $texto = trim((string) ($avisar['mensaje'] ?? ''));
        if ($texto === '') {
            $texto = trim($mensaje);
        }
        $donde = trim((string) ($pagina['titulo'] ?? ''));
        $ruta = trim((string) ($pagina['url'] ?? ''));
        $aviso = $remitente->name.' te envió: '.$texto;
        if ($donde !== '') {
            $aviso .= ' · '.$donde;
        }
        if ($ruta !== '') {
            $aviso .= ' '.$ruta;
        }

        Notification::query()->create([
            'sender_id' => $remitente->id,
            'receiver_id' => $miguel->id,
            'message' => Str::limit($aviso, 2000, ''),
        ]);

        $resultado['respuesta'] = rtrim($resultado['respuesta'])."\n\nSe lo envié a Miguel Aular. Le llega en notificaciones.";

        return $resultado;
    }

    private function esMigue(string $texto): bool
    {
        $texto = trim($texto);
        if ($texto === '') {
            return false;
        }

        if (preg_match('/^(?:el\s+|la\s+)?(?:migue|miguel(?:\s+aular)?)$/iu', $texto)) {
            return true;
        }

        return (bool) preg_match(
            '/\b(?:para|dile a|d[ií]le a|av[ií]sale a|env[ií]aselo a|m[aá]ndaselo a|es para)\s+(?:el\s+|la\s+)?(migue|miguel(?:\s+aular)?)\b/iu',
            $texto
        );
    }

    private function miguel(): ?User
    {
        return User::query()
            ->where(function ($query) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%miguel%aular%'])
                    ->orWhereRaw('LOWER(email) = ?', ['miguelaular17@gmail.com']);
            })
            ->orderBy('id')
            ->first();
    }
}
