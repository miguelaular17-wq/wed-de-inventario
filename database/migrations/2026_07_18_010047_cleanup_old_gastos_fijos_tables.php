<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop old tables
        Schema::dropIfExists('gasto_fijo_config');
        Schema::dropIfExists('gasto_fijo_custom');
        Schema::dropIfExists('gasto_fijo_oculto');

        // 2. Drop unique constraints from gasto_fijo_pagos (since we'll replace tabla_idx/fila_idx)
        // Check if index exists first to avoid errors. The previous unique index was:
        // $table->unique(['tabla_idx', 'fila_idx', 'mes_idx', 'anio']);
        
        Schema::table('gasto_fijo_pagos', function (Blueprint $table) {
            // Because index names are auto-generated, it is safer to drop by index name
            // The default name in Laravel is usually table_column1_column2_..._unique
            $table->dropUnique('gasto_fijo_pagos_tabla_idx_fila_idx_mes_idx_anio_unique');
        });

        Schema::table('gasto_fijo_pagos', function (Blueprint $table) {
            $table->dropColumn(['tabla_idx', 'fila_idx']);
            $table->unique(['gasto_fijo_id', 'mes_idx', 'anio'], 'gf_pagos_gasto_mes_anio_unique');
        });
    }

    public function down(): void
    {
        Schema::table('gasto_fijo_pagos', function (Blueprint $table) {
            $table->dropUnique('gf_pagos_gasto_mes_anio_unique');
            $table->unsignedTinyInteger('tabla_idx')->default(0);
            $table->unsignedSmallInteger('fila_idx')->default(0);
        });

        Schema::table('gasto_fijo_pagos', function (Blueprint $table) {
            $table->unique(['tabla_idx', 'fila_idx', 'mes_idx', 'anio']);
        });
    }
};
