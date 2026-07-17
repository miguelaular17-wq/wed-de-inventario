<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conciliacion_lineas', function (Blueprint $table) {
            // Tipo de movimiento: 'cargo' (débito) o 'abono' (crédito)
            $table->string('tipo')->nullable()->default('abono')->after('estado');
            // session_id para agrupar la sesión de carga
            if (!Schema::hasColumn('conciliacion_lineas', 'session_id')) {
                $table->string('session_id')->nullable()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('conciliacion_lineas', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
