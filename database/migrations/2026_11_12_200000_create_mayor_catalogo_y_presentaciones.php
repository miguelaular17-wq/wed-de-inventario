<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mayor_catalogo')) {
            Schema::create('mayor_catalogo', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('producto_id')->unique();
                $table->string('presentacion', 10)->default('unidad')->comment('unidad | caja | paquete');
                $table->unsignedInteger('unidades')->default(1)->comment('Unidades que trae cada caja o paquete');
                $table->decimal('precio_usd', 14, 2)->nullable()->comment('Precio al mayor de cada presentación; vacío = precio mayor × unidades');
                $table->decimal('costo_usd', 14, 4)->nullable()->comment('Costo por unidad; vacío = el de Suite');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('mayor_factura_items') && ! Schema::hasColumn('mayor_factura_items', 'unidades')) {
            Schema::table('mayor_factura_items', function (Blueprint $table) {
                $table->string('presentacion', 10)->default('unidad');
                $table->unsignedInteger('unidades_por')->default(1)->comment('Unidades de cada caja o paquete al facturar');
                $table->unsignedInteger('unidades')->nullable()->comment('cantidad × unidades_por: lo que sale del stock de JRZ');
            });
            DB::table('mayor_factura_items')->whereNull('unidades')->update(['unidades' => DB::raw('cantidad')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mayor_factura_items', 'unidades')) {
            Schema::table('mayor_factura_items', function (Blueprint $table) {
                $table->dropColumn(['presentacion', 'unidades_por', 'unidades']);
            });
        }
        Schema::dropIfExists('mayor_catalogo');
    }
};
