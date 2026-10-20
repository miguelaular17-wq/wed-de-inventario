<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conciliacion_cierres') || Schema::hasColumn('conciliacion_cierres', 'sin_movimientos')) {
            return;
        }

        Schema::table('conciliacion_cierres', function (Blueprint $table) {
            $table->boolean('sin_movimientos')->default(false)->after('fecha_hasta');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('conciliacion_cierres', 'sin_movimientos')) {
            Schema::table('conciliacion_cierres', function (Blueprint $table) {
                $table->dropColumn('sin_movimientos');
            });
        }
    }
};
