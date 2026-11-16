<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('requisiciones_manuales')) {
            return;
        }

        Schema::table('requisiciones_manuales', function (Blueprint $table) {
            $table->text('codigo')->change();
            $table->text('producto')->default('')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('requisiciones_manuales')) {
            return;
        }

        Schema::table('requisiciones_manuales', function (Blueprint $table) {
            $table->string('codigo', 64)->change();
            $table->string('producto')->default('')->change();
        });
    }
};
