<?php

namespace App\Http\Controllers;

use App\Services\Finanzas\GastoDirectivosService;
use App\Support\SimpleXlsxWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
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
        $data['personaDeEgreso'] = $data['egresos']->mapWithKeys(
            fn ($mov) => [$mov->id => $this->service->codigoPersonaDeEgreso($mov)]
        );

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

        $codigos = collect(GastoDirectivosService::PERSONAS)->pluck('codigo');
        $persona = strtoupper(trim((string) $request->query('persona', '')));

        if ($codigos->contains($persona)) {
            [$nombre, $contenido] = $this->pdfPersona($desde, $hasta, $persona);

            return response($contenido, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
            ]);
        }

        $archivos = [];
        foreach ($codigos as $codigo) {
            [$nombre, $contenido] = $this->pdfPersona($desde, $hasta, $codigo);
            $archivos[$nombre] = $contenido;
        }

        $zip = sprintf('Gasto_Directivos_%s_%s.zip', str_replace('-', '', $desde), str_replace('-', '', $hasta));

        return response(SimpleXlsxWriter::zipFiles($archivos), 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$zip.'"',
        ]);
    }

    /**
     * @return array{0: string, 1: string} nombre de archivo y contenido del PDF
     */
    private function pdfPersona(string $desde, string $hasta, string $codigo): array
    {
        $data = $this->service->resumenPersona($desde, $hasta, $codigo);
        $data['logo'] = is_file(public_path('logo.png')) ? public_path('logo.png') : null;
        $data['generado'] = now()->format('d/m/Y H:i');

        $pdf = Pdf::loadView('finanzas.pdf.gasto_directivos', $data)
            ->setPaper('a4', 'portrait');

        $nombre = sprintf(
            'Gasto_Directivos_%s_%s_%s.pdf',
            str_replace(' ', '_', Str::ascii($data['persona']['nombre'])),
            str_replace('-', '', $desde),
            str_replace('-', '', $hasta)
        );

        return [$nombre, $pdf->output()];
    }
}
