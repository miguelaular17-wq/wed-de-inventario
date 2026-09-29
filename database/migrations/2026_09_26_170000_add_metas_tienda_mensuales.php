<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venta_diaria_metas')) {
            return;
        }

        Schema::table('venta_diaria_metas', function (Blueprint $table) {
            if (! Schema::hasColumn('venta_diaria_metas', 'venta_historica')) {
                $table->decimal('venta_historica', 14, 2)->default(0)->after('meta_prod_domingo');
            }
            if (! Schema::hasColumn('venta_diaria_metas', 'venta_meta_mes')) {
                $table->decimal('venta_meta_mes', 14, 2)->default(0)->after('venta_historica');
            }
            if (! Schema::hasColumn('venta_diaria_metas', 'productos_meta_mes')) {
                $table->decimal('productos_meta_mes', 14, 2)->default(0)->after('venta_meta_mes');
            }
            if (! Schema::hasColumn('venta_diaria_metas', 'clientes_meta_mes')) {
                $table->decimal('clientes_meta_mes', 14, 2)->default(0)->after('productos_meta_mes');
            }
            if (! Schema::hasColumn('venta_diaria_metas', 'periodo_label')) {
                $table->string('periodo_label', 64)->nullable()->after('clientes_meta_mes');
            }
        });

        $mensuales = [
            'DORAL' => [243133.96, 316074.148, 25253.02, 6346.6],
            'VIRTUDES' => [224233.14, 291503.082, 21376.55, 6513],
            'CENTRO' => [198377.07, 257890.191, 24854.57, 7542.6],
            'ZAMORA' => [0, 153743.425, 13677.4, 3185.6],
            'SAMBIL' => [0, 128433.5, 1163, 754],
            'NUNES' => [3356, 14937, 60, 375.6],
            'MOVISTAR' => [0, 3500, 30, 100],
        ];

        $label = 'META MES '.mb_strtoupper(now()->locale('es')->translatedFormat('F Y'), 'UTF-8');

        foreach ($mensuales as $sede => [$hist, $meta, $prod, $cli]) {
            DB::table('venta_diaria_metas')->where('sede', $sede)->update([
                'venta_historica' => $hist,
                'venta_meta_mes' => $meta,
                'productos_meta_mes' => $prod,
                'clientes_meta_mes' => $cli,
                'periodo_label' => $label,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('venta_diaria_metas')) {
            return;
        }

        Schema::table('venta_diaria_metas', function (Blueprint $table) {
            foreach (['venta_historica', 'venta_meta_mes', 'productos_meta_mes', 'clientes_meta_mes', 'periodo_label'] as $col) {
                if (Schema::hasColumn('venta_diaria_metas', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
