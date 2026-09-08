<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pat_alquileres') && ! Schema::hasColumn('pat_alquileres', 'comision')) {
            Schema::table('pat_alquileres', function (Blueprint $table) {
                $table->decimal('comision', 18, 2)->default(0)->after('canon_quincenal');
            });
        }

        if (Schema::hasTable('pat_transacciones') && ! Schema::hasColumn('pat_transacciones', 'alquiler_id')) {
            Schema::table('pat_transacciones', function (Blueprint $table) {
                $table->unsignedBigInteger('alquiler_id')->nullable()->after('reserva_id');
            });
        }

        if (Schema::hasTable('pat_transacciones') && ! Schema::hasColumn('pat_transacciones', 'alquiler_pago_id')) {
            Schema::table('pat_transacciones', function (Blueprint $table) {
                $table->unsignedBigInteger('alquiler_pago_id')->nullable()->after('alquiler_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pat_transacciones') && Schema::hasColumn('pat_transacciones', 'alquiler_pago_id')) {
            Schema::table('pat_transacciones', function (Blueprint $table) {
                $table->dropColumn('alquiler_pago_id');
            });
        }

        if (Schema::hasTable('pat_transacciones') && Schema::hasColumn('pat_transacciones', 'alquiler_id')) {
            Schema::table('pat_transacciones', function (Blueprint $table) {
                $table->dropColumn('alquiler_id');
            });
        }

        if (Schema::hasTable('pat_alquileres') && Schema::hasColumn('pat_alquileres', 'comision')) {
            Schema::table('pat_alquileres', function (Blueprint $table) {
                $table->dropColumn('comision');
            });
        }
    }
};
