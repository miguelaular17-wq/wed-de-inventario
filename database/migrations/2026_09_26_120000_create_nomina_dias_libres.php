<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nomina_dias_libres')) {
            return;
        }

        Schema::create('nomina_dias_libres', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empleado_id');
            $table->date('fecha');
            $table->string('estado', 16)->default('PENDIENTE');
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->unsignedBigInteger('aprobado_por')->nullable();
            $table->timestamp('aprobado_at')->nullable();
            $table->text('nota')->nullable();
            $table->timestamps();

            $table->unique(['empleado_id', 'fecha']);
            $table->index(['estado', 'fecha']);
            $table->index('empleado_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomina_dias_libres');
    }
};
