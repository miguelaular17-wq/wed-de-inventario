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
    public const COMISION_VENTAS_PROPIAS_SIN_HS = 'VENTAS_PROPIAS_SIN_HS';
    public const COMISION_SUPERVISOR_SEDE = 'SUPERVISOR_SEDE';
    public const COMISION_ENCARGADO = 'ENCARGADO';
    public const COMISION_SUPERVISOR_EQUIPO = 'SUPERVISOR_EQUIPO';
    public const COMISION_SERVICIO_TECNICO = 'SERVICIO_TECNICO';
    public const COMISION_NUNES = 'NUNES';
    public const COMISION_MOVISTAR = 'MOVISTAR';
    public const COMISION_DIGITAL = 'DIGITAL';
    public const COMISION_PCP = 'PCP';
    public const COMISION_SAMBIL = 'SAMBIL';
    public const COMISION_SAMBIL_SIN_HS = 'SAMBIL_SIN_HS';
    public const COMISION_TECNOHOUSE_SIN_HS = 'TECNOHOUSE_SIN_HS';
    public const COMISION_COLABORADOR = 'COLABORADOR';
    public const COMISION_NINGUNA = 'SIN_COMISION';

    protected $table = 'nomina_empleados';

    protected $fillable = [
        'cliente_id',
        'user_id',
        'email',
        'telefono',
        'foto_url',
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
        'exento_retencion_comision',
        'modo_comision',
        'codigo_vendedor',
        'empresa_id',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'salario_base' => 'decimal:2',
        'es_supervisor' => 'boolean',
        'es_servicio_tecnico' => 'boolean',
        'exento_retencion_comision' => 'boolean',
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

    public function diasLibres(): HasMany
    {
        return $this->hasMany(NominaDiaLibre::class, 'empleado_id')->orderByDesc('fecha')->orderByDesc('id');
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
        $nombres = $this->jefesListados()->map(fn (self $jefe) => $jefe->nombreCorto())->filter()->unique();

        return $nombres->isNotEmpty() ? $nombres->implode(', ') : '—';
    }

    /**
     * Nombre corto para chips (primer nombre + último apellido).
     */
    public function nombreCorto(): string
    {
        $partes = array_values(array_filter(preg_split('/\s+/', trim($this->nombre())) ?: []));
        if ($partes === []) {
            return '—';
        }
        if (count($partes) === 1) {
            return $partes[0];
        }

        return $partes[0].' '.$partes[count($partes) - 1];
    }

    /**
     * @return \Illuminate\Support\Collection<int, self>
     */
    public function jefesListados()
    {
        $jefes = $this->relationLoaded('jefes') ? $this->jefes : $this->jefes()->with('cliente')->get();
        if ($jefes->isNotEmpty()) {
            return $jefes->unique('id')->values();
        }

        return collect($this->supervisor ? [$this->supervisor] : []);
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

    public function exentoRetencionComision(): bool
    {
        return (bool) $this->exento_retencion_comision;
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

    /** Nombre corto para selects y listados. */
    public static function modosComision(): array
    {
        return [
            self::COMISION_VENTAS_PROPIAS => 'Ventas propias',
            self::COMISION_VENTAS_PROPIAS_SIN_HS => 'Propias sin HS',
            self::COMISION_SUPERVISOR_SEDE => 'Supervisor sede',
            self::COMISION_ENCARGADO => 'Encargado',
            self::COMISION_SUPERVISOR_EQUIPO => 'Sup. equipo',
            self::COMISION_DIGITAL => 'Digital',
            self::COMISION_PCP => 'PCP',
            self::COMISION_SAMBIL => 'Sambil',
            self::COMISION_SAMBIL_SIN_HS => 'Sambil sin HS',
            self::COMISION_TECNOHOUSE_SIN_HS => 'Tecno House sin HS',
            self::COMISION_COLABORADOR => 'Colaborador',
            self::COMISION_SERVICIO_TECNICO => 'Servicio Técnico',
            self::COMISION_NUNES => 'Nunes',
            self::COMISION_MOVISTAR => 'Movistar',
            self::COMISION_NINGUNA => 'Sin comisión',
        ];
    }

    /** Descripción completa del modo (porcentajes y reglas). */
    public static function modosComisionDetalle(): array
    {
        return [
            self::COMISION_VENTAS_PROPIAS => 'Venta neta (telefonía 0,20% / resto 1%)',
            self::COMISION_VENTAS_PROPIAS_SIN_HS => 'Venta neta (telefonía 0,10% / resto 1%; sin Honor ni Samsung)',
            self::COMISION_SUPERVISOR_SEDE => 'Venta neta de la tienda (0,05%)',
            self::COMISION_ENCARGADO => 'Venta neta de la tienda (% propio en Configuración)',
            self::COMISION_SUPERVISOR_EQUIPO => 'Ventas de subordinados (0,10%)',
            self::COMISION_DIGITAL => 'Venta neta de sus trabajadores (0,30%)',
            self::COMISION_PCP => 'Venta neta de la tienda (0,015%)',
            self::COMISION_SAMBIL => 'Venta neta de la tienda (0,20%)',
            self::COMISION_SAMBIL_SIN_HS => 'Venta neta Sambil sin Honor ni Samsung (0,20%)',
            self::COMISION_TECNOHOUSE_SIN_HS => 'Venta neta Tecno House sin Honor ni Samsung (0,20%)',
            self::COMISION_COLABORADOR => 'Venta neta Sambil+Doral+Zamora+Centro+Virtudes (0,25%)',
            self::COMISION_SERVICIO_TECNICO => 'ST − 058 × 50%; resto como vendedor (0,20%/1%); sin retención',
            self::COMISION_NUNES => 'Venta neta total de la sede Nunes (0,60%)',
            self::COMISION_MOVISTAR => 'Venta neta propia sin facturas ST (telefonía 0,20% / resto 1%)',
            self::COMISION_NINGUNA => 'No genera comisión',
        ];
    }

    public static function etiquetaModoComision(?string $modo): string
    {
        $modos = self::modosComision();

        return $modos[$modo] ?? (string) $modo;
    }

    public static function detalleModoComision(?string $modo): string
    {
        $detalles = self::modosComisionDetalle();

        return $detalles[$modo] ?? '';
    }

    public static function modosComisionAgregadosSede(): array
    {
        return [
            self::COMISION_SUPERVISOR_SEDE,
            self::COMISION_ENCARGADO,
            self::COMISION_NUNES,
            self::COMISION_PCP,
            self::COMISION_SAMBIL,
            self::COMISION_SAMBIL_SIN_HS,
            self::COMISION_TECNOHOUSE_SIN_HS,
            self::COMISION_COLABORADOR,
        ];
    }

    /** Sedes que suman para la comisión de colaborador. */
    public static function sedesComisionColaborador(): array
    {
        return ['SAMBIL', 'DORAL', 'ZAMORA', 'CENTRO', 'VIRTUDES'];
    }

    public static function modosComisionAgregadosEquipo(): array
    {
        return [
            self::COMISION_SUPERVISOR_EQUIPO,
            self::COMISION_DIGITAL,
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

    /**
     * Código principal + alias (máx. 3 en total).
     *
     * @return list<string>
     */
    public function codigosVendedor(): array
    {
        $codigos = [];
        if ($principal = $this->codigoVendedor()) {
            $codigos[] = $principal;
        }

        foreach ($this->relationLoaded('vendedores') ? $this->vendedores : $this->vendedores()->get() as $alias) {
            foreach ([$alias->codigo_profit, $alias->nombre_normalizado, $alias->nombre_vendedor] as $valor) {
                $normalizado = self::normalizarVendedor($valor);
                if ($normalizado && ! in_array($normalizado, $codigos, true)) {
                    $codigos[] = $normalizado;
                }
            }
            if (count($codigos) >= 3) {
                break;
            }
        }

        return array_slice($codigos, 0, 3);
    }

    public static function buscarPorVendedor(?string $vendedor): ?self
    {
        $codigo = self::normalizarVendedor($vendedor);
        if ($codigo === null) {
            return null;
        }

        $porPrincipal = static::query()
            ->whereRaw('UPPER(TRIM(codigo_vendedor)) = ?', [$codigo])
            ->first();
        if ($porPrincipal) {
            return $porPrincipal;
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('nomina_empleado_vendedores')) {
            return null;
        }

        $alias = NominaEmpleadoVendedor::query()
            ->with('empleado')
            ->where(function ($q) use ($codigo) {
                $q->where('nombre_normalizado', $codigo)
                    ->orWhereRaw('UPPER(TRIM(COALESCE(codigo_profit, \'\'))) = ?', [$codigo]);
            })
            ->first();

        return $alias?->empleado;
    }
}
