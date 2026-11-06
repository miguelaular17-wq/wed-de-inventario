<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lotes de punto de venta que Tesorería anota en el cuadre de caja. Cada uno queda también como
 * lote POS en Tesorería (tesoreria_ingresos); aquí se guarda el enlace para no duplicarlo al reprocesar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cuadre_caja_lotes')) {
            return;
        }

        Schema::create('cuadre_caja_lotes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cuadre_caja_id')->constrained('cuadres_caja')->cascadeOnDelete();
            $t->string('codigo_metodo', 32);
            $t->string('codigo_cuenta', 32)->default('');
            $t->string('lote', 64);
            $t->decimal('monto', 18, 2);
            $t->unsignedBigInteger('tesoreria_ingreso_id')->nullable()->index();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamps();
            $t->unique(['cuadre_caja_id', 'codigo_metodo', 'codigo_cuenta', 'lote'], 'cuadre_caja_lotes_linea_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuadre_caja_lotes');
    }
};
