<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Services\Nomina\RetencionAcumuladaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RetencionController extends Controller
{
    public function __construct(private RetencionAcumuladaService $retenciones) {}

    public function index(Request $request): View
    {
        $anioParam = (string) $request->query('anio', (string) now()->year);
        $anio = $anioParam === 'todo' ? null : (int) $anioParam;
        $sede = trim((string) $request->query('sede', ''));
        $resumen = $this->retenciones->resumen($anio);
        $trabajadoresTotal = count($resumen['trabajadores']);

        if ($sede !== '') {
            $resumen['trabajadores'] = array_values(array_filter(
                $resumen['trabajadores'],
                fn (array $trabajador) => $trabajador['sede'] === $sede
            ));
            $resumen['total_filtrado'] = round(array_sum(array_column($resumen['trabajadores'], 'retencion')), 2);
        }

        return view('nomina.retenciones.index', [
            'resumen' => $resumen,
            'anioParam' => $anio === null ? 'todo' : (string) $anio,
            'sede' => $sede,
            'trabajadoresTotal' => $trabajadoresTotal,
        ]);
    }
}
