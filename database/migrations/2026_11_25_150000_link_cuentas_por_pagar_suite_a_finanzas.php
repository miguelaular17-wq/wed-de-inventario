<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cuentas_por_pagar')) {
            Schema::table('cuentas_por_pagar', function (Blueprint $table) {
                if (! Schema::hasColumn('cuentas_por_pagar', 'origen')) {
                    $table->string('origen', 32)->default('manual')->after('estado');
                }
                if (! Schema::hasColumn('cuentas_por_pagar', 'referencia')) {
                    $table->string('referencia', 64)->nullable()->after('origen');
                }
            });

            try {
                Schema::table('cuentas_por_pagar', function (Blueprint $table) {
                    $table->unique(['origen', 'referencia'], 'cuentas_por_pagar_origen_referencia_unique');
                });
            } catch (\Throwable) {
                // Índice ya existe.
            }
        }

        if (Schema::hasTable('cuentas_por_pagar_suite') && ! Schema::hasColumn('cuentas_por_pagar_suite', 'cuenta_por_pagar_id')) {
            Schema::table('cuentas_por_pagar_suite', function (Blueprint $table) {
                $table->unsignedBigInteger('cuenta_por_pagar_id')->nullable()->after('credito')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cuentas_por_pagar_suite') && Schema::hasColumn('cuentas_por_pagar_suite', 'cuenta_por_pagar_id')) {
            Schema::table('cuentas_por_pagar_suite', function (Blueprint $table) {
                $table->dropColumn('cuenta_por_pagar_id');
            });
        }

        if (Schema::hasTable('cuentas_por_pagar')) {
            try {
                Schema::table('cuentas_por_pagar', function (Blueprint $table) {
                    $table->dropUnique('cuentas_por_pagar_origen_referencia_unique');
                });
            } catch (\Throwable) {
            }

            Schema::table('cuentas_por_pagar', function (Blueprint $table) {
                if (Schema::hasColumn('cuentas_por_pagar', 'referencia')) {
                    $table->dropColumn('referencia');
                }
                if (Schema::hasColumn('cuentas_por_pagar', 'origen')) {
                    $table->dropColumn('origen');
                }
            });
        }
    }
};
