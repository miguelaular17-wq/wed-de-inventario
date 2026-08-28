<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nomina_empresas')) {
            Schema::create('nomina_empresas', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 32)->unique();
                $table->string('nombre', 160);
                $table->string('estado', 16)->default('ACTIVO');
                $table->timestamps();
            });
        }

        $now = now();
        foreach ([
            ['codigo' => 'J401722296', 'nombre' => 'INVERSIONES DORAL PARAGUANÁ, C.A.'],
            ['codigo' => 'J409254852', 'nombre' => 'LNACEH SPORT, C.A.'],
            ['codigo' => 'J501653879', 'nombre' => 'NUNES STORE, C.A.'],
            ['codigo' => 'J501653895', 'nombre' => 'GRUPO JRZ TECH ELECTRONICS, C.A.'],
            ['codigo' => 'J412919512', 'nombre' => 'EURONISSI, C.A.'],
        ] as $fila) {
            $existe = DB::table('nomina_empresas')->where('codigo', $fila['codigo'])->exists();
            if (! $existe) {
                DB::table('nomina_empresas')->insert($fila + [
                    'estado' => 'ACTIVO',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('nomina_empleados') && ! Schema::hasColumn('nomina_empleados', 'empresa_id')) {
            Schema::table('nomina_empleados', function (Blueprint $table) {
                $table->unsignedBigInteger('empresa_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nomina_empleados') && Schema::hasColumn('nomina_empleados', 'empresa_id')) {
            Schema::table('nomina_empleados', function (Blueprint $table) {
                $table->dropColumn('empresa_id');
            });
        }
        Schema::dropIfExists('nomina_empresas');
    }
};
