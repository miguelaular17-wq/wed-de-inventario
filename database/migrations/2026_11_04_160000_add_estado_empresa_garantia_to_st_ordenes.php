<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('st_ordenes') && ! Schema::hasColumn('st_ordenes', 'estado_empresa_garantia')) {
            Schema::table('st_ordenes', function (Blueprint $table) {
                $table->string('estado_empresa_garantia', 32)->nullable()->after('empresa_envio_garantia');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('st_ordenes') && Schema::hasColumn('st_ordenes', 'estado_empresa_garantia')) {
            Schema::table('st_ordenes', function (Blueprint $table) {
                $table->dropColumn('estado_empresa_garantia');
            });
        }
    }
};
