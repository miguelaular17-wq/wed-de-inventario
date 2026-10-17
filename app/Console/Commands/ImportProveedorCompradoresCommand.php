<?php

namespace App\Console\Commands;

use App\Models\ProveedorComprador;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportProveedorCompradoresCommand extends Command
{
    protected $signature = 'compras:import-proveedor-compradores
        {path? : Ruta al Excel COMPR Y PROVEEDOR.xlsx}
        {--dry-run : Solo muestra el resumen sin escribir}';

    protected $description = 'Asigna un comprador a cada proveedor desde el Excel (Hoja2: proveedor, comprador)';

    /** Clave Excel → pistas para encontrar el user comprador. */
    private const ALIASES = [
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

    public function handle(): int
    {
        $path = $this->argument('path')
            ?: 'c:/Users/freyg/Downloads/COMPR Y PROVEEDOR.xlsx';

        if (! is_file($path)) {
            $this->error("No se encontró: {$path}");

            return self::FAILURE;
        }

        $pares = $this->leerHoja2($path);
        if ($pares === []) {
            $this->error('Hoja2 vacía o sin columnas proveedor/comprador.');

            return self::FAILURE;
        }

        $mapaUsers = $this->resolverUsuarios();
        foreach (self::ALIASES as $clave => $_) {
            $uid = $mapaUsers[$clave] ?? null;
            $this->line($uid
                ? "  {$clave} → user #{$uid} (".User::find($uid)?->name.')'
                : "  {$clave} → SIN USUARIO (revisar aliases)");
        }

        $dry = (bool) $this->option('dry-run');
        $upserts = [];
        $sinUser = 0;
        foreach ($pares as $proveedor => $clave) {
            $clave = mb_strtoupper(trim($clave), 'UTF-8');
            $userId = $mapaUsers[$clave] ?? null;
            if (! $userId) {
                $sinUser++;
            }
            $upserts[] = [
                'proveedor' => $proveedor,
                'comprador_clave' => $clave !== '' ? $clave : null,
                'user_id' => $userId,
                'updated_at' => now(),
                'created_at' => now(),
            ];
        }

        $this->info(($dry ? '[dry-run] ' : '').count($upserts).' proveedores; sin user: '.$sinUser);

        if ($dry) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($upserts) {
            foreach (array_chunk($upserts, 100) as $chunk) {
                ProveedorComprador::query()->upsert(
                    $chunk,
                    ['proveedor'],
                    ['comprador_clave', 'user_id', 'updated_at']
                );
            }
        });

        $this->info('Importado en proveedor_compradores: '.ProveedorComprador::count().' filas.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, string> proveedor => comprador_clave
     */
    private function leerHoja2(string $path): array
    {
        $script = storage_path('app/_tmp_read_prov_compr.py');
        $outFile = storage_path('app/_tmp_prov_compr.json');
        $py = <<<'PY'
import json, openpyxl, sys
path, out = sys.argv[1], sys.argv[2]
wb = openpyxl.load_workbook(path, data_only=True)
sheet = wb['Hoja2'] if 'Hoja2' in wb.sheetnames else wb.active
rows = list(sheet.iter_rows(values_only=True))
if not rows:
    json.dump({}, open(out, 'w', encoding='utf-8'), ensure_ascii=False)
    raise SystemExit(0)
header = [str(c or '').strip().lower() for c in rows[0]]
try:
    i_prov = header.index('proveedor')
    i_comp = header.index('comprador')
except ValueError:
    i_prov, i_comp = 0, 1
pares = {}
for row in rows[1:]:
    if not row:
        continue
    prov = str(row[i_prov] or '').strip() if i_prov < len(row) else ''
    comp = str(row[i_comp] or '').strip() if i_comp < len(row) else ''
    if prov:
        pares[prov] = comp
json.dump(pares, open(out, 'w', encoding='utf-8'), ensure_ascii=False)
PY;
        file_put_contents($script, $py);
        $cmd = 'python '.escapeshellarg($script).' '.escapeshellarg($path).' '.escapeshellarg($outFile);
        exec($cmd, $output, $code);
        @unlink($script);
        if ($code !== 0 || ! is_file($outFile)) {
            $this->error('No se pudo leer el Excel con Python/openpyxl.');

            return [];
        }
        $data = json_decode((string) file_get_contents($outFile), true);
        @unlink($outFile);

        return is_array($data) ? $data : [];
    }

    /** @return array<string, int> clave => user_id */
    private function resolverUsuarios(): array
    {
        $out = [];
        $users = User::query()->get(['id', 'name', 'email', 'role']);

        foreach (self::ALIASES as $clave => $cfg) {
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
