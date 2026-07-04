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
        Schema::table('cobranzas', function (Blueprint $table) {
            $table->decimal('meses_antiguedad', 8, 2)->default(0)->after('saldo_usd');
            $table->string('estatus')->default('RECIENTE')->after('meses_antiguedad');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cobranzas', function (Blueprint $table) {
            $table->dropColumn(['meses_antiguedad', 'estatus']);
        });
    }
};
