<?php

namespace App\Models\Concerns;

use App\Models\Auditoria;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda en `auditorias` cada alta, cambio y baja del modelo: campo por campo, antes y después.
 * Solo cubre cambios hechos con Eloquent (save, update, delete), no consultas directas.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($modelo) => $modelo->registrarAuditoria(Auditoria::CREADO, $modelo->cambiosAuditables(null, $modelo->getAttributes())));
        static::updated(function ($modelo) {
            $cambios = $modelo->cambiosAuditables($modelo->getRawOriginal(), $modelo->getChanges());
            if ($cambios !== []) {
                $modelo->registrarAuditoria(Auditoria::ACTUALIZADO, $cambios);
            }
        });
        static::deleted(fn ($modelo) => $modelo->registrarAuditoria(Auditoria::ELIMINADO, $modelo->cambiosAuditables($modelo->getRawOriginal(), null)));
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<Auditoria, $this> */
    public function auditorias()
    {
        return $this->hasMany(Auditoria::class, 'auditable_id')
            ->where('auditable_type', $this->tipoAuditable())
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function tipoAuditable(): string
    {
        return class_basename(static::class);
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $despues
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    protected function cambiosAuditables(?array $antes, ?array $despues): array
    {
        $ignorar = ['created_at', 'updated_at'];
        $campos = array_keys($despues ?? $antes ?? []);
        $cambios = [];
        foreach ($campos as $campo) {
            if (in_array($campo, $ignorar, true)) {
                continue;
            }
            $a = $antes !== null ? $this->valorAuditable($antes[$campo] ?? null) : null;
            $d = $despues !== null ? $this->valorAuditable($despues[$campo] ?? null) : null;
            if ($antes !== null && $despues !== null && $a === $d) {
                continue;
            }
            if ($a === null && $d === null) {
                continue;
            }
            $cambios[$campo] = [$a, $d];
        }

        return $cambios;
    }

    protected function valorAuditable(mixed $valor): mixed
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d H:i:s');
        }
        if (is_string($valor) && str_starts_with($valor, 'data:')) {
            return '[imagen/firma de '.strlen($valor).' bytes]';
        }
        if (is_string($valor) && ($valor[0] ?? '') !== '' && in_array($valor[0], ['{', '['], true)) {
            $json = json_decode($valor, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }
        if (is_string($valor) && mb_strlen($valor) > 2000) {
            return mb_substr($valor, 0, 2000).'…';
        }

        return $valor;
    }

    /** @param  array<string, array{0: mixed, 1: mixed}>  $cambios */
    protected function registrarAuditoria(string $accion, array $cambios): void
    {
        if (! Schema::hasTable('auditorias')) {
            return;
        }

        $user = auth()->user();
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        Auditoria::query()->insert([
            'auditable_type' => $this->tipoAuditable(),
            'auditable_id' => $this->getKey(),
            'accion' => $accion,
            'cambios' => json_encode($cambios, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
            'motivo' => Auditoria::motivoActual(),
            'user_id' => $user?->getAuthIdentifier(),
            'user_nombre' => $user?->name,
            'ip' => $request?->ip(),
            'ruta' => $request ? mb_substr($request->method().' '.$request->path(), 0, 255) : 'consola',
            'created_at' => now(),
        ]);
    }
}
