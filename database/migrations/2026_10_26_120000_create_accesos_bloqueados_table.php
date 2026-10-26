<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Accesos (menús, vistas, reportes) y funciones que el administrador desactiva.
        // user_id NULL = desactivado para todos (menos el administrador).
        if (! Schema::hasTable('accesos_bloqueados')) {
            Schema::create('accesos_bloqueados', function (Blueprint $table) {
                $table->id();
                $table->string('clave', 120);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('creado_por')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'clave']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accesos_bloqueados');
    }
};
