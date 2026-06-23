<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('requisiciones_manuales', function (Blueprint $table) {
                $table->dropUnique(['sede_local', 'codigo']);
            });
        } catch (\Exception $e) {
            // Already dropped or doesn't exist
        }

        try {
            Schema::table('requisiciones_manuales', function (Blueprint $table) {
                $table->unique(['sede_local', 'codigo', 'sede_origen'], 'req_manuales_sede_codigo_origen_unique');
            });
        } catch (\Exception $e) {
            // Already exists
        }
    }

    public function down(): void
    {
        Schema::table('requisiciones_manuales', function (Blueprint $table) {
            $table->dropUnique('req_manuales_sede_codigo_origen_unique');
            $table->unique(['sede_local', 'codigo']);
        });
    }
};
