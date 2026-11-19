<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meta_quincena_productos')) {
            return;
        }

        Schema::table('meta_quincena_productos', function (Blueprint $table) {
            if (! Schema::hasColumn('meta_quincena_productos', 'valor_unitario')) {
                $table->decimal('valor_unitario', 12, 2)->nullable()->after('cantidad_inicial');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('meta_quincena_productos')) {
            return;
        }

        Schema::table('meta_quincena_productos', function (Blueprint $table) {
            if (Schema::hasColumn('meta_quincena_productos', 'valor_unitario')) {
                $table->dropColumn('valor_unitario');
            }
        });
    }
};
