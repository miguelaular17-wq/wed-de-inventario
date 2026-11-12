<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Forma de pago del movimiento (sale en el recibo de pago). */
    public function up(): void
    {
        if (Schema::hasTable('cobranza_movimientos') && ! Schema::hasColumn('cobranza_movimientos', 'forma_pago')) {
            Schema::table('cobranza_movimientos', function (Blueprint $table) {
                $table->string('forma_pago', 40)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cobranza_movimientos', 'forma_pago')) {
            Schema::table('cobranza_movimientos', function (Blueprint $table) {
                $table->dropColumn('forma_pago');
            });
        }
    }
};
