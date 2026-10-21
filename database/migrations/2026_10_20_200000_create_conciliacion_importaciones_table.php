<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conciliacion_importaciones')) {
            Schema::create('conciliacion_importaciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('session_id')->nullable();
                $table->string('banco', 80);
                $table->string('titular', 120);
                $table->string('archivo_nombre', 255);
                $table->string('archivo_path', 500)->nullable();
                $table->string('archivo_hash', 64)->nullable()->index();
                $table->string('extension', 10);
                $table->string('estado', 30)->default('pendiente')->index();
                $table->unsignedInteger('filas_leidas')->default(0);
                $table->unsignedInteger('filas_importadas')->default(0);
                $table->unsignedInteger('filas_duplicadas')->default(0);
                $table->unsignedInteger('filas_rechazadas')->default(0);
                $table->unsignedInteger('filas_conciliadas')->default(0);
                $table->unsignedInteger('filas_pendientes')->default(0);
                $table->unsignedInteger('ultima_fila')->default(0)->comment('Última fila del archivo ya guardada (reanudar en reintentos)');
                $table->unsignedInteger('intentos')->default(0);
                $table->json('rechazos')->nullable();
                $table->text('mensaje')->nullable();
                $table->timestamp('iniciado_at')->nullable();
                $table->timestamp('finalizado_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('conciliacion_lineas') && ! Schema::hasColumn('conciliacion_lineas', 'importacion_id')) {
            Schema::table('conciliacion_lineas', function (Blueprint $table) {
                $table->unsignedBigInteger('importacion_id')->nullable();
                $table->unsignedInteger('fila_origen')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('conciliacion_lineas', 'importacion_id')) {
            Schema::table('conciliacion_lineas', function (Blueprint $table) {
                $table->dropColumn(['importacion_id', 'fila_origen']);
            });
        }
        Schema::dropIfExists('conciliacion_importaciones');
    }
};
