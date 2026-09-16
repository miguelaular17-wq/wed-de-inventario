<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nomina_descuentos_mercancia')) {
            return;
        }

        Schema::table('nomina_descuentos_mercancia', function (Blueprint $table) {
            if (! Schema::hasColumn('nomina_descuentos_mercancia', 'destino')) {
                $table->string('destino', 16)->default('NOMINA')->after('monto');
                $table->index(['destino', 'estado']);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('nomina_descuentos_mercancia')) {
            return;
        }

        Schema::table('nomina_descuentos_mercancia', function (Blueprint $table) {
            if (Schema::hasColumn('nomina_descuentos_mercancia', 'destino')) {
                $table->dropIndex(['destino', 'estado']);
                $table->dropColumn('destino');
            }
        });
    }
};
