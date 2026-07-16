<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tracks hardcoded rows hidden by the user
        Schema::create('gasto_fijo_oculto', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('tabla_idx');
            $table->unsignedSmallInteger('fila_idx');
            $table->timestamps();
            $table->unique(['tabla_idx', 'fila_idx']);
        });

        // Custom rows added by the user (not in the hardcoded array)
        Schema::create('gasto_fijo_custom', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('tabla_idx');
            $table->string('sede', 200)->default('');
            $table->string('servicio', 200);
            $table->string('fecha', 100)->default('');
            $table->string('empresa', 200)->default('');
            $table->decimal('costo', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gasto_fijo_oculto');
        Schema::dropIfExists('gasto_fijo_custom');
    }
};
