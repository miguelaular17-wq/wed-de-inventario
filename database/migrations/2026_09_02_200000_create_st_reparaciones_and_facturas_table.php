<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('st_reparaciones')) {
            Schema::create('st_reparaciones', function (Blueprint $table) {
                $table->id();
                $table->string('sede', 32);
                $table->string('tipo', 16);
                $table->string('cliente_nombre')->nullable();
                $table->string('cliente_telefono', 40)->nullable();
                $table->string('producto');
                $table->string('categoria', 32)->default('otro');
                $table->string('comprobante_venta', 64)->nullable();
                $table->text('falla')->nullable();
                $table->string('accion', 32)->default('pendiente');
                $table->string('repuestos_texto')->nullable();
                $table->decimal('costo_interno', 12, 2)->nullable();
                $table->string('estado', 32)->default('en_proceso');
                $table->text('observaciones')->nullable();
                $table->foreignId('tecnico_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['sede', 'estado']);
                $table->index('created_at');
            });
        }

        if (! Schema::hasTable('st_facturas')) {
            Schema::create('st_facturas', function (Blueprint $table) {
                $table->id();
                $table->string('sede', 32);
                $table->unsignedInteger('numero');
                $table->string('cliente_nombre');
                $table->text('descripcion')->nullable();
                $table->decimal('presupuesto', 12, 2)->nullable();
                $table->decimal('costo_mano_obra', 12, 2)->nullable();
                $table->decimal('costo_refacciones', 12, 2)->nullable();
                $table->decimal('total', 12, 2);
                $table->string('estado_pago', 16)->default('pendiente');
                $table->date('fecha');
                $table->foreignId('tecnico_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['sede', 'numero']);
                $table->index(['sede', 'estado_pago']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('st_facturas');
        Schema::dropIfExists('st_reparaciones');
    }
};
