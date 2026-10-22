<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conciliacion_lineas') && ! Schema::hasColumn('conciliacion_lineas', 'devolucion_linea_id')) {
            Schema::table('conciliacion_lineas', function (Blueprint $table) {
                // Cargo del banco y su abono de devolución quedan conciliados entre sí.
                $table->unsignedBigInteger('devolucion_linea_id')->nullable()->index();
            });
        }

        if (Schema::hasTable('flujo_cajas')) {
            Schema::table('flujo_cajas', function (Blueprint $table) {
                if (! Schema::hasColumn('flujo_cajas', 'anulado')) {
                    $table->boolean('anulado')->default(false);
                }
                if (! Schema::hasColumn('flujo_cajas', 'anulado_motivo')) {
                    $table->string('anulado_motivo', 255)->nullable();
                }
                if (! Schema::hasColumn('flujo_cajas', 'anulado_at')) {
                    $table->timestamp('anulado_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('conciliacion_lineas') && Schema::hasColumn('conciliacion_lineas', 'devolucion_linea_id')) {
            Schema::table('conciliacion_lineas', function (Blueprint $table) {
                $table->dropIndex(['devolucion_linea_id']);
                $table->dropColumn('devolucion_linea_id');
            });
        }

        if (Schema::hasTable('flujo_cajas')) {
            Schema::table('flujo_cajas', function (Blueprint $table) {
                foreach (['anulado', 'anulado_motivo', 'anulado_at'] as $col) {
                    if (Schema::hasColumn('flujo_cajas', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
