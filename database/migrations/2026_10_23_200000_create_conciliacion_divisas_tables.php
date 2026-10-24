<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Movimientos de cuentas en divisas (Binance, Mercantil Panamá, Wells Fargo, Citizens…), montos en USD.
        if (! Schema::hasTable('conciliacion_divisas_lineas')) {
            Schema::create('conciliacion_divisas_lineas', function (Blueprint $table) {
                $table->id();
                $table->string('banco', 80);
                $table->string('titular', 120);
                $table->date('fecha');
                $table->string('descripcion', 500);
                $table->string('referencia', 120)->nullable();
                $table->decimal('monto', 18, 2)->comment('USD; negativo = salida de la cuenta');
                $table->string('tipo', 10)->comment('cargo | abono');
                $table->string('estado', 20)->default('pendiente')->comment('pendiente | conciliado | comision | ignorado');
                $table->unsignedBigInteger('flujo_caja_id')->nullable();
                $table->string('huella', 64)->unique()->comment('Evita duplicar el mismo movimiento al volver a subir el archivo');
                $table->string('archivo', 255)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();

                $table->index(['banco', 'titular', 'fecha']);
                $table->index('flujo_caja_id');
            });
        }

        // Qué columna es cada dato en el extracto de cada cuenta (se recuerda entre cargas).
        if (! Schema::hasTable('conciliacion_divisas_formatos')) {
            Schema::create('conciliacion_divisas_formatos', function (Blueprint $table) {
                $table->id();
                $table->string('banco', 80);
                $table->string('titular', 120);
                $table->json('mapeo');
                $table->timestamps();

                $table->unique(['banco', 'titular']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliacion_divisas_formatos');
        Schema::dropIfExists('conciliacion_divisas_lineas');
    }
};
