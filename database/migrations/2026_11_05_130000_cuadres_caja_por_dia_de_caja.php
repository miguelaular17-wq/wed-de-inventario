<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El cuadre deja de esperar al cierre de Suite: es un día de una caja con una cajera, armado con los
 * cobros de cajas_movimientos. id_cierre queda solo como referencia y es nulo hasta que Suite registra el cierre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cuadres_caja')) {
            return;
        }

        DB::table('cuadres_caja')->whereNull('codigo_caja')->update(['codigo_caja' => '']);
        DB::table('cuadres_caja')->whereNull('usuario')->update(['usuario' => '']);

        $duplicados = DB::table('cuadres_caja')
            ->select('sede', 'fecha_cierre', 'codigo_caja', 'usuario')
            ->selectRaw('MIN(id) as conservar')
            ->groupBy('sede', 'fecha_cierre', 'codigo_caja', 'usuario')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        foreach ($duplicados as $d) {
            DB::table('cuadres_caja')
                ->where(['sede' => $d->sede, 'fecha_cierre' => $d->fecha_cierre, 'codigo_caja' => $d->codigo_caja, 'usuario' => $d->usuario])
                ->where('id', '<>', $d->conservar)
                ->delete();
        }

        Schema::table('cuadres_caja', function (Blueprint $t) {
            $t->dropUnique(['sede', 'id_cierre']);
        });
        Schema::table('cuadres_caja', function (Blueprint $t) {
            $t->unsignedBigInteger('id_cierre')->nullable()->change();
            $t->string('codigo_caja', 32)->default('')->nullable(false)->change();
            $t->string('usuario', 32)->default('')->nullable(false)->change();
            $t->unique(['sede', 'fecha_cierre', 'codigo_caja', 'usuario'], 'cuadres_caja_dia_caja_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cuadres_caja')) {
            return;
        }

        DB::table('cuadres_caja')->whereNull('id_cierre')->delete();
        Schema::table('cuadres_caja', function (Blueprint $t) {
            $t->dropUnique('cuadres_caja_dia_caja_unique');
        });
        Schema::table('cuadres_caja', function (Blueprint $t) {
            $t->unsignedBigInteger('id_cierre')->nullable(false)->change();
            $t->string('codigo_caja', 32)->nullable()->default(null)->change();
            $t->string('usuario', 32)->nullable()->default(null)->change();
            $t->unique(['sede', 'id_cierre']);
        });
    }
};
