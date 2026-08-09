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

        DB::unprepared(<<<'SQL'
ALTER TABLE inventario_v2.stock_actual DROP CONSTRAINT ck_stock_actual_sede;
ALTER TABLE inventario_v2.stock_actual ADD CONSTRAINT ck_stock_actual_sede CHECK (sede IN ('JRZ','DORAL','VIRTUDES','ZAMORA','CENTRO','SAMBIL','NUNES','MOVISTAR'));

ALTER TABLE inventario_v2.ventas_historicas DROP CONSTRAINT ck_ventas_historicas_sede;
ALTER TABLE inventario_v2.ventas_historicas ADD CONSTRAINT ck_ventas_historicas_sede CHECK (sede IN ('JRZ','DORAL','VIRTUDES','ZAMORA','CENTRO','SAMBIL','NUNES','MOVISTAR'));

ALTER TABLE inventario_v2.reposicion DROP CONSTRAINT ck_reposicion_sede;
ALTER TABLE inventario_v2.reposicion ADD CONSTRAINT ck_reposicion_sede CHECK (sede IN ('JRZ','DORAL','VIRTUDES','ZAMORA','CENTRO','SAMBIL','NUNES','MOVISTAR'));

ALTER TABLE inventario_v2.inventario_derivado DROP CONSTRAINT ck_inventario_derivado_sede;
ALTER TABLE inventario_v2.inventario_derivado ADD CONSTRAINT ck_inventario_derivado_sede CHECK (sede IN ('JRZ','DORAL','VIRTUDES','ZAMORA','CENTRO','SAMBIL','NUNES','MOVISTAR'));

ALTER TABLE inventario_v2.config_sede DROP CONSTRAINT ck_config_sede;
ALTER TABLE inventario_v2.config_sede ADD CONSTRAINT ck_config_sede CHECK (sede IN ('JRZ','DORAL','VIRTUDES','ZAMORA','CENTRO','SAMBIL','NUNES','MOVISTAR'));

INSERT INTO inventario_v2.config_sede (sede, tiempo_pronostico)
VALUES ('NUNES', 15), ('MOVISTAR', 15)
ON CONFLICT (sede) DO NOTHING;
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // We can optionally drop and revert, but since there's data, reverting might cause errors if NUNES/MOVISTAR data exists.
        // It's safer to just let it be, or we can revert the constraints but risk constraint violation on down.
        DB::unprepared(<<<'SQL'
DELETE FROM inventario_v2.config_sede WHERE sede IN ('NUNES','MOVISTAR');

ALTER TABLE inventario_v2.stock_actual DROP CONSTRAINT ck_stock_actual_sede;
ALTER TABLE inventario_v2.stock_actual ADD CONSTRAINT ck_stock_actual_sede CHECK (sede IN ('JRZ','DORAL','VIRTUDES','ZAMORA','CENTRO','SAMBIL'));

ALTER TABLE inventario_v2.ventas_historicas DROP CONSTRAINT ck_ventas_historicas_sede;
ALTER TABLE inventario_v2.ventas_historicas ADD CONSTRAINT ck_ventas_historicas_sede CHECK (sede IN ('JRZ','DORAL','VIRTUDES','ZAMORA','CENTRO','SAMBIL'));

ALTER TABLE inventario_v2.reposicion DROP CONSTRAINT ck_reposicion_sede;
ALTER TABLE inventario_v2.reposicion ADD CONSTRAINT ck_reposicion_sede CHECK (sede IN ('JRZ','DORAL','VIRTUDES','ZAMORA','CENTRO','SAMBIL'));

ALTER TABLE inventario_v2.inventario_derivado DROP CONSTRAINT ck_inventario_derivado_sede;
ALTER TABLE inventario_v2.inventario_derivado ADD CONSTRAINT ck_inventario_derivado_sede CHECK (sede IN ('JRZ','DORAL','VIRTUDES','ZAMORA','CENTRO','SAMBIL'));

ALTER TABLE inventario_v2.config_sede DROP CONSTRAINT ck_config_sede;
ALTER TABLE inventario_v2.config_sede ADD CONSTRAINT ck_config_sede CHECK (sede IN ('JRZ','DORAL','VIRTUDES','ZAMORA','CENTRO','SAMBIL'));
SQL);
    }
};
