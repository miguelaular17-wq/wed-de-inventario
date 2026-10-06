<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SyncDesktopController extends Controller
{
    private function disk()
    {
        return Storage::disk('local');
    }

    private function dir(): string
    {
        return trim((string) config('sync_desktop.storage_dir'), '/');
    }

    private function manifestPath(): string
    {
        return $this->dir().'/'.config('sync_desktop.manifest_name');
    }

    private function exePath(): string
    {
        return $this->dir().'/'.config('sync_desktop.exe_name');
    }

    private function tokenOk(Request $request): bool
    {
        $esperado = trim((string) config('sync_desktop.token'));
        if ($esperado === '') {
            return false;
        }
        $recibido = trim((string) (
            $request->header('X-Sync-Token')
            ?: $request->query('token', '')
        ));

        return hash_equals($esperado, $recibido);
    }

    private function abortSinToken(Request $request): void
    {
        if (! $this->tokenOk($request)) {
            abort(403, 'Token inválido');
        }
    }

    private function leerManifest(): array
    {
        $path = $this->manifestPath();
        if (! $this->disk()->exists($path)) {
            return [
                'version' => '0.0.0',
                'notes' => 'Aún no hay paquete publicado.',
                'published_at' => null,
                'filename' => config('sync_desktop.exe_name'),
                'size' => 0,
            ];
        }

        $data = json_decode($this->disk()->get($path), true);

        return is_array($data) ? $data : [];
    }

    private function baseUrl(): string
    {
        $base = trim((string) config('sync_desktop.public_base'));

        return $base !== '' ? $base : rtrim((string) config('app.url'), '/');
    }

    /** Credenciales y versión para el sincronizador (token requerido). */
    public function bootstrap(Request $request)
    {
        $this->abortSinToken($request);

        $web = array_filter([
            'host' => config('sync_desktop.web_db.host'),
            'port' => config('sync_desktop.web_db.port'),
            'database' => config('sync_desktop.web_db.database'),
            'user' => config('sync_desktop.web_db.user'),
            'password' => config('sync_desktop.web_db.password'),
        ], fn ($v) => $v !== null && $v !== '');

        $manifest = $this->leerManifest();
        $token = urlencode((string) config('sync_desktop.token'));

        return response()->json([
            'ok' => true,
            'app' => 'JRZ-TECH Sync',
            'web_db' => $web,
            'version' => $manifest['version'] ?? '0.0.0',
            'notes' => $manifest['notes'] ?? '',
            'download_url' => $this->baseUrl().'/sync-desktop/download?token='.$token,
            'published_at' => $manifest['published_at'] ?? null,
            'size' => $manifest['size'] ?? 0,
        ]);
    }

    /** Manifiesto de versión (token requerido). */
    public function manifest(Request $request)
    {
        $this->abortSinToken($request);
        $manifest = $this->leerManifest();
        $token = urlencode((string) config('sync_desktop.token'));
        $manifest['download_url'] = $this->baseUrl().'/sync-desktop/download?token='.$token;
        $manifest['ok'] = true;

        return response()->json($manifest);
    }

    /** Descarga del .exe (token requerido). */
    public function download(Request $request): StreamedResponse
    {
        $this->abortSinToken($request);
        $path = $this->exePath();
        if (! $this->disk()->exists($path)) {
            abort(404, 'No hay instalador publicado');
        }

        return $this->disk()->download(
            $path,
            config('sync_desktop.exe_name'),
            ['Content-Type' => 'application/octet-stream']
        );
    }

    /** Publicar nueva versión desde el panel admin. */
    public function publish(Request $request)
    {
        $request->validate([
            'version' => ['required', 'string', 'max:32', 'regex:/^\d+\.\d+\.\d+$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'paquete' => ['required', 'file', 'max:204800'],
        ]);

        $file = $request->file('paquete');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['exe', 'zip'], true)) {
            return back()->withErrors(['paquete' => 'Sube un .exe o un .zip con el ejecutable.']);
        }

        $dir = $this->dir();
        $this->disk()->makeDirectory($dir);

        if ($ext === 'exe') {
            $this->disk()->putFileAs($dir, $file, config('sync_desktop.exe_name'));
        } else {
            $tmpZip = storage_path('app/'.$dir.'/upload.zip');
            @mkdir(dirname($tmpZip), 0775, true);
            $file->move(dirname($tmpZip), 'upload.zip');
            $zip = new \ZipArchive;
            if ($zip->open($tmpZip) !== true) {
                @unlink($tmpZip);

                return back()->withErrors(['paquete' => 'No se pudo abrir el ZIP.']);
            }
            $found = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (str_ends_with(strtolower($name), '.exe')) {
                    $content = $zip->getFromIndex($i);
                    $this->disk()->put($this->exePath(), $content);
                    $found = true;
                    break;
                }
            }
            $zip->close();
            @unlink($tmpZip);
            if (! $found) {
                return back()->withErrors(['paquete' => 'El ZIP no contiene un .exe.']);
            }
        }

        $size = $this->disk()->exists($this->exePath()) ? $this->disk()->size($this->exePath()) : 0;
        $manifest = [
            'version' => $request->version,
            'notes' => (string) ($request->notes ?? ''),
            'published_at' => now()->toIso8601String(),
            'filename' => config('sync_desktop.exe_name'),
            'size' => $size,
            'published_by' => $request->user()?->email,
        ];
        $this->disk()->put($this->manifestPath(), json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return back()->with('success', "Versión {$request->version} publicada. Las sedes pueden actualizar desde la app.");
    }
}
