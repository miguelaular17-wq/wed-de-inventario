<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de cada cambio en fichas de servicio técnico (órdenes, envíos, transferencias, equipos):
 * qué campo cambió, de qué valor a cuál, quién, cuándo y desde dónde. En PostgreSQL un trigger impide
 * modificar o borrar filas: la auditoría solo crece.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('auditorias')) {
            Schema::create('auditorias', function (Blueprint $t) {
                $t->id();
                $t->string('auditable_type', 64);
                $t->unsignedBigInteger('auditable_id');
                $t->string('accion', 16);
                $t->json('cambios')->nullable();
                $t->text('motivo')->nullable();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('user_nombre', 255)->nullable();
                $t->string('ip', 45)->nullable();
                $t->string('ruta', 255)->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->index(['auditable_type', 'auditable_id']);
                $t->index('created_at');
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION auditorias_inmutable() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'La auditoría no se puede modificar ni borrar';
                END;
                $$ LANGUAGE plpgsql;

                DROP TRIGGER IF EXISTS auditorias_sin_cambios ON auditorias;
                CREATE TRIGGER auditorias_sin_cambios
                    BEFORE UPDATE OR DELETE ON auditorias
                    FOR EACH ROW EXECUTE FUNCTION auditorias_inmutable();
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS auditorias_sin_cambios ON auditorias; DROP FUNCTION IF EXISTS auditorias_inmutable();');
        }
        Schema::dropIfExists('auditorias');
    }
};
