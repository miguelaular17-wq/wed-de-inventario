<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('producto_ediciones')) {
            return;
        }
        if (! Schema::hasColumn('producto_ediciones', 'lote_inicial')) {
            Schema::table('producto_ediciones', function (Blueprint $table) {
                $table->unsignedInteger('lote_inicial')->nullable()->after('precio_mayor')
                    ->comment('Cantidad mínima de compra al mayor');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('producto_ediciones') && Schema::hasColumn('producto_ediciones', 'lote_inicial')) {
            Schema::table('producto_ediciones', function (Blueprint $table) {
                $table->dropColumn('lote_inicial');
            });
        }
    }
};
