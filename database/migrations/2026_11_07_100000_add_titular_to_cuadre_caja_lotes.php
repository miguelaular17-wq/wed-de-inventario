<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada lote del punto de venta lleva su titular (JRZ, DORAL, LNACEH…), además del banco.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cuadre_caja_lotes') || Schema::hasColumn('cuadre_caja_lotes', 'titular')) {
            return;
        }

        Schema::table('cuadre_caja_lotes', function (Blueprint $t) {
            $t->string('titular', 32)->default('');
        });
        if (Schema::hasTable('tesoreria_ingresos')) {
            DB::table('cuadre_caja_lotes')
                ->whereNotNull('tesoreria_ingreso_id')
                ->update(['titular' => DB::raw('COALESCE((SELECT UPPER(i.titular) FROM tesoreria_ingresos i WHERE i.id = cuadre_caja_lotes.tesoreria_ingreso_id), \'\')')]);
        }
        Schema::table('cuadre_caja_lotes', function (Blueprint $t) {
            $t->dropUnique('cuadre_caja_lotes_linea_banco_unique');
            $t->unique(['cuadre_caja_id', 'codigo_metodo', 'codigo_cuenta', 'banco', 'titular', 'lote'], 'cuadre_caja_lotes_linea_cuenta_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('cuadre_caja_lotes', 'titular')) {
            return;
        }

        Schema::table('cuadre_caja_lotes', function (Blueprint $t) {
            $t->dropUnique('cuadre_caja_lotes_linea_cuenta_unique');
            $t->unique(['cuadre_caja_id', 'codigo_metodo', 'codigo_cuenta', 'banco', 'lote'], 'cuadre_caja_lotes_linea_banco_unique');
        });
        Schema::table('cuadre_caja_lotes', function (Blueprint $t) {
            $t->dropColumn('titular');
        });
    }
};
