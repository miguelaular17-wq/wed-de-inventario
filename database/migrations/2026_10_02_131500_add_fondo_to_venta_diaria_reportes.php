<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venta_diaria_reportes')) {
            return;
        }

        Schema::table('venta_diaria_reportes', function (Blueprint $table) {
            if (! Schema::hasColumn('venta_diaria_reportes', 'fondo_bs')) {
                $table->decimal('fondo_bs', 14, 2)->default(0);
            }
            if (! Schema::hasColumn('venta_diaria_reportes', 'fondo_divisas')) {
                $table->decimal('fondo_divisas', 14, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('venta_diaria_reportes') || ! Schema::hasColumn('venta_diaria_reportes', 'fondo_bs')) {
            return;
        }

        Schema::table('venta_diaria_reportes', function (Blueprint $table) {
            $table->dropColumn(['fondo_bs', 'fondo_divisas']);
        });
    }
};
