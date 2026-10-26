<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Accesos y funciones desactivados por el administrador (config/accesos.php + permisos).
 * Se aplican a cualquier rol, gerente incluido; el administrador nunca queda bloqueado.
 */
class AccesosService
{
    public const PREFIJO_PERMISO = 'permiso:';

    /** @var array<int, list<string>> claves bloqueadas por usuario (memo de la petición) */
    private array $memo = [];

    private ?bool $hayTabla = null;

    /** @var array<int, list<\Illuminate\Routing\Route>> */
    private array $rutasMemo = [];

    /**
     * @return array<string, array<string, array{label: string, rutas?: list<string>}>> grupo => clave => acceso
     */
    public function catalogo(): array
    {
        $grupos = config('accesos.grupos', []);
        $permisos = config('permissions.assignable', []);
        foreach (config('permissions.groups', []) as $grupo => $claves) {
            foreach ($claves as $p) {
                if (isset($permisos[$p])) {
                    $grupos['Funciones · ' . $grupo][self::PREFIJO_PERMISO . $p] = ['label' => $permisos[$p]];
                }
            }
        }

        return $grupos;
    }

    /** @return list<string> */
    public function clavesValidas(): array
    {
        return array_merge(...array_values(array_map('array_keys', $this->catalogo())));
    }

    /**
     * Claves desactivadas que aplican a la persona: las de todos + las suyas.
     *
     * @return list<string>
     */
    public function clavesBloqueadas(?User $user): array
    {
        if (! $this->tabla()) {
            return [];
        }
        // Sin sesión: solo cuentan los accesos rápidos de la pantalla de inicio desactivados para todos.
        if (! $user) {
            return $this->memo[0] ??= array_values(array_intersect($this->clavesGuardadas(null), $this->clavesInvitado()));
        }
        // Solo el rol admin queda fuera (isAdmin() también incluye al gerente, que sí se puede limitar).
        if ($user->role === User::ROLE_ADMIN) {
            return [];
        }

        return $this->memo[$user->id] ??= DB::table('accesos_bloqueados')
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id))
            ->distinct()
            ->pluck('clave')
            ->map(fn ($c) => (string) $c)
            ->all();
    }

    /** @return list<string> claves guardadas solo para todos (user_id NULL) o solo para la persona */
    public function clavesGuardadas(?int $userId): array
    {
        if (! $this->tabla()) {
            return [];
        }

        return DB::table('accesos_bloqueados')
            ->when($userId === null, fn ($q) => $q->whereNull('user_id'), fn ($q) => $q->where('user_id', $userId))
            ->pluck('clave')->map(fn ($c) => (string) $c)->all();
    }

    /** @param  list<string>  $claves */
    public function guardar(?int $userId, array $claves, ?int $adminId): void
    {
        $claves = array_values(array_unique(array_intersect($claves, $this->clavesValidas())));
        if ($userId !== null) {
            // Los accesos rápidos de inicio son para quien no tiene sesión: solo se desactivan para todos.
            $claves = array_values(array_diff($claves, $this->clavesInvitado()));
        }
        DB::transaction(function () use ($userId, $claves, $adminId) {
            DB::table('accesos_bloqueados')
                ->when($userId === null, fn ($q) => $q->whereNull('user_id'), fn ($q) => $q->where('user_id', $userId))
                ->delete();
            $ahora = now();
            DB::table('accesos_bloqueados')->insert(array_map(fn ($c) => [
                'clave' => $c, 'user_id' => $userId, 'creado_por' => $adminId, 'created_at' => $ahora, 'updated_at' => $ahora,
            ], $claves));
        });
        $this->memo = [];
        $this->rutasMemo = [];
    }

    /** Permisos negados como "función" (los usa User::canAccess). */
    public function permisoBloqueado(User $user, string $permiso): bool
    {
        return in_array(self::PREFIJO_PERMISO . $permiso, $this->clavesBloqueadas($user), true);
    }

    /** @return list<string> patrones de nombre de ruta bloqueados para la persona (o para quien no tiene sesión) */
    public function patronesRuta(?User $user): array
    {
        $accesos = array_merge(...array_values(config('accesos.grupos', [])));
        $patrones = [];
        foreach ($this->clavesBloqueadas($user) as $clave) {
            // Los accesos rápidos de inicio son para invitados; con sesión manda el acceso normal.
            if ($user && ! empty($accesos[$clave]['invitado'])) {
                continue;
            }
            foreach ($accesos[$clave]['rutas'] ?? [] as $p) {
                $patrones[] = $p;
            }
        }

        return $patrones;
    }

    /** @return list<string> claves de los accesos rápidos públicos (pantalla de inicio) */
    public function clavesInvitado(): array
    {
        $out = [];
        foreach (config('accesos.grupos', []) as $items) {
            foreach ($items as $clave => $acceso) {
                if (! empty($acceso['invitado'])) {
                    $out[] = $clave;
                }
            }
        }

        return $out;
    }

    /** Un atajo de la pantalla de inicio sigue visible. */
    public function accesoRapidoActivo(string $clave): bool
    {
        return ! in_array($clave, $this->clavesBloqueadas(null), true);
    }

    public function rutaBloqueada(?User $user, ?string $nombreRuta): bool
    {
        if ($nombreRuta === null || $nombreRuta === '') {
            return false;
        }
        $patrones = $this->patronesRuta($user);

        return $patrones !== [] && Str::is($patrones, $nombreRuta);
    }

    public function peticionBloqueada(?User $user, Request $request): bool
    {
        $patrones = $this->patronesRuta($user);

        return $patrones !== [] && $request->routeIs(...$patrones);
    }

    /** Un enlace del menú o de un atajo apunta a una ruta desactivada. */
    public function urlBloqueada(?User $user, string $url): bool
    {
        if ($this->patronesRuta($user) === []) {
            return false;
        }
        $rutas = $this->rutasMemo[$user?->id ?? 0] ??= collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => $this->rutaBloqueada($user, $r->getName()))
            ->values()->all();
        if ($rutas === []) {
            return false;
        }
        $peticion = Request::create($url, 'GET');
        foreach ($rutas as $ruta) {
            if (in_array('GET', $ruta->methods(), true) && $ruta->matches($peticion)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Quita del menú lateral los enlaces desactivados (y los grupos que quedan vacíos).
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function filtrarMenu(?User $user, array $items): array
    {
        if ($this->patronesRuta($user) === []) {
            return $items;
        }
        $out = [];
        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'link') {
                if (! $this->urlBloqueada($user, (string) ($item['url'] ?? ''))) {
                    $out[] = $item;
                }

                continue;
            }
            if (isset($item['items']) && is_array($item['items'])) {
                $item['items'] = $this->filtrarMenu($user, $item['items']);
                if ($item['items'] === []) {
                    continue;
                }
            }
            $out[] = $item;
        }

        return $out;
    }

    private function tabla(): bool
    {
        if ($this->hayTabla !== null) {
            return $this->hayTabla;
        }
        try {
            return $this->hayTabla = Schema::hasTable('accesos_bloqueados');
        } catch (\Throwable) {
            return $this->hayTabla = false;
        }
    }
}
