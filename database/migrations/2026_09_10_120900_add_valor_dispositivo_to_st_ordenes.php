<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('st_ordenes')) {
            return;
        }

        Schema::table('st_ordenes', function (Blueprint $table) {
            $table->string('tipo_gestion', 32)->default('ST')->change();
            if (! Schema::hasColumn('st_ordenes', 'valor_dispositivo')) {
                $table->decimal('valor_dispositivo', 14, 2)->nullable()->after('rango_garantia');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('st_ordenes') || ! Schema::hasColumn('st_ordenes', 'valor_dispositivo')) {
            return;
        }

        Schema::table('st_ordenes', function (Blueprint $table) {
            $table->dropColumn('valor_dispositivo');
        });
    }
};
