<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('archivero_carpetas')) {
            Schema::create('archivero_carpetas', function (Blueprint $table) {
                $table->id();
                $table->string('ruta', 500)->unique()->comment('Ruta completa: «COBRANZAS JENU/2025/ENERO»');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('archivero_archivos')) {
            Schema::create('archivero_archivos', function (Blueprint $table) {
                $table->id();
                $table->string('carpeta', 500)->default('')->comment('Ruta de la carpeta; vacío = raíz');
                $table->string('nombre', 300);
                $table->string('extension', 20)->nullable();
                $table->unsignedBigInteger('tamano')->default(0);
                $table->string('mime', 150)->nullable();
                $table->string('objeto', 200)->unique()->comment('Clave en el bucket «archivero» (nombre aleatorio)');
                $table->string('origen', 40)->nullable()->comment('web | pendrive');
                $table->timestamp('modificado_en')->nullable()->comment('Fecha del archivo original');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();

                $table->index('carpeta');
                $table->unique(['carpeta', 'nombre']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('archivero_archivos');
        Schema::dropIfExists('archivero_carpetas');
    }
};
