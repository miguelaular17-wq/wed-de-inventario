<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nomina_empleados')) {
            return;
        }

        if (! Schema::hasColumn('nomina_empleados', 'foto_url')) {
            Schema::table('nomina_empleados', function (Blueprint $table) {
                $table->string('foto_url', 500)->nullable()->after('telefono');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nomina_empleados') && Schema::hasColumn('nomina_empleados', 'foto_url')) {
            Schema::table('nomina_empleados', function (Blueprint $table) {
                $table->dropColumn('foto_url');
            });
        }
    }
};
