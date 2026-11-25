<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tesoreria_caja_movimientos')) {
            return;
        }

        Schema::table('tesoreria_caja_movimientos', function (Blueprint $table) {
            if (! Schema::hasColumn('tesoreria_caja_movimientos', 'validado_at')) {
                $table->timestamp('validado_at')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('tesoreria_caja_movimientos', 'validado_por')) {
                $table->unsignedBigInteger('validado_por')->nullable()->after('validado_at');
            }
        });

        // Histórico de Cobranza ya impactó saldo: se marca validado para no romper cajas.
        if (Schema::hasColumn('tesoreria_caja_movimientos', 'origen')
            && Schema::hasColumn('tesoreria_caja_movimientos', 'validado_at')) {
            $ahora = now();
            DB::table('tesoreria_caja_movimientos')
                ->where('origen', 'cobranza')
                ->whereNull('validado_at')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($ahora) {
                    foreach ($rows as $row) {
                        DB::table('tesoreria_caja_movimientos')
                            ->where('id', $row->id)
                            ->update([
                                'validado_at' => $row->created_at ?? $ahora,
                            ]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tesoreria_caja_movimientos')) {
            return;
        }

        Schema::table('tesoreria_caja_movimientos', function (Blueprint $table) {
            if (Schema::hasColumn('tesoreria_caja_movimientos', 'validado_por')) {
                $table->dropColumn('validado_por');
            }
            if (Schema::hasColumn('tesoreria_caja_movimientos', 'validado_at')) {
                $table->dropColumn('validado_at');
            }
        });
    }
};
