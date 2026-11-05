<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ingresos de los cierres de caja de Suite (cierres_cajas + cierre_metodos_pago) que sube el sincronizador
 * de cada sede. Solo lo que entró por forma de pago: el cuadre se concilia en la web.
 * El sincronizador vuelve a subir los últimos días: la clave es (sede, id_cierre).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cuadres_caja')) {
            Schema::create('cuadres_caja', function (Blueprint $t) {
                $t->id();
                $t->string('sede', 16);
                $t->unsignedBigInteger('id_cierre');
                $t->date('fecha_cierre');
                $t->timestamp('registrado_at')->nullable();
                $t->string('codigo_caja', 32)->nullable();
                $t->string('descripcion_caja', 128)->nullable();
                $t->string('usuario', 32)->nullable();
                $t->string('usuario_nombre', 128)->nullable();
                $t->smallInteger('estado')->nullable();
                $t->decimal('factor_cambio', 18, 5)->default(0);
                // Lo llena Tesorería en la web; el sincronizador no toca estas columnas.
                $t->text('observacion_tesoreria')->nullable();
                $t->timestamp('conciliado_at')->nullable();
                $t->unsignedBigInteger('conciliado_por')->nullable();
                $t->timestamps();
                $t->unique(['sede', 'id_cierre']);
                $t->index(['fecha_cierre', 'sede']);
                $t->index('usuario');
            });
        }

        if (! Schema::hasTable('cuadre_caja_pagos')) {
            Schema::create('cuadre_caja_pagos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('cuadre_caja_id')->constrained('cuadres_caja')->cascadeOnDelete();
                $t->string('codigo_metodo', 32)->nullable();
                $t->string('forma_pago', 128)->nullable();
                $t->string('codigo_cuenta', 32)->nullable();
                $t->string('cuenta', 128)->nullable();
                $t->boolean('en_dolares')->default(false);
                $t->decimal('ingresos', 18, 5)->default(0);
                $t->decimal('ingresos_usd', 18, 5)->default(0);
                $t->string('lote', 64)->nullable();
                $t->index('cuadre_caja_id');
            });
        }

        // Lo que Tesorería contó de cada forma de pago. Tabla aparte porque el sincronizador
        // reemplaza cuadre_caja_pagos en cada subida.
        if (! Schema::hasTable('cuadre_caja_conteos')) {
            Schema::create('cuadre_caja_conteos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('cuadre_caja_id')->constrained('cuadres_caja')->cascadeOnDelete();
                $t->string('codigo_metodo', 32);
                $t->string('codigo_cuenta', 32)->default('');
                $t->string('forma_pago', 128)->nullable();
                $t->string('cuenta', 128)->nullable();
                $t->boolean('en_dolares')->default(false);
                $t->decimal('monto', 18, 5)->default(0);
                $t->unsignedBigInteger('user_id')->nullable();
                $t->timestamps();
                $t->unique(['cuadre_caja_id', 'codigo_metodo', 'codigo_cuenta'], 'cuadre_caja_conteos_linea_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cuadre_caja_conteos');
        Schema::dropIfExists('cuadre_caja_pagos');
        Schema::dropIfExists('cuadres_caja');
    }
};
