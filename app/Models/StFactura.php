<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class StFactura extends Model
{
    protected $table = 'st_facturas';

    protected $fillable = [
        'sede',
        'numero',
        'cliente_nombre',
        'descripcion',
        'presupuesto',
        'costo_mano_obra',
        'costo_refacciones',
        'total',
        'estado_pago',
        'fecha',
        'tecnico_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'presupuesto' => 'decimal:2',
            'costo_mano_obra' => 'decimal:2',
            'costo_refacciones' => 'decimal:2',
            'total' => 'decimal:2',
            'fecha' => 'date',
        ];
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function codigo(): string
    {
        return sprintf('F-%s-%04d', strtoupper((string) $this->sede), (int) $this->numero);
    }

    public function etiquetaEstadoPago(): string
    {
        return config('servicio_tecnico.estados_pago_factura.'.$this->estado_pago, $this->estado_pago);
    }

    public function scopeVisiblePara(Builder $query, User $user): Builder
    {
        if ($user->veSoloSusFacturasTaller()) {
            $query->where('tecnico_id', $user->id);
        }

        if ($user->scopesServicioToOwnSede()) {
            $query->where('sede', strtoupper((string) $user->sede));
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
            $datos['fecha'] = $datos['fecha'] ?? now()->toDateString();

            if (! isset($datos['total'])) {
                $datos['total'] = (float) ($datos['costo_mano_obra'] ?? 0) + (float) ($datos['costo_refacciones'] ?? 0);
            }

            return self::create($datos);
        });
    }
}
