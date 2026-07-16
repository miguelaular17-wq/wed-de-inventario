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
        Schema::table('gasto_fijo_config', function (Blueprint $table) {
            $table->decimal('costo', 10, 2)->nullable()->after('fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gasto_fijo_config', function (Blueprint $table) {
            $table->dropColumn('costo');
        });
    }
};
