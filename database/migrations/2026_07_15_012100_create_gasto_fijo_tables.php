<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gasto_fijo_pagos', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('tabla_idx');
            $table->unsignedSmallInteger('fila_idx');
            $table->unsignedTinyInteger('mes_idx');
            $table->unsignedSmallInteger('anio');
            $table->decimal('monto', 10, 2)->nullable();
            $table->boolean('pagado')->default(false);
            $table->timestamp('pagado_at')->nullable();
            $table->timestamps();

            $table->unique(['tabla_idx', 'fila_idx', 'mes_idx', 'anio']);
        });

        Schema::create('gasto_fijo_config', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('tabla_idx');
            $table->unsignedSmallInteger('fila_idx');
            $table->string('fecha', 100);
            $table->timestamps();

            $table->unique(['tabla_idx', 'fila_idx']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gasto_fijo_pagos');
        Schema::dropIfExists('gasto_fijo_config');
    }
};
