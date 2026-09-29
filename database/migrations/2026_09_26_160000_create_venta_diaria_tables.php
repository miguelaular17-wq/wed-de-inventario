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
            Schema::create('venta_diaria_metas', function (Blueprint $table) {
                $table->id();
                $table->string('sede', 32)->unique();
                $table->decimal('meta_venta_lv_sab', 14, 4)->default(0);
                $table->decimal('meta_venta_domingo', 14, 4)->default(0);
                $table->decimal('meta_prod_lv_sab', 14, 4)->default(0);
                $table->decimal('meta_prod_domingo', 14, 4)->default(0);
                $table->timestamps();
            });

            $now = now();
            $metas = [
                ['DORAL', 11346.251466667, 5267.9024666667, 906.51866666667, 420.88366666667],
                ['VIRTUDES', 10464.2132, 4858.3847, 767.36333333333, 356.27583333333],
                ['CENTRO', 9257.5966, 4298.16985, 892.21533333333, 414.24283333333],
                ['ZAMORA', 5518.9947435897, 2562.3904166667, 490.98358974359, 227.95666666667],
                ['SAMBIL', 4610.4333333333, 2140.5583333333, 41.748717948718, 19.383333333333],
                ['NUNES', 536.2, 248.95, 2.1538461538462, 1],
                ['MOVISTAR', 125.64102564103, 58.333333333333, 1.0769230769231, 0.5],
            ];
            foreach ($metas as [$sede, $vl, $vd, $pl, $pd]) {
                DB::table('venta_diaria_metas')->insert([
                    'sede' => $sede,
                    'meta_venta_lv_sab' => $vl,
                    'meta_venta_domingo' => $vd,
                    'meta_prod_lv_sab' => $pl,
                    'meta_prod_domingo' => $pd,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (! Schema::hasTable('venta_diaria_reportes')) {
            Schema::create('venta_diaria_reportes', function (Blueprint $table) {
                $table->id();
                $table->string('sede', 32);
                $table->date('fecha');
                $table->decimal('tasa', 14, 4)->default(0);
                $table->decimal('divisas_efectivo', 14, 2)->default(0);
                $table->decimal('efectivo_bs', 14, 2)->default(0);
                $table->decimal('punto_venta_bs', 14, 2)->default(0);
                $table->decimal('transf_pm_bs', 14, 2)->default(0);
                $table->decimal('zelle_binance', 14, 2)->default(0);
                $table->decimal('cashea', 14, 2)->default(0);
                $table->decimal('abonos', 14, 2)->default(0);
                $table->decimal('total_creditos', 14, 2)->default(0);
                $table->decimal('z_fiscal_bs', 14, 2)->default(0);
                $table->decimal('productos_vendidos', 12, 2)->default(0);
                $table->decimal('deliverys_pendientes', 14, 2)->default(0);
                $table->text('observaciones')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique(['sede', 'fecha']);
                $table->index('fecha');
            });
        }

        if (! Schema::hasTable('venta_diaria_cajas')) {
            Schema::create('venta_diaria_cajas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('reporte_id');
                $table->string('nombre', 120);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->decimal('efectivo_usd', 14, 2)->default(0);
                $table->decimal('efectivo_bs', 14, 2)->default(0);
                $table->decimal('punto_venta', 14, 2)->default(0);
                $table->decimal('transf_pm', 14, 2)->default(0);
                $table->decimal('zelle_binance', 14, 2)->default(0);
                $table->decimal('cashea', 14, 2)->default(0);
                $table->decimal('fact_credito', 14, 2)->default(0);
                $table->decimal('abonos', 14, 2)->default(0);
                $table->timestamps();

                $table->index('reporte_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_diaria_cajas');
        Schema::dropIfExists('venta_diaria_reportes');
        Schema::dropIfExists('venta_diaria_metas');
    }
};
