<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tesoreria_caja_movimientos')) {
            return;
        }
        Schema::table('tesoreria_caja_movimientos', function (Blueprint $table) {
            if (! Schema::hasColumn('tesoreria_caja_movimientos', 'tipo_gasto')) {
                $table->string('tipo_gasto', 120)->nullable()->after('categoria');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tesoreria_caja_movimientos')) {
            return;
        }
        Schema::table('tesoreria_caja_movimientos', function (Blueprint $table) {
            if (Schema::hasColumn('tesoreria_caja_movimientos', 'tipo_gasto')) {
                $table->dropColumn('tipo_gasto');
            }
        });
    }
};
