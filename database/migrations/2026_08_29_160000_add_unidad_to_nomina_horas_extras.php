<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nomina_horas_extras') || Schema::hasColumn('nomina_horas_extras', 'unidad')) {
            return;
        }

        Schema::table('nomina_horas_extras', function (Blueprint $table) {
            $table->string('unidad', 8)->default('HORAS')->after('horas');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('nomina_horas_extras') || ! Schema::hasColumn('nomina_horas_extras', 'unidad')) {
            return;
        }

        Schema::table('nomina_horas_extras', function (Blueprint $table) {
            $table->dropColumn('unidad');
        });
    }
};
