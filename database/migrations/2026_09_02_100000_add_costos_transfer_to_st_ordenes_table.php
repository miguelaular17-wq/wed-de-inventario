<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('st_ordenes')) {
            return;
        }

        Schema::table('st_ordenes', function (Blueprint $table) {
            if (! Schema::hasColumn('st_ordenes', 'presupuesto')) {
                $table->decimal('presupuesto', 12, 2)->nullable()->after('observaciones');
            }
            if (! Schema::hasColumn('st_ordenes', 'costo_mano_obra')) {
                $table->decimal('costo_mano_obra', 12, 2)->nullable()->after('presupuesto');
            }
            if (! Schema::hasColumn('st_ordenes', 'costo_refacciones')) {
                $table->decimal('costo_refacciones', 12, 2)->nullable()->after('costo_mano_obra');
            }
            if (! Schema::hasColumn('st_ordenes', 'tecnico_id')) {
                $table->foreignId('tecnico_id')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('st_ordenes', 'sede_origen_transfer')) {
                $table->string('sede_origen_transfer', 32)->nullable()->after('tecnico_id');
            }
            if (! Schema::hasColumn('st_ordenes', 'transfer_estado')) {
                $table->string('transfer_estado', 16)->nullable()->after('sede_origen_transfer');
            }
            if (! Schema::hasColumn('st_ordenes', 'repuestos_descontados_at')) {
                $table->timestamp('repuestos_descontados_at')->nullable()->after('transfer_estado');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('st_ordenes')) {
            return;
        }

        Schema::table('st_ordenes', function (Blueprint $table) {
            $columns = [
                'repuestos_descontados_at',
                'transfer_estado',
                'sede_origen_transfer',
                'tecnico_id',
                'costo_refacciones',
                'costo_mano_obra',
                'presupuesto',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('st_ordenes', $column)) {
                    if ($column === 'tecnico_id') {
                        $table->dropConstrainedForeignId('tecnico_id');
                    } else {
                        $table->dropColumn($column);
                    }
                }
            }
        });
    }
};
