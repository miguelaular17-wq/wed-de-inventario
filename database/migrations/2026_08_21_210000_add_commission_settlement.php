<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nomina_periodos', function (Blueprint $table) {
            if (! Schema::hasColumn('nomina_periodos', 'fecha_pago_comision')) {
                $table->date('fecha_pago_comision')->nullable()->after('fecha_fin');
            }
        });

        if (! Schema::hasTable('nomina_grupos_comision')) {
            Schema::create('nomina_grupos_comision', function (Blueprint $table) {
                $table->id();
                $table->string('grupo', 24);
                $table->string('categoria', 256);
                $table->string('categoria_normalizada', 256);
                $table->timestamps();
                $table->unique(['grupo', 'categoria_normalizada']);
            });
        }

        if (! Schema::hasTable('nomina_liquidaciones_comision')) {
            Schema::create('nomina_liquidaciones_comision', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('periodo_id');
                $table->unsignedBigInteger('empleado_id');
                $table->string('modo', 32);
                $table->decimal('base_total', 14, 2)->default(0);
                $table->decimal('base_telefonia', 14, 2)->default(0);
                $table->decimal('base_otros', 14, 2)->default(0);
                $table->decimal('pct_telefonia', 8, 4)->default(0);
                $table->decimal('pct_otros', 8, 4)->default(0);
                $table->decimal('comision_telefonia', 14, 2)->default(0);
                $table->decimal('comision_otros', 14, 2)->default(0);
                $table->decimal('comision_total', 14, 2)->default(0);
                $table->decimal('abonos', 14, 2)->default(0);
                $table->decimal('retencion_pct', 8, 4)->default(10);
                $table->decimal('retencion', 14, 2)->default(0);
                $table->decimal('descuentos', 14, 2)->default(0);
                $table->decimal('prestamos', 14, 2)->default(0);
                $table->decimal('total_pagar', 14, 2)->default(0);
                $table->date('fecha_pago')->nullable();
                $table->json('snapshot')->nullable();
                $table->timestamps();
                $table->unique(['periodo_id', 'empleado_id']);
            });
        }

        if (! Schema::hasTable('nomina_comision_abonos')) {
            Schema::create('nomina_comision_abonos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empleado_id');
                $table->date('fecha');
                $table->decimal('monto', 12, 2);
                $table->string('motivo')->nullable();
                $table->string('estado', 16)->default('PENDIENTE');
                $table->unsignedBigInteger('periodo_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nomina_comision_descuentos')) {
            Schema::create('nomina_comision_descuentos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empleado_id');
                $table->date('fecha');
                $table->string('tipo', 24);
                $table->decimal('monto', 12, 2);
                $table->string('motivo')->nullable();
                $table->string('estado', 16)->default('PENDIENTE');
                $table->unsignedBigInteger('periodo_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        $ahora = now();
        DB::table('nomina_config')->updateOrInsert(['clave' => 'comision_supervisor_pct'], ['valor' => '0.10', 'updated_at' => $ahora]);
        DB::table('nomina_config')->updateOrInsert(['clave' => 'comision_marketing_pct'], ['valor' => '0.10', 'updated_at' => $ahora]);
        DB::table('nomina_config')->updateOrInsert(['clave' => 'comision_telefonia_pct'], ['valor' => '0.20', 'updated_at' => $ahora]);
        DB::table('nomina_config')->updateOrInsert(['clave' => 'comision_otros_pct'], ['valor' => '1', 'updated_at' => $ahora]);
        DB::table('nomina_config')->updateOrInsert(['clave' => 'retencion_comision_pct'], ['valor' => '10', 'updated_at' => $ahora]);

        $this->sembrarCategorias();
    }

    public function down(): void
    {
        Schema::dropIfExists('nomina_comision_descuentos');
        Schema::dropIfExists('nomina_comision_abonos');
        Schema::dropIfExists('nomina_liquidaciones_comision');
        Schema::dropIfExists('nomina_grupos_comision');
        if (Schema::hasColumn('nomina_periodos', 'fecha_pago_comision')) {
            Schema::table('nomina_periodos', function (Blueprint $table) {
                $table->dropColumn('fecha_pago_comision');
            });
        }
    }

    private function sembrarCategorias(): void
    {
        $grupos = [
            'TELEFONIA' => [
                'ACCESORIOS TECNOLOGICOS', 'ARGOM', 'BLACKVIEW', 'ELECTRONICA', 'FORROS',
                'INVICTA', 'TELEFONIA', 'RELOJES INTELIGENTES', 'AMAZFIT',
            ],
            'OTROS' => [
                'ACCESORIOS DE VESTIR', 'ACCESORIOS ESTETICOS', 'ARTICULOS DE CABELLO',
                'ARTICULOS ESCOLARES', 'Accesorios de bebe', 'Accesorios de niño', 'BEBE',
                'BIOSEGURIDAD', 'BISUTERIA', 'Bodys', 'CARTERAS Y BOLSOS', 'COSMETICOS',
                'Conjuntos', 'DECORACION Y ARREGLOS', 'Decoracion', 'HIGIENE', 'HOGAR',
                'JUGUETERIA', 'Juguetes', 'LIQUIDACION', 'Lenceria', 'MAQUILLAJE ORIGINAL',
                'MOVISTAR', 'PERFUMERIA', 'PRODUCTOS CAPILARES', 'PRODUCTOS DE CONSUMO',
                'Pantalones', 'Perfumes', 'Sin categoría', 'TEXTILES', 'Uniformes',
            ],
        ];

        foreach ($grupos as $grupo => $categorias) {
            foreach ($categorias as $categoria) {
                $normalizada = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $categoria) ?? ''), 'UTF-8');
                DB::table('nomina_grupos_comision')->updateOrInsert(
                    ['grupo' => $grupo, 'categoria_normalizada' => $normalizada],
                    ['categoria' => $categoria, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }
    }
};
