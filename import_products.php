<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\V2\Producto;
use Illuminate\Support\Facades\DB;

$json = file_get_contents(__DIR__.'/productos de puebra.json');
$items = json_decode($json, true);

$count = 0;
DB::transaction(function () use ($items, &$count) {
    foreach ($items as $item) {
        // Parse categories (e.g. "ELECTRONICA,EQUIPOS VARIOS")
        $cats = explode(',', $item['categories'] ?? '');
        $categoria = trim($cats[0] ?? 'GENERAL');
        $subcategoria = trim($cats[1] ?? '');

        Producto::updateOrCreate(
            ['codigo' => $item['codigo']],
            [
                'nombre' => $item['descripcion'] ?? 'SIN NOMBRE',
                'categoria' => $categoria,
                'subcategoria' => $subcategoria,
                'proveedor' => 'GENERICO',
                'activo' => true,
                'precio_unidad' => $item['precio1'] ?? 0,
                'precio_mayor' => $item['precio3'] ?? 0,
                'url_imagen' => $item['url_imagen'] ?? null,
                'excluir_compras' => false,
            ]
        );
        $count++;
    }
});

echo "Imported $count products successfully.\n";
