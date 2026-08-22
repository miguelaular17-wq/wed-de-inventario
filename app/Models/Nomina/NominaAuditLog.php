<?php

namespace App\Models\Nomina;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominaAuditLog extends Model
{
    protected $table = 'nomina_audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'accion',
        'entidad',
        'entidad_id',
        'valores_anteriores',
        'valores_nuevos',
        'ip',
        'created_at',
    ];

    protected $casts = [
        'valores_anteriores' => 'array',
        'valores_nuevos' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function registrar(
        string $accion,
        string $entidad,
        ?int $entidadId,
        ?array $anteriores = null,
        ?array $nuevos = null
    ): self {
        return self::create([
            'user_id' => auth()->id(),
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'valores_anteriores' => $anteriores,
            'valores_nuevos' => $nuevos,
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
