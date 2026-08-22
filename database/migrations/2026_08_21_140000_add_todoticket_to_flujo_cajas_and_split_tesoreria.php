<?php

use App\Services\BankReconciliationMatcher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flujo_cajas', function (Blueprint $table) {
            $table->boolean('es_todoticket')->default(false)->after('tipo_gasto');
            $table->json('detalle_todoticket')->nullable()->after('es_todoticket');
        });

        if (Schema::hasTable('tesoreria_ingresos')) {
            $matcher = new BankReconciliationMatcher();
            $rows = DB::table('tesoreria_ingresos')->where('tipo', 'punto_venta')->get();
            foreach ($rows as $row) {
                [$banco, $titular] = $matcher->partesCuenta($row->banco, $row->titular);
                if ($banco === $row->banco && $titular === (string) $row->titular) {
                    continue;
                }
                DB::table('tesoreria_ingresos')->where('id', $row->id)->update([
                    'banco' => $banco,
                    'titular' => $titular !== '' ? $titular : $row->titular,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('flujo_cajas', function (Blueprint $table) {
            $table->dropColumn(['es_todoticket', 'detalle_todoticket']);
        });
    }
};
