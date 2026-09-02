<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('st_repuestos')) {
            Schema::create('st_repuestos', function (Blueprint $table) {
                $table->id();
                $table->string('sede', 32);
                $table->string('codigo', 64)->nullable();
                $table->string('nombre');
                $table->string('categoria', 64)->nullable();
                $table->unsignedInteger('stock')->default(0);
                $table->unsignedInteger('stock_min')->default(0);
                $table->decimal('costo', 12, 2)->default(0);
                $table->decimal('precio_venta', 12, 2)->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->index(['sede', 'activo']);
                $table->index(['sede', 'nombre']);
            });
        }

        if (! Schema::hasTable('st_orden_repuestos')) {
            Schema::create('st_orden_repuestos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('orden_id')->constrained('st_ordenes')->cascadeOnDelete();
                $table->foreignId('repuesto_id')->constrained('st_repuestos')->restrictOnDelete();
                $table->unsignedInteger('cantidad');
                $table->decimal('precio_unitario', 12, 2)->default(0);
                $table->decimal('costo_unitario', 12, 2)->default(0);
                $table->boolean('descontado')->default(false);
                $table->timestamps();

                $table->unique(['orden_id', 'repuesto_id']);
            });
        }

        if (! Schema::hasTable('st_movimientos_repuesto')) {
            Schema::create('st_movimientos_repuesto', function (Blueprint $table) {
                $table->id();
                $table->foreignId('repuesto_id')->constrained('st_repuestos')->cascadeOnDelete();
                $table->foreignId('orden_id')->nullable()->constrained('st_ordenes')->nullOnDelete();
                $table->string('tipo', 16);
                $table->integer('cantidad');
                $table->unsignedInteger('stock_antes');
                $table->unsignedInteger('stock_despues');
                $table->string('motivo')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['repuesto_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('st_orden_eventos')) {
            Schema::create('st_orden_eventos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('orden_id')->constrained('st_ordenes')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('tipo', 32);
                $table->text('descripcion');
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['orden_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('st_orden_eventos');
        Schema::dropIfExists('st_movimientos_repuesto');
        Schema::dropIfExists('st_orden_repuestos');
        Schema::dropIfExists('st_repuestos');
    }
};
