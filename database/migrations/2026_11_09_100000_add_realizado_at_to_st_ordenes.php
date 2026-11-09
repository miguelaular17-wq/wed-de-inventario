<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha en que el servicio quedó realizado (hoja de conformidad o entrega al cliente). Con ella la orden sale
 * del listado de órdenes y pasa a «Servicios realizados». Las ya entregadas o con conformidad se marcan con su fecha.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('st_ordenes') || Schema::hasColumn('st_ordenes', 'realizado_at')) {
            return;
        }

        Schema::table('st_ordenes', function (Blueprint $t) {
            $t->timestamp('realizado_at')->nullable();
            $t->index('realizado_at');
        });

        if (Schema::hasColumn('st_ordenes', 'conformidad_at')) {
            DB::table('st_ordenes')->whereNotNull('conformidad_at')->update(['realizado_at' => DB::raw('conformidad_at')]);
        }
        DB::table('st_ordenes')->whereNull('realizado_at')->where('estado', 'entregado')
            ->update(['realizado_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('st_ordenes', 'realizado_at')) {
            return;
        }

        Schema::table('st_ordenes', function (Blueprint $t) {
            $t->dropIndex(['realizado_at']);
            $t->dropColumn('realizado_at');
        });
    }
};
