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
            if (! Schema::hasColumn('tesoreria_caja_movimientos', 'flujo_caja_id')) {
                $table->unsignedBigInteger('flujo_caja_id')->nullable()->after('user_id');
                $table->index('flujo_caja_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tesoreria_caja_movimientos')) {
            return;
        }
        Schema::table('tesoreria_caja_movimientos', function (Blueprint $table) {
            if (Schema::hasColumn('tesoreria_caja_movimientos', 'flujo_caja_id')) {
                $table->dropIndex(['flujo_caja_id']);
                $table->dropColumn('flujo_caja_id');
            }
        });
    }
};
