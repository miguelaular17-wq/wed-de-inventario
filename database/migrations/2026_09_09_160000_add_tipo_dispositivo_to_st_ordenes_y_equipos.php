<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('st_ordenes')) {
            Schema::table('st_ordenes', function (Blueprint $table) {
                if (! Schema::hasColumn('st_ordenes', 'tipo_dispositivo')) {
                    $table->string('tipo_dispositivo', 32)->default('celular');
                }
                if (! Schema::hasColumn('st_ordenes', 'atributos')) {
                    $table->json('atributos')->nullable();
                }
            });
        }

        if (Schema::hasTable('st_equipos')) {
            Schema::table('st_equipos', function (Blueprint $table) {
                if (! Schema::hasColumn('st_equipos', 'tipo_dispositivo')) {
                    $table->string('tipo_dispositivo', 32)->default('celular');
                }
                if (! Schema::hasColumn('st_equipos', 'atributos')) {
                    $table->json('atributos')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('st_ordenes')) {
            Schema::table('st_ordenes', function (Blueprint $table) {
                if (Schema::hasColumn('st_ordenes', 'tipo_dispositivo')) {
                    $table->dropColumn('tipo_dispositivo');
                }
                if (Schema::hasColumn('st_ordenes', 'atributos')) {
                    $table->dropColumn('atributos');
                }
            });
        }

        if (Schema::hasTable('st_equipos')) {
            Schema::table('st_equipos', function (Blueprint $table) {
                if (Schema::hasColumn('st_equipos', 'tipo_dispositivo')) {
                    $table->dropColumn('tipo_dispositivo');
                }
                if (Schema::hasColumn('st_equipos', 'atributos')) {
                    $table->dropColumn('atributos');
                }
            });
        }
    }
};
