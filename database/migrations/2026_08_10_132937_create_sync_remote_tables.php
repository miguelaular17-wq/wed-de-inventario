<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tablas para el sistema de control remoto de sincronizadores.
     *
     * sync_heartbeats : Cada sincronizador registra su presencia cada 60 s.
     * sync_commands   : Cola de comandos enviados desde el admin web.
     */
    public function up(): void
    {
        // ── Heartbeats ──────────────────────────────────────────────────
        Schema::create('sync_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->string('sede', 20)->unique();
            $table->string('version', 20)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            // 'activo' mientras last_seen_at < 5 min, 'inactivo' si más
            $table->string('estado', 20)->default('activo');
            // JSON con módulos habilitados y otras métricas
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        // ── Comandos ─────────────────────────────────────────────────────
        Schema::create('sync_commands', function (Blueprint $table) {
            $table->id();
            $table->string('sede', 20)->index();
            // 'sync_stock' | 'sync_precios' | 'sync_cobranzas' | 'sync_compras'
            $table->string('comando', 50);
            $table->jsonb('parametros')->nullable();
            // 'pendiente' → 'ejecutando' → 'completado' | 'error'
            $table->string('estado', 20)->default('pendiente');
            $table->text('mensaje')->nullable();
            $table->string('solicitado_por', 120)->nullable();
            $table->timestampTz('ejecutado_at')->nullable();
            $table->timestamps();

            $table->index(['sede', 'estado']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_remote_tables');
    }
};
