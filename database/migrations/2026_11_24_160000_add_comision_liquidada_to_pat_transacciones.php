<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pat_transacciones')) {
            return;
        }

        Schema::table('pat_transacciones', function (Blueprint $table) {
            if (! Schema::hasColumn('pat_transacciones', 'comision_liquidada')) {
                // Las ya existentes se consideran liquidadas; las nuevas pendientes se marcan false.
                $table->boolean('comision_liquidada')->default(true)->after('tipo');
            }
            if (! Schema::hasColumn('pat_transacciones', 'cobranza_egreso_id')) {
                $table->unsignedBigInteger('cobranza_egreso_id')->nullable()->after('cobranza_movimiento_id');
                $table->index('cobranza_egreso_id');
            }
        });

        if (Schema::hasColumn('pat_transacciones', 'comision_liquidada')) {
            DB::statement("UPDATE pat_transacciones SET comision_liquidada = TRUE WHERE tipo = 'comision'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pat_transacciones')) {
            return;
        }

        Schema::table('pat_transacciones', function (Blueprint $table) {
            if (Schema::hasColumn('pat_transacciones', 'cobranza_egreso_id')) {
                $table->dropColumn('cobranza_egreso_id');
            }
            if (Schema::hasColumn('pat_transacciones', 'comision_liquidada')) {
                $table->dropColumn('comision_liquidada');
            }
        });
    }
};
