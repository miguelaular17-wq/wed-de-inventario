<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pat_propiedades', function (Blueprint $table) {
            $table->string('documento_digitalizado', 64)->nullable()->after('valor_inversion');
        });

        $marcas = [
            'PAT-013' => 'C/V',
            'PAT-012' => 'C/V',
            'PAT-015' => 'C/V',
            'PAT-007' => 'C/V',
            'PAT-005' => 'C/V',
            'PAT-031' => 'C/V',
            'PAT-026' => 'C/V',
            'PAT-023' => 'C/V',
            'PAT-011' => 'Sin documento',
        ];

        foreach ($marcas as $codigo => $marca) {
            DB::table('pat_propiedades')->where('codigo', $codigo)->update([
                'documento_digitalizado' => $marca,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('pat_propiedades', function (Blueprint $table) {
            $table->dropColumn('documento_digitalizado');
        });
    }
};
