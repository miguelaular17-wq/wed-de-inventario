<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoSolicitado extends Model
{
    protected $table = 'pedidos_solicitados';

    protected $fillable = [
        'producto_id',
        'codigo',
        'producto',
        'categoria',
        'proveedor',
        'compra_proveedor',
        'fecha_compra',
        'fecha_despacho_estimada',
        'motivo_fuera_mercado',
        'solicitante',
        'sede',
        'notas',
        'estado',
        'atendido_at',
        'atendido_por',
    ];

    protected $casts = [
        'atendido_at' => 'datetime',
        'fecha_compra' => 'date',
        'fecha_despacho_estimada' => 'date',
    ];

    public function atendidoPorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendido_por');
    }

    public function isPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }

    public function marcarComprado(
        string $proveedor,
        string $fechaCompra,
        string $fechaDespacho,
        int $userId
    ): void {
        $this->update([
            'estado' => 'comprado',
            'compra_proveedor' => $proveedor,
            'fecha_compra' => $fechaCompra,
            'fecha_despacho_estimada' => $fechaDespacho,
            'atendido_at' => now(),
            'atendido_por' => $userId,
        ]);
    }

    public function marcarFueraMercado(string $motivo, int $userId): void
    {
        $this->update([
            'estado' => 'fuera_de_mercado',
            'motivo_fuera_mercado' => $motivo,
            'atendido_at' => now(),
            'atendido_por' => $userId,
        ]);
    }
}
