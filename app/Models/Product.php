<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $connection = 'sqlite';

    protected $table = 'products';

    protected $fillable = [
        'cod_centro',
        'producto',
        'categoria',
        'subcategoria',
        'proveedor',
    ];

    public function sedeMetrics(): HasMany
    {
        return $this->hasMany(ProductSedeMetric::class);
    }

    public function metricFor(string $sede): ?ProductSedeMetric
    {
        return $this->sedeMetrics->firstWhere('sede', $sede);
    }
}
