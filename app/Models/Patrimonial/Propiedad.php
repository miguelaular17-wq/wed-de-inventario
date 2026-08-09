<?php
namespace App\Models\Patrimonial;

use Illuminate\Database\Eloquent\Model;

class Propiedad extends Model
{
    protected $table = 'pat_propiedades';

    protected $fillable = [
        'codigo', 'nombre', 'tipo', 'direccion', 'ubicacion',
        'fotos', 'estado', 'propietario', 'responsable',
        'fecha_adquisicion', 'valor_inversion', 'observaciones',
    ];

    protected $casts = [
        'fotos'             => 'array',
        'fecha_adquisicion' => 'date',
        'valor_inversion'   => 'decimal:2',
    ];

    public function alquileres()
    {
        return $this->hasMany(Alquiler::class, 'propiedad_id');
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'propiedad_id');
    }

    public function transacciones()
    {
        return $this->hasMany(PatTransaccion::class, 'propiedad_id');
    }

    public function inventarioItems()
    {
        return $this->hasMany(InventarioItem::class, 'propiedad_id');
    }

    public function llaves()
    {
        return $this->hasMany(Llave::class, 'propiedad_id');
    }

    public function documentos()
    {
        return $this->hasMany(Documento::class, 'propiedad_id');
    }

    // Helpers
    public function alquilerActivo()
    {
        return $this->alquileres()->where('estado', 'activo')->latest()->first();
    }

    public function balanceMes(int $mes, int $anio): array
    {
        $txs = $this->transacciones()
            ->where('mes', $mes)->where('anio', $anio)->get();

        $ingresos   = $txs->where('tipo', 'ingreso')->sum('monto');
        $gastos     = $txs->where('tipo', 'gasto')->sum('monto');
        $comisiones = $txs->where('tipo', 'comision')->sum('monto');

        return [
            'ingresos'   => $ingresos,
            'gastos'     => $gastos,
            'comisiones' => $comisiones,
            'balance'    => $ingresos - $gastos - $comisiones,
        ];
    }

    public static function estadoLabel(?string $estado): string
    {
        if ($estado === null) return 'Sin Estado';
        return match($estado) {
            'disponible'    => 'Disponible',
            'alquilado'     => 'Alquilado',
            'uso_propio'    => 'Uso Propio',
            'remodelacion'  => 'Remodelación',
            'no_disponible' => 'No Disponible',
            default         => ucfirst($estado),
        };
    }

    public static function estadoColor(?string $estado): string
    {
        if ($estado === null) return '#64748b';
        return match($estado) {
            'disponible'    => '#10b981',
            'alquilado'     => '#2563eb',
            'uso_propio'    => '#8b5cf6',
            'remodelacion'  => '#f59e0b',
            'no_disponible' => '#ef4444',
            default         => '#64748b',
        };
    }
}
