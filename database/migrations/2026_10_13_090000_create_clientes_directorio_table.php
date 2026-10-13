<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clientes_directorio')) {
            return;
        }

        Schema::create('clientes_directorio', function (Blueprint $table) {
            $table->id();
            $table->string('sede_nombre', 64);
            $table->string('codigo', 64);
            $table->string('nombre', 200)->default('');
            $table->string('cedula', 40)->nullable();
            $table->string('telefono_celular', 120)->nullable();
            $table->timestamps();

            $table->unique(['sede_nombre', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes_directorio');
    }
};
