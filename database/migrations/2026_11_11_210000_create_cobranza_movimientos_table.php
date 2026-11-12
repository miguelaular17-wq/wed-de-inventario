<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cobranza_movimientos')) {
            Schema::create('cobranza_movimientos', function (Blueprint $table) {
                $table->id();
                $table->date('fecha');
                $table->string('tipo', 10); // ingreso | egreso
                $table->string('ambito', 20)->default('cobranza'); // cobranza | contratos | patrimonial | general
                $table->string('banco', 120);
                $table->string('titular', 120)->nullable();
                $table->string('categoria_cuenta', 120)->nullable();
                $table->decimal('monto_usd', 15, 2)->default(0);
                $table->decimal('tasa_cambio', 15, 4)->default(0);
                $table->decimal('monto_bs', 15, 2)->default(0);
                $table->string('referencia', 120)->nullable();
                $table->string('concepto', 255);
                $table->string('contraparte', 255)->nullable()->comment('Quién paga (ingreso) o a quién se paga (egreso)');
                $table->text('observaciones')->nullable();
                // Ingreso y egreso del mismo dinero registrados juntos (cruce).
                $table->string('cruce_id', 36)->nullable()->index();
                // Factura, cuota, alquiler o reserva a la que pertenece (fases siguientes).
                $table->string('vinculo_tipo', 40)->nullable();
                $table->unsignedBigInteger('vinculo_id')->nullable();
                // Copia oculta en flujo_cajas para que la conciliación bancaria lo empareje.
                $table->unsignedBigInteger('flujo_caja_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();

                $table->index(['fecha', 'tipo']);
                $table->index(['vinculo_tipo', 'vinculo_id']);
            });
        }

        if (Schema::hasTable('flujo_cajas') && ! Schema::hasColumn('flujo_cajas', 'origen')) {
            Schema::table('flujo_cajas', function (Blueprint $table) {
                $table->string('origen', 20)->nullable()->index();
            });
        }

        // Cuentas de cruce (sin banco real) que también ve Finanzas: Banco Cruzado para avances, Banco Cruce Cobranza para Cobranza.
        if (Schema::hasTable('finanzas_catalogos')) {
            foreach (['BANCO CRUZADO' => 'AVANCES', 'BANCO CRUCE COBRANZA' => 'COBRANZA'] as $banco => $titular) {
                $existe = DB::table('finanzas_catalogos')
                    ->where('tipo', 'cuenta')
                    ->whereRaw('UPPER(TRIM(banco)) = ?', [$banco])
                    ->exists();
                if (! $existe) {
                    DB::table('finanzas_catalogos')->insert([
                        'tipo' => 'cuenta',
                        'banco' => $banco,
                        'nombre' => $titular,
                        'categoria' => 'CUENTAS DE CRUCE',
                        'activo' => true,
                        'origen' => 'manual',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('finanzas_catalogos')) {
            DB::table('finanzas_catalogos')
                ->where('tipo', 'cuenta')
                ->whereIn('banco', ['BANCO CRUZADO', 'BANCO CRUCE COBRANZA'])
                ->whereIn('nombre', ['AVANCES', 'COBRANZA'])
                ->delete();
        }
        if (Schema::hasTable('flujo_cajas') && Schema::hasColumn('flujo_cajas', 'origen')) {
            Schema::table('flujo_cajas', function (Blueprint $table) {
                $table->dropIndex(['origen']);
                $table->dropColumn('origen');
            });
        }
        Schema::dropIfExists('cobranza_movimientos');
    }
};
