<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class SyncHeartbeat extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'inventario_v2.sync_heartbeats';
    protected $guarded    = [];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'metadata'     => 'array',
    ];

    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('last_seen_at', '>=', now()->subMinutes(5));
    }

    public function getEsActivoAttribute(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->gte(now()->subMinutes(5));
    }

    public function getTiempoAttribute(): string
    {
        if (! $this->last_seen_at) return 'Nunca';
        return $this->last_seen_at->diffForHumans();
    }
}
