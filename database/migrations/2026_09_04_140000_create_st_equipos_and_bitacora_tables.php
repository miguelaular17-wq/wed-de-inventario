<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('st_equipos')) {
            Schema::create('st_equipos', function (Blueprint $table) {
                $table->id();
                $table->string('imei', 32)->nullable();
                $table->string('imei2', 32)->nullable();
                $table->string('serial', 64)->nullable();
                $table->string('marca', 64)->nullable();
                $table->string('modelo', 128)->nullable();
                $table->string('color', 64)->nullable();
                $table->string('telefono_asociado', 40)->nullable();
                $table->string('estado_actual', 32)->default('en_taller');
                $table->string('sede_actual', 32)->nullable();
                $table->timestamps();

                $table->unique('imei');
                $table->index('serial');
                $table->index('telefono_asociado');
                $table->index(['sede_actual', 'estado_actual']);
            });
        }

        if (! Schema::hasTable('st_equipo_eventos')) {
            Schema::create('st_equipo_eventos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('equipo_id')->constrained('st_equipos')->cascadeOnDelete();
                $table->foreignId('orden_id')->nullable()->constrained('st_ordenes')->nullOnDelete();
                $table->foreignId('backup_id')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('sede', 32)->nullable();
                $table->string('tipo', 32);
                $table->string('titulo')->nullable();
                $table->text('descripcion')->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['equipo_id', 'created_at']);
                $table->index('tipo');
            });
        }

        if (! Schema::hasTable('st_backups')) {
            Schema::create('st_backups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('orden_id')->constrained('st_ordenes')->cascadeOnDelete();
                $table->foreignId('equipo_cliente_id')->nullable()->constrained('st_equipos')->nullOnDelete();
                $table->string('marca', 64)->nullable();
                $table->string('modelo', 128)->nullable();
                $table->string('imei', 32)->nullable();
                $table->string('serial', 64)->nullable();
                $table->string('estado_fisico')->nullable();
                $table->string('accesorios')->nullable();
                $table->text('condiciones')->nullable();
                $table->string('firma_cliente')->nullable();
                $table->string('firma_empleado')->nullable();
                $table->string('estado', 32)->default('entregado');
                $table->timestamp('entregado_at')->nullable();
                $table->timestamp('devuelto_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('st_ordenes')) {
            Schema::table('st_ordenes', function (Blueprint $table) {
                if (! Schema::hasColumn('st_ordenes', 'tipo_gestion')) {
                    $table->string('tipo_gestion', 16)->default('ST')->after('numero');
                }
                if (! Schema::hasColumn('st_ordenes', 'equipo_id')) {
                    $table->foreignId('equipo_id')->nullable()->after('tipo_gestion')->constrained('st_equipos')->nullOnDelete();
                }
                if (! Schema::hasColumn('st_ordenes', 'imei')) {
                    $table->string('imei', 32)->nullable()->after('equipo');
                }
                if (! Schema::hasColumn('st_ordenes', 'sede_destino_transfer')) {
                    $table->string('sede_destino_transfer', 32)->nullable()->after('sede_origen_transfer');
                }
            });

            if (Schema::hasColumn('st_ordenes', 'imei')) {
                Schema::table('st_ordenes', function (Blueprint $table) {
                    $table->index('imei');
                    $table->index('tipo_gestion');
                    $table->index('equipo_id');
                });
            }
        }

        if (Schema::hasTable('st_equipo_eventos') && Schema::hasTable('st_backups')) {
            Schema::table('st_equipo_eventos', function (Blueprint $table) {
                try {
                    $table->foreign('backup_id')->references('id')->on('st_backups')->nullOnDelete();
                } catch (\Throwable) {
                    // Already constrained or driver limitation in tests.
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('st_ordenes')) {
            Schema::table('st_ordenes', function (Blueprint $table) {
                foreach (['sede_destino_transfer', 'imei', 'equipo_id', 'tipo_gestion'] as $column) {
                    if (! Schema::hasColumn('st_ordenes', $column)) {
                        continue;
                    }
                    if ($column === 'equipo_id') {
                        try {
                            $table->dropConstrainedForeignId('equipo_id');
                        } catch (\Throwable) {
                            $table->dropColumn('equipo_id');
                        }
                    } else {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('st_equipo_eventos');
        Schema::dropIfExists('st_backups');
        Schema::dropIfExists('st_equipos');
    }
};
