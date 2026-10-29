<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tesoreria_ingresos') && ! Schema::hasColumn('tesoreria_ingresos', 'conciliar_en')) {
            Schema::table('tesoreria_ingresos', function (Blueprint $table) {
                // Primer día del mes en que se concilia un lote del cierre de mes (el banco lo abona el día 1).
                $table->date('conciliar_en')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tesoreria_ingresos') && Schema::hasColumn('tesoreria_ingresos', 'conciliar_en')) {
            Schema::table('tesoreria_ingresos', function (Blueprint $table) {
                $table->dropIndex(['conciliar_en']);
                $table->dropColumn('conciliar_en');
            });
        }
    }
};
