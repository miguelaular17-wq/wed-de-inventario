<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_SUPERVISOR = 'supervisor';
    public const ROLE_TELEFONIA = 'telefonia';
    public const ROLE_COMPRADOR = 'comprador';
    public const ROLE_SEDE = 'sede';
    public const ROLE_VENDEDOR = 'vendedor';
    public const ROLE_MARKETING = 'marketing';
    public const ROLE_FINANZAS = 'finanzas';
    public const ROLE_COBRANZA = 'cobranza';
    public const ROLE_CONTABILIDAD = 'contabilidad';
    public const ROLE_AUDITOR = 'auditor';
    public const ROLE_TESORERIA = 'tesoreria';
    public const ROLE_GERENTE = 'gerente';
    public const ROLE_RRHH = 'rrhh';
    public const ROLE_TECNICO = 'tecnico';

    /** Roles de oficina que no eligen ni usan sede de operación. */
    public const ROLES_WITHOUT_SEDE = [
        self::ROLE_COMPRADOR,
        self::ROLE_MARKETING,
        self::ROLE_FINANZAS,
        self::ROLE_COBRANZA,
        self::ROLE_CONTABILIDAD,
        self::ROLE_AUDITOR,
        self::ROLE_TESORERIA,
        self::ROLE_GERENTE,
        self::ROLE_RRHH,
    ];

    /** Roles con sede fija asignada; no pueden cambiarla. */
    public const ROLES_SEDE_LOCKED = [
        self::ROLE_SUPERVISOR,
        self::ROLE_TELEFONIA,
        self::ROLE_TECNICO,
    ];

    /** @var list<string>|null */
    protected ?array $extraPermissionCache = null;

    protected $fillable = [
        'name',
        'email',
        'password',
        'password_plain',
        'role',
        'sede',
        'tutorial_step',
        'ver_publicidad_equipo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'ver_publicidad_equipo' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN || $this->role === self::ROLE_GERENTE;
    }

    public function isSupervisor(): bool
    {
        return $this->role === self::ROLE_SUPERVISOR;
    }

    public function isTelefonia(): bool
    {
        return $this->role === self::ROLE_TELEFONIA;
    }

    public function isComprador(): bool
    {
        return $this->role === self::ROLE_COMPRADOR;
    }

    public function isSede(): bool
    {
        return $this->role === self::ROLE_SEDE;
    }

    public function isVendedor(): bool
    {
        return $this->role === self::ROLE_VENDEDOR;
    }

    public function isMarketing(): bool
    {
        return $this->role === self::ROLE_MARKETING;
    }

    public function isAuditor(): bool
    {
        return $this->role === self::ROLE_AUDITOR;
    }

    public function isTesoreria(): bool
    {
        return $this->role === self::ROLE_TESORERIA;
    }

    public function isFinanzas(): bool
    {
        return $this->role === self::ROLE_FINANZAS;
    }

    public function isCobranza(): bool
    {
        return $this->role === self::ROLE_COBRANZA;
    }

    public function isContabilidad(): bool
    {
        return $this->role === self::ROLE_CONTABILIDAD;
    }

    public function isGerente(): bool
    {
        return $this->role === self::ROLE_GERENTE;
    }

    public function isRrhh(): bool
    {
        return $this->role === self::ROLE_RRHH;
    }

    public function isTecnico(): bool
    {
        return $this->role === self::ROLE_TECNICO;
    }

    /**
     * Ficha de nómina de servicio técnico enlazada a este usuario.
     */
    public function empleadoServicioTecnico(): ?\App\Models\Nomina\NominaEmpleado
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('nomina_empleados')) {
                return null;
            }

            return \App\Models\Nomina\NominaEmpleado::query()
                ->where('user_id', $this->id)
                ->where('es_servicio_tecnico', true)
                ->where('estado', 'ACTIVO')
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Técnico (rol o ficha ST enlazada): solo ve sus facturas de taller.
     * Gerente y admin ven todas.
     */
    public function veSoloSusFacturasTaller(): bool
    {
        if ($this->isAdmin() || $this->isGerente()) {
            return false;
        }

        return $this->isTecnico() || $this->empleadoServicioTecnico() !== null;
    }

    public function sedeIsLocked(): bool
    {
        return in_array($this->role, self::ROLES_SEDE_LOCKED, true);
    }

    public function scopesServicioToOwnSede(): bool
    {
        return $this->isTecnico();
    }

    public function puedeTransferirServicio(): bool
    {
        if ($this->isAdmin() || $this->isGerente()) {
            return true;
        }

        if ($this->canAccess('servicio.transferir')) {
            return true;
        }

        // Supervisores u otros con acceso ST completo (no técnico acotado a su sede).
        return $this->canAccess('servicio') && ! $this->scopesServicioToOwnSede();
    }

    /**
     * Tablas de Compras que se pueden asignar aparte del permiso completo.
     *
     * @return array<string, string> tab => permission
     */
    public static function comprasTabPermissions(): array
    {
        return [
            'distribucion' => 'compras.distribucion',
            'necesidad' => 'compras.necesidad',
            'proveedores' => 'compras.proveedores',
            'sobrestock' => 'compras.sobrestock',
            'qpedir' => 'compras.qpedir',
            'existencias' => 'compras.existencias',
            'publicidad' => 'compras.publicidad',
        ];
    }

    public function hasFullComprasAccess(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $owned = array_merge($this->rolePermissionKeys(), $this->extraPermissionKeys());

        return in_array('compras', $owned, true);
    }

    public function canEnterCompras(): bool
    {
        if ($this->hasFullComprasAccess()) {
            return true;
        }

        foreach (self::comprasTabPermissions() as $permission) {
            if ($this->canAccess($permission)) {
                return true;
            }
        }

        return false;
    }

    public function canAccessComprasTab(string $tab): bool
    {
        if ($this->hasFullComprasAccess()) {
            return true;
        }

        if ($tab === 'publicidad' && $this->isMarketing()) {
            return true;
        }

        $permission = self::comprasTabPermissions()[$tab] ?? null;

        return $permission ? $this->canAccess($permission) : false;
    }

    /**
     * Query string del primer tab de compras permitido (dashboard).
     *
     * @return array<string, string>
     */
    public function defaultComprasRouteParams(): array
    {
        if ($this->canAccessComprasTab('distribucion')) {
            return ['tab' => 'productos'];
        }
        if ($this->canAccessComprasTab('necesidad')) {
            return ['tab' => 'productos', 'status' => 'Comprar'];
        }
        if ($this->canAccessComprasTab('proveedores')) {
            return ['tab' => 'proveedores'];
        }
        if ($this->canAccessComprasTab('sobrestock')) {
            return ['tab' => 'sobrestock'];
        }
        if ($this->canAccessComprasTab('qpedir')) {
            return ['tab' => 'qpedir'];
        }
        if ($this->canAccessComprasTab('publicidad')) {
            return ['tab' => 'publicidad'];
        }

        return ['tab' => 'productos'];
    }

    public function defaultComprasUrl(): string
    {
        if (
            ! $this->hasFullComprasAccess()
            && $this->canAccessComprasTab('existencias')
            && ! $this->canAccessComprasTab('distribucion')
            && ! $this->canAccessComprasTab('necesidad')
            && ! $this->canAccessComprasTab('proveedores')
            && ! $this->canAccessComprasTab('sobrestock')
            && ! $this->canAccessComprasTab('qpedir')
            && ! $this->canAccessComprasTab('publicidad')
        ) {
            return route('comprador.existencias');
        }

        return route('comprador.dashboard', $this->defaultComprasRouteParams());
    }

    public function canSeeCatalogoExistencias(): bool
    {
        return $this->isVendedor()
            || $this->canAccess('catalogo.existencias')
            || $this->canSeeCatalogoPrecios();
    }

    public function canSeeCatalogoPrecios(): bool
    {
        return $this->isVendedor()
            || $this->isComprador()
            || $this->canAccess('catalogo.precios');
    }

    public function extraPermissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    /**
     * @return list<string>
     */
    public function extraPermissionKeys(): array
    {
        if ($this->extraPermissionCache !== null) {
            return $this->extraPermissionCache;
        }

        try {
            if ($this->relationLoaded('extraPermissions')) {
                return $this->extraPermissionCache = $this->extraPermissions->pluck('permission')->all();
            }

            return $this->extraPermissionCache = $this->extraPermissions()->pluck('permission')->all();
        } catch (\Throwable) {
            return $this->extraPermissionCache = [];
        }
    }

    /**
     * Permisos que vienen del rol (sin extras).
     *
     * @return list<string>
     */
    public function rolePermissionKeys(): array
    {
        $perms = config('permissions.roles.'.$this->role, []);
        if (in_array('*', $perms, true) || $this->isAdmin()) {
            return array_keys(config('permissions.assignable', []));
        }

        return array_values($perms);
    }

    public function canAccess(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $owned = array_merge($this->rolePermissionKeys(), $this->extraPermissionKeys());

        if ($permission === 'finanzas.ver' && in_array('finanzas.editar', $owned, true)) {
            return true;
        }

        if ($permission === 'meta.ver' && in_array('meta', $owned, true)) {
            return true;
        }

        if ($permission === 'marketing.publicidad_equipo' && $this->isMarketing() && (bool) $this->ver_publicidad_equipo) {
            return true;
        }

        return in_array($permission, $owned, true);
    }

    /**
     * @param  list<string>  $permissions
     */
    public function canAccessAny(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->canAccess($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $permissions
     */
    public function syncExtraPermissions(array $permissions): void
    {
        $assignable = array_keys(config('permissions.assignable', []));
        $requested = array_values(array_unique(array_intersect($permissions, $assignable)));
        $rolePerms = config('permissions.roles.'.$this->role, []);

        if (in_array('*', $rolePerms, true) || $this->isAdmin()) {
            $requested = [];
        } else {
            $requested = array_values(array_diff($requested, $rolePerms));
            if (in_array('finanzas.editar', $requested, true)) {
                $requested = array_values(array_diff($requested, ['finanzas.ver']));
            }
        }

        $this->extraPermissions()->delete();
        foreach ($requested as $permission) {
            $this->extraPermissions()->create(['permission' => $permission]);
        }

        $this->extraPermissionCache = $requested;
        $this->unsetRelation('extraPermissions');
    }

    public function canAccessNomina(): bool
    {
        return $this->canAccess('nomina');
    }

    public function canViewTeamPublicidad(): bool
    {
        return $this->canAccess('marketing.publicidad_equipo');
    }

    public function hasAccessToSedeViews(): bool
    {
        return $this->canAccess('operacion');
    }

    public function requiresSede(): bool
    {
        return ! in_array($this->role, self::ROLES_WITHOUT_SEDE, true);
    }

    public function hasAccessToMovimientos(): bool
    {
        return $this->role === self::ROLE_ADMIN || $this->role === self::ROLE_GERENTE;
    }

    /**
     * Get the notifications for the user.
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class, 'receiver_id')->orderBy('created_at', 'desc');
    }
}
