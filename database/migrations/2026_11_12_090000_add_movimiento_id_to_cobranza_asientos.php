<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cobranza_asientos') && ! Schema::hasColumn('cobranza_asientos', 'movimiento_id')) {
            Schema::table('cobranza_asientos', function (Blueprint $table) {
                // Asiento registrado junto con un ingreso o egreso del flujo (mismo monto).
                $table->unsignedBigInteger('movimiento_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cobranza_asientos') && Schema::hasColumn('cobranza_asientos', 'movimiento_id')) {
            Schema::table('cobranza_asientos', function (Blueprint $table) {
                $table->dropIndex(['movimiento_id']);
                $table->dropColumn('movimiento_id');
            });
        }
    }
};
