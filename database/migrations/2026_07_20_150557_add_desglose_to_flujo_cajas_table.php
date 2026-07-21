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
        Schema::table('flujo_cajas', function (Blueprint $table) {
            $table->json('desglose')->nullable()->after('comprobante_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flujo_cajas', function (Blueprint $table) {
            $table->dropColumn('desglose');
        });
    }
};
