<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Movimiento en divisas sin pareja que se da por bueno con una explicación (estado "justificado"). */
    public function up(): void
    {
        if (! Schema::hasTable('conciliacion_divisas_lineas')) {
            return;
        }
        Schema::table('conciliacion_divisas_lineas', function (Blueprint $table) {
            if (! Schema::hasColumn('conciliacion_divisas_lineas', 'nota')) {
                $table->text('nota')->nullable();
            }
            if (! Schema::hasColumn('conciliacion_divisas_lineas', 'justificado_por')) {
                $table->unsignedBigInteger('justificado_por')->nullable();
            }
            if (! Schema::hasColumn('conciliacion_divisas_lineas', 'justificado_at')) {
                $table->timestamp('justificado_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('conciliacion_divisas_lineas')) {
            return;
        }
        Schema::table('conciliacion_divisas_lineas', function (Blueprint $table) {
            foreach (['nota', 'justificado_por', 'justificado_at'] as $col) {
                if (Schema::hasColumn('conciliacion_divisas_lineas', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
