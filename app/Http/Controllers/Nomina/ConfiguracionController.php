<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\Nomina\NominaConfig;
use App\Services\Nomina\AttendanceService;
use App\Services\Nomina\CommissionCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        return view('nomina.configuracion.index', [
            'valorHoraExtra' => $this->attendance->valorHoraTrabajador(),
            'valorHoraExtraTrabajador' => $this->attendance->valorHoraTrabajador(),
            'valorHoraExtraSupervisor' => $this->attendance->valorHoraSupervisor(),
            'descuentoVentaPct' => NominaConfig::getDecimal('descuento_venta_pct', 25),
            'comisionSupervisorPct' => NominaConfig::getDecimal('comision_supervisor_pct', 0.05),
            'comisionMarketingPct' => NominaConfig::getDecimal('comision_marketing_pct', 0.10),
            'comisionNunesPct' => NominaConfig::getDecimal('comision_nunes_pct', 0.60),
            'comisionDigitalPct' => NominaConfig::getDecimal('comision_digital_pct', 0.30),
            'comisionPcpPct' => NominaConfig::getDecimal('comision_pcp_pct', 0.015),
            'comisionSambilPct' => NominaConfig::getDecimal('comision_sambil_pct', 0.20),
            'comisionTelefoniaPct' => NominaConfig::getDecimal('comision_telefonia_pct', 0.20),
            'comisionOtrosPct' => NominaConfig::getDecimal('comision_otros_pct', 1),
            'retencionComisionPct' => NominaConfig::getDecimal('retencion_comision_pct', 10),
            'comisionServicioTecnicoPct' => NominaConfig::getDecimal('comision_servicio_tecnico_pct', 50),
            'categoriasTelefonia' => $this->categorias->categorias(CommissionCategoryService::TELEFONIA),
            'categoriasOtros' => $this->categorias->categorias(CommissionCategoryService::OTROS),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'valor_hora_extra' => ['nullable', 'numeric', 'min:0'],
            'valor_hora_extra_trabajador' => ['nullable', 'required_without:valor_hora_extra', 'numeric', 'min:0'],
            'valor_hora_extra_supervisor' => ['nullable', 'numeric', 'min:0'],
            'descuento_venta_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'comision_supervisor_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'comision_marketing_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'comision_nunes_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'comision_digital_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'comision_pcp_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'comision_sambil_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
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
            'comision_nunes_pct',
            'comision_digital_pct',
            'comision_pcp_pct',
            'comision_sambil_pct',
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
}
