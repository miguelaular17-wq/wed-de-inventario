<?php

namespace App\Http\Controllers;

use App\Services\Finanzas\GastoDirectivosService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GastoDirectivosController extends Controller
{
    public function __construct(
        private GastoDirectivosService $service,
    ) {}

    public function index(Request $request): View
    {
        $desde = $this->service->parseFecha(
            $request->query('desde'),
            now()->startOfMonth()->toDateString()
        );
        $hasta = $this->service->parseFecha(
            $request->query('hasta'),
            now()->toDateString()
        );

        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $data = $this->service->resumen($desde, $hasta);

        return view('finanzas.gasto_directivos', $data);
    }

    public function reporte(Request $request): Response|StreamedResponse
    {
        $desde = $this->service->parseFecha(
            $request->query('desde'),
            now()->startOfMonth()->toDateString()
        );
        $hasta = $this->service->parseFecha(
            $request->query('hasta'),
            now()->toDateString()
        );

        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $data = $this->service->resumen($desde, $hasta);
        $data['logo'] = is_file(public_path('logo.png')) ? public_path('logo.png') : null;
        $data['generado'] = now()->format('d/m/Y H:i');

        $pdf = Pdf::loadView('finanzas.pdf.gasto_directivos', $data)
            ->setPaper('a4', 'portrait');

        $filename = sprintf(
            'Gasto_Directivos_%s_%s.pdf',
            str_replace('-', '', $desde),
            str_replace('-', '', $hasta)
        );

        return $pdf->download($filename);
    }
}
