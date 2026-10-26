<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hora del movimiento en el extracto (Binance trae fecha y hora): permite cruzar cobros del mismo monto y día.
        if (Schema::hasTable('conciliacion_divisas_lineas') && ! Schema::hasColumn('conciliacion_divisas_lineas', 'hora')) {
            Schema::table('conciliacion_divisas_lineas', function (Blueprint $table) {
                $table->time('hora')->nullable()->after('fecha');
            });
        }

        // Segundo documento de la conciliación: reporte de pagos recibidos en caja (Cloud), uno por cobro.
        if (! Schema::hasTable('conciliacion_divisas_pagos')) {
            Schema::create('conciliacion_divisas_pagos', function (Blueprint $table) {
                $table->id();
                $table->string('banco', 80);
                $table->string('titular', 120);
                $table->string('nro', 40)->nullable();
                $table->date('fecha');
                $table->dateTime('fecha_hora')->nullable();
                $table->decimal('monto', 18, 2);
                $table->string('localidad', 120)->nullable();
                $table->string('terminal', 120)->nullable();
                $table->string('usuario', 120)->nullable();
                $table->string('pagador', 160)->nullable();
                $table->string('referencia', 120)->nullable();
                $table->unsignedBigInteger('linea_id')->nullable()->comment('Movimiento del extracto con el que se cruzó');
                $table->string('huella', 64)->unique();
                $table->string('archivo', 255)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();

                $table->index(['banco', 'titular', 'fecha']);
                $table->index('linea_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliacion_divisas_pagos');
        if (Schema::hasTable('conciliacion_divisas_lineas') && Schema::hasColumn('conciliacion_divisas_lineas', 'hora')) {
            Schema::table('conciliacion_divisas_lineas', function (Blueprint $table) {
                $table->dropColumn('hora');
            });
        }
    }
};
