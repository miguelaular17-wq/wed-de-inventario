<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Archivo del archivero de Cobranza: el contenido está en el bucket «archivero» con un nombre aleatorio. */
class ArchiveroArchivo extends Model
{
    protected $table = 'archivero_archivos';

    protected $fillable = ['carpeta', 'nombre', 'extension', 'tamano', 'mime', 'objeto', 'origen', 'modificado_en', 'user_id'];

    protected $casts = [
        'tamano' => 'integer',
        'modificado_en' => 'datetime',
    ];

    public function usuario(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tamanoTexto(): string
    {
        $b = (int) $this->tamano;

        return match (true) {
            $b >= 1048576 => number_format($b / 1048576, 1).' MB',
            $b >= 1024 => number_format($b / 1024, 0).' KB',
            default => $b.' B',
        };
    }

    public function icono(): string
    {
        return match (mb_strtolower((string) $this->extension)) {
            'pdf' => '📕',
            'xls', 'xlsx', 'csv' => '📗',
            'doc', 'docx', 'pub' => '📘',
            'ppt', 'pptx' => '📙',
            'jpg', 'jpeg', 'png', 'webp', 'gif' => '🖼️',
            'zip', 'rar' => '🗜️',
            default => '📄',
        };
    }

    /** Se abre en el navegador (PDF e imágenes); lo demás se descarga. */
    public function seVeEnNavegador(): bool
    {
        return in_array(mb_strtolower((string) $this->extension), ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'txt'], true);
    }
}
