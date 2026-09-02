<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('st_ordenes')) {
            return;
        }

        Schema::create('st_ordenes', function (Blueprint $table) {
            $table->id();
            $table->string('sede', 32);
            $table->unsignedInteger('numero');
            $table->string('cliente_nombre');
            $table->string('cliente_telefono', 40)->nullable();
            $table->string('cliente_cedula', 40)->nullable();
            $table->string('equipo')->nullable();
            $table->string('serial')->nullable();
            $table->text('falla')->nullable();
            $table->string('accesorios')->nullable();
            $table->text('diagnostico')->nullable();
            $table->string('estado', 32)->default('pendiente');
            $table->string('prioridad', 16)->default('normal');
            $table->date('fecha_ingreso');
            $table->date('fecha_prometida')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['sede', 'numero']);
            $table->index(['sede', 'estado']);
            $table->index('fecha_ingreso');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('st_ordenes');
    }
};
