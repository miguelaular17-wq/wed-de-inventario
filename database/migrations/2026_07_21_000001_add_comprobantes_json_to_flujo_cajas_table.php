<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flujo_cajas', function (Blueprint $table) {
            $table->json('comprobantes')->nullable()->after('comprobante_url');
        });
    }

    public function down(): void
    {
        Schema::table('flujo_cajas', function (Blueprint $table) {
            $table->dropColumn('comprobantes');
        });
    }
};
