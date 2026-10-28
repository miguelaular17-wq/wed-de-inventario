<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conciliacion_lineas') && ! Schema::hasColumn('conciliacion_lineas', 'conciliacion_nota')) {
            Schema::table('conciliacion_lineas', function (Blueprint $table) {
                // Qué es un movimiento del banco que se concilió sin egreso/ingreso del sistema.
                $table->text('conciliacion_nota')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('conciliacion_lineas') && Schema::hasColumn('conciliacion_lineas', 'conciliacion_nota')) {
            Schema::table('conciliacion_lineas', fn (Blueprint $table) => $table->dropColumn('conciliacion_nota'));
        }
    }
};
