<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('analisis_categoria_checks')) {
            return;
        }

        Schema::create('analisis_categoria_checks', function (Blueprint $table) {
            $table->id();
            $table->string('sede', 40);
            $table->string('categoria', 191);
            $table->string('subcategoria', 191);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['sede', 'categoria', 'subcategoria'], 'analisis_cat_sede_cat_sub_unique');
            $table->index(['sede', 'categoria']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analisis_categoria_checks');
    }
};
