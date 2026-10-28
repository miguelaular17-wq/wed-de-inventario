<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['flujo_cajas', 'compra_divisas'] as $tabla) {
            if (Schema::hasTable($tabla) && ! Schema::hasColumn($tabla, 'conciliacion_nota')) {
                Schema::table($tabla, function (Blueprint $table) {
                    // Por qué se dio por conciliado un movimiento en tránsito (sin línea del banco, monto distinto, cuenta cambiada).
                    $table->text('conciliacion_nota')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['flujo_cajas', 'compra_divisas'] as $tabla) {
            if (Schema::hasTable($tabla) && Schema::hasColumn($tabla, 'conciliacion_nota')) {
                Schema::table($tabla, fn (Blueprint $table) => $table->dropColumn('conciliacion_nota'));
            }
        }
    }
};
