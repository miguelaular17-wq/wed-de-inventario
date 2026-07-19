<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientePersonal extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'cliente_personals';
    
    protected $fillable = ['codigo_cliente', 'nombre_cliente'];
}
