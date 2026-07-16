<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // scoped() = singleton por request en PHP-FPM.
        // El cach\u00e9 de instancia ($loadForSedeCache) se comparte dentro del request
        // y se libera autom\u00e1ticamente al terminar. Evita reconstruir la colecci\u00f3n de
        // 12,947 productos cuando m\u00faltiples m\u00e9todos la solicitan en el mismo request.
        $this->app->scoped(\App\Services\InventarioV2Repository::class);
        $this->app->scoped(\App\Services\ProductRepository::class);
    }

    public function boot(): void
    {
        // Logging de SQL solo en entorno de desarrollo (DEBUG=true)
        // En producción se omite para eliminar escrituras síncronas a disco por cada query
        if (config('app.debug')) {
            \Illuminate\Support\Facades\DB::listen(function ($query) {
                \Illuminate\Support\Facades\Log::info(sprintf(
                    '[SQL] %.2f ms | %s',
                    $query->time,
                    $query->sql
                ));
            });
        }

        \Illuminate\Pagination\Paginator::useBootstrapFive();

        if ($path = env('SESSION_PATH')) {
            if (! is_dir($path)) {
                @mkdir($path, 0755, true);
            }
            config(['session.files' => $path]);
        }

        if ($cachePath = env('CACHE_PATH')) {
            if (! is_dir($cachePath)) {
                @mkdir($cachePath, 0755, true);
            }
            config(['cache.stores.file.path' => $cachePath]);
        }
    }
}
