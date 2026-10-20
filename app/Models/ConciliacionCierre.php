<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConciliacionCierre extends Model
{
    protected $table = 'conciliacion_cierres';

    protected $guarded = [];

    protected $casts = [
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date',
        'sin_movimientos' => 'boolean',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
