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
        ini_set('memory_limit', '512M');
        $sede = (string) $request->session()->get('sede_local');
        $tipoReporte = $request->query('tipo_reporte', 'ventas');
        if (! in_array($tipoReporte, ['ventas', 'personalizada', 'mayor_demanda', 'hechas'], true)) {
            $tipoReporte = 'ventas';
        }

        $sedesOrigen = $this->export->sedesOrigen($sede);
        $defaultSedeOrigen = $sedesOrigen[0] ?? '';
        $selectedSedeOrigen = (string) $request->query('sede_origen', $defaultSedeOrigen);
        if ($selectedSedeOrigen !== 'Todas' && ! $this->export->resolveSedeKey($selectedSedeOrigen)) {
            $selectedSedeOrigen = $defaultSedeOrigen;
        }

        $selectedCategoria = (string) $request->query('categoria', 'Todas');
        $selectedSubcategoria = (string) $request->query('subcategoria', 'Todas');

        if ($tipoReporte === 'hechas') {
            return view('requisicion.export', [
                'sede' => $sede,
                'tipoReporte' => $tipoReporte,
                'sedesOrigen' => $sedesOrigen,
                'totalRequisicion' => 0,
                'pendientesRegistradas' => 0,
                'categories' => [],
                'subcategories' => [],
                'subByCat' => [],
                'selectedSedeOrigen' => $selectedSedeOrigen,
                'selectedCategoria' => 'Todas',
                'selectedSubcategoria' => 'Todas',
                'excluirCategorias' => false,
                'excludeCategories' => [],
                'excludeCodes' => [],
                'previewRows' => collect(),
                'filteredCount' => 0,
                'lotesHechas' => $this->lotesRequisicionesHechas($sede),
            ]);
        }

        if ($tipoReporte === 'personalizada') {
            $sedeOrigenKey = $selectedSedeOrigen === 'Todas'
                ? null
                : $this->export->resolveSedeKey($selectedSedeOrigen);

            \App\Services\Profiler::start('RequisicionController::form loadForSede');
            $products = $this->products->loadForSede($sede)->keyBy('cod_centro');
            \App\Services\Profiler::stop('RequisicionController::form loadForSede');
            $manualCats = $this->reqPersonalizada->loadManuales($sede, false, null);

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

            \App\Services\Profiler::start('RequisicionController::form buildExport & map');
            $previewRows = $this->reqPersonalizada->buildExport(
                $sede,
                $sedeOrigenKey,
                $selectedCategoria !== 'Todas' ? $selectedCategoria : null,
                $selectedSubcategoria,
                true,
                null,
            )->map(function (array $r) use ($products) {
                $p = $products->get($r['codigo_completo'] ?? $r['codigo']);

                return [
                    'codigo' => $r['codigo'],
                    'producto' => $r['producto'],
                    'categoria' => $p['categoria'] ?? '—',
                    'subcategoria' => $p['subcategoria'] ?? '—',
                    'opc' => config('inventario.display.'.$r['sede_origen'], $r['sede_origen']),
                    'cantidad' => $r['cantidad'],
                    'usuario' => $r['usuario'] ?? null,
                ];
            });
            \App\Services\Profiler::stop('RequisicionController::form buildExport & map');

            return view('requisicion.export', [
                'sede' => $sede,
                'tipoReporte' => $tipoReporte,
                'sedesOrigen' => $sedesOrigen,
                'totalRequisicion' => $this->reqPersonalizada->countPendientes($sede, null),
                'pendientesRegistradas' => $this->reqPersonalizada->countPendientes($sede, null),
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
        
        \App\Services\Profiler::start('RequisicionController::form loadForSede & calcular');
        $ventasRows = $this->ventas->calcular($this->products->loadForSede($sede), $sede, $tp);
        \App\Services\Profiler::stop('RequisicionController::form loadForSede & calcular');

        if ($tipoReporte === 'mayor_demanda') {
            $ventasRows = $ventasRows->filter(function (array $row) use ($sede) {
                if (($row['accion'] ?? '') !== 'HACER REQUISICION') {
                    return false;
                }

                $ventas15d = $row['ventas_internas_15d'] ?? [];
                $localSales = (int) ($row['venta'] ?? 0);

                if ($localSales <= 0) {
                    return false;
                }

                $maxOtherSales = 0;
                foreach ($ventas15d as $s => $qty) {
                    if ($s !== $sede) {
                        $maxOtherSales = max($maxOtherSales, (int) $qty);
                    }
                }

                return $localSales > $maxOtherSales;
            })->sortByDesc(fn (array $row) => (int) ($row['venta'] ?? 0))->values();
        }

        \App\Services\Profiler::start('RequisicionController::form extract categories');
        $categories = $ventasRows
            ->where('accion', 'HACER REQUISICION')
            ->pluck('categoria')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $subcategories = [];
        if ($selectedCategoria !== 'Todas') {
            $subcategories = $ventasRows
                ->where('accion', 'HACER REQUISICION')
                ->where('categoria', $selectedCategoria)
                ->pluck('subcategoria')
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
        }
        \App\Services\Profiler::stop('RequisicionController::form extract categories');

        \App\Services\Profiler::start('RequisicionController::form build preview');
        $previewRows = $this->export->previewExportRows(
            $ventasRows,
            $selectedSedeOrigen,
            $sede,
            false,
            $selectedCategoria === 'Todas' ? 'Todas' : $selectedCategoria,
            $selectedSubcategoria === 'Todas' ? 'Todas' : $selectedSubcategoria
        );
        \App\Services\Profiler::stop('RequisicionController::form build preview');

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
            'tipoReporte' => $tipoReporte,
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
            'pendientesRegistradas' => $this->reqPersonalizada->countPendientes($sede, null),
        ]);
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\Response|RedirectResponse
    {
        ini_set('memory_limit', '512M');
        $sede = (string) $request->session()->get('sede_local');
        $tp = (float) $request->session()->get('tiempo_pronostico', config('inventario.tiempo_pronostico_default'));
        $tipoReporte = $request->input('tipo_reporte', 'ventas');
        if (! in_array($tipoReporte, ['ventas', 'personalizada', 'mayor_demanda'], true)) {
            $tipoReporte = 'ventas';
        }
        $sedeOrigen = (string) $request->input('sede_origen');
        $categoria = (string) $request->input('categoria', 'Todas');
        $subcategoria = (string) $request->input('subcategoria', 'Todas');
        if ($tipoReporte === 'personalizada') {
            $sedeOrigenKey = ($sedeOrigen === 'Todas' || $sedeOrigen === '')
                ? null
                : $this->export->resolveSedeKey($sedeOrigen);

            if ($sedeOrigen !== 'Todas' && $sedeOrigen !== '' && ! $sedeOrigenKey) {
                return $this->errorExport('Sede origen inválida.');
            }

            $lines = $this->reqPersonalizada->buildExport(
                $sede,
                $sedeOrigenKey,
                $categoria !== 'Todas' ? $categoria : null,
                $subcategoria,
                true,
                null,
            );

            if ($lines->isEmpty()) {
                return $this->errorExport('No hay requisiciones registradas pendientes para exportar.');
            }

            $documentos = [];
            foreach ($lines->groupBy(fn (array $line) => strtoupper((string) ($line['sede_origen'] ?? ''))) as $origenKey => $grupo) {
                if ($origenKey === '' || $grupo->isEmpty()) {
                    continue;
                }
                $display = config('inventario.display.'.$origenKey, $origenKey);
                $documentos[] = [
                    'filename' => 'Requisicion_manual_'.$display.'.csv',
                    'lines' => $grupo->values(),
                ];
            }

            $this->reqPersonalizada->applyExport(
                $lines,
                $sede,
                $this->stock,
                auth()->user()?->email,
            );

            return $this->descargarDocumentos($sede, $documentos);
        }

        $incluirParcial = $request->boolean('incluir_parcial');
        $excludeCategories = array_filter((array) $request->input('exclude_categories', []));
        if ($categoria !== 'Todas' || ! $request->boolean('excluir_categorias')) {
            $excludeCategories = [];
        }
        $excludeCodes = array_filter((array) $request->input('exclude_codes', []));

        if ($sedeOrigen === 'Todas') {
            $sedesStock = $this->export->sedesOrigen($sede);
            $documentos = [];
            $ventasRows = $this->ventas->calcular($this->products->loadForSede($sede), $sede, $tp);

            if ($tipoReporte === 'mayor_demanda') {
                $ventasRows = $ventasRows->filter(function (array $row) use ($sede) {
                    if (($row['accion'] ?? '') !== 'HACER REQUISICION') {
                        return false;
                    }

                    $ventas15d = $row['ventas_internas_15d'] ?? [];
                    $localSales = (int) ($row['venta'] ?? 0);

                    if ($localSales <= 0) {
                        return false;
                    }

                    $maxOtherSales = 0;
                    foreach ($ventas15d as $s => $qty) {
                        if ($s !== $sede) {
                            $maxOtherSales = max($maxOtherSales, (int) $qty);
                        }
                    }

                    return $localSales > $maxOtherSales;
                })->sortByDesc(fn (array $row) => (int) ($row['venta'] ?? 0))->values();
            }

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
                    $this->stock->applyRequisition($lines, $origSede, $sede, null, $tipoReporte);
                    $documentos[] = [
                        'filename' => 'Requisicion_'.$sede.'_desde_'.$origSede.'.csv',
                        'lines' => $lines,
                    ];
                }
            }

            if ($documentos === []) {
                return $this->errorExport('No hay filas exportables para ninguna sede origen.');
            }

            return $this->descargarDocumentos($sede, $documentos);
        }

        $sedeOrigenKey = $this->export->resolveSedeKey($sedeOrigen);
        if (! $sedeOrigenKey) {
            return $this->errorExport('Sede origen inválida.');
        }

        $ventasRows = $this->ventas->calcular($this->products->loadForSede($sede), $sede, $tp);

        if ($tipoReporte === 'mayor_demanda') {
            $ventasRows = $ventasRows->filter(function (array $row) use ($sede) {
                if (($row['accion'] ?? '') !== 'HACER REQUISICION') {
                    return false;
                }

                $ventas15d = $row['ventas_internas_15d'] ?? [];
                $localSales = (int) ($row['venta'] ?? 0);

                if ($localSales <= 0) {
                    return false;
                }

                $maxOtherSales = 0;
                foreach ($ventas15d as $s => $qty) {
                    if ($s !== $sede) {
                        $maxOtherSales = max($maxOtherSales, (int) $qty);
                    }
                }

                return $localSales > $maxOtherSales;
            })->sortByDesc(fn (array $row) => (int) ($row['venta'] ?? 0))->values();
        }
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
            return $this->errorExport('No hay filas exportables para esa sede origen.');
        }

        $this->stock->applyRequisition($lines, $sedeOrigenKey, $sede, null, $tipoReporte);

        return $this->descargarDocumentos($sede, [[
            'filename' => 'Requisicion_'.$sede.'_desde_'.$sedeOrigenKey.'.csv',
            'lines' => $lines,
        ]]);
    }

    private function errorExport(string $message): \Symfony\Component\HttpFoundation\Response|RedirectResponse
    {
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors(['export' => $message]);
    }

    /**
     * @param  list<array{filename: string, lines: \Illuminate\Support\Collection<int, array<string, mixed>>}>  $documentos
     */
    private function descargarDocumentos(string $sede, array $documentos): \Symfony\Component\HttpFoundation\Response
    {
        $files = [];
        foreach ($documentos as $doc) {
            $base = preg_replace('/\.csv$/i', '', (string) $doc['filename']) ?: 'requisicion';
            $pair = $this->export->csvPair($doc['lines']);
            $files[] = [
                'filename' => $base.'_separador_punto_y_coma.csv',
                'content' => base64_encode($pair['punto_y_coma']),
            ];
            $files[] = [
                'filename' => $base.'_separador_coma.csv',
                'content' => base64_encode($pair['coma']),
            ];
        }

        if (! request()->ajax() && ! request()->wantsJson()) {
            return response()->view('requisicion.descargas', ['files' => $files]);
        }

        return response()->json(['files' => $files]);
    }

    public function exportHecha(Request $request): \Symfony\Component\HttpFoundation\Response|RedirectResponse
    {
        $sede = (string) $request->session()->get('sede_local');
        $data = $request->validate([
            'origen' => 'required|string|max:40',
            'minuto' => 'required|date_format:Y-m-d H:i',
            'source_type' => 'nullable|string|max:40',
        ]);

        $lines = $this->lineasRequisicionHecha(
            $sede,
            $data['origen'],
            $data['minuto'],
            $data['source_type'] ?? ''
        );

        if ($lines->isEmpty()) {
            return $this->errorExport('No encontré esa requisición para volver a descargarla.');
        }

        $origen = strtoupper(trim($data['origen']));
        $marca = str_replace(['-', ' ', ':'], '', $data['minuto']);

        return $this->descargarDocumentos($sede, [[
            'filename' => 'Requisicion_'.$sede.'_desde_'.$origen.'_'.$marca.'.csv',
            'lines' => $lines,
        ]]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function lotesRequisicionesHechas(string $sede)
    {
        try {
            $movs = \App\Models\V2\Movimiento::query()
                ->with('producto:id,codigo,nombre')
                ->where('tipo', 'REQUISICION')
                ->whereRaw('UPPER(TRIM(destino)) = ?', [strtoupper(trim($sede))])
                ->orderByDesc('created_at')
                ->limit(1500)
                ->get();
        } catch (\Throwable) {
            return collect();
        }

        return $movs
            ->groupBy(function ($mov) {
                $minuto = optional($mov->created_at)->format('Y-m-d H:i') ?: '';
                $source = (string) ($mov->metadata['source_type'] ?? '');

                return $minuto.'|'.strtoupper((string) $mov->origen).'|'.$source;
            })
            ->map(function ($grupo, $clave) {
                [$minuto, $origen, $source] = array_pad(explode('|', (string) $clave, 3), 3, '');
                $primero = $grupo->first();

                return [
                    'minuto' => $minuto,
                    'fecha' => optional($primero->created_at)->format('d/m/Y H:i') ?: $minuto,
                    'origen' => $origen,
                    'origen_label' => config('inventario.display.'.$origen, $origen),
                    'source_type' => $source,
                    'source_label' => match ($source) {
                        'ventas' => 'Automática',
                        'mayor_demanda' => 'Mayor demanda',
                        'manual' => 'Registrada',
                        default => 'Requisición',
                    },
                    'productos' => $grupo->count(),
                    'unidades' => (int) $grupo->sum('cantidad'),
                ];
            })
            ->sortByDesc('minuto')
            ->take(40)
            ->values();
    }

    private function lineasRequisicionHecha(string $sede, string $origen, string $minuto, string $sourceType)
    {
        $desde = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $minuto);
        $hasta = $desde->copy()->addMinute();

        $query = \App\Models\V2\Movimiento::query()
            ->with('producto:id,codigo,nombre')
            ->where('tipo', 'REQUISICION')
            ->whereRaw('UPPER(TRIM(destino)) = ?', [strtoupper(trim($sede))])
            ->whereRaw('UPPER(TRIM(origen)) = ?', [strtoupper(trim($origen))])
            ->where('created_at', '>=', $desde)
            ->where('created_at', '<', $hasta);

        if ($sourceType !== '') {
            $query->where('metadata->source_type', $sourceType);
        } else {
            $query->where(function ($q) {
                $q->whereNull('metadata->source_type')
                    ->orWhere('metadata->source_type', '');
            });
        }

        return $query->get()->map(function ($mov) {
            $codigo = trim((string) ($mov->metadata['codigo'] ?? $mov->producto?->codigo ?? ''));
            if ($codigo === '' || (int) $mov->cantidad <= 0) {
                return null;
            }

            return [
                'codigo' => trim(explode('/', $codigo)[0]),
                'unidad' => 'UND',
                'cantidad' => (int) $mov->cantidad,
            ];
        })->filter()->values();
    }
}


