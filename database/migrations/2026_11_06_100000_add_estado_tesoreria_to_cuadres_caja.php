<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tesorería procesa la caja (se puede reabrir) o la cierra con un motivo (definitivo).
 * conciliado_at sigue marcando que Tesorería ya la revisó: el sincronizador no borra esos días.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cuadres_caja') || Schema::hasColumn('cuadres_caja', 'estado_tesoreria')) {
            return;
        }

        Schema::table('cuadres_caja', function (Blueprint $t) {
            $t->string('estado_tesoreria', 12)->default('pendiente');
            $t->string('motivo_cierre', 64)->nullable();
            $t->timestamp('reabierto_at')->nullable();
            $t->unsignedBigInteger('reabierto_por')->nullable();
            $t->index('estado_tesoreria');
        });

        DB::table('cuadres_caja')->whereNotNull('conciliado_at')->update(['estado_tesoreria' => 'procesado']);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('cuadres_caja', 'estado_tesoreria')) {
            return;
        }

        Schema::table('cuadres_caja', function (Blueprint $t) {
            $t->dropIndex(['estado_tesoreria']);
            $t->dropColumn(['estado_tesoreria', 'motivo_cierre', 'reabierto_at', 'reabierto_por']);
        });
    }
};
