<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // First truncate existing config to avoid issues since we just created it
        DB::connection('pgsql')->table('catalogo_config')->truncate();

        Schema::connection('pgsql')->table('catalogo_config', function (Blueprint $table) {
            $table->string('nombre')->after('id')->comment('Nombre descriptivo del catálogo');
            $table->string('archivo')->after('nombre')->comment('Nombre del archivo .pdf en Supabase');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->table('catalogo_config', function (Blueprint $table) {
            $table->dropColumn(['nombre', 'archivo']);
        });
    }
};
