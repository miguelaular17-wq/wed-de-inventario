<?php

namespace App\Console\Commands;

use App\Http\Controllers\Cobranza\ArchiveroController;
use App\Services\ArchiveroStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;

/**
 * Copia una carpeta (por ejemplo el pendrive D:\) al archivero, conservando sus carpetas.
 * Lo que ya está (misma carpeta, nombre y tamaño) no se vuelve a subir: se puede repetir si se corta.
 */
class ArchiveroImportarCommand extends Command
{
    protected $signature = 'archivero:importar
        {origen : Carpeta a copiar, p. ej. D:\}
        {--destino= : Carpeta del archivero donde se pone (por defecto la raíz)}
        {--conexion= : Base de datos donde se registran (p. ej. «produccion»)}
        {--max-mb=50 : No sube archivos más grandes que esto}
        {--simular : Solo muestra lo que haría}';

    protected $description = 'Copia los archivos de una carpeta (pendrive) al archivero de Cobranza';

    /** Basura del sistema, accesos directos, instaladores y descargas a medias. */
    private const EXCLUIR_EXT = ['lnk', 'tmp', 'exe', 'msi', 'crdownload', 'part', 'ini', 'db'];

    /** Archivos que se suben en paralelo. */
    private const A_LA_VEZ = 8;

    public function handle(ArchiveroStorage $storage): int
    {
        $origen = rtrim((string) $this->argument('origen'), '\\/');
        if (! is_dir($origen.DIRECTORY_SEPARATOR)) {
            $this->error("No existe la carpeta {$origen}");

            return self::FAILURE;
        }
        $destino = ArchiveroController::normalizar((string) $this->option('destino'));
        $db = DB::connection($this->option('conexion') ?: null);
        $maxBytes = (int) $this->option('max-mb') * 1024 * 1024;
        $simular = (bool) $this->option('simular');

        // Con el pooler de Supabase (modo transacción) cada consulta suelta puede olvidar el search_path: se fija en cada transacción.
        $esquema = fn (callable $fn) => $db->transaction(function () use ($db, $fn) {
            if ($db->getDriverName() === 'pgsql') {
                $db->statement('SET LOCAL search_path TO '.implode(', ', (array) ($db->getConfig('search_path') ?: 'public')));
            }

            return $fn();
        });

        $existentes = [];
        foreach ($esquema(fn () => $db->table('archivero_archivos')->get(['carpeta', 'nombre', 'tamano'])) as $e) {
            $existentes[$e->carpeta."\0".$e->nombre] = (int) $e->tamano;
        }

        $finder = (new Finder)->files()->in($origen.DIRECTORY_SEPARATOR)->ignoreDotFiles(false)->ignoreUnreadableDirs()
            ->exclude(['System Volume Information', '$RECYCLE.BIN']);

        $stats = ['subidos' => 0, 'ya_estaban' => 0, 'excluidos' => 0, 'muy_grandes' => 0, 'errores' => 0, 'bytes' => 0];
        $grandes = [];
        $lote = [];
        foreach ($finder as $f) {
            $nombre = $f->getFilename();
            $ext = mb_strtolower($f->getExtension());
            if (str_starts_with($nombre, '~$') || in_array($ext, self::EXCLUIR_EXT, true) || in_array(mb_strtolower($nombre), ['thumbs.db', 'desktop.ini'], true)) {
                $stats['excluidos']++;

                continue;
            }
            $carpeta = ArchiveroController::normalizar($destino.'/'.$f->getRelativePath());
            $tamano = (int) $f->getSize();
            if (($existentes[$carpeta."\0".$nombre] ?? null) === $tamano) {
                $stats['ya_estaban']++;

                continue;
            }
            if ($tamano > $maxBytes) {
                $stats['muy_grandes']++;
                $grandes[] = sprintf('%.1f MB  %s', $tamano / 1048576, $f->getPathname());

                continue;
            }
            if ($simular) {
                $stats['subidos']++;
                $stats['bytes'] += $tamano;

                continue;
            }

            $lote[$storage->nuevaClave($ext)] = [
                'ruta' => $f->getPathname(), 'mime' => mime_content_type($f->getPathname()) ?: 'application/octet-stream',
                'carpeta' => $carpeta, 'nombre' => $nombre, 'extension' => $ext, 'tamano' => $tamano,
                'modificado_en' => date('Y-m-d H:i:s', $f->getMTime()),
            ];
            if (count($lote) >= self::A_LA_VEZ) {
                $this->subirLote($lote, $storage, $db, $esquema, $existentes, $stats);
                $lote = [];
            }
        }
        if ($lote !== []) {
            $this->subirLote($lote, $storage, $db, $esquema, $existentes, $stats);
        }

        $this->info(sprintf('%s %d archivos (%.1f MB) · ya estaban %d · excluidos %d · más grandes de %d MB %d · errores %d',
            $simular ? 'Se subirían' : 'Subidos', $stats['subidos'], $stats['bytes'] / 1048576, $stats['ya_estaban'],
            $stats['excluidos'], (int) $this->option('max-mb'), $stats['muy_grandes'], $stats['errores']));
        foreach ($grandes as $g) {
            $this->line('  no subido (muy grande): '.$g);
        }

        return $stats['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** Sube el lote en paralelo y registra en una sola transacción los que entraron. */
    private function subirLote(array $lote, ArchiveroStorage $storage, $db, callable $esquema, array &$existentes, array &$stats): void
    {
        $antes = intdiv($stats['subidos'], 50);
        $subir = function (array $archivos) use ($storage) {
            try {
                return $storage->subirVarios(array_map(fn ($a) => ['ruta' => $a['ruta'], 'mime' => $a['mime']], $archivos));
            } catch (\Throwable $e) {
                return array_fill_keys(array_keys($archivos), $e->getMessage());
            }
        };
        $resultado = $subir($lote);
        // Los cortes de conexión son pasajeros: se reintentan hasta 2 veces, con una pausa.
        for ($intento = 1; $intento <= 2; $intento++) {
            $fallidos = array_intersect_key($lote, array_filter($resultado, fn ($r) => $r !== null));
            if ($fallidos === []) {
                break;
            }
            sleep(5 * $intento);
            $resultado = array_merge($resultado, $subir($fallidos));
        }

        $ok = [];
        foreach ($lote as $clave => $a) {
            if ($resultado[$clave] === null) {
                $ok[$clave] = $a;
            } else {
                $stats['errores']++;
                $this->warn('Error con '.$a['ruta'].': '.mb_substr((string) $resultado[$clave], 0, 200));
            }
        }
        if ($ok === []) {
            return;
        }

        try {
            $esquema(function () use ($db, $ok) {
                foreach ($ok as $clave => $a) {
                    $db->table('archivero_archivos')->updateOrInsert(
                        ['carpeta' => $a['carpeta'], 'nombre' => $a['nombre']],
                        ['extension' => $a['extension'], 'tamano' => $a['tamano'], 'mime' => $a['mime'], 'objeto' => $clave, 'origen' => 'pendrive',
                            'modificado_en' => $a['modificado_en'], 'created_at' => now(), 'updated_at' => now()],
                    );
                }
            });
        } catch (\Throwable $e) {
            $stats['errores'] += count($ok);
            $this->warn('No se pudieron registrar '.count($ok).' archivos: '.mb_substr($e->getMessage(), 0, 200));

            return;
        }

        foreach ($ok as $a) {
            $existentes[$a['carpeta']."\0".$a['nombre']] = $a['tamano'];
            $stats['subidos']++;
            $stats['bytes'] += $a['tamano'];
        }
        if (intdiv($stats['subidos'], 50) > $antes) {
            $this->line(sprintf('… %d subidos (%.0f MB)', $stats['subidos'], $stats['bytes'] / 1048576));
        }
    }
}
