<?php

namespace App\Models\Mayor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MayorClienteArchivo extends Model
{
    public const MAX_POR_CLIENTE = 4;

    protected $table = 'mayor_cliente_archivos';

    protected $fillable = [
        'cliente_id',
        'slot',
        'url',
        'nombre_archivo',
        'mime',
        'user_id',
    ];

    protected $casts = [
        'slot' => 'integer',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(MayorCliente::class, 'cliente_id');
    }

    public function esImagen(): bool
    {
        $mime = strtolower((string) $this->mime);

        return str_starts_with($mime, 'image/')
            || preg_match('/\.(jpe?g|png|webp|gif)$/i', (string) $this->nombre_archivo) === 1;
    }
}
