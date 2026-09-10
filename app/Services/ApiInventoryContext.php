<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ApiInventoryContext
{
    public function sede(Request $request): string
    {
        /** @var User|null $user */
        $user = $request->user();
        $solicitada = strtoupper(trim((string) (
            $request->header('X-Sede-Local')
            ?: $request->input('sede')
            ?: $request->query('sede')
        )));

        if ($user?->sedeIsLocked()) {
            $fija = strtoupper(trim((string) $user->sede));
            if ($fija === '') {
                throw ValidationException::withMessages([
                    'sede' => 'La cuenta no tiene una sede asignada.',
                ]);
            }
            if ($solicitada !== '' && $solicitada !== $fija) {
                throw ValidationException::withMessages([
                    'sede' => 'Esta cuenta solo puede operar en su sede asignada.',
                ]);
            }
            $solicitada = $fija;
        } elseif ($solicitada === '' && $user?->sede) {
            $solicitada = strtoupper((string) $user->sede);
        }

        if (! in_array($solicitada, config('inventario.sedes_locales', []), true)) {
            throw ValidationException::withMessages([
                'sede' => 'Selecciona una sede de operación válida.',
            ]);
        }

        return $solicitada;
    }

    public function usuarioScope(?User $user): ?string
    {
        if (! $user || $user->isAdmin() || $user->isGerente()) {
            return null;
        }

        return $user->email;
    }

    /**
     * @return list<array{codigo:string,nombre:string}>
     */
    public function sedesLocales(): array
    {
        return collect(config('inventario.sedes_locales', []))
            ->map(fn (string $codigo) => [
                'codigo' => $codigo,
                'nombre' => config('inventario.display.'.$codigo, $codigo),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{codigo:string,nombre:string}>
     */
    public function sedesOrigen(string $sedeLocal): array
    {
        return collect(config('inventario.sedes_stock', []))
            ->reject(fn (string $codigo) => $codigo === $sedeLocal)
            ->map(fn (string $codigo) => [
                'codigo' => $codigo,
                'nombre' => config('inventario.display.'.$codigo, $codigo),
            ])
            ->values()
            ->all();
    }
}
