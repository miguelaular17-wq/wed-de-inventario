<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tesoreria_cajas')) {
            Schema::create('tesoreria_cajas', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 32)->unique();
                $table->string('nombre', 120);
                $table->string('moneda', 8)->default('VES');
                $table->decimal('saldo', 18, 2)->default(0);
                $table->unsignedInteger('orden')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tesoreria_caja_movimientos')) {
            Schema::create('tesoreria_caja_movimientos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('caja_id');
                $table->string('tipo', 16); // ingreso | egreso
                $table->date('fecha');
                $table->decimal('monto', 18, 2);
                $table->string('concepto', 300);
                $table->string('referencia', 120)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();

                $table->index(['caja_id', 'fecha']);
                $table->index('tipo');
            });
        }

        $ahora = now();
        foreach ([
            ['codigo' => 'CAJA_BS', 'nombre' => 'Caja Principal Bolívares', 'moneda' => 'VES', 'orden' => 1],
            ['codigo' => 'CAJA_USD', 'nombre' => 'Caja Principal Divisas', 'moneda' => 'USD', 'orden' => 2],
        ] as $caja) {
            $existe = DB::table('tesoreria_cajas')->where('codigo', $caja['codigo'])->exists();
            if (! $existe) {
                DB::table('tesoreria_cajas')->insert($caja + [
                    'saldo' => 0,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tesoreria_caja_movimientos');
        Schema::dropIfExists('tesoreria_cajas');
    }
};
