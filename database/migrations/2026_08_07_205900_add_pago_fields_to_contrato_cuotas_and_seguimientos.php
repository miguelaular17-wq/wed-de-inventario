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
        Schema::table('contrato_cuotas', function (Blueprint $table) {
            $table->decimal('tasa_cambio', 12, 4)->nullable();
            $table->string('banco_destino')->nullable();
            $table->string('banco_origen')->nullable();
            $table->string('referencia')->nullable();
        });
        
        Schema::table('contrato_seguimientos', function (Blueprint $table) {
            $table->json('detalles_pago')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contrato_cuotas', function (Blueprint $table) {
            $table->dropColumn(['tasa_cambio', 'banco_destino', 'banco_origen', 'referencia']);
        });
        
        Schema::table('contrato_seguimientos', function (Blueprint $table) {
            $table->dropColumn('detalles_pago');
        });
    }
};
