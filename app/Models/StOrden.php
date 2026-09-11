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
    public const ESTADO_UBICANDO_REPUESTO = 'ubicando_repuesto';
    public const ESTADO_LISTO = 'listo';
    public const ESTADO_ENTREGADO = 'entregado';
    public const ESTADO_CANCELADO = 'cancelado';

    public const TRANSFER_PENDIENTE = 'pendiente';
    public const TRANSFER_ACEPTADA = 'aceptada';

    public const TIPO_ST = 'ST';
    public const TIPO_GARANTIA = 'GARANTIA';
    public const TIPO_REPARACION_INTERNA = 'REPARACION_INTERNA';

    public const RANGO_DENTRO = 'dentro';
    public const RANGO_FUERA = 'fuera';

    public const RANGOS_GARANTIA = [
        self::RANGO_DENTRO => 'Dentro del rango de cambio',
        self::RANGO_FUERA => 'Fuera del rango de cambio',
    ];

    public const TIPOS_GESTION = [
        self::TIPO_ST => 'Servicio técnico',
        self::TIPO_GARANTIA => 'Garantía',
        self::TIPO_REPARACION_INTERNA => 'Reparación interna',
    ];

    public const EMPRESAS_ENVIO_GARANTIA = [
        'GLOBAL FIT' => 'GLOBAL FIT',
        'TECNOTROPOLIS' => 'TECNOTROPOLIS',
        'TOTALINK' => 'TOTALINK',
        'HONOR' => 'HONOR',
    ];

    public const GARANTIA_PENDIENTE_ENVIO = 'pendiente_envio';
    public const GARANTIA_ENVIADO = 'enviado';
    public const GARANTIA_EN_PROCESO = 'en_proceso';
    public const GARANTIA_RECIBIDO = 'recibido';

    public const ESTADOS_GARANTIA_EXTERNA = [
        self::GARANTIA_PENDIENTE_ENVIO => 'Pendiente de envío',
        self::GARANTIA_ENVIADO => 'Enviado',
        self::GARANTIA_EN_PROCESO => 'En proceso',
        self::GARANTIA_RECIBIDO => 'Recibido',
    ];

    public const ESTADOS = [
        self::ESTADO_PENDIENTE => 'Pendiente',
        self::ESTADO_EN_PROCESO => 'En proceso',
        self::ESTADO_UBICANDO_REPUESTO => 'Ubicando repuesto',
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
        'tipo_gestion',
        'tipo_dispositivo',
        'rango_garantia',
        'empresa_envio_garantia',
        'estado_garantia_externa',
        'motivo_envio_garantia',
        'observacion_envio_garantia',
        'garantia_enviado_at',
        'garantia_enviado_por',
        'garantia_recibido_at',
        'garantia_recibido_por',
        'valor_dispositivo',
        'equipo_id',
        'cliente_nombre',
        'cliente_telefono',
        'cliente_cedula',
        'equipo',
        'imei',
        'serial',
        'falla',
        'accesorios',
        'diagnostico',
        'estado',
        'prioridad',
        'fecha_ingreso',
        'fecha_prometida',
        'observaciones',
        'inspeccion_recepcion',
        'firma_recepcion_cliente',
        'firma_recepcion_empleado',
        'conformidad_trabajo',
        'firma_conformidad_cliente',
        'firma_conformidad_empleado',
        'conformidad_at',
        'presupuesto',
        'costo_mano_obra',
        'costo_refacciones',
        'created_by',
        'updated_by',
        'tecnico_id',
        'sede_origen_transfer',
        'sede_destino_transfer',
        'transfer_estado',
        'repuestos_descontados_at',
        'atributos',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
            'fecha_prometida' => 'date',
            'garantia_enviado_at' => 'datetime',
            'garantia_recibido_at' => 'datetime',
            'valor_dispositivo' => 'decimal:2',
            'presupuesto' => 'decimal:2',
            'costo_mano_obra' => 'decimal:2',
            'costo_refacciones' => 'decimal:2',
            'repuestos_descontados_at' => 'datetime',
            'inspeccion_recepcion' => 'array',
            'atributos' => 'array',
            'conformidad_at' => 'datetime',
        ];
    }

    /**
     * @return list<array{clave:string,etiqueta:string,estado:string}>
     */
    public function itemsInspeccionRecepcion(): array
    {
        $guardado = is_array($this->inspeccion_recepcion) ? $this->inspeccion_recepcion : [];
        $items = [];
        foreach (self::checklistPara($this->tipo_dispositivo) as $clave => $etiqueta) {
            $estado = $guardado[$clave] ?? '';
            if (is_array($estado)) {
                $estado = (string) ($estado['estado'] ?? '');
            }
            $items[] = [
                'clave' => $clave,
                'etiqueta' => $etiqueta,
                'estado' => in_array($estado, ['ok', 'dano', 'na'], true) ? $estado : '',
            ];
        }

        return $items;
    }

    public function etiquetaEstadoInspeccion(?string $estado): string
    {
        return match ($estado) {
            'ok' => 'OK',
            'dano' => 'Daño / falla',
            'na' => 'N/A',
            default => '—',
        };
    }

    public function backupVigente(): ?StBackup
    {
        return $this->backups->firstWhere('estado', StBackup::ESTADO_ENTREGADO)
            ?? $this->backups->sortByDesc('id')->first();
    }

    public function tieneConformidad(): bool
    {
        return $this->conformidad_at !== null || filled($this->firma_conformidad_cliente);
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

    public function enviadoPorGarantia(): BelongsTo
    {
        return $this->belongsTo(User::class, 'garantia_enviado_por');
    }

    public function recibidoPorGarantia(): BelongsTo
    {
        return $this->belongsTo(User::class, 'garantia_recibido_por');
    }

    public function equipoCelular(): BelongsTo
    {
        return $this->belongsTo(StEquipo::class, 'equipo_id');
    }

    public function backups(): HasMany
    {
        return $this->hasMany(StBackup::class, 'orden_id');
    }

    public function etiquetaTipoGestion(): string
    {
        return self::TIPOS_GESTION[$this->tipo_gestion ?? self::TIPO_ST] ?? (string) $this->tipo_gestion;
    }

    public function esGarantia(): bool
    {
        return ($this->tipo_gestion ?? self::TIPO_ST) === self::TIPO_GARANTIA;
    }

    public function estadoGarantiaExternaActual(): ?string
    {
        if (! $this->esGarantia()) {
            return null;
        }

        return $this->estado_garantia_externa ?: self::GARANTIA_PENDIENTE_ENVIO;
    }

    public function etiquetaEstadoGarantiaExterna(): ?string
    {
        $estado = $this->estadoGarantiaExternaActual();

        return $estado ? (self::ESTADOS_GARANTIA_EXTERNA[$estado] ?? $estado) : null;
    }

    public function garantiaExternaBloqueada(): bool
    {
        return $this->esGarantia() && in_array($this->estadoGarantiaExternaActual(), [
            self::GARANTIA_ENVIADO,
            self::GARANTIA_EN_PROCESO,
        ], true);
    }

    public function esReparacionInterna(): bool
    {
        return ($this->tipo_gestion ?? self::TIPO_ST) === self::TIPO_REPARACION_INTERNA;
    }

    public function esCambioEnRango(): bool
    {
        return $this->esGarantia() && $this->rango_garantia === self::RANGO_DENTRO;
    }

    public function etiquetaRangoGarantia(): ?string
    {
        if (! $this->esGarantia() || ! $this->rango_garantia) {
            return null;
        }

        return self::RANGOS_GARANTIA[$this->rango_garantia] ?? $this->rango_garantia;
    }

    /**
     * @return array<string, string>
     */
    public function estadosPermitidos(): array
    {
        $permitidos = match ($this->estado) {
            self::ESTADO_PENDIENTE => [self::ESTADO_EN_PROCESO, self::ESTADO_UBICANDO_REPUESTO, self::ESTADO_CANCELADO],
            self::ESTADO_EN_PROCESO => [self::ESTADO_PENDIENTE, self::ESTADO_UBICANDO_REPUESTO, self::ESTADO_LISTO, self::ESTADO_CANCELADO],
            self::ESTADO_UBICANDO_REPUESTO => [self::ESTADO_PENDIENTE, self::ESTADO_EN_PROCESO, self::ESTADO_CANCELADO],
            self::ESTADO_LISTO => [self::ESTADO_EN_PROCESO, self::ESTADO_ENTREGADO],
            default => [],
        };

        return array_intersect_key(self::ESTADOS, array_flip($permitidos));
    }

    public function marcaEquipo(): string
    {
        $marca = trim((string) ($this->equipoCelular?->marca ?? ''));
        if ($marca !== '') {
            return $marca;
        }
        $equipo = trim((string) ($this->equipo ?? ''));
        if ($equipo === '') {
            return 'la marca del equipo';
        }
        $partes = preg_split('/\s+/', $equipo) ?: [];

        return $partes[0] !== '' ? $partes[0] : 'la marca del equipo';
    }

    /**
     * @return array<string, string>
     */
    public static function checklistPara(?string $tipo): array
    {
        $tipo = $tipo ?: 'celular';
        $map = config('servicio_tecnico.checklist_recepcion_por_tipo', []);

        return $map[$tipo] ?? $map['celular'] ?? config('servicio_tecnico.checklist_recepcion', []);
    }

    public function etiquetaTipoDispositivo(): string
    {
        $tipo = $this->tipo_dispositivo ?: 'celular';
        $map = config('servicio_tecnico.tipos_dispositivo', []);

        return $map[$tipo] ?? $tipo;
    }

    public function atributo(string $clave, mixed $default = null): mixed
    {
        $attrs = is_array($this->atributos) ? $this->atributos : [];

        return $attrs[$clave] ?? $default;
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
            ->max('numero');

        return ((int) $max) + 1;
    }

    public static function crearEnSede(array $datos, User $user): self
    {
        return DB::transaction(function () use ($datos, $user) {
            $sede = strtoupper((string) $datos['sede']);
            $datos['sede'] = $sede;
            $datos['numero'] = self::siguienteNumero($sede);
            $datos['tipo_gestion'] = strtoupper((string) ($datos['tipo_gestion'] ?? self::TIPO_ST));
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

            if ($orden->equipo_id) {
                $equipo = StEquipo::query()->find($orden->equipo_id);
                if ($equipo) {
                    app(\App\Services\ServicioTecnico\StEquipoService::class)->registrarEvento(
                        $equipo,
                        $user,
                        \App\Models\StEquipoEvento::TIPO_REGISTRO,
                        'Orden creada',
                        sprintf(
                            '%s · Falla: %s',
                            $orden->etiquetaTipoGestion(),
                            $orden->falla ?: '—'
                        ),
                        $orden,
                        $sede,
                        [
                            'tipo_gestion' => $orden->tipo_gestion,
                            'cliente' => $orden->cliente_nombre,
                        ]
                    );
                }
            }

            return $orden;
        });
    }
}
