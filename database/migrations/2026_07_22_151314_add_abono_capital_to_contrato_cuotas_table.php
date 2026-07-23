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
        Schema::table('contrato_cuotas', function (Blueprint $table) {
            $table->decimal('abono_capital', 14, 2)->default(0)->after('monto_pagado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contrato_cuotas', function (Blueprint $table) {
            $table->dropColumn('abono_capital');
        });
    }
};
