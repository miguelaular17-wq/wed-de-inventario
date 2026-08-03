<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    DB::statement('ALTER TABLE flujo_cajas DROP CONSTRAINT IF EXISTS flujo_cajas_categoria_egreso_check');
    DB::statement("ALTER TABLE flujo_cajas ADD CONSTRAINT flujo_cajas_categoria_egreso_check CHECK (categoria_egreso::text = ANY (ARRAY['egreso_realizado'::character varying, 'otros_egresos'::character varying, 'traslados'::character varying, 'egreso_divisas'::character varying]::text[]))");
    echo "Constraint updated successfully.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
