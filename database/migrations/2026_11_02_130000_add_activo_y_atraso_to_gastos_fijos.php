<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gastos_fijos', function (Blueprint $table) {
            if (! Schema::hasColumn('gastos_fijos', 'activo')) {
                // Desactivado: sigue en la tabla, pero no avisa ni aparece para vincular pagos.
                $table->boolean('activo')->default(true);
            }
            if (! Schema::hasColumn('gastos_fijos', 'meses_atraso')) {
                // 1 = el mes se paga en el mes siguiente (ej. octubre se paga en noviembre).
                $table->unsignedTinyInteger('meses_atraso')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('gastos_fijos', function (Blueprint $table) {
            foreach (['activo', 'meses_atraso'] as $col) {
                if (Schema::hasColumn('gastos_fijos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
