<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsistenteMensaje extends Model
{
    protected $table = 'asistente_mensajes';

    protected $fillable = [
        'user_id',
        'rol',
        'texto',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
