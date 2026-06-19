<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequisicionManual extends Model
{
    protected $table = 'requisiciones_manuales';

    protected $fillable = [
        'sede_local',
        'codigo',
        'producto',
        'sede_origen',
        'cantidad',
        'usuario',
        'aplicada_at',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'aplicada_at' => 'datetime',
    ];

    public function isPendiente(): bool
    {
        return $this->aplicada_at === null;
    }
}
