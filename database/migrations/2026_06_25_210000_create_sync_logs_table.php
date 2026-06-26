<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared("CREATE SCHEMA IF NOT EXISTS inventario_v2");

        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS inventario_v2.sync_logs (
    id                      BIGSERIAL PRIMARY KEY,
    sede                    VARCHAR(16) NOT NULL,
    tipo                    VARCHAR(32) NOT NULL,
    registros_procesados    INTEGER NOT NULL DEFAULT 0,
    metadata                JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT NOW()
)
SQL);
        
        DB::unprepared(<<<'SQL'
CREATE INDEX IF NOT EXISTS ix_sync_logs_sede_tipo ON inventario_v2.sync_logs (sede, tipo);
CREATE INDEX IF NOT EXISTS ix_sync_logs_created_at ON inventario_v2.sync_logs (created_at DESC);
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP TABLE IF EXISTS inventario_v2.sync_logs CASCADE');
    }
};
