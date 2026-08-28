<?php

use App\Services\InventarioHistoricoDateSanitizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        app(InventarioHistoricoDateSanitizer::class)->run();
    }

    public function down(): void
    {
        //
    }
};
