<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE contratos ALTER COLUMN interes_porcentaje TYPE numeric(14, 8)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE contratos ALTER COLUMN interes_porcentaje TYPE numeric(10, 6)');
        }
    }
};
