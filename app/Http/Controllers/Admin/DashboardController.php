<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MovimientoQueryService;
use App\Services\ProductRepository;
use App\Services\VentasCalculator;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, ProductRepository $products, MovimientoQueryService $movimientos): View
    {
        return view('admin.dashboard', [
            'productCount' => $this->productCount($products),
            'movementStats' => $movimientos->stats(),
            'lastImport' => $products->lastStockUpdate(),
        ]);
    }

    private function productCount(ProductRepository $products): int
    {
        if (config('database.default') === 'pgsql') {
            return \App\Models\V2\Producto::query()->where('activo', true)->count();
        }

        return \App\Models\Product::query()->count();
    }
}
