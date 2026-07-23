<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContratoSeguimiento extends Model
{
    protected $table = 'contrato_seguimientos';

    protected $fillable = [
        'contrato_id', 'cuota_id', 'usuario_id',
        'fecha_hora', 'resultado', 'fecha_prometida_pago',
        'comentarios', 'contactado',
    ];

    protected $casts = [
        'fecha_hora'           => 'datetime',
        'fecha_prometida_pago' => 'date',
        'contactado'           => 'boolean',
    ];

    public const RESULTADOS = [
        'CONTACTADO'      => 'Contactado',
        'NO_CONTESTA'     => 'No contesta',
        'PROMESA_PAGO'    => 'Promesa de pago',
        'BUZON_MENSAJES'  => 'Buzón de mensajes',
        'SIN_SEÑAL'       => 'Sin señal',
        'PAGO_PARCIAL'    => 'Pago parcial recibido',
        'PAGO_COMPLETO'   => 'Pago completo recibido',
        'NUEVO_PRESTAMO'  => 'Nuevo préstamo agregado',
        'SIN_FONDOS'      => 'Sin fondos',
        'RENEGOCIACION'   => 'Solicita renegociación',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(ContratoCuota::class, 'cuota_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function resultadoLabel(): string
    {
        return self::RESULTADOS[$this->resultado] ?? $this->resultado;
    }
}
