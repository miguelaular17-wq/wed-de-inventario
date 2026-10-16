<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class UploadLocalImages extends Command
{
    protected $signature = 'imagenes:subir-locales
        {directorio : Ruta de la carpeta con las imágenes}
        {--produccion : Usar la BDD de producción (SYNC_DESKTOP_*)}
        {--dry-run : Solo mostrar qué se subiría, sin subir}';

    protected $description = 'Sube imágenes locales a Supabase para productos sin foto (omite placeholders NO IMAGE)';

    /** MD5 de placeholders “NO IMAGE AVAILABLE” exportados de Profit. */
    private const PLACEHOLDER_MD5 = [
        '34097c870e54de7ea5596680bee5aad6', // 5743 bytes
        'f28f7987119868dd7ed204981a00ade5', // 6517 bytes
    ];

    private const PLACEHOLDER_SIZES = [5743, 6517];

    public function handle(): int
    {
        ini_set('memory_limit', '512M');

        $dir = $this->argument('directorio');
        if (! File::isDirectory($dir)) {
            $this->error("El directorio no existe: $dir");

            return 1;
        }

        $supabaseUrl = rtrim((string) env('SUPABASE_URL'), '/');
        $supabaseKey = (string) env('SUPABASE_KEY');
        if ($supabaseUrl === '' || $supabaseKey === '') {
            $this->error('Faltan SUPABASE_URL / SUPABASE_KEY en .env');

            return 1;
        }

        $connection = $this->resolveConnection();
        $this->info('Conexión: '.$connection.($this->option('produccion') ? ' (producción)' : ' (local)'));

        $this->info('Indexando carpeta (omite NO IMAGE)...');
        $fileIndex = $this->indexDirectory($dir);
        $this->info('Imágenes reales en carpeta: '.count($fileIndex));

        $this->info('Cargando productos sin imagen en BDD...');
        $productos = DB::connection($connection)->select(
            "SELECT id, codigo FROM inventario_v2.productos
             WHERE url_imagen IS NULL
                OR url_imagen = ''
                OR url_imagen ILIKE '%no-image%'"
        );

        if ($productos === []) {
            $this->info('Todos los productos ya tienen imagen.');

            return 0;
        }
        $this->info('Productos sin imagen en BDD: '.count($productos));

        // Solo los que faltan Y tienen foto real en la carpeta (1 archivo = 1 producto).
        $pendientes = [];
        $usados = [];
        $sinMatch = 0;
        foreach ($productos as $p) {
            $codigo = trim((string) $p->codigo);
            if ($codigo === '') {
                $sinMatch++;

                continue;
            }
            $path = $this->findFileForCodigo($codigo, $fileIndex, $usados);
            if ($path === null) {
                $sinMatch++;

                continue;
            }
            $usados[$path] = true;
            $pendientes[] = ['id' => (int) $p->id, 'codigo' => $codigo, 'path' => $path];
        }

        $this->info('Coincidencias a subir (faltan + hay foto real): '.count($pendientes));
        $this->info('Sin foto en carpeta (no se tocan): '.$sinMatch);

        if ($pendientes === []) {
            $this->info('Nada que subir.');

            return 0;
        }

        $dry = (bool) $this->option('dry-run');
        $subidas = 0;
        $errores = 0;

        $this->output->progressStart(count($pendientes));

        foreach ($pendientes as $item) {
            $this->output->progressAdvance();

            $path = $item['path'];
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $nombreBase = pathinfo($path, PATHINFO_FILENAME);

            if ($dry) {
                $subidas++;

                continue;
            }

            $contenido = file_get_contents($path);
            if ($contenido === false || $contenido === '') {
                $errores++;

                continue;
            }

            $mime = $this->mimeFromExt($ext);
            $safeBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nombreBase) ?: ('p'.$item['id']);
            $safeBase = trim($safeBase, '._-') ?: ('p'.$item['id']);
            $fileName = $safeBase.'.'.$ext;
            $uploadUrl = "{$supabaseUrl}/storage/v1/object/imagenes_producto/imagenes/{$fileName}";

            $response = Http::withoutVerifying()
                ->timeout(60)
                ->withHeaders([
                    'Authorization' => "Bearer {$supabaseKey}",
                    'Content-Type' => $mime,
                    'x-upsert' => 'true',
                ])
                ->withBody($contenido, $mime)
                ->post($uploadUrl);

            unset($contenido);

            if (! $response->successful()) {
                $errores++;
                $this->newLine();
                $this->error("Error subiendo {$nombreBase}: ".$response->body());

                continue;
            }

            $publicUrl = "{$supabaseUrl}/storage/v1/object/public/imagenes_producto/imagenes/{$fileName}";
            DB::connection($connection)->update(
                'UPDATE inventario_v2.productos SET url_imagen = ? WHERE id = ?',
                [$publicUrl, $item['id']]
            );

            $subidas++;

            if ($subidas % 100 === 0) {
                gc_collect_cycles();
            }
        }

        $this->output->progressFinish();
        $this->info($dry ? 'Dry-run completado.' : 'Proceso completado.');
        $this->info('Imágenes subidas/asignadas: '.$subidas.' (solo las que faltaban y tenían foto real)');
        $this->info('Errores de subida: '.$errores);

        return $errores > 0 ? 1 : 0;
    }

    /**
     * @return array<string, string> lowercase basename => full path
     */
    private function indexDirectory(string $dir): array
    {
        $index = [];
        $placeholderCount = 0;

        foreach (File::files($dir) as $archivo) {
            $ext = strtolower($archivo->getExtension());
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'], true)) {
                continue;
            }

            $size = $archivo->getSize();
            if (in_array($size, self::PLACEHOLDER_SIZES, true)) {
                $md5 = md5_file($archivo->getPathname());
                if (in_array($md5, self::PLACEHOLDER_MD5, true)) {
                    $placeholderCount++;

                    continue;
                }
            }

            $key = mb_strtolower(trim($archivo->getFilenameWithoutExtension()), 'UTF-8');
            if ($key !== '' && ! isset($index[$key])) {
                $index[$key] = $archivo->getPathname();
            }
        }

        $this->info('Omitidas (NO IMAGE AVAILABLE): '.$placeholderCount);

        return $index;
    }

    /**
     * @param  array<string, string>  $fileIndex
     * @param  array<string, true>  $usados
     */
    private function findFileForCodigo(string $codigo, array $fileIndex, array $usados): ?string
    {
        $candidatos = [$codigo];
        foreach (preg_split('/[\/\\\\]+/', $codigo) ?: [] as $parte) {
            $parte = trim($parte);
            if ($parte !== '') {
                $candidatos[] = $parte;
            }
        }

        foreach ($candidatos as $c) {
            $key = mb_strtolower($c, 'UTF-8');
            if (isset($fileIndex[$key]) && ! isset($usados[$fileIndex[$key]])) {
                return $fileIndex[$key];
            }
        }

        return null;
    }

    private function mimeFromExt(string $ext): string
    {
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            default => 'application/octet-stream',
        };
    }

    private function resolveConnection(): string
    {
        if (! $this->option('produccion')) {
            return 'pgsql';
        }

        $host = (string) env('SYNC_DESKTOP_DB_HOST');
        $port = (string) env('SYNC_DESKTOP_DB_PORT', '5432');
        $database = (string) env('SYNC_DESKTOP_DB_DATABASE', 'postgres');
        $username = (string) env('SYNC_DESKTOP_DB_USER');
        $password = (string) env('SYNC_DESKTOP_DB_PASSWORD');

        if ($host === '' || $username === '') {
            $this->error('Faltan SYNC_DESKTOP_DB_* en .env para producción.');
            exit(1);
        }

        config([
            'database.connections.pgsql_produccion' => [
                'driver' => 'pgsql',
                'host' => $host,
                'port' => $port,
                'database' => $database,
                'username' => $username,
                'password' => $password,
                'charset' => 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
                'search_path' => 'inventario_v2,public',
                'sslmode' => 'require',
                'options' => extension_loaded('pdo_pgsql') ? [
                    \PDO::ATTR_EMULATE_PREPARES => true,
                ] : [],
            ],
        ]);

        DB::purge('pgsql_produccion');

        return 'pgsql_produccion';
    }
}
