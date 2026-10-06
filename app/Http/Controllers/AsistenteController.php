<?php

namespace App\Http\Controllers;

use App\Models\AsistenteMensaje;
use App\Services\AsistenteAccionService;
use App\Services\AsistenteAvisoService;
use App\Services\AsistentePaginaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AsistenteController extends Controller
{
    public function mensajes(Request $request): JsonResponse
    {
        return response()->json([
            'mensajes' => $this->historial($request->user()->id),
        ]);
    }

    public function consultar(Request $request, AsistentePaginaService $asistente, AsistenteAccionService $acciones, AsistenteAvisoService $avisos): JsonResponse
    {
        $request->merge([
            'pagina' => $this->pagina($request->input('pagina')),
        ]);

        $data = $request->validate([
            'mensaje' => ['required', 'string', 'max:4000'],
            'pagina' => ['nullable', 'array'],
            'pagina.titulo' => ['nullable', 'string', 'max:180'],
            'pagina.url' => ['nullable', 'string', 'max:200'],
            'pagina.texto' => ['nullable', 'string', 'max:8000'],
            'pagina.campos' => ['nullable', 'array', 'max:80'],
            'pagina.campos.*.clave' => ['nullable', 'string', 'max:80'],
            'pagina.campos.*.etiqueta' => ['nullable', 'string', 'max:80'],
            'pagina.campos.*.tipo' => ['nullable', 'string', 'max:20'],
            'pagina.campos.*.valor' => ['nullable', 'string', 'max:120'],
            'pagina.campos.*.opciones' => ['nullable', 'array', 'max:30'],
            'pagina.campos.*.opciones.*' => ['nullable', 'string', 'max:80'],
            'historial' => ['nullable', 'array', 'max:8'],
            'historial.*.rol' => ['nullable', 'string', 'in:usuario,asistente'],
            'historial.*.texto' => ['nullable', 'string', 'max:1500'],
            'imagen' => ['nullable', 'image', 'max:5120'],
        ]);

        $userId = (int) $request->user()->id;
        $pendiente = \Illuminate\Support\Facades\Cache::get('asistente.pendiente.'.$userId);

        try {
            $resultado = $asistente->consultar(
                $data['mensaje'],
                $data['pagina'] ?? [],
                $this->historial($userId, 6),
                $request->file('imagen'),
                is_array($pendiente) ? $pendiente : null
            );
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $resultado = $acciones->completar($request->user(), $resultado, $data['mensaje']);
        $resultado = $avisos->enviar($request->user(), $resultado, $data['mensaje'], $data['pagina'] ?? []);
        $resultado['guardar'] = false;
        unset($resultado['guardar_bloqueado'], $resultado['avisar']);

        AsistenteMensaje::query()->create([
            'user_id' => $userId,
            'rol' => 'usuario',
            'texto' => $data['mensaje'],
        ]);
        AsistenteMensaje::query()->create([
            'user_id' => $userId,
            'rol' => 'asistente',
            'texto' => $resultado['respuesta'],
        ]);
        $this->recortar($userId);

        return response()->json($resultado);
    }

    /**
     * @return array{titulo:string,url:string,texto:string,campos:list<array{clave:string,etiqueta:string,tipo:string,valor:string,opciones:list<string>}>}
     */
    private function pagina(mixed $pagina): array
    {
        $pagina = is_array($pagina) ? $pagina : [];
        $campos = [];
        foreach (array_slice((array) ($pagina['campos'] ?? []), 0, 80) as $campo) {
            if (! is_array($campo)) {
                continue;
            }
            $opciones = [];
            foreach (array_slice((array) ($campo['opciones'] ?? []), 0, 30) as $opcion) {
                $opciones[] = $this->corte((string) $opcion, 80);
            }
            $campos[] = [
                'clave' => $this->corte((string) ($campo['clave'] ?? ''), 80),
                'etiqueta' => $this->corte((string) ($campo['etiqueta'] ?? ''), 80),
                'tipo' => $this->corte((string) ($campo['tipo'] ?? ''), 20),
                'valor' => $this->corte((string) ($campo['valor'] ?? ''), 120),
                'opciones' => $opciones,
            ];
        }

        return [
            'titulo' => $this->corte((string) ($pagina['titulo'] ?? ''), 180),
            'url' => $this->corte((string) ($pagina['url'] ?? ''), 200),
            'texto' => $this->corte((string) ($pagina['texto'] ?? ''), 8000),
            'campos' => $campos,
        ];
    }

    private function corte(string $valor, int $max): string
    {
        if (mb_strlen($valor, 'UTF-8') <= $max) {
            return $valor;
        }

        return mb_substr($valor, 0, $max, 'UTF-8');
    }

    /**
     * @return list<array{rol:string,texto:string}>
     */
    private function historial(int $userId, int $limite = 40): array
    {
        return AsistenteMensaje::query()
            ->where('user_id', $userId)
            ->latest('id')
            ->limit($limite)
            ->get()
            ->reverse()
            ->map(fn (AsistenteMensaje $mensaje) => [
                'rol' => $mensaje->rol,
                'texto' => $mensaje->texto,
            ])
            ->values()
            ->all();
    }

    private function recortar(int $userId): void
    {
        $conservar = AsistenteMensaje::query()
            ->where('user_id', $userId)
            ->latest('id')
            ->limit(40)
            ->pluck('id');

        AsistenteMensaje::query()
            ->where('user_id', $userId)
            ->whereNotIn('id', $conservar)
            ->delete();
    }
}
