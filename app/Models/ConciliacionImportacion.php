<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConciliacionImportacion extends Model
{
    public const PENDIENTE = 'pendiente';

    public const PROCESANDO = 'procesando';

    public const COMPLETADA = 'completada';

    public const COMPLETADA_CON_PENDIENTES = 'completada_con_pendientes';

    public const FALLIDA = 'fallida';

    public const ESTADOS_FINALES = [self::COMPLETADA, self::COMPLETADA_CON_PENDIENTES, self::FALLIDA];

    protected $table = 'conciliacion_importaciones';

    protected $guarded = [];

    protected $casts = [
        'rechazos' => 'array',
        'iniciado_at' => 'datetime',
        'finalizado_at' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function terminada(): bool
    {
        return in_array($this->estado, self::ESTADOS_FINALES, true);
    }

    public function etiquetaEstado(): string
    {
        return match ($this->estado) {
            self::PENDIENTE => 'En cola',
            self::PROCESANDO => 'Procesando',
            self::COMPLETADA => 'Completada',
            self::COMPLETADA_CON_PENDIENTES => 'Completada con filas rechazadas',
            self::FALLIDA => 'Fallida',
            default => $this->estado,
        };
    }

    public function visiblePara(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return (int) $this->user_id === (int) $user->id || $user->isAdmin();
    }
}
