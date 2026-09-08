<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pat_reservas') && ! Schema::hasColumn('pat_reservas', 'comision')) {
            Schema::table('pat_reservas', function (Blueprint $table) {
                $table->decimal('comision', 18, 2)->default(0)->after('precio_noche');
            });
        }

        if (Schema::hasTable('pat_transacciones') && ! Schema::hasColumn('pat_transacciones', 'reserva_id')) {
            Schema::table('pat_transacciones', function (Blueprint $table) {
                $table->unsignedBigInteger('reserva_id')->nullable()->after('propiedad_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pat_reservas') && Schema::hasColumn('pat_reservas', 'comision')) {
            Schema::table('pat_reservas', function (Blueprint $table) {
                $table->dropColumn('comision');
            });
        }

        if (Schema::hasTable('pat_transacciones') && Schema::hasColumn('pat_transacciones', 'reserva_id')) {
            Schema::table('pat_transacciones', function (Blueprint $table) {
                $table->dropColumn('reserva_id');
            });
        }
    }
};
