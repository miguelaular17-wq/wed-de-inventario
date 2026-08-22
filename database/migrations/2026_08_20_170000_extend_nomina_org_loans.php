<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createSedes();
        $this->createCargos();
        $this->ensureEmpleados();
        $this->ensureVendedores();
        $this->ensureAuditLogs();
        $this->createPrestamos();
    }

    public function down(): void
    {
        Schema::dropIfExists('nomina_prestamo_abonos');
        Schema::dropIfExists('nomina_prestamo_cuotas');
        Schema::dropIfExists('nomina_prestamos');

        if (Schema::hasTable('nomina_empleados')) {
            Schema::table('nomina_empleados', function (Blueprint $table) {
                foreach (['sede_id', 'cargo_id', 'supervisor_id', 'es_supervisor'] as $column) {
                    if (Schema::hasColumn('nomina_empleados', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('nomina_cargos');
        Schema::dropIfExists('nomina_sedes');
    }

    private function createSedes(): void
    {
        if (Schema::hasTable('nomina_sedes')) {
            return;
        }

        Schema::create('nomina_sedes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 128);
            $table->string('codigo', 32)->unique();
            $table->string('direccion', 255)->nullable();
            $table->string('estado', 16)->default('ACTIVO');
            $table->timestamps();
        });
    }

    private function createCargos(): void
    {
        if (Schema::hasTable('nomina_cargos')) {
            return;
        }

        Schema::create('nomina_cargos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 128)->unique();
            $table->text('descripcion')->nullable();
            $table->string('estado', 16)->default('ACTIVO');
            $table->timestamps();
        });
    }

    private function ensureEmpleados(): void
    {
        if (! Schema::hasTable('nomina_empleados')) {
            Schema::create('nomina_empleados', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('email')->nullable();
                $table->string('telefono', 64)->nullable();
                $table->date('fecha_ingreso')->nullable();
                $table->string('cargo', 128)->nullable();
                $table->string('sede', 32)->nullable();
                $table->decimal('salario_base', 12, 2)->default(0);
                $table->string('tipo_salario', 24)->default('QUINCENAL');
                $table->string('estado', 16)->default('ACTIVO');
                $table->unsignedBigInteger('sede_id')->nullable();
                $table->unsignedBigInteger('cargo_id')->nullable();
                $table->unsignedBigInteger('supervisor_id')->nullable();
                $table->boolean('es_supervisor')->default(false);
                $table->timestamps();
                $table->unique('cliente_id');
            });

            $this->addEmpleadoForeignKeys();

            return;
        }

        Schema::table('nomina_empleados', function (Blueprint $table) {
            if (! Schema::hasColumn('nomina_empleados', 'sede_id')) {
                $table->unsignedBigInteger('sede_id')->nullable();
            }
            if (! Schema::hasColumn('nomina_empleados', 'cargo_id')) {
                $table->unsignedBigInteger('cargo_id')->nullable();
            }
            if (! Schema::hasColumn('nomina_empleados', 'supervisor_id')) {
                $table->unsignedBigInteger('supervisor_id')->nullable();
            }
            if (! Schema::hasColumn('nomina_empleados', 'es_supervisor')) {
                $table->boolean('es_supervisor')->default(false);
            }
        });

        $this->addEmpleadoForeignKeys();
    }

    private function addEmpleadoForeignKeys(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $this->addForeignKeyIfMissing('nomina_empleados', 'fk_nomina_empleados_sede', 'sede_id', 'nomina_sedes');
        $this->addForeignKeyIfMissing('nomina_empleados', 'fk_nomina_empleados_cargo', 'cargo_id', 'nomina_cargos');
        $this->addForeignKeyIfMissing('nomina_empleados', 'fk_nomina_empleados_supervisor', 'supervisor_id', 'nomina_empleados');
    }

    private function addForeignKeyIfMissing(string $table, string $name, string $column, string $references): void
    {
        $exists = DB::selectOne(
            'SELECT 1 FROM pg_constraint WHERE conname = ?',
            [$name]
        );

        if ($exists) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s(id) ON DELETE SET NULL',
            $table,
            $name,
            $column,
            $references
        ));
    }

    private function ensureVendedores(): void
    {
        if (Schema::hasTable('nomina_empleado_vendedores')) {
            return;
        }

        Schema::create('nomina_empleado_vendedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('nomina_empleados')->cascadeOnDelete();
            $table->string('nombre_vendedor');
            $table->string('nombre_normalizado');
            $table->string('codigo_profit', 64)->nullable();
            $table->string('sede', 16)->nullable();
            $table->timestamps();
            $table->unique('nombre_normalizado');
        });
    }

    private function ensureAuditLogs(): void
    {
        if (Schema::hasTable('nomina_audit_logs')) {
            return;
        }

        Schema::create('nomina_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 64);
            $table->string('entidad', 64);
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->json('valores_anteriores')->nullable();
            $table->json('valores_nuevos')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    private function createPrestamos(): void
    {
        if (! Schema::hasTable('nomina_prestamos')) {
            Schema::create('nomina_prestamos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empleado_id')->constrained('nomina_empleados')->restrictOnDelete();
                $table->date('fecha');
                $table->decimal('monto_original', 12, 2);
                $table->unsignedInteger('numero_cuotas');
                $table->decimal('valor_cuota', 12, 2);
                $table->string('frecuencia', 16);
                $table->date('fecha_inicio');
                $table->date('fecha_fin_estimada')->nullable();
                $table->decimal('saldo_pendiente', 12, 2);
                $table->string('estado', 16)->default('ACTIVO');
                $table->text('motivo')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nomina_prestamo_cuotas')) {
            Schema::create('nomina_prestamo_cuotas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('prestamo_id')->constrained('nomina_prestamos')->restrictOnDelete();
                $table->unsignedInteger('numero');
                $table->date('fecha_programada');
                $table->decimal('monto', 12, 2);
                $table->decimal('monto_pagado', 12, 2)->default(0);
                $table->string('estado', 16)->default('PENDIENTE');
                $table->date('fecha_pago')->nullable();
                $table->unsignedBigInteger('nomina_periodo_id')->nullable();
                $table->unsignedBigInteger('abono_id')->nullable();
                $table->timestamps();
                $table->unique(['prestamo_id', 'numero']);
            });
        }

        if (! Schema::hasTable('nomina_prestamo_abonos')) {
            Schema::create('nomina_prestamo_abonos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('prestamo_id')->constrained('nomina_prestamos')->restrictOnDelete();
                $table->unsignedBigInteger('cuota_id')->nullable();
                $table->date('fecha');
                $table->decimal('monto', 12, 2);
                $table->string('tipo', 32);
                $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('observacion')->nullable();
                $table->timestamps();
            });
        }
    }
};
