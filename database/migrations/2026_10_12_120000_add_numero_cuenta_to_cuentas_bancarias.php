<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cuentas_bancarias')) {
            return;
        }

        if (! Schema::hasColumn('cuentas_bancarias', 'numero_cuenta')) {
            Schema::table('cuentas_bancarias', function (Blueprint $table) {
                $table->string('numero_cuenta', 32)->nullable();
            });
        }

        foreach (config('cuentas_bancarias_numeros', []) as [$banco, $titular, $numero]) {
            $existe = DB::table('cuentas_bancarias')
                ->where('banco', $banco)
                ->where('titular', $titular)
                ->exists();

            if (! $existe) {
                DB::table('cuentas_bancarias')->insert([
                    'banco' => $banco,
                    'titular' => $titular,
                    'numero_cuenta' => $numero,
                    'categoria_reporte' => 'BANCA NACIONAL - ALTO Y MEDIANO MOVIMIENTO',
                    'mostrar_en_principal' => false,
                    'orden' => 900,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                continue;
            }

            DB::table('cuentas_bancarias')
                ->where('banco', $banco)
                ->where('titular', $titular)
                ->update([
                    'numero_cuenta' => $numero,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cuentas_bancarias') && Schema::hasColumn('cuentas_bancarias', 'numero_cuenta')) {
            Schema::table('cuentas_bancarias', function (Blueprint $table) {
                $table->dropColumn('numero_cuenta');
            });
        }
    }
};
