<?php

namespace App\Models\Mayor;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Visita de la hoja de ruta: negocio visitado, quién atendió, su firma y la evidencia. */
class MayorVisita extends Model
{
    public const RESULTADOS = [
        'interesado' => 'Interesado',
        'pedido' => 'Hizo pedido',
        'volver' => 'Volver a visitar',
        'no_interesado' => 'No interesado',
    ];

    protected $table = 'mayor_visitas';

    protected $fillable = [
        'fecha', 'user_id', 'cliente_id', 'negocio_nombre', 'negocio_documento', 'negocio_telefono', 'negocio_direccion', 'zona',
        'atendio_nombre', 'atendio_cargo', 'atendio_documento', 'atendio_telefono', 'resultado', 'proxima_visita', 'observaciones',
        'firma_url', 'evidencias', 'latitud', 'longitud',
    ];

    protected $casts = [
        'fecha' => 'date',
        'proxima_visita' => 'date',
        'evidencias' => 'array',
        'latitud' => 'float',
        'longitud' => 'float',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(MayorCliente::class, 'cliente_id');
    }

    public function etiquetaResultado(): string
    {
        return self::RESULTADOS[$this->resultado] ?? ucfirst((string) $this->resultado);
    }
}
