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
            $table->unsignedBigInteger('sede_id')->nullable();
            $table->string('codigo')->nullable();
            $table->string('cliente')->nullable();
            $table->decimal('saldo_bs', 15, 2)->default(0);
            $table->decimal('saldo_usd', 15, 2)->default(0);
            $table->date('fecha_emision')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cobranzas', function (Blueprint $table) {
            $table->dropColumn(['sede_id', 'codigo', 'cliente', 'saldo_bs', 'saldo_usd', 'fecha_emision']);
        });
    }
};
