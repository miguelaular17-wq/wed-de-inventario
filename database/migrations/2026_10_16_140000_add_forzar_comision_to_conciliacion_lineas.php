<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conciliacion_lineas')) {
            return;
        }

        Schema::table('conciliacion_lineas', function (Blueprint $table) {
            if (! Schema::hasColumn('conciliacion_lineas', 'forzar_comision')) {
                $table->boolean('forzar_comision')->default(false)->after('estado');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('conciliacion_lineas')) {
            return;
        }

        Schema::table('conciliacion_lineas', function (Blueprint $table) {
            if (Schema::hasColumn('conciliacion_lineas', 'forzar_comision')) {
                $table->dropColumn('forzar_comision');
            }
        });
    }
};
