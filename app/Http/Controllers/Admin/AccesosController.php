<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccesosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** El administrador desactiva accesos y funciones para todos o para una persona (gerente incluido). */
class AccesosController extends Controller
{
    public function __construct(private AccesosService $accesos) {}

    public function index(Request $request): View
    {
        $this->soloAdmin($request);
        $usuarios = User::query()->where('role', '!=', User::ROLE_ADMIN)->orderBy('name')->get(['id', 'name', 'email', 'role']);
        $usuarioId = $request->integer('usuario') ?: null;
        $usuario = $usuarioId ? $usuarios->firstWhere('id', $usuarioId) : null;
        if ($usuarioId && ! $usuario) {
            abort(404);
        }

        // Cuántas personas tienen algo desactivado (para mostrarlo en el selector).
        $porUsuario = \Illuminate\Support\Facades\Schema::hasTable('accesos_bloqueados')
            ? DB::table('accesos_bloqueados')->whereNotNull('user_id')->selectRaw('user_id, COUNT(*) AS n')->groupBy('user_id')->pluck('n', 'user_id')
            : collect();

        return view('admin.accesos.index', [
            'catalogo' => $this->accesos->catalogo(),
            'usuarios' => $usuarios,
            'usuario' => $usuario,
            'marcadas' => $this->accesos->clavesGuardadas($usuario?->id),
            'globales' => $usuario ? $this->accesos->clavesGuardadas(null) : [],
            'porUsuario' => $porUsuario,
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $this->soloAdmin($request);
        $data = $request->validate([
            'usuario' => ['nullable', 'integer'],
            'claves' => ['nullable', 'array'],
            'claves.*' => ['string', 'max:120'],
        ]);
        $usuario = null;
        if (! empty($data['usuario'])) {
            $usuario = User::query()->whereKey($data['usuario'])->where('role', '!=', User::ROLE_ADMIN)->firstOrFail();
        }

        $claves = $data['claves'] ?? [];
        $this->accesos->guardar($usuario?->id, $claves, $request->user()->id);

        $para = $usuario ? $usuario->name : 'todos los usuarios';

        return redirect()
            ->route('admin.accesos.index', array_filter(['usuario' => $usuario?->id]))
            ->with('status', count($claves) === 0
                ? "Sin accesos desactivados para {$para}."
                : count(array_intersect($claves, $this->accesos->clavesValidas())) . " acceso(s) desactivado(s) para {$para}.");
    }

    /** El gerente entra al grupo admin (isAdmin), pero esta pantalla es solo del administrador. */
    private function soloAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === User::ROLE_ADMIN, 403, 'Solo el administrador gestiona los accesos.');
    }
}
