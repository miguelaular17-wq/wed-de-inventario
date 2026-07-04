<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conciliacion_lineas', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('descripcion');
            $table->string('referencia')->nullable();
            $table->decimal('monto', 15, 2);
            $table->string('estado')->default('pendiente'); // pendiente, conciliado, ignorado
            $table->unsignedBigInteger('flujo_caja_id')->nullable(); // Si hizo match
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conciliacion_lineas');
    }
};
