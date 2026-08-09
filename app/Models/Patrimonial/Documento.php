<?php
namespace App\Models\Patrimonial;

use Illuminate\Database\Eloquent\Model;

class Documento extends Model
{
    protected $table = 'pat_documentos';

    protected $fillable = [
        'propiedad_id', 'tipo', 'nombre',
        'ruta_archivo', 'tamano_bytes', 'observaciones',
    ];

    public function propiedad()
    {
        return $this->belongsTo(Propiedad::class, 'propiedad_id');
    }

    public function getTamanoLegible(): string
    {
        $bytes = $this->tamano_bytes ?? 0;
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }

    public function getIcono(): string
    {
        return match($this->tipo) {
            'contrato'  => '📄',
            'factura'   => '🧾',
            'permiso'   => '📋',
            'foto'      => '🖼️',
            default     => '📎',
        };
    }
}
