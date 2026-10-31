<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajuste de facturas por el administrador. El sincronizador de cada sede borra y vuelve a insertar el último mes
 * de ventas_documentos / ventas_detalle: los triggers hacen que no borre ni pise lo marcado ajuste_manual, ni vuelva
 * a crear una factura o renglón cuya clave (número, renglón, fecha) cambió el administrador.
 * La web se salta la protección con SET LOCAL app.ajuste_factura = '1'.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['ventas_documentos', 'ventas_detalle'] as $tabla) {
            if (Schema::hasTable($tabla) && ! Schema::hasColumn($tabla, 'ajuste_manual')) {
                Schema::table($tabla, fn (Blueprint $t) => $t->boolean('ajuste_manual')->default(false));
            }
        }

        if (! Schema::hasTable('factura_ajustes')) {
            Schema::create('factura_ajustes', function (Blueprint $t) {
                $t->id();
                $t->string('tabla', 16);
                $t->unsignedBigInteger('registro_id');
                $t->string('sede', 16);
                $t->string('tipo_documento', 8);
                $t->string('numero_documento', 32);
                $t->integer('item_numero')->nullable();
                $t->date('fecha')->nullable();
                $t->boolean('clave_cambiada')->default(false);
                $t->json('cambios');
                $t->unsignedBigInteger('user_id')->nullable();
                $t->timestamps();
                $t->index(['tabla', 'sede', 'tipo_documento', 'numero_documento'], 'factura_ajustes_clave_index');
                $t->index('registro_id');
            });
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        $s = DB::selectOne('SELECT current_schema() AS s')->s;

        DB::unprepared(<<<SQL
CREATE OR REPLACE FUNCTION {$s}.proteger_ajuste_documento() RETURNS trigger LANGUAGE plpgsql AS \$fn\$
BEGIN
    IF COALESCE(current_setting('app.ajuste_factura', true), '') = '1' THEN
        IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
        RETURN NEW;
    END IF;
    IF TG_OP = 'DELETE' THEN
        IF OLD.ajuste_manual THEN RETURN NULL; END IF;
        RETURN OLD;
    ELSIF TG_OP = 'UPDATE' THEN
        IF OLD.ajuste_manual THEN RETURN OLD; END IF;
        RETURN NEW;
    END IF;
    IF EXISTS (SELECT 1 FROM {$s}.factura_ajustes a WHERE a.clave_cambiada AND a.tabla = 'documento'
            AND a.sede = NEW.sede AND a.tipo_documento = NEW.tipo_documento AND a.numero_documento = NEW.numero_documento) THEN
        RETURN NULL;
    END IF;
    RETURN NEW;
END
\$fn\$;

CREATE OR REPLACE FUNCTION {$s}.proteger_ajuste_detalle() RETURNS trigger LANGUAGE plpgsql AS \$fn\$
BEGIN
    IF COALESCE(current_setting('app.ajuste_factura', true), '') = '1' THEN
        IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
        RETURN NEW;
    END IF;
    IF TG_OP = 'DELETE' THEN
        IF OLD.ajuste_manual THEN RETURN NULL; END IF;
        RETURN OLD;
    ELSIF TG_OP = 'UPDATE' THEN
        IF OLD.ajuste_manual THEN RETURN OLD; END IF;
        RETURN NEW;
    END IF;
    IF EXISTS (SELECT 1 FROM {$s}.factura_ajustes a WHERE a.clave_cambiada AND a.tabla = 'detalle'
            AND a.sede = NEW.sede AND a.tipo_documento = NEW.tipo_documento AND a.numero_documento = NEW.numero_documento
            AND a.item_numero = NEW.item_numero AND a.fecha = NEW.fecha) THEN
        RETURN NULL;
    END IF;
    RETURN NEW;
END
\$fn\$;

DROP TRIGGER IF EXISTS proteger_ajuste ON {$s}.ventas_documentos;
CREATE TRIGGER proteger_ajuste BEFORE INSERT OR UPDATE OR DELETE ON {$s}.ventas_documentos
    FOR EACH ROW EXECUTE FUNCTION {$s}.proteger_ajuste_documento();
DROP TRIGGER IF EXISTS proteger_ajuste ON {$s}.ventas_detalle;
CREATE TRIGGER proteger_ajuste BEFORE INSERT OR UPDATE OR DELETE ON {$s}.ventas_detalle
    FOR EACH ROW EXECUTE FUNCTION {$s}.proteger_ajuste_detalle();
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $s = DB::selectOne('SELECT current_schema() AS s')->s;
            DB::unprepared("DROP TRIGGER IF EXISTS proteger_ajuste ON {$s}.ventas_documentos;
                DROP TRIGGER IF EXISTS proteger_ajuste ON {$s}.ventas_detalle;
                DROP FUNCTION IF EXISTS {$s}.proteger_ajuste_documento();
                DROP FUNCTION IF EXISTS {$s}.proteger_ajuste_detalle();");
        }
        Schema::dropIfExists('factura_ajustes');
        foreach (['ventas_documentos', 'ventas_detalle'] as $tabla) {
            if (Schema::hasTable($tabla) && Schema::hasColumn($tabla, 'ajuste_manual')) {
                Schema::table($tabla, fn (Blueprint $t) => $t->dropColumn('ajuste_manual'));
            }
        }
    }
};
