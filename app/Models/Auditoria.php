<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una fila por cambio. No se edita ni se borra (en PostgreSQL lo impide un trigger). */
class Auditoria extends Model
{
    public const CREADO = 'creado';

    public const ACTUALIZADO = 'actualizado';

    public const ELIMINADO = 'eliminado';

    public $timestamps = false;

    protected $table = 'auditorias';

    protected $guarded = [];

    protected $casts = [
        'cambios' => 'array',
        'created_at' => 'datetime',
    ];

    private static ?string $motivo = null;

    /**
     * Los cambios hechos dentro del callback quedan auditados con este motivo.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function conMotivo(?string $motivo, callable $callback): mixed
    {
        $anterior = self::$motivo;
        self::$motivo = $motivo !== null ? trim($motivo) : null;
        try {
            return $callback();
        } finally {
            self::$motivo = $anterior;
        }
    }

    public static function motivoActual(): ?string
    {
        return self::$motivo ?: null;
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
