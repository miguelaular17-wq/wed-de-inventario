<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('venta_diaria_reportes')) {
            Schema::table('venta_diaria_reportes', function (Blueprint $table) {
                if (! Schema::hasColumn('venta_diaria_reportes', 'zelle')) {
                    $table->decimal('zelle', 14, 2)->default(0);
                    $table->decimal('binance', 14, 2)->default(0);
                    $table->decimal('mercantil_panama', 14, 2)->default(0);
                    $table->decimal('preventa', 14, 2)->default(0);
                    $table->decimal('pago_movil_bs', 14, 2)->default(0);
                    $table->decimal('transferencias_bs', 14, 2)->default(0);
                    $table->decimal('flaexpay', 14, 2)->default(0);
                    $table->decimal('krece', 14, 2)->default(0);
                }
            });

            if (Schema::hasColumn('venta_diaria_reportes', 'zelle_binance')) {
                DB::table('venta_diaria_reportes')->update([
                    'zelle' => DB::raw('zelle_binance'),
                    'pago_movil_bs' => DB::raw('transf_pm_bs'),
                ]);
            }
        }

        if (Schema::hasTable('venta_diaria_cajas')) {
            Schema::table('venta_diaria_cajas', function (Blueprint $table) {
                if (! Schema::hasColumn('venta_diaria_cajas', 'zelle')) {
                    $table->decimal('zelle', 14, 2)->default(0);
                    $table->decimal('binance', 14, 2)->default(0);
                    $table->decimal('mercantil_panama', 14, 2)->default(0);
                    $table->decimal('preventa', 14, 2)->default(0);
                    $table->decimal('pago_movil', 14, 2)->default(0);
                    $table->decimal('transferencias', 14, 2)->default(0);
                    $table->decimal('flaexpay', 14, 2)->default(0);
                    $table->decimal('krece', 14, 2)->default(0);
                }
            });

            if (Schema::hasColumn('venta_diaria_cajas', 'zelle_binance')) {
                DB::table('venta_diaria_cajas')->update([
                    'zelle' => DB::raw('zelle_binance'),
                    'pago_movil' => DB::raw('transf_pm'),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('venta_diaria_reportes') && Schema::hasColumn('venta_diaria_reportes', 'zelle')) {
            Schema::table('venta_diaria_reportes', function (Blueprint $table) {
                $table->dropColumn([
                    'zelle', 'binance', 'mercantil_panama', 'preventa',
                    'pago_movil_bs', 'transferencias_bs', 'flaexpay', 'krece',
                ]);
            });
        }

        if (Schema::hasTable('venta_diaria_cajas') && Schema::hasColumn('venta_diaria_cajas', 'zelle')) {
            Schema::table('venta_diaria_cajas', function (Blueprint $table) {
                $table->dropColumn([
                    'zelle', 'binance', 'mercantil_panama', 'preventa',
                    'pago_movil', 'transferencias', 'flaexpay', 'krece',
                ]);
            });
        }
    }
};
