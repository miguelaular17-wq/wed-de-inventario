<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flujo_cajas', function (Blueprint $table) {
            $table->string('banco')->nullable();
            $table->string('titular')->nullable();
            $table->string('categoria_cuenta')->nullable();
            
            $table->decimal('monto_usd', 15, 2)->nullable();
            $table->decimal('tasa_cambio', 15, 2)->nullable();
            $table->decimal('diferencial_cambiario', 15, 2)->nullable();
            $table->decimal('monto_bs', 15, 2)->nullable();
            $table->decimal('comision', 15, 2)->nullable();
            $table->string('motivo')->nullable();
            
            $table->enum('categoria_egreso', ['egreso_realizado', 'otros_egresos'])->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('flujo_cajas', function (Blueprint $table) {
            $table->dropColumn([
                'banco', 'titular', 'categoria_cuenta', 'monto_usd', 
                'tasa_cambio', 'diferencial_cambiario', 'monto_bs', 
                'comision', 'motivo', 'categoria_egreso'
            ]);
        });
    }
};
