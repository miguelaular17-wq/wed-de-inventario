<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalisisCategoriaCheck extends Model
{
    protected $table = 'analisis_categoria_checks';

    protected $fillable = [
        'sede',
        'categoria',
        'subcategoria',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
