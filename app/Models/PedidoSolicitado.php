<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoSolicitado extends Model
{
    protected $table = 'pedidos_solicitados';

    protected $fillable = [
        'producto_id',
        'codigo',
        'producto',
        'categoria',
        'proveedor',
        'solicitante',
        'notas',
        'estado',
        'atendido_at',
    ];

    protected $casts = [
        'atendido_at' => 'datetime',
    ];

    public function isPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }

    public function marcarAtendido(): void
    {
        $this->update([
            'estado' => 'atendido',
            'atendido_at' => now(),
        ]);
    }
}
