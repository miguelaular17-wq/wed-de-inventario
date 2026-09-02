<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class StOrden extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_EN_PROCESO = 'en_proceso';
    public const ESTADO_LISTO = 'listo';
    public const ESTADO_ENTREGADO = 'entregado';
    public const ESTADO_CANCELADO = 'cancelado';

    public const TRANSFER_PENDIENTE = 'pendiente';
    public const TRANSFER_ACEPTADA = 'aceptada';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE => 'Pendiente',
        self::ESTADO_EN_PROCESO => 'En proceso',
        self::ESTADO_LISTO => 'Listo',
        self::ESTADO_ENTREGADO => 'Entregado',
        self::ESTADO_CANCELADO => 'Cancelado',
    ];

    public const PRIORIDADES = [
        'baja' => 'Baja',
        'normal' => 'Normal',
        'alta' => 'Alta',
        'urgente' => 'Urgente',
    ];

    protected $table = 'st_ordenes';

    protected $fillable = [
        'sede',
        'numero',
        'cliente_nombre',
        'cliente_telefono',
        'cliente_cedula',
        'equipo',
        'serial',
        'falla',
        'accesorios',
        'diagnostico',
        'estado',
        'prioridad',
        'fecha_ingreso',
        'fecha_prometida',
        'observaciones',
        'presupuesto',
        'costo_mano_obra',
        'costo_refacciones',
        'created_by',
        'updated_by',
        'tecnico_id',
        'sede_origen_transfer',
        'transfer_estado',
        'repuestos_descontados_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
            'fecha_prometida' => 'date',
            'presupuesto' => 'decimal:2',
            'costo_mano_obra' => 'decimal:2',
            'costo_refacciones' => 'decimal:2',
            'repuestos_descontados_at' => 'datetime',
        ];
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function repuestosLineas(): HasMany
    {
        return $this->hasMany(StOrdenRepuesto::class, 'orden_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(StOrdenEvento::class, 'orden_id')->orderByDesc('created_at');
    }

    public function etiquetaEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function etiquetaPrioridad(): string
    {
        return self::PRIORIDADES[$this->prioridad] ?? $this->prioridad;
    }

    public function codigo(): string
    {
        return sprintf('%s-%04d', strtoupper((string) $this->sede), (int) $this->numero);
    }

    public function costoTotal(): float
    {
        return (float) ($this->costo_mano_obra ?? 0) + (float) ($this->costo_refacciones ?? 0);
    }

    public function excedePresupuesto(): bool
    {
        $presupuesto = (float) ($this->presupuesto ?? 0);
        $costo = $this->costoTotal();

        return $presupuesto > 0 && $costo > $presupuesto;
    }

    public function transferenciaPendiente(): bool
    {
        return $this->transfer_estado === self::TRANSFER_PENDIENTE;
    }

    public function puedeConfirmarRecepcion(User $user): bool
    {
        return $this->transferenciaPendiente()
            && strtoupper((string) $this->sede) === strtoupper((string) $user->sede);
    }

    public function scopeVisiblePara(Builder $query, User $user): Builder
    {
        if ($user->scopesServicioToOwnSede()) {
            $sede = strtoupper((string) $user->sede);

            return $query->where(function (Builder $inner) use ($sede) {
                $inner->where('sede', $sede)
                    ->orWhere('sede_origen_transfer', $sede);
            });
        }

        return $query;
    }

    public static function siguienteNumero(string $sede): int
    {
        $max = static::query()
            ->where('sede', strtoupper($sede))
            ->lockForUpdate()
            ->max('numero');

        return ((int) $max) + 1;
    }

    public static function crearEnSede(array $datos, User $user): self
    {
        return DB::transaction(function () use ($datos, $user) {
            $sede = strtoupper((string) $datos['sede']);
            $datos['sede'] = $sede;
            $datos['numero'] = self::siguienteNumero($sede);
            $datos['created_by'] = $user->id;
            $datos['updated_by'] = $user->id;
            $datos['tecnico_id'] = $datos['tecnico_id'] ?? ($user->isTecnico() ? $user->id : null);

            $orden = self::create($datos);

            StOrdenEvento::create([
                'orden_id' => $orden->id,
                'user_id' => $user->id,
                'tipo' => StOrdenEvento::TIPO_CREADA,
                'descripcion' => 'Orden '.$orden->codigo().' registrada',
                'created_at' => now(),
            ]);

            return $orden;
        });
    }
}
