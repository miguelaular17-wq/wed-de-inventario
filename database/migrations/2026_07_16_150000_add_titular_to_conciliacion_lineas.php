<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conciliacion_lineas', function (Blueprint $table) {
            // Columna titular después de banco
            $table->string('titular')->nullable()->after('banco');
            // Índice compuesto para agrupar/filtrar rápido
            $table->index(['banco', 'titular'], 'idx_conciliacion_banco_titular');
        });
    }

    public function down(): void
    {
        Schema::table('conciliacion_lineas', function (Blueprint $table) {
            $table->dropIndex('idx_conciliacion_banco_titular');
            $table->dropColumn('titular');
        });
    }
};
