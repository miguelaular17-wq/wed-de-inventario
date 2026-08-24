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
-- Notas y "pagado manual" se guardaron con el id interno de SQL Server.
-- El sync actual deja id_documento NULL y usa numero_documento (factura).
-- Reasignar las claves al número de factura para que vuelvan a verse.

UPDATE inventario_v2.cobranza_notas n
SET id_documento = m.numero_documento
FROM (
    SELECT DISTINCT ON (id_documento)
        id_documento,
        numero_documento
    FROM inventario_v2.historial_cobranzas
    WHERE id_documento IS NOT NULL
      AND BTRIM(id_documento) <> ''
      AND numero_documento IS NOT NULL
      AND BTRIM(numero_documento) <> ''
      AND id_documento <> numero_documento
    ORDER BY id_documento, fecha_registro DESC
) m
WHERE n.id_documento = m.id_documento
  AND NOT EXISTS (
      SELECT 1
      FROM inventario_v2.cobranza_notas x
      WHERE x.id_documento = m.numero_documento
        AND x.id <> n.id
  );

UPDATE inventario_v2.cobranzas_pagadas_manualmente p
SET id_documento = m.numero_documento
FROM (
    SELECT DISTINCT ON (id_documento)
        id_documento,
        numero_documento
    FROM inventario_v2.historial_cobranzas
    WHERE id_documento IS NOT NULL
      AND BTRIM(id_documento) <> ''
      AND numero_documento IS NOT NULL
      AND BTRIM(numero_documento) <> ''
      AND id_documento <> numero_documento
    ORDER BY id_documento, fecha_registro DESC
) m
WHERE p.id_documento = m.id_documento
  AND NOT EXISTS (
      SELECT 1
      FROM inventario_v2.cobranzas_pagadas_manualmente x
      WHERE x.id_documento = m.numero_documento
        AND x.id <> p.id
  );

UPDATE inventario_v2.historial_cobranzas
SET id_documento = COALESCE(NULLIF(BTRIM(factura_padre), ''), numero_documento)
WHERE id_documento IS NULL
  AND COALESCE(NULLIF(BTRIM(COALESCE(factura_padre, '')), ''), numero_documento) IS NOT NULL
  AND BTRIM(COALESCE(NULLIF(BTRIM(COALESCE(factura_padre, '')), ''), numero_documento)) <> '';
SQL);
    }

    public function down(): void
    {
        // Irreversible: the SQL Server internal ids are no longer the live key.
    }
};
