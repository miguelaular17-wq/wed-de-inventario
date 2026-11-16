<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mayor_config')) {
            Schema::create('mayor_config', function (Blueprint $table) {
                $table->string('clave', 80)->primary();
                $table->text('valor')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (Schema::hasTable('mayor_facturas')) {
            Schema::table('mayor_facturas', function (Blueprint $table) {
                if (! Schema::hasColumn('mayor_facturas', 'subtotal_usd')) {
                    $table->decimal('subtotal_usd', 14, 2)->nullable()->after('tasa_bcv');
                }
                if (! Schema::hasColumn('mayor_facturas', 'descuento_divisa_pct')) {
                    $table->decimal('descuento_divisa_pct', 8, 4)->default(0)->after('subtotal_usd');
                }
                if (! Schema::hasColumn('mayor_facturas', 'descuento_usd')) {
                    $table->decimal('descuento_usd', 14, 2)->default(0)->after('descuento_divisa_pct');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mayor_facturas')) {
            Schema::table('mayor_facturas', function (Blueprint $table) {
                foreach (['descuento_usd', 'descuento_divisa_pct', 'subtotal_usd'] as $col) {
                    if (Schema::hasColumn('mayor_facturas', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        Schema::dropIfExists('mayor_config');
    }
};
