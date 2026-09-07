<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('st_ordenes')) {
            return;
        }

        Schema::table('st_ordenes', function (Blueprint $table) {
            if (! Schema::hasColumn('st_ordenes', 'inspeccion_recepcion')) {
                $table->json('inspeccion_recepcion')->nullable();
            }
            if (! Schema::hasColumn('st_ordenes', 'firma_recepcion_cliente')) {
                $table->longText('firma_recepcion_cliente')->nullable();
            }
            if (! Schema::hasColumn('st_ordenes', 'firma_recepcion_empleado')) {
                $table->longText('firma_recepcion_empleado')->nullable();
            }
            if (! Schema::hasColumn('st_ordenes', 'conformidad_trabajo')) {
                $table->text('conformidad_trabajo')->nullable();
            }
            if (! Schema::hasColumn('st_ordenes', 'firma_conformidad_cliente')) {
                $table->longText('firma_conformidad_cliente')->nullable();
            }
            if (! Schema::hasColumn('st_ordenes', 'firma_conformidad_empleado')) {
                $table->longText('firma_conformidad_empleado')->nullable();
            }
            if (! Schema::hasColumn('st_ordenes', 'conformidad_at')) {
                $table->timestamp('conformidad_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('st_ordenes')) {
            return;
        }

        Schema::table('st_ordenes', function (Blueprint $table) {
            foreach ([
                'inspeccion_recepcion',
                'firma_recepcion_cliente',
                'firma_recepcion_empleado',
                'conformidad_trabajo',
                'firma_conformidad_cliente',
                'firma_conformidad_empleado',
                'conformidad_at',
            ] as $column) {
                if (Schema::hasColumn('st_ordenes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
