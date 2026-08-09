<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get allowed values for categoria_egreso check constraint
$rows = DB::select("SELECT pg_get_constraintdef(c.oid) AS def FROM pg_constraint c JOIN pg_class t ON t.oid = c.conrelid WHERE t.relname = 'flujo_cajas' AND c.contype = 'c'");
foreach($rows as $r) echo $r->def . PHP_EOL;
