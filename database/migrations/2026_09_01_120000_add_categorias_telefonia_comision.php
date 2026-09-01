<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nomina_grupos_comision')) {
            return;
        }

        $categorias = config('inventario.categorias_telefonia', []);
        foreach ($categorias as $categoria) {
            $normalizada = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $categoria) ?? ''), 'UTF-8');
            DB::table('nomina_grupos_comision')->updateOrInsert(
                ['grupo' => 'TELEFONIA', 'categoria_normalizada' => $normalizada],
                ['categoria' => $categoria, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        //
    }
};
