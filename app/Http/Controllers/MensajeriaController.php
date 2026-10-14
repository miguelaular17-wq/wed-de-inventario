<?php

namespace App\Http\Controllers;

use App\Services\InterconectadosSmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MensajeriaController extends Controller
{
    public function __construct(private InterconectadosSmsService $sms)
    {
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            abort_unless($user && ($user->isAdmin() || $user->isGerente()), 403);

            return $next($request);
        });
    }

    public function index(): View
    {
        return view('mensajeria.index', [
            'usuarioConfigurado' => (string) config('sms.user'),
            'claveConfigurada' => (string) config('sms.password') !== '',
            'resultados' => session('mensajeria_resultados', []),
        ]);
    }

    public function enviar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'modo' => ['required', 'in:uno,masivo'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'numeros' => ['nullable', 'string', 'max:20000'],
            'mensaje' => ['required', 'string', 'max:320'],
        ]);

        $user = (string) config('sms.user');
        $password = (string) config('sms.password');
        if ($user === '' || $password === '') {
            return back()->withInput()->with('error', 'Abre la configuración y guarda el usuario y la clave de la API.');
        }

        $numeros = $data['modo'] === 'masivo'
            ? $this->sms->separarNumeros((string) ($data['numeros'] ?? ''))
            : array_filter([trim((string) ($data['telefono'] ?? ''))]);

        if ($numeros === []) {
            return back()->withInput()->with('error', 'Escribe al menos un número.');
        }
        if (count($numeros) > 300) {
            return back()->withInput()->with('error', 'El envío masivo acepta hasta 300 números por vez.');
        }

        $resultados = [];
        foreach ($numeros as $numero) {
            $resultados[] = $this->sms->enviar($numero, $data['mensaje'], $user, $password);
        }

        $enviados = count(array_filter($resultados, fn (array $fila) => $fila['ok']));

        return redirect()
            ->route('mensajeria.index')
            ->with('mensajeria_resultados', $resultados)
            ->with('success', $enviados.' de '.count($resultados).' mensajes enviados.');
    }

    public function configuracion(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user' => ['required', 'string', 'max:80'],
            'password' => ['nullable', 'string', 'max:80'],
        ]);

        $user = trim($data['user']);
        $password = (string) ($data['password'] ?? '');
        if ($password === '') {
            $password = (string) config('sms.password');
        }
        if ($password === '') {
            return back()->with('error', 'Escribe la clave de la API.');
        }

        $this->guardarCredenciales($user, $password);

        return redirect()->route('mensajeria.index')->with('success', 'Configuración de la API guardada.');
    }

    private function guardarCredenciales(string $user, string $password): void
    {
        $path = base_path('.env');
        $env = is_file($path) ? (string) file_get_contents($path) : '';
        foreach (['SMS_USER' => $user, 'SMS_PASSWORD' => $password, 'SMS_API_URL' => (string) config('sms.url')] as $clave => $valor) {
            $linea = $clave.'='.$this->lineaEnv($valor);
            if (preg_match('/^'.$clave.'=.*$/m', $env)) {
                $env = preg_replace('/^'.$clave.'=.*$/m', $linea, $env) ?? $env;
            } else {
                $env = rtrim($env)."\n".$linea."\n";
            }
        }
        file_put_contents($path, $env);
        config([
            'sms.user' => $user,
            'sms.password' => $password,
        ]);
    }

    private function lineaEnv(string $valor): string
    {
        if ($valor === '' || preg_match('/[\s#="\']/', $valor)) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $valor).'"';
        }

        return $valor;
    }
}
