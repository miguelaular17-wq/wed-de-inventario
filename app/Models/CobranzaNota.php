<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CobranzaNota extends Model
{
    protected $connection = 'pgsql';
    protected $table = 'cobranza_notas';
    protected $fillable = ['id_documento', 'nota', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
