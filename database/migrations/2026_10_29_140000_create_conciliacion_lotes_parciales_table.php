<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conciliacion_lotes_parciales')) {
            return;
        }
        // Abono de un cierre (Mercantil) que trae varios lotes: los que ya están en Tesorería quedan ligados
        // y los que faltan (tesoreria_ingreso_id nulo) esperan a registrarse para completar la conciliación.
        Schema::create('conciliacion_lotes_parciales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conciliacion_linea_id')->index();
            $table->unsignedBigInteger('tesoreria_ingreso_id')->nullable()->index();
            $table->string('lote', 30);
            $table->decimal('monto', 18, 2);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conciliacion_lotes_parciales');
    }
};
