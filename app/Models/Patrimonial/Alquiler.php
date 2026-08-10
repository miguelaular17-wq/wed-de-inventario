<?php
namespace App\Models\Patrimonial;

use Illuminate\Database\Eloquent\Model;

class Alquiler extends Model
{
    protected $table = 'pat_alquileres';

    protected $fillable = [
        'propiedad_id', 'inquilino_nombre', 'inquilino_contacto',
        'contrato_nro', 'fecha_inicio', 'fecha_fin', 'tipo_canon',
        'canon_mensual', 'canon_quincenal', 'dia_pago', 'forma_pago',
        'estado', 'observaciones',
    ];

    protected $casts = [
        'fecha_inicio'     => 'date',
        'fecha_fin'        => 'date',
        'canon_mensual'    => 'decimal:2',
        'canon_quincenal'  => 'decimal:2',
    ];

    public function propiedad()
    {
        return $this->belongsTo(Propiedad::class, 'propiedad_id');
    }

    public function pagos()
    {
        return $this->hasMany(AlquilerPago::class, 'alquiler_id');
    }

    public function canonActual(): float
    {
        return $this->tipo_canon === 'quincenal'
            ? (float)($this->canon_quincenal ?? 0)
            : (float)($this->canon_mensual ?? 0);
    }

    public function actualizarVencimientos()
    {
        $hoy = \Carbon\Carbon::now()->startOfDay();
        $this->pagos()->where('estado', 'pendiente')
             ->where('fecha_vencimiento', '<', $hoy)
             ->update(['estado' => 'vencido']);
    }

    public function generarPagosPendientes()
    {
        if ($this->estado !== 'activo') return;

        $fechaInicio = \Carbon\Carbon::parse($this->fecha_inicio)->startOfDay();
        $hoy = \Carbon\Carbon::now()->startOfDay();
        
        if ($this->tipo_canon === 'quincenal') {
            $fechaCalculo = $fechaInicio->copy();
            $quincena = 1;
            while ($fechaCalculo->lte($hoy)) {
                $periodo = $fechaCalculo->format('Y-m') . '-Q' . $quincena;
                $existe = $this->pagos()->where('periodo', $periodo)->exists();
                
                if (!$existe) {
                    $this->pagos()->create([
                        'periodo' => $periodo,
                        'fecha_vencimiento' => $fechaCalculo->format('Y-m-d'),
                        'monto' => $this->canonActual(),
                        'estado' => 'pendiente',
                    ]);
                }
                $fechaCalculo->addDays(15);
                $quincena++;
            }
        } else {
            $fechaCalculo = $fechaInicio->copy()->startOfMonth();
            while ($fechaCalculo->lte($hoy->copy()->startOfMonth())) {
                $periodo = $fechaCalculo->format('Y-m');
                $diaPago = $this->dia_pago ?: $fechaInicio->day;
                $diaCalculado = min($diaPago, $fechaCalculo->daysInMonth);
                
                $vencimiento = \Carbon\Carbon::createFromDate($fechaCalculo->year, $fechaCalculo->month, $diaCalculado)->startOfDay();
                
                $existe = $this->pagos()->where('periodo', $periodo)->exists();
                
                if (!$existe) {
                    $this->pagos()->create([
                        'periodo' => $periodo,
                        'fecha_vencimiento' => $vencimiento->format('Y-m-d'),
                        'monto' => $this->canonActual(),
                        'estado' => 'pendiente',
                    ]);
                }
                $fechaCalculo->addMonth();
            }
        }
    }
}
