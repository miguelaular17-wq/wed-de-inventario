<?php

namespace App\Models;

use App\Models\Nomina\NominaEmpleado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cliente extends Model
{
    use HasFactory;

    protected $fillable = ['cedula', 'nombre'];

    public function empleadoNomina(): HasOne
    {
        return $this->hasOne(NominaEmpleado::class, 'cliente_id');
    }
}
