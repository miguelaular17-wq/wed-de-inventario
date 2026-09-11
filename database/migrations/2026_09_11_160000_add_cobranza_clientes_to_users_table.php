<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'cobranza_clientes')) {
                $table->string('cobranza_clientes', 16)->default('todos')->after('ver_publicidad_equipo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'cobranza_clientes')) {
                $table->dropColumn('cobranza_clientes');
            }
        });
    }
};
