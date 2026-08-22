<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nomina_empleados') || Schema::hasColumn('nomina_empleados', 'codigo_vendedor')) {
            return;
        }

        Schema::table('nomina_empleados', function (Blueprint $table) {
            $table->string('codigo_vendedor', 255)->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('nomina_empleados') && Schema::hasColumn('nomina_empleados', 'codigo_vendedor')) {
            Schema::table('nomina_empleados', function (Blueprint $table) {
                $table->dropColumn('codigo_vendedor');
            });
        }
    }
};
