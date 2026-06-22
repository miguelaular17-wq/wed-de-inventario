<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesCollections;
use App\Services\ProductRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendedorController extends Controller
{
    use PaginatesCollections;

    public function __construct(
        private ProductRepository $products,
    ) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $sedeLocal = (string) $request->session()->get('sede_local');

        if (!$sedeLocal) {
            $sedes = config('inventario.sedes_stock');
            $sedeLocal = !empty($sedes) ? $sedes[0] : 'DORAL';
        }

        // loadForSede returns all products with all sedes stock inside their 'stocks' field
        $products = $this->products->loadForSede($sedeLocal);

        if ($q !== '') {
            $qLower = mb_strtolower($q);
            $products = $products->filter(function ($row) use ($qLower) {
                return str_contains(mb_strtolower((string) ($row['cod_centro'] ?? '')), $qLower)
                    || str_contains(mb_strtolower((string) ($row['producto'] ?? '')), $qLower);
            });
        }

        // Map each product to sum the stock across all sedes to get the global stock
        $mappedProducts = $products->map(function ($row) {
            $globalStock = 0;
            if (isset($row['stocks']) && is_array($row['stocks'])) {
                $globalStock = array_sum($row['stocks']);
            }
            $row['existencia_global'] = $globalStock;
            return $row;
        });

        $rows = $this->paginateCollection($mappedProducts, $request);

        $casheaLevelsPath = storage_path('app/cashea_levels.json');
        $defaultLevels = [
            1 => 60,
            2 => 50,
            3 => 40,
            4 => 40,
            5 => 40,
            6 => 40,
        ];
        $casheaLevels = $defaultLevels;
        if (file_exists($casheaLevelsPath)) {
            $stored = json_decode(file_get_contents($casheaLevelsPath), true);
            if (is_array($stored)) {
                foreach (range(1, 6) as $nivel) {
                    if (isset($stored[$nivel])) {
                        $casheaLevels[$nivel] = (int) $stored[$nivel];
                    }
                }
            }
        }

        return view('vendedor.index', [
            'rows' => $rows,
            'q' => $q,
            'sedes' => config('inventario.sedes_stock'),
            'stockUpdatedAt' => $this->products->lastStockUpdate(),
            'casheaLevels' => $casheaLevels,
        ]);
    }
}
