<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. PROPIEDADES ───────────────────────────────────────────────────
        Schema::create('pat_propiedades', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 32)->unique();
            $table->string('nombre', 256);
            $table->string('tipo', 64); // casa, apartamento, local, galpón, terreno, condominio, vehículo, otro
            $table->text('direccion')->nullable();
            $table->string('ubicacion', 256)->nullable();
            $table->jsonb('fotos')->nullable();          // array of file paths/URLs
            $table->string('estado', 32)->default('disponible'); // disponible, alquilado, uso_propio, remodelacion, no_disponible
            $table->string('propietario', 256)->nullable();
            $table->string('responsable', 256)->nullable();
            $table->date('fecha_adquisicion')->nullable();
            $table->decimal('valor_inversion', 18, 2)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        // ── 2. ALQUILERES FIJOS ──────────────────────────────────────────────
        Schema::create('pat_alquileres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('pat_propiedades')->onDelete('cascade');
            $table->string('inquilino_nombre', 256);
            $table->string('inquilino_contacto', 256)->nullable();
            $table->string('contrato_nro', 64)->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('tipo_canon', 16)->default('mensual'); // mensual, quincenal
            $table->decimal('canon_mensual', 18, 2)->nullable();
            $table->decimal('canon_quincenal', 18, 2)->nullable();
            $table->tinyInteger('dia_pago')->nullable();           // día del mes
            $table->string('forma_pago', 64)->nullable();
            $table->string('estado', 32)->default('activo');      // activo, vencido, terminado
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        // ── 3. PAGOS DE ALQUILER ─────────────────────────────────────────────
        Schema::create('pat_alquiler_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alquiler_id')->constrained('pat_alquileres')->onDelete('cascade');
            $table->string('periodo', 32);       // e.g. "2026-08" or "2026-08-Q1"
            $table->date('fecha_vencimiento');
            $table->date('fecha_pago')->nullable();
            $table->decimal('monto', 18, 2);
            $table->string('estado', 32)->default('pendiente'); // pagado, pendiente, vencido
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        // ── 4. RESERVAS TEMPORALES ───────────────────────────────────────────
        Schema::create('pat_reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('pat_propiedades')->onDelete('cascade');
            $table->string('cliente_nombre', 256);
            $table->string('cliente_contacto', 256)->nullable();
            $table->date('fecha_entrada');
            $table->date('fecha_salida');
            $table->decimal('precio_noche', 18, 2)->default(0);
            $table->string('estado', 32)->default('confirmada'); // confirmada, cancelada, completada
            $table->string('moneda', 8)->default('usd');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        // ── 5. TRANSACCIONES FINANCIERAS ─────────────────────────────────────
        Schema::create('pat_transacciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('pat_propiedades')->onDelete('cascade');
            $table->string('tipo', 16);          // ingreso, gasto, comision
            $table->string('categoria', 128);    // alquiler, reserva, reparacion, agua, luz, etc.
            $table->string('descripcion', 512)->nullable();
            $table->decimal('monto', 18, 2);
            $table->string('moneda', 8)->default('usd');
            $table->smallInteger('mes');          // 1-12
            $table->smallInteger('anio');
            $table->date('fecha');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        // ── 6. INVENTARIO POR PROPIEDAD ──────────────────────────────────────
        Schema::create('pat_inventario_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('pat_propiedades')->onDelete('cascade');
            $table->string('articulo', 256);
            $table->integer('cantidad')->default(1);
            $table->string('estado_articulo', 64)->nullable(); // bueno, regular, dañado
            $table->text('observacion')->nullable();
            $table->jsonb('fotos')->nullable();
            $table->timestamps();
        });

        // ── 7. CONTROL DE LLAVES ─────────────────────────────────────────────
        Schema::create('pat_llaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('pat_propiedades')->onDelete('cascade');
            $table->string('descripcion', 128);   // "Llave 1", "Llave principal"
            $table->string('ubicacion_actual', 256)->nullable();
            $table->string('responsable', 256)->nullable();
            $table->date('fecha_entrega')->nullable();
            $table->date('fecha_devolucion')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        // ── 8. DOCUMENTOS ────────────────────────────────────────────────────
        Schema::create('pat_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('pat_propiedades')->onDelete('cascade');
            $table->string('tipo', 64);          // contrato, factura, permiso, foto, otro
            $table->string('nombre', 256);
            $table->string('ruta_archivo', 512);
            $table->bigInteger('tamano_bytes')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pat_documentos');
        Schema::dropIfExists('pat_llaves');
        Schema::dropIfExists('pat_inventario_items');
        Schema::dropIfExists('pat_transacciones');
        Schema::dropIfExists('pat_reservas');
        Schema::dropIfExists('pat_alquiler_pagos');
        Schema::dropIfExists('pat_alquileres');
        Schema::dropIfExists('pat_propiedades');
    }
};
