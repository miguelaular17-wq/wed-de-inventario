<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nomina_periodos')) {
            return;
        }

        if (! Schema::hasColumn('nomina_periodos', 'tasa_bcv')) {
            Schema::table('nomina_periodos', function (Blueprint $table) {
                $table->decimal('tasa_bcv', 12, 4)->nullable()->after('fecha_pago_comision');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nomina_periodos') && Schema::hasColumn('nomina_periodos', 'tasa_bcv')) {
            Schema::table('nomina_periodos', function (Blueprint $table) {
                $table->dropColumn('tasa_bcv');
            });
        }
    }
};
