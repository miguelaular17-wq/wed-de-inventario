<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StEquipo extends Model
{
    public const ESTADO_DISPONIBLE = 'disponible';
    public const ESTADO_EN_TALLER = 'en_taller';
    public const ESTADO_EN_TRANSITO = 'en_transito';
    public const ESTADO_CON_CLIENTE = 'con_cliente';
    public const ESTADO_ENTREGADO = 'entregado';

    public const ESTADOS = [
        self::ESTADO_DISPONIBLE => 'Disponible',
        self::ESTADO_EN_TALLER => 'En taller',
        self::ESTADO_EN_TRANSITO => 'En tránsito',
        self::ESTADO_CON_CLIENTE => 'Con cliente',
        self::ESTADO_ENTREGADO => 'Entregado',
    ];

    protected $table = 'st_equipos';

    protected $fillable = [
        'imei',
        'imei2',
        'serial',
        'marca',
        'modelo',
        'color',
        'telefono_asociado',
        'estado_actual',
        'sede_actual',
        'tipo_dispositivo',
        'atributos',
    ];

    protected function casts(): array
    {
        return [
            'atributos' => 'array',
        ];
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(StEquipoEvento::class, 'equipo_id')->orderByDesc('created_at')->orderByDesc('id');
    }

    public function ordenes(): HasMany
    {
        return $this->hasMany(StOrden::class, 'equipo_id')->orderByDesc('fecha_ingreso')->orderByDesc('id');
    }

    public function etiquetaEstado(): string
    {
        return self::ESTADOS[$this->estado_actual] ?? (string) $this->estado_actual;
    }

    public function etiqueta(): string
    {
        $partes = array_filter([
            $this->marca,
            $this->modelo,
        ]);

        return $partes !== [] ? implode(' ', $partes) : 'Equipo #'.$this->id;
    }

    public function identificador(): string
    {
        if ($this->imei) {
            return 'IMEI '.$this->imei;
        }
        if ($this->serial) {
            return 'Serial '.$this->serial;
        }

        return '#'.$this->id;
    }

    public function scopeBuscar(Builder $query, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') {
            return $query->whereRaw('1 = 0');
        }

        $like = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $needle = '%'.$term.'%';

        return $query->where(function (Builder $inner) use ($term, $like, $needle) {
            $inner->where('imei', $like, $needle)
                ->orWhere('imei2', $like, $needle)
                ->orWhere('serial', $like, $needle)
                ->orWhere('telefono_asociado', $like, $needle)
                ->orWhere('marca', $like, $needle)
                ->orWhere('modelo', $like, $needle);

            if (ctype_digit($term)) {
                $inner->orWhere('id', (int) $term);
            }
        });
    }
}
