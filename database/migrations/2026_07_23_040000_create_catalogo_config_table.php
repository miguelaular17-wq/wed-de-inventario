<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('pgsql')->create('catalogo_config', function (Blueprint $table) {
            $table->id();
            $table->json('categorias')->nullable()->comment('Array de categorías seleccionadas');
            $table->json('subcategorias')->nullable()->comment('Array de subcategorías seleccionadas');
            $table->boolean('solo_existencia')->default(true)->comment('Filtrar solo productos con existencia > 0');
            $table->text('url_supabase')->nullable()->comment('URL pública del catálogo en Supabase');
            $table->timestamp('ultima_generacion')->nullable()->comment('Cuándo se generó por última vez');
            $table->timestamps();
        });

        // Insert a single default config row
        DB::connection('pgsql')->table('catalogo_config')->insert([
            'categorias'       => json_encode([]),
            'subcategorias'    => json_encode([]),
            'solo_existencia'  => true,
            'url_supabase'     => null,
            'ultima_generacion'=> null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('catalogo_config');
    }
};
