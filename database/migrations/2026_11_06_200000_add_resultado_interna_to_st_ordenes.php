<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resultado de una reparación interna (sin reparación, para repuesto o reparado) y el técnico que la reparó.
 * Va aparte del reconocimiento de la marca: las internas siguen saliendo en el reporte por marca.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('st_ordenes') || Schema::hasColumn('st_ordenes', 'resultado_interna')) {
            return;
        }

        Schema::table('st_ordenes', function (Blueprint $t) {
            $t->string('resultado_interna', 24)->nullable();
            $t->unsignedBigInteger('reparado_por')->nullable();
            $t->index('resultado_interna');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('st_ordenes', 'resultado_interna')) {
            return;
        }

        Schema::table('st_ordenes', function (Blueprint $t) {
            $t->dropIndex(['resultado_interna']);
            $t->dropColumn(['resultado_interna', 'reparado_por']);
        });
    }
};
