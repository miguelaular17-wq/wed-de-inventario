<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pedidos_solicitados')) {
            return;
        }

        Schema::table('pedidos_solicitados', function (Blueprint $table) {
            $table->string('codigo', 255)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pedidos_solicitados')) {
            return;
        }

        Schema::table('pedidos_solicitados', function (Blueprint $table) {
            $table->string('codigo', 64)->change();
        });
    }
};
