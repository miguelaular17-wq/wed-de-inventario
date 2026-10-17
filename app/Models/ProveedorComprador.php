<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ProveedorComprador extends Model
{
    protected $table = 'proveedor_compradores';

    protected $fillable = [
        'proveedor',
        'comprador_clave',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function tableReady(): bool
    {
        return Schema::hasTable('proveedor_compradores');
    }

    /** @return list<string> */
    public static function proveedoresDeUsuario(?int $userId): array
    {
        if (! $userId || ! self::tableReady()) {
            return [];
        }

        return self::query()
            ->where('user_id', $userId)
            ->orderBy('proveedor')
            ->pluck('proveedor')
            ->map(fn ($p) => trim((string) $p))
            ->filter()
            ->values()
            ->all();
    }

    public static function userIdPorProveedor(string $proveedor): ?int
    {
        if (! self::tableReady()) {
            return null;
        }

        $id = self::query()
            ->whereRaw('UPPER(TRIM(proveedor)) = UPPER(?)', [trim($proveedor)])
            ->value('user_id');

        return $id !== null ? (int) $id : null;
    }

    /** @return Collection<string, int> proveedor => user_id */
    public static function mapaProveedorUserId(): Collection
    {
        if (! self::tableReady()) {
            return collect();
        }

        return self::query()
            ->whereNotNull('user_id')
            ->get(['proveedor', 'user_id'])
            ->mapWithKeys(fn ($row) => [trim((string) $row->proveedor) => (int) $row->user_id]);
    }
}
