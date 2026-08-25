<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaConfig;
use App\Models\Nomina\NominaReglaComision;
use App\Services\Nomina\AttendanceService;
use App\Services\Nomina\CommissionCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ConfiguracionController extends Controller
{
    public function __construct(
        private AttendanceService $attendance,
        private CommissionCategoryService $categorias,
    ) {
    }

    public function index(): View
    {
        $productos = collect();
        if (Schema::hasTable('productos')) {
            $productosQuery = DB::table('productos')
                ->select(['id', 'codigo', 'nombre', 'categoria', 'subcategoria']);

            if (Schema::hasColumn('productos', 'activo')) {
                $productosQuery->where('activo', true);
            }
            if (Schema::hasColumn('productos', 'oculto')) {
                $productosQuery->where('oculto', false);
            }

            $productos = $productosQuery
                ->orderBy('categoria')
                ->orderBy('subcategoria')
                ->orderBy('nombre')
                ->get();
        }

        return view('nomina.configuracion.index', [
            'valorHoraExtra' => $this->attendance->valorHoraEmpresa(),
            'descuentoVentaPct' => NominaConfig::getDecimal('descuento_venta_pct', 20),
            'comisionSupervisorPct' => NominaConfig::getDecimal('comision_supervisor_pct', 0.05),
            'comisionMarketingPct' => NominaConfig::getDecimal('comision_marketing_pct', 0.10),
            'comisionTelefoniaPct' => NominaConfig::getDecimal('comision_telefonia_pct', 0.20),
            'comisionOtrosPct' => NominaConfig::getDecimal('comision_otros_pct', 1),
            'retencionComisionPct' => NominaConfig::getDecimal('retencion_comision_pct', 10),
            'comisionServicioTecnicoPct' => NominaConfig::getDecimal('comision_servicio_tecnico_pct', 50),
            'categoriasTelefonia' => $this->categorias->categorias(CommissionCategoryService::TELEFONIA),
            'categoriasOtros' => $this->categorias->categorias(CommissionCategoryService::OTROS),
            'reglasComision' => NominaReglaComision::query()
                ->orderByDesc('activo')
                ->orderByRaw("CASE nivel WHEN 'PRODUCTO' THEN 1 WHEN 'SUBCATEGORIA' THEN 2 WHEN 'CATEGORIA' THEN 3 ELSE 4 END")
                ->orderBy('nombre')
                ->get(),
            'productosComision' => $productos,
            'categoriasComision' => $productos->pluck('categoria')
                ->filter()
                ->unique()
                ->sort()
                ->values(),
            'subcategoriasComision' => $productos
                ->filter(fn ($producto) => filled($producto->categoria) && filled($producto->subcategoria))
                ->groupBy('categoria')
                ->map(fn ($items) => $items->pluck('subcategoria')->unique()->sort()->values()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'valor_hora_extra' => ['required', 'numeric', 'min:0'],
            'descuento_venta_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'comision_supervisor_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'comision_marketing_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'comision_telefonia_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'comision_otros_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'retencion_comision_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'comision_servicio_tecnico_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $this->attendance->guardarTarifasEmpresa($data);
        NominaConfig::put('descuento_venta_pct', round((float) $data['descuento_venta_pct'], 2));
        foreach ([
            'comision_supervisor_pct',
            'comision_marketing_pct',
            'comision_telefonia_pct',
            'comision_otros_pct',
            'retencion_comision_pct',
            'comision_servicio_tecnico_pct',
        ] as $clave) {
            if (array_key_exists($clave, $data) && $data[$clave] !== null) {
                NominaConfig::put($clave, round((float) $data[$clave], 4));
            }
        }

        return redirect()
            ->route('nomina.configuracion.index')
            ->with('status', 'Configuración de nómina guardada.');
    }

    public function storeRegla(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'nivel' => ['required', 'in:PRODUCTO,SUBCATEGORIA,CATEGORIA,GENERAL'],
            'producto_ids' => ['nullable', 'required_if:nivel,PRODUCTO', 'array', 'min:1'],
            'producto_ids.*' => ['integer', 'distinct', 'exists:productos,id'],
            'categorias' => ['nullable', 'required_if:nivel,CATEGORIA', 'array', 'min:1'],
            'categorias.*' => ['string', 'distinct', 'max:256'],
            'subcategorias' => ['nullable', 'required_if:nivel,SUBCATEGORIA', 'array', 'min:1'],
            'subcategorias.*' => ['string', 'distinct', 'max:1024'],
            'porcentaje' => ['required', 'numeric', 'min:0', 'max:100'],
            'base_comisionable' => ['required', 'in:NETO,MARGEN,TOTAL'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
        ]);

        $alcances = [];
        if ($data['nivel'] === 'PRODUCTO') {
            $productos = DB::table('productos')
                ->whereIn('id', $data['producto_ids'])
                ->get()
                ->keyBy('id');
            foreach ($data['producto_ids'] as $productoId) {
                $producto = $productos->get($productoId);
                if (! $producto?->codigo) {
                    return back()->withInput()->withErrors([
                        'producto_ids' => 'Uno de los productos seleccionados no tiene código.',
                    ]);
                }
                $alcances[] = [
                    'producto_id' => $producto->id,
                    'codigo_producto' => $producto->codigo,
                    'categoria' => null,
                    'subcategoria' => null,
                ];
            }
        } elseif ($data['nivel'] === 'CATEGORIA') {
            foreach ($data['categorias'] as $categoria) {
                if (! DB::table('productos')->where('categoria', $categoria)->exists()) {
                    return back()->withInput()->withErrors(['categorias' => 'Una categoría seleccionada no existe.']);
                }
                $alcances[] = [
                    'producto_id' => null,
                    'codigo_producto' => null,
                    'categoria' => $categoria,
                    'subcategoria' => null,
                ];
            }
        } elseif ($data['nivel'] === 'SUBCATEGORIA') {
            foreach ($data['subcategorias'] as $seleccion) {
                $alcance = json_decode($seleccion, true);
                $categoria = $alcance['categoria'] ?? null;
                $subcategoria = $alcance['subcategoria'] ?? null;
                if (! $categoria || ! $subcategoria || ! DB::table('productos')
                    ->where('categoria', $categoria)
                    ->where('subcategoria', $subcategoria)
                    ->exists()) {
                    return back()->withInput()->withErrors([
                        'subcategorias' => 'Una subcategoría seleccionada no es válida.',
                    ]);
                }
                $alcances[] = [
                    'producto_id' => null,
                    'codigo_producto' => null,
                    'categoria' => $categoria,
                    'subcategoria' => $subcategoria,
                ];
            }
        } else {
            $alcances[] = [
                'producto_id' => null,
                'codigo_producto' => null,
                'categoria' => null,
                'subcategoria' => null,
            ];
        }

        $base = [
            'nombre' => $data['nombre'],
            'nivel' => $data['nivel'],
            'porcentaje' => $data['porcentaje'],
            'base_comisionable' => $data['base_comisionable'],
            'fecha_inicio' => $data['fecha_inicio'],
            'fecha_fin' => $data['fecha_fin'] ?? null,
            'activo' => true,
        ];
        DB::transaction(function () use ($alcances, $base) {
            foreach ($alcances as $alcance) {
                NominaReglaComision::create($base + $alcance);
            }
        });

        return back()->with('status', count($alcances).' regla(s) de comisión creada(s).');
    }

    public function destroyRegla(NominaReglaComision $regla): RedirectResponse
    {
        $regla->update(['activo' => false]);

        return back()->with('status', 'Regla de comisión desactivada.');
    }
}
