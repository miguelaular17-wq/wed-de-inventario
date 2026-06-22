<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '120');

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
