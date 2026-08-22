<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nomina_sedes')) {
            return;
        }

        Schema::table('nomina_sedes', function (Blueprint $table) {
            if (! Schema::hasColumn('nomina_sedes', 'tipo')) {
                $table->string('tipo', 16)->default('SEDE');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('nomina_sedes') || ! Schema::hasColumn('nomina_sedes', 'tipo')) {
            return;
        }

        Schema::table('nomina_sedes', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
