<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('flujo_cajas') && ! Schema::hasColumn('flujo_cajas', 'conciliar_en')) {
            Schema::table('flujo_cajas', function (Blueprint $table) {
                // Primer día del mes en que se concilia un egreso del cierre de mes que el banco registra después (p. ej. el lunes 2).
                $table->date('conciliar_en')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('flujo_cajas') && Schema::hasColumn('flujo_cajas', 'conciliar_en')) {
            Schema::table('flujo_cajas', function (Blueprint $table) {
                $table->dropIndex(['conciliar_en']);
                $table->dropColumn('conciliar_en');
            });
        }
    }
};
