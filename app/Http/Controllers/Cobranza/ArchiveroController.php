<?php

namespace App\Http\Controllers\Cobranza;

use App\Http\Controllers\Controller;
use App\Models\ArchiveroArchivo;
use App\Services\ArchiveroStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Archivero de Cobranza: carpetas y archivos (lo que estaba en el pendrive y lo que se suba desde la web). */
class ArchiveroController extends Controller
{
    public function __construct(private ArchiveroStorage $storage) {}

    public function index(Request $request): View
    {
        $carpeta = self::normalizar((string) $request->query('carpeta', ''));
        $q = trim((string) $request->query('q', ''));

        if ($q !== '') {
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $archivos = ArchiveroArchivo::query()
                ->where(fn ($w) => $w->where('nombre', $like, '%'.$q.'%')->orWhere('carpeta', $like, '%'.$q.'%'))
                ->orderBy('carpeta')->orderBy('nombre')->limit(300)->get();

            return view('cobranza.archivero.index', [
                'carpeta' => $carpeta, 'q' => $q, 'subcarpetas' => collect(), 'archivos' => $archivos, 'migas' => self::migas($carpeta),
            ]);
        }

        return view('cobranza.archivero.index', [
            'carpeta' => $carpeta,
            'q' => '',
            'subcarpetas' => $this->subcarpetas($carpeta),
            'archivos' => ArchiveroArchivo::query()->where('carpeta', $carpeta)->orderBy('nombre')->get(),
            'migas' => self::migas($carpeta),
        ]);
    }

    public function subir(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'carpeta' => ['nullable', 'string', 'max:500'],
            'archivos' => ['required', 'array', 'min:1', 'max:30'],
            'archivos.*' => ['file', 'max:51200'],
        ]);
        $carpeta = self::normalizar((string) ($data['carpeta'] ?? ''));
        $n = 0;
        foreach ($request->file('archivos') as $archivo) {
            $nombre = self::nombreLibre($carpeta, $archivo->getClientOriginalName());
            $ext = mb_strtolower($archivo->getClientOriginalExtension());
            $clave = $this->storage->nuevaClave($ext);
            $mime = $archivo->getMimeType() ?: 'application/octet-stream';
            $this->storage->subir($clave, fopen($archivo->getRealPath(), 'r'), $mime);
            ArchiveroArchivo::create([
                'carpeta' => $carpeta, 'nombre' => $nombre, 'extension' => $ext, 'tamano' => $archivo->getSize(),
                'mime' => $mime, 'objeto' => $clave, 'origen' => 'web', 'modificado_en' => now(), 'user_id' => $request->user()?->id,
            ]);
            $n++;
        }

        return redirect()->route('cobranza.archivero.index', array_filter(['carpeta' => $carpeta]))
            ->with('success', $n === 1 ? 'Archivo guardado.' : "{$n} archivos guardados.");
    }

    public function crearCarpeta(Request $request): RedirectResponse
    {
        $data = $request->validate(['carpeta' => ['nullable', 'string', 'max:500'], 'nombre' => ['required', 'string', 'max:120']]);
        $nombre = trim(str_replace(['/', '\\'], '-', $data['nombre']));
        $ruta = self::normalizar(trim((string) ($data['carpeta'] ?? '')).'/'.$nombre);
        DB::table('archivero_carpetas')->insertOrIgnore(['ruta' => $ruta, 'user_id' => $request->user()?->id, 'created_at' => now(), 'updated_at' => now()]);

        return redirect()->route('cobranza.archivero.index', ['carpeta' => $ruta])->with('success', 'Carpeta creada.');
    }

    public function ver(ArchiveroArchivo $archivo): StreamedResponse
    {
        $respuesta = $this->storage->descargar($archivo->objeto);
        $cuerpo = $respuesta->toPsrResponse()->getBody();
        $disposicion = ($archivo->seVeEnNavegador() ? 'inline' : 'attachment').'; filename="'.addcslashes($archivo->nombre, '"\\').'"';

        return response()->stream(function () use ($cuerpo) {
            while (! $cuerpo->eof()) {
                echo $cuerpo->read(1024 * 256);
                flush();
            }
        }, 200, [
            'Content-Type' => $archivo->mime ?: 'application/octet-stream',
            'Content-Disposition' => $disposicion,
            'Content-Length' => (string) $archivo->tamano,
            'Cache-Control' => 'private, max-age=0',
        ]);
    }

    public function borrar(ArchiveroArchivo $archivo): RedirectResponse
    {
        $this->storage->borrar($archivo->objeto);
        $carpeta = $archivo->carpeta;
        $archivo->delete();

        return redirect()->route('cobranza.archivero.index', array_filter(['carpeta' => $carpeta]))->with('success', 'Archivo borrado.');
    }

    /**
     * Carpetas que cuelgan directamente de $carpeta, con cuántos archivos tienen dentro (contando subcarpetas).
     *
     * @return \Illuminate\Support\Collection<int, array{ruta: string, nombre: string, archivos: int}>
     */
    private function subcarpetas(string $carpeta): \Illuminate\Support\Collection
    {
        $prefijo = $carpeta === '' ? '' : $carpeta.'/';
        $rutas = ArchiveroArchivo::query()
            ->when($carpeta !== '', fn ($q) => $q->where('carpeta', 'like', addcslashes($prefijo, '%_').'%'))
            ->where('carpeta', '!=', $carpeta)
            ->selectRaw('carpeta, COUNT(*) as n')->groupBy('carpeta')->pluck('n', 'carpeta')
            ->all();
        foreach (DB::table('archivero_carpetas')->when($carpeta !== '', fn ($q) => $q->where('ruta', 'like', addcslashes($prefijo, '%_').'%'))->pluck('ruta') as $ruta) {
            $rutas[$ruta] ??= 0;
        }

        $hijas = [];
        foreach ($rutas as $ruta => $n) {
            $resto = $carpeta === '' ? $ruta : mb_substr($ruta, mb_strlen($prefijo));
            if ($resto === '' || ($carpeta !== '' && ! str_starts_with($ruta, $prefijo))) {
                continue;
            }
            $nombre = explode('/', $resto)[0];
            $hijas[$nombre] = ($hijas[$nombre] ?? 0) + (int) $n;
        }
        uksort($hijas, fn ($a, $b) => strnatcasecmp($a, $b));

        return collect($hijas)->map(fn ($n, $nombre) => ['ruta' => $prefijo.$nombre, 'nombre' => (string) $nombre, 'archivos' => $n])->values();
    }

    /** @return list<array{nombre: string, ruta: string}> */
    private static function migas(string $carpeta): array
    {
        $migas = [];
        $acumulado = '';
        foreach ($carpeta === '' ? [] : explode('/', $carpeta) as $parte) {
            $acumulado = $acumulado === '' ? $parte : $acumulado.'/'.$parte;
            $migas[] = ['nombre' => $parte, 'ruta' => $acumulado];
        }

        return $migas;
    }

    /** «\COBRANZAS JENU\\2025\» → «COBRANZAS JENU/2025». */
    public static function normalizar(string $ruta): string
    {
        $partes = array_filter(array_map('trim', preg_split('#[\\\\/]+#', $ruta) ?: []), fn ($p) => $p !== '' && $p !== '.' && $p !== '..');

        return implode('/', $partes);
    }

    /** Si ya hay un archivo con ese nombre en la carpeta, «nombre (2).pdf». */
    private static function nombreLibre(string $carpeta, string $nombre): string
    {
        $nombre = trim(str_replace(['/', '\\'], '-', $nombre)) ?: 'archivo';
        $base = pathinfo($nombre, PATHINFO_FILENAME);
        $ext = pathinfo($nombre, PATHINFO_EXTENSION);
        $candidato = $nombre;
        for ($i = 2; ArchiveroArchivo::query()->where('carpeta', $carpeta)->where('nombre', $candidato)->exists(); $i++) {
            $candidato = $base.' ('.$i.')'.($ext !== '' ? '.'.$ext : '');
        }

        return $candidato;
    }
}
