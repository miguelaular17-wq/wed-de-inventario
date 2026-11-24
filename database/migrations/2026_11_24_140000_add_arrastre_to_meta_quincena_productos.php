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
            // Permite la misma meta arrastrada al responsable aunque el producto
            // también se asigne a otra persona en la quincena nueva.
            try {
                $table->dropUnique('meta_quincena_prod_sede_uq');
            } catch (\Throwable) {
                // Índice ya eliminado o nombre distinto según motor.
            }

            if (! Schema::hasColumn('meta_quincena_productos', 'cantidad_arrastre')) {
                $table->decimal('cantidad_arrastre', 18, 4)->default(0)->after('cantidad_inicial');
            }
            if (! Schema::hasColumn('meta_quincena_productos', 'arrastra_de_meta_id')) {
                $table->unsignedBigInteger('arrastra_de_meta_id')->nullable()->after('cantidad_arrastre');
                $table->unique('arrastra_de_meta_id', 'meta_quincena_arrastre_origen_uq');
            }

            $table->index(['producto_id', 'sede', 'quincena_inicio'], 'meta_quincena_prod_sede_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('meta_quincena_productos')) {
            return;
        }

        Schema::table('meta_quincena_productos', function (Blueprint $table) {
            try {
                $table->dropUnique('meta_quincena_arrastre_origen_uq');
            } catch (\Throwable) {
            }
            try {
                $table->dropIndex('meta_quincena_prod_sede_idx');
            } catch (\Throwable) {
            }
            if (Schema::hasColumn('meta_quincena_productos', 'arrastra_de_meta_id')) {
                $table->dropColumn('arrastra_de_meta_id');
            }
            if (Schema::hasColumn('meta_quincena_productos', 'cantidad_arrastre')) {
                $table->dropColumn('cantidad_arrastre');
            }
            try {
                $table->unique(['producto_id', 'sede', 'quincena_inicio'], 'meta_quincena_prod_sede_uq');
            } catch (\Throwable) {
            }
        });
    }
};
