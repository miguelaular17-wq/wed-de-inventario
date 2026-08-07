<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tesoreria_ingresos', function (Blueprint $table) {
            $table->string('titular')->nullable()->after('banco');
            $table->boolean('es_conciliado')->default(false)->after('lote_referencia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tesoreria_ingresos', function (Blueprint $table) {
            $table->dropColumn(['titular', 'es_conciliado']);
        });
    }
};
