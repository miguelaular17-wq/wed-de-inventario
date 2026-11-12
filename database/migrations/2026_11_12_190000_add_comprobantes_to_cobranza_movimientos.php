<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Comprobantes de pago (URLs) de cada movimiento del flujo de caja de Cobranza. */
    public function up(): void
    {
        if (Schema::hasTable('cobranza_movimientos') && ! Schema::hasColumn('cobranza_movimientos', 'comprobantes')) {
            Schema::table('cobranza_movimientos', function (Blueprint $table) {
                $table->json('comprobantes')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cobranza_movimientos', 'comprobantes')) {
            Schema::table('cobranza_movimientos', function (Blueprint $table) {
                $table->dropColumn('comprobantes');
            });
        }
    }
};
