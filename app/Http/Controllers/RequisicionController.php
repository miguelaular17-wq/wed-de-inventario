<?php

namespace App\Http\Controllers;

use App\Services\ProductRepository;
use App\Services\RequisicionExportService;
use App\Services\RequisicionPersonalizadaService;
use App\Services\StockMovementService;
use App\Services\VentasCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class RequisicionController extends Controller
{
    public function __construct(
        private ProductRepository $products,
        private VentasCalculator $ventas,
        private RequisicionExportService $export,
        private RequisicionPersonalizadaService $reqPersonalizada,
        private StockMovementService $stock,
    ) {}

    public function form(Request $request): View
    {
        $sede = (string) $request->session()->get('sede_local');
        $tipoReporte = $request->query('tipo_reporte', 'ventas') === 'personalizada' ? 'personalizada' : 'ventas';

        $sedesOrigen = $this->export->sedesOrigen($sede);
        $defaultSedeOrigen = $sedesOrigen[0] ?? '';
        $selectedSedeOrigen = (string) $request->query('sede_origen', $defaultSedeOrigen);
        if ($selectedSedeOrigen !== 'Todas' && ! $this->export->resolveSedeKey($selectedSedeOrigen)) {
            $selectedSedeOrigen = $defaultSedeOrigen;
        }

        $selectedCategoria = (string) $request->query('categoria', 'Todas');
        $selectedSubcategoria = (string) $request->query('subcategoria', 'Todas');

        if ($tipoReporte === 'personalizada') {
            $sedeOrigenKey = $selectedSedeOrigen === 'Todas'
                ? null
                : $this->export->resolveSedeKey($selectedSedeOrigen);

            $products = $this->products->loadForSede($sede)->keyBy('cod_centro');
            $manualCats = $this->reqPersonalizada->loadManuales($sede);

            $subByCat = [];
            $manualCats->each(function ($m) use ($products, &$subByCat) {
                $p = $products->get($m->codigo);
                if (! $p) {
                    return;
                }
                $cat = (string) ($p['categoria'] ?? '');
                $sub = (string) ($p['subcategoria'] ?? '');
                if ($cat === '' || $sub === '') {
                    return;
                }
                $subByCat[$cat] = $subByCat[$cat] ?? [];
                if (! in_array($sub, $subByCat[$cat], true)) {
                    $subByCat[$cat][] = $sub;
                }
            });
            foreach ($subByCat as $k => $v) {
                sort($subByCat[$k]);
            }

            $categories = $manualCats->map(fn ($m) => $products->get($m->codigo)['categoria'] ?? '')
                ->filter()->unique()->sort()->values()->all();

            $subcategories = $manualCats->map(fn ($m) => $products->get($m->codigo)['subcategoria'] ?? '')
                ->filter()->unique()->sort()->values()->all();

            $previewRows = $this->reqPersonalizada->buildExport(
                $sede,
                $sedeOrigenKey,
                $selectedCategoria !== 'Todas' ? $selectedCategoria : null,
                $selectedSubcategoria,
            )->map(function (array $r) use ($products) {
                $p = $products->get($r['codigo']);

                return [
                    'codigo' => $r['codigo'],
                    'producto' => $r['producto'],
                    'categoria' => $p['categoria'] ?? '—',
                    'subcategoria' => $p['subcategoria'] ?? '—',
                    'opc' => config('inventario.display.'.$r['sede_origen'], $r['sede_origen']),
                    'cantidad' => $r['cantidad'],
                ];
            });

            return view('requisicion.export', [
                'sede' => $sede,
                'tipoReporte' => $tipoReporte,
                'sedesOrigen' => $sedesOrigen,
                'totalRequisicion' => $this->reqPersonalizada->countPendientes($sede),
                'categories' => $categories,
                'subcategories' => $subcategories,
                'subByCat' => $subByCat,
                'selectedSedeOrigen' => $selectedSedeOrigen,
                'selectedCategoria' => $selectedCategoria,
                'selectedSubcategoria' => $selectedSubcategoria,
                'excluirCategorias' => false,
                'excludeCategories' => [],
                'excludeCodes' => [],
                'previewRows' => $previewRows,
                'filteredCount' => $previewRows->count(),
            ]);
        }

        $tp = (float) $request->session()->get('tiempo_pronostico', config('inventario.tiempo_pronostico_default'));
        $ventasRows = $this->ventas->calcular($this->products->loadForSede($sede), $sede, $tp);

        $categories = $ventasRows
            ->where('accion', 'HACER REQUISICION')
            ->pluck('categoria')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $subcategories = $ventasRows
            ->where('accion', 'HACER REQUISICION')
            ->pluck('subcategoria')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $subByCat = [];
        $ventasRows->where('accion', 'HACER REQUISICION')->each(function (array $r) use (&$subByCat) {
            $cat = (string) ($r['categoria'] ?? '');
            $sub = (string) ($r['subcategoria'] ?? '');
            if ($cat === '' || $sub === '') {
                return;
            }
            $subByCat[$cat] = $subByCat[$cat] ?? [];
            if (! in_array($sub, $subByCat[$cat], true)) {
                $subByCat[$cat][] = $sub;
            }
        });
        foreach ($subByCat as $k => $v) {
            sort($subByCat[$k]);
        }

        $excluirCategorias = $selectedCategoria === 'Todas' && $request->boolean('excluir_categorias');

        $excludeCategories = $request->query('exclude_categories', []);
        if (! is_array($excludeCategories)) {
            $excludeCategories = [$excludeCategories];
        }
        if (! $excluirCategorias) {
            $excludeCategories = [];
        } else {
            $excludeCategories = array_values(array_filter($excludeCategories));
        }
        $excludeCodes = $request->query('exclude_codes', []);
        if (! is_array($excludeCodes)) {
            $excludeCodes = [$excludeCodes];
        }

        // For preview we want to show all candidate rows (so excluded rows remain visible and marked).
        $previewRows = $this->export->previewExportRows(
            $ventasRows,
            $selectedSedeOrigen,
            $sede,
            false,
            $selectedCategoria,
            $selectedSubcategoria,
            $excludeCategories,
            [], // pass empty excludeCodes so preview still shows excluded items
        );

        return view('requisicion.export', [
            'sede' => $sede,
            'tipoReporte' => 'ventas',
            'sedesOrigen' => $sedesOrigen,
            'totalRequisicion' => $ventasRows->where('accion', 'HACER REQUISICION')->count(),
            'categories' => $categories,
            'subcategories' => $subcategories,
            'subByCat' => $subByCat,
            'selectedSedeOrigen' => $selectedSedeOrigen,
            'selectedCategoria' => $selectedCategoria,
            'selectedSubcategoria' => $selectedSubcategoria,
            'excluirCategorias' => $excluirCategorias,
            'excludeCategories' => $excludeCategories,
            'excludeCodes' => $excludeCodes,
            'previewRows' => $previewRows,
            'filteredCount' => $previewRows->count(),
        ]);
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\Response|RedirectResponse
    {
        $sede = (string) $request->session()->get('sede_local');
        $tp = (float) $request->session()->get('tiempo_pronostico', config('inventario.tiempo_pronostico_default'));
        $tipoReporte = $request->input('tipo_reporte', 'ventas') === 'personalizada' ? 'personalizada' : 'ventas';
        $sedeOrigen = (string) $request->input('sede_origen');
        $categoria = (string) $request->input('categoria', 'Todas');
        $subcategoria = (string) $request->input('subcategoria', 'Todas');

        if ($tipoReporte === 'personalizada') {
            $sedeOrigenKey = ($sedeOrigen === 'Todas' || $sedeOrigen === '')
                ? null
                : $this->export->resolveSedeKey($sedeOrigen);

            if ($sedeOrigen !== 'Todas' && $sedeOrigen !== '' && ! $sedeOrigenKey) {
                return back()->withErrors(['export' => 'Sede origen inválida.']);
            }

            $lines = $this->reqPersonalizada->buildExport(
                $sede,
                $sedeOrigenKey,
                $categoria !== 'Todas' ? $categoria : null,
                $subcategoria,
            );

            if ($lines->isEmpty()) {
                return back()->withErrors(['export' => 'No hay requisiciones manuales pendientes para exportar.']);
            }

            $this->reqPersonalizada->applyExport(
                $lines,
                $sede,
                $this->stock,
                auth()->user()?->email,
            );

            $filename = $sedeOrigenKey
                ? 'Requisicion_manual_'.config('inventario.display.'.$sedeOrigenKey, $sedeOrigenKey).'.csv'
                : 'Requisicion_manual_'.$sede.'_todas.csv';

            return response($this->export->toCsv($lines), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        $incluirParcial = $request->boolean('incluir_parcial');
        $excludeCategories = array_filter((array) $request->input('exclude_categories', []));
        if ($categoria !== 'Todas' || ! $request->boolean('excluir_categorias')) {
            $excludeCategories = [];
        }
        $excludeCodes = array_filter((array) $request->input('exclude_codes', []));

        if ($sedeOrigen === 'Todas') {
            $sedesStock = $this->export->sedesOrigen($sede);
            
            $zip = new \ZipArchive();
            $zipFile = tempnam(sys_get_temp_dir(), 'zip');
            if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                return back()->withErrors(['export' => 'No se pudo crear el archivo ZIP.']);
            }

            $hasFiles = false;
            $ventasRows = $this->ventas->calcular($this->products->loadForSede($sede), $sede, $tp);

            foreach ($sedesStock as $origSede) {
                $displayOrig = config('inventario.display.'.$origSede, $origSede);
                $lines = $this->export->buildExport(
                    $ventasRows,
                    $displayOrig,
                    $sede,
                    $incluirParcial,
                    $categoria,
                    $subcategoria,
                    $excludeCategories,
                    $excludeCodes,
                );

                if ($lines->isNotEmpty()) {
                    // Apply requisition stock movement
                    $this->stock->applyRequisition($lines, $origSede, $sede);
                    
                    // Generate CSV content
                    $csvContent = $this->export->toCsv($lines);
                    
                    // Add to ZIP
                    $zip->addFromString('Requisicion_'.$sede.'_desde_'.$origSede.'.csv', $csvContent);
                    $hasFiles = true;
                }
            }

            $zip->close();

            if (! $hasFiles) {
                @unlink($zipFile);
                return back()->withErrors(['export' => 'No hay filas exportables para ninguna sede origen.']);
            }

            return response()->download($zipFile, 'Requisiciones_'.$sede.'_todas.zip')->deleteFileAfterSend(true);
        }

        $sedeOrigenKey = $this->export->resolveSedeKey($sedeOrigen);
        if (! $sedeOrigenKey) {
            return back()->withErrors(['export' => 'Sede origen inválida.']);
        }

        $ventasRows = $this->ventas->calcular($this->products->loadForSede($sede), $sede, $tp);
        $lines = $this->export->buildExport(
            $ventasRows,
            $sedeOrigen,
            $sede,
            $incluirParcial,
            $categoria,
            $subcategoria,
            $excludeCategories,
            $excludeCodes,
        );

        if ($lines->isEmpty()) {
            return back()->withErrors(['export' => 'No hay filas exportables para esa sede origen.']);
        }

        $this->stock->applyRequisition($lines, $sedeOrigenKey, $sede);

        $filename = 'Requisicion_'.$sede.'_desde_'.$sedeOrigenKey.'.csv';

        return response($this->export->toCsv($lines), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
