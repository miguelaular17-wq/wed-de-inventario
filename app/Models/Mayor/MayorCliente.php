<?php

namespace App\Models\Mayor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MayorCliente extends Model
{
    protected $table = 'mayor_clientes';

    protected $fillable = ['documento', 'nombre', 'telefono', 'email', 'direccion', 'user_id'];

    public function facturas(): HasMany
    {
        return $this->hasMany(MayorFactura::class, 'cliente_id');
    }

    /** "J-40123456-7", "v 12.345.678" → "J401234567", "V12345678". */
    public static function normalizarDocumento(?string $documento): string
    {
        return mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $documento) ?? '', 'UTF-8');
    }
}
