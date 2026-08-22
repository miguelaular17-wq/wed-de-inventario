<?php

namespace App\Models\Nomina;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominaAjuste extends Model
{
    protected $table = 'nomina_ajustes';

    protected $fillable = [
        'nomina_registro_id',
        'tipo',
        'concepto',
        'monto',
        'usuario_id',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function registro(): BelongsTo
    {
        return $this->belongsTo(NominaRegistro::class, 'nomina_registro_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
