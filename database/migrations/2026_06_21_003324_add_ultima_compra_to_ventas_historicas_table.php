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
        // Add the new column without schema prefix for the connection to handle
        Schema::connection('pgsql')->table('ventas_historicas', function (Blueprint $table) {
            if (! Schema::connection('pgsql')->hasColumn('ventas_historicas', 'ultima_compra')) {
                $table->date('ultima_compra')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql')->table('ventas_historicas', function (Blueprint $table) {
            $table->dropColumn('ultima_compra');
        });
    }
};
