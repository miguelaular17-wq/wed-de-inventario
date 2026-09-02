<?php

namespace App\Models\Nomina;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NominaEmpleado extends Model
{
    public const COMISION_VENTAS_PROPIAS = 'VENTAS_PROPIAS';
    public const COMISION_SUPERVISOR_SEDE = 'SUPERVISOR_SEDE';
    public const COMISION_SUPERVISOR_EQUIPO = 'SUPERVISOR_EQUIPO';
    public const COMISION_SERVICIO_TECNICO = 'SERVICIO_TECNICO';
    public const COMISION_NINGUNA = 'SIN_COMISION';

    protected $table = 'nomina_empleados';

    protected $fillable = [
        'cliente_id',
        'user_id',
        'email',
        'telefono',
        'fecha_ingreso',
        'cargo',
        'sede',
        'salario_base',
        'tipo_salario',
        'estado',
        'sede_id',
        'cargo_id',
        'supervisor_id',
        'es_supervisor',
        'es_servicio_tecnico',
        'modo_comision',
        'codigo_vendedor',
        'empresa_id',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'salario_base' => 'decimal:2',
        'es_supervisor' => 'boolean',
        'es_servicio_tecnico' => 'boolean',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sedeCatalogo(): BelongsTo
    {
        return $this->belongsTo(NominaSede::class, 'sede_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(NominaEmpresa::class, 'empresa_id');
    }

    public function cargoCatalogo(): BelongsTo
    {
        return $this->belongsTo(NominaCargo::class, 'cargo_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_id');
    }

    public function jefes(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'nomina_empleado_supervisores', 'empleado_id', 'supervisor_id')
            ->withTimestamps();
    }

    public function equipo(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'nomina_empleado_supervisores', 'supervisor_id', 'empleado_id')
            ->withTimestamps();
    }

    public function subordinados(): HasMany
    {
        return $this->hasMany(self::class, 'supervisor_id');
    }

    public function prestamos(): HasMany
    {
        return $this->hasMany(NominaPrestamo::class, 'empleado_id');
    }

    public function abonosSueldo(): HasMany
    {
        return $this->hasMany(NominaAbonoSueldo::class, 'empleado_id')->orderByDesc('fecha')->orderByDesc('id');
    }

    public function vendedores(): HasMany
    {
        return $this->hasMany(NominaEmpleadoVendedor::class, 'empleado_id');
    }

    public function inasistencias(): HasMany
    {
        return $this->hasMany(NominaInasistencia::class, 'empleado_id')->orderByDesc('fecha')->orderByDesc('id');
    }

    public function horasExtras(): HasMany
    {
        return $this->hasMany(NominaHoraExtra::class, 'empleado_id')->orderByDesc('fecha')->orderByDesc('id');
    }

    public function nombre(): string
    {
        return $this->cliente?->nombre ?? 'Sin nombre';
    }

    public function cedula(): string
    {
        return $this->cliente?->cedula ?? '';
    }

    public function nombreEmpresa(): string
    {
        $empresa = $this->empresa;

        return $empresa ? $empresa->codigo.' · '.$empresa->nombre : '—';
    }

    public function codigoEmpresa(): string
    {
        return $this->empresa?->codigo ?? '—';
    }

    public function nombreSede(): string
    {
        return $this->sedeCatalogo?->nombre ?? $this->sede ?? '—';
    }

    public function nombreCargo(): string
    {
        return $this->cargoCatalogo?->nombre ?? $this->cargo ?? '—';
    }

    public function nombreSupervisor(): string
    {
        return $this->jefes->map(fn (self $jefe) => $jefe->nombre())->filter()->unique()->implode(' / ')
            ?: ($this->supervisor?->nombre() ?? '—');
    }

    public function isActivo(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    public function generaComision(): bool
    {
        $modo = (string) ($this->modo_comision ?: self::COMISION_NINGUNA);

        return $modo !== self::COMISION_NINGUNA;
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', 'ACTIVO');
    }

    public function scopeComisionables($query)
    {
        return $query->activos()->where('modo_comision', '!=', self::COMISION_NINGUNA);
    }

    public function scopeBuscar($query, ?string $termino)
    {
        $termino = trim((string) $termino);
        if ($termino === '') {
            return $query;
        }

        $like = '%'.mb_strtolower($termino, 'UTF-8').'%';

        return $query->where(function ($q) use ($like) {
            $q->whereHas('cliente', function ($cliente) use ($like) {
                $cliente->whereRaw('LOWER(nombre) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(cedula) LIKE ?', [$like]);
            })->orWhereRaw('LOWER(codigo_vendedor) LIKE ?', [$like]);
        });
    }

    public function descuentosMercancia(): HasMany
    {
        return $this->hasMany(NominaDescuentoMercancia::class, 'empleado_id')->orderByDesc('fecha')->orderByDesc('id');
    }

    public function deducciones(): HasMany
    {
        return $this->hasMany(NominaDeduccion::class, 'empleado_id')->orderByDesc('fecha')->orderByDesc('id');
    }

    public function ajustes(): HasMany
    {
        return $this->hasMany(NominaEmpleadoAjuste::class, 'empleado_id')->orderByDesc('fecha')->orderByDesc('id');
    }

    public static function modosComision(): array
    {
        return [
            self::COMISION_VENTAS_PROPIAS => 'Ventas propias: venta neta (telefonía 0,20% / resto 1%)',
            self::COMISION_SUPERVISOR_SEDE => 'Supervisor de sede: venta neta de la tienda (0,05%)',
            self::COMISION_SUPERVISOR_EQUIPO => 'Supervisor de equipo/Marketing: ventas de subordinados (0,10%)',
            self::COMISION_SERVICIO_TECNICO => 'Servicio Técnico: ST − 058 × 50%; el resto como vendedor (0,20%/1%)',
            self::COMISION_NINGUNA => 'Sin comisión',
        ];
    }

    public static function normalizarVendedor(?string $valor): ?string
    {
        $valor = trim(preg_replace('/\s+/', ' ', (string) $valor) ?? '');
        if ($valor === '') {
            return null;
        }

        return mb_strtoupper($valor, 'UTF-8');
    }

    public function codigoVendedor(): ?string
    {
        return self::normalizarVendedor($this->codigo_vendedor);
    }

    public static function buscarPorVendedor(?string $vendedor): ?self
    {
        $codigo = self::normalizarVendedor($vendedor);
        if ($codigo === null) {
            return null;
        }

        return static::query()
            ->whereRaw('UPPER(TRIM(codigo_vendedor)) = ?', [$codigo])
            ->first();
    }
}
