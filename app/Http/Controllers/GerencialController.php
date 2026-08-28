<?php

namespace App\Http\Controllers;

use App\Services\GerencialAnalyticsService;
use App\Services\GerencialDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GerencialController extends Controller
{
    public function dashboard(Request $request, GerencialDashboardService $gerencial): View
    {
        $ctx = $this->contexto($request, $gerencial);
        $data = $gerencial->resumen(
            $ctx['periodo'],
            $ctx['filtros']['sede'],
            $ctx['filtros']['categoria'],
            $ctx['filtros']['vendedor'],
            $ctx['filtros']['producto'],
            $ctx['filtros']['ranking']
        );

        return view('gerencial.dashboard', $ctx + [
            'total' => $data['total'],
            'porSede' => $data['por_sede'],
            'tops' => $data['tops'],
            'diario' => $data['diario'],
            'usaLineas' => $data['usa_lineas'],
        ]);
    }

    public function devoluciones(Request $request, GerencialDashboardService $gerencial, GerencialAnalyticsService $analytics): View
    {
        $ctx = $this->contexto($request, $gerencial);
        $conDetalle = $request->boolean('ver_detalle');
        $data = $analytics->devoluciones(
            $ctx['periodo'],
            $ctx['filtros']['sede'],
            $ctx['filtros']['vendedor'],
            $ctx['filtros']['producto'],
            $conDetalle
        );

        return view('gerencial.devoluciones', $ctx + $data + [
            'verDetalle' => $conDetalle,
        ]);
    }

    public function valorizados(Request $request, GerencialDashboardService $gerencial, GerencialAnalyticsService $analytics): View
    {
        $ctx = $this->contexto($request, $gerencial);
        $data = $analytics->valorizados(
            $ctx['periodo'],
            $ctx['filtros']['sede'],
            $ctx['filtros']['categoria'],
            $ctx['filtros']['producto']
        );

        return view('gerencial.valorizados', $ctx + $data);
    }

    public function ajustes(Request $request, GerencialDashboardService $gerencial, GerencialAnalyticsService $analytics): View
    {
        $ctx = $this->contexto($request, $gerencial);
        $data = $analytics->ajustes(
            $ctx['periodo'],
            $ctx['filtros']['sede'],
            $request->query('tipo')
        );

        return view('gerencial.ajustes', $ctx + $data + [
            'tipo' => $request->query('tipo'),
        ]);
    }

    public function rentabilidad(Request $request, GerencialDashboardService $gerencial, GerencialAnalyticsService $analytics): View
    {
        $ctx = $this->contexto($request, $gerencial);
        $data = $analytics->rentabilidad(
            $ctx['periodo'],
            $ctx['filtros']['sede'],
            $ctx['filtros']['categoria'],
            $ctx['filtros']['vendedor'],
            $ctx['filtros']['producto']
        );

        return view('gerencial.rentabilidad', $ctx + $data);
    }

    /**
     * @return array{periodo:array,filtros:array,sedes:array,catalogos:array}
     */
    private function contexto(Request $request, GerencialDashboardService $gerencial): array
    {
        $periodo = $gerencial->resolverPeriodo(
            $request->query('preset'),
            $request->query('desde'),
            $request->query('hasta')
        );

        return [
            'periodo' => $periodo,
            'filtros' => [
                'sede' => $request->query('sede', 'todas'),
                'categoria' => $request->query('categoria'),
                'vendedor' => $request->query('vendedor'),
                'producto' => $request->query('producto'),
                'preset' => $periodo['preset'],
                'desde' => $periodo['inicio']->toDateString(),
                'hasta' => $periodo['fin']->toDateString(),
                'ranking' => in_array($request->query('ranking'), ['usd', 'unidades', 'clientes', 'utilidad'], true)
                    ? $request->query('ranking')
                    : 'usd',
            ],
            'sedes' => $gerencial->sedesVentas(),
            'catalogos' => $gerencial->catalogos(),
        ];
    }
}
