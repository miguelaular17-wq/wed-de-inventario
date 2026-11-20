<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nomina_abonos_sueldo')) {
            return;
        }

        if (! Schema::hasColumn('nomina_abonos_sueldo', 'txt_exportado_at')) {
            Schema::table('nomina_abonos_sueldo', function (Blueprint $table) {
                $table->timestamp('txt_exportado_at')->nullable()->after('created_by');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nomina_abonos_sueldo') && Schema::hasColumn('nomina_abonos_sueldo', 'txt_exportado_at')) {
            Schema::table('nomina_abonos_sueldo', function (Blueprint $table) {
                $table->dropColumn('txt_exportado_at');
            });
        }
    }
};
