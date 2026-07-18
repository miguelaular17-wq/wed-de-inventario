<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gastos_fijos', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('grupo_id'); // 0, 1, 2 (antiguo tabla_idx)
            $table->string('sede', 200)->default('');
            $table->string('servicio', 200);
            $table->string('fecha', 100)->default('');
            $table->string('empresa', 200)->default('');
            $table->decimal('costo', 10, 2)->default(0);
            $table->integer('orden')->default(0);
            $table->boolean('visible')->default(true);
            $table->timestamps();
        });

        Schema::table('gasto_fijo_pagos', function (Blueprint $table) {
            // Permitimos nulable temporalmente mientras migramos datos
            $table->foreignId('gasto_fijo_id')->nullable()->constrained('gastos_fijos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('gasto_fijo_pagos', function (Blueprint $table) {
            $table->dropForeign(['gasto_fijo_id']);
            $table->dropColumn('gasto_fijo_id');
        });

        Schema::dropIfExists('gastos_fijos');
    }
};
