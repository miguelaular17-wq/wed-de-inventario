<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('venta_diaria_reportes') && ! Schema::hasColumn('venta_diaria_reportes', 'iphone')) {
            Schema::table('venta_diaria_reportes', function (Blueprint $table) {
                $table->decimal('iphone', 14, 2)->default(0)->after('abonos');
                $table->decimal('gift_card', 14, 2)->default(0)->after('iphone');
            });
        }

        if (Schema::hasTable('venta_diaria_cajas') && ! Schema::hasColumn('venta_diaria_cajas', 'iphone')) {
            Schema::table('venta_diaria_cajas', function (Blueprint $table) {
                $table->decimal('iphone', 14, 2)->default(0)->after('abonos');
                $table->decimal('gift_card', 14, 2)->default(0)->after('iphone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('venta_diaria_reportes') && Schema::hasColumn('venta_diaria_reportes', 'iphone')) {
            Schema::table('venta_diaria_reportes', function (Blueprint $table) {
                $table->dropColumn(['iphone', 'gift_card']);
            });
        }

        if (Schema::hasTable('venta_diaria_cajas') && Schema::hasColumn('venta_diaria_cajas', 'iphone')) {
            Schema::table('venta_diaria_cajas', function (Blueprint $table) {
                $table->dropColumn(['iphone', 'gift_card']);
            });
        }
    }
};
