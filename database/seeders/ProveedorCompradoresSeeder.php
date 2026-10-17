<?php

namespace Database\Seeders;

use App\Models\ProveedorComprador;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProveedorCompradoresSeeder extends Seeder
{
    /**
     * Asigna comprador a cada proveedor (Excel COMPR Y PROVEEDOR / Hoja2).
     *
     * | Clave  | Usuario         | Proveedores |
     * |--------|-----------------|-------------|
     * | MAVO   | Edward mavo     | 158         |
     * | CARLOS | CARLOS SEMECO   | 67          |
     * | PAOLA  | paola colina    | 71          |
     */
    private const USUARIOS = [
        'MAVO' => [
            'emails' => ['edward.mavo.corporativo@gmail.com'],
            'names' => ['edward mavo', 'mavo'],
        ],
        'CARLOS' => [
            'emails' => ['semeco@gmail.com'],
            'names' => ['carlos semeco'],
        ],
        'PAOLA' => [
            'emails' => ['pao_cristina92_13@hotmail.es'],
            'names' => ['paola colina'],
        ],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('proveedor_compradores')) {
            $this->command?->warn('Tabla proveedor_compradores no existe. Corre migraciones primero.');

            return;
        }

        $pares = $this->cargarPares();
        if ($pares === []) {
            $this->command?->error('No se encontraron pares proveedor→comprador.');

            return;
        }

        $mapaUsers = $this->resolverUsuarios();
        foreach (self::USUARIOS as $clave => $_) {
            $uid = $mapaUsers[$clave] ?? null;
            $this->command?->info($uid
                ? "{$clave} → user #{$uid} (".(User::find($uid)?->name ?? '').')'
                : "{$clave} → SIN USUARIO (no se encontró en users)");
        }

        $now = now();
        $rows = [];
        $sinUser = 0;
        foreach ($pares as $proveedor => $clave) {
            $clave = mb_strtoupper(trim((string) $clave), 'UTF-8');
            $userId = $mapaUsers[$clave] ?? null;
            if (! $userId) {
                $sinUser++;
            }
            $rows[] = [
                'proveedor' => trim((string) $proveedor),
                'comprador_clave' => $clave !== '' ? $clave : null,
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 100) as $chunk) {
                ProveedorComprador::query()->upsert(
                    $chunk,
                    ['proveedor'],
                    ['comprador_clave', 'user_id', 'updated_at']
                );
            }
        });

        $totales = ProveedorComprador::query()
            ->selectRaw('comprador_clave, count(*) as n')
            ->groupBy('comprador_clave')
            ->pluck('n', 'comprador_clave');

        $this->command?->info('ProveedorCompradoresSeeder: '.count($rows).' filas (sin user: '.$sinUser.').');
        foreach (['MAVO', 'CARLOS', 'PAOLA'] as $clave) {
            $this->command?->line("  {$clave}: ".((int) ($totales[$clave] ?? 0)));
        }
    }

    /** @return array<string, string> */
    private function cargarPares(): array
    {
        $path = database_path('seeders/data/proveedor_compradores.json');
        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    /** @return array<string, int> */
    private function resolverUsuarios(): array
    {
        $out = [];
        $users = User::query()->get(['id', 'name', 'email', 'role']);

        foreach (self::USUARIOS as $clave => $cfg) {
            $emails = array_map('strtolower', $cfg['emails'] ?? []);
            $names = array_map('mb_strtolower', $cfg['names'] ?? []);

            $match = $users->first(function (User $u) use ($emails, $names) {
                $email = mb_strtolower((string) $u->email);
                $name = mb_strtolower(trim((string) $u->name));
                if (in_array($email, $emails, true)) {
                    return true;
                }
                foreach ($names as $n) {
                    if ($n !== '' && ($name === $n || str_contains($name, $n))) {
                        return true;
                    }
                }

                return false;
            });

            if ($match) {
                $out[$clave] = (int) $match->id;
            }
        }

        return $out;
    }
}
