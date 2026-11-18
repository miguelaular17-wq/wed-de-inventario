@extends('layouts.app')

@section('title', 'Configuración de nómina')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Configuración</h1>
            <p class="muted" style="margin:4px 0 0;">Ajustes globales de nómina. Aplican a todos los empleados.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('nomina.configuracion.update') }}">
        @csrf @method('PUT')

        <div class="nomina-card" style="margin-top:16px; max-width:960px;">
            <h3>Inasistencias y horas extras</h3>
            <p class="muted">Valor por día: salario mensual ÷ 30. Las horas extras se pueden cambiar aquí.</p>
            <div class="nomina-form-grid">
                <div class="field">
                    <label>Hora extra trabajador (USD)</label>
                    <input type="number" step="0.01" min="0" name="valor_hora_extra_trabajador" value="{{ number_format($valorHoraExtraTrabajador ?? $valorHoraExtra, 2, '.', '') }}" required>
                </div>
                <div class="field">
                    <label>Hora extra supervisor (USD)</label>
                    <input type="number" step="0.01" min="0" name="valor_hora_extra_supervisor" value="{{ number_format($valorHoraExtraSupervisor ?? 1.5, 2, '.', '') }}" required>
                </div>
                <div class="field">
                    <label>Descuento de respaldo para datos antiguos (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="descuento_venta_pct" value="{{ number_format($descuentoVentaPct, 2, '.', '') }}" required>
                </div>
            </div>
        </div>

        <div class="nomina-card" style="margin-top:16px; max-width:960px;">
            <h3>Comisiones por rol o sede</h3>
            <p class="muted">Porcentajes de los modos que pagan sobre venta neta de tienda, equipo o sedes.</p>
            <div class="nomina-form-grid">
                <div class="field">
                    <label>Supervisor de sede (%)</label>
                    <input type="number" step="0.0001" min="0" max="100" name="comision_supervisor_pct" value="{{ number_format($comisionSupervisorPct, 4, '.', '') }}" required>
                </div>
                <div class="field">
                    <label>Encargado de tienda (%)</label>
                    <input type="number" step="0.0001" min="0" max="100" name="comision_encargado_pct" value="{{ number_format($comisionEncargadoPct, 4, '.', '') }}" required>
                </div>
                <div class="field">
                    <label>Supervisor de equipo / Marketing (%)</label>
                    <input type="number" step="0.0001" min="0" max="100" name="comision_marketing_pct" value="{{ number_format($comisionMarketingPct, 4, '.', '') }}" required>
                </div>
                <div class="field">
                    <label>Digital — ventas de trabajadores (%)</label>
                    <input type="number" step="0.0001" min="0" max="100" name="comision_digital_pct" value="{{ number_format($comisionDigitalPct, 4, '.', '') }}" required>
                </div>
                <div class="field">
                    <label>Nunes — venta total sede (%)</label>
                    <input type="number" step="0.0001" min="0" max="100" name="comision_nunes_pct" value="{{ number_format($comisionNunesPct, 4, '.', '') }}" required>
                </div>
                <div class="field">
                    <label>PCP — venta neta tienda (%)</label>
                    <input type="number" step="0.0001" min="0" max="100" name="comision_pcp_pct" value="{{ number_format($comisionPcpPct, 4, '.', '') }}" required>
                </div>
                <div class="field">
                    <label>Sambil — venta neta tienda (%)</label>
                    <input type="number" step="0.0001" min="0" max="100" name="comision_sambil_pct" value="{{ number_format($comisionSambilPct, 4, '.', '') }}" required>
                </div>
                <div class="field">
                    <label>Sambil sin Honor/Samsung — venta neta tienda (%)</label>
                    <input type="number" step="0.0001" min="0" max="100" name="comision_sambil_sin_hs_pct" value="{{ number_format($comisionSambilSinHsPct, 4, '.', '') }}" required>
                </div>
                <div class="field">
                    <label>Tecno House sin Honor/Samsung — venta neta tienda (%)</label>
                    <input type="number" step="0.0001" min="0" max="100" name="comision_tecnohouse_sin_hs_pct" value="{{ number_format($comisionTecnohouseSinHsPct, 4, '.', '') }}" required>
                </div>
                <div class="field">
                    <label>Colaborador — Sambil+Doral+Zamora+Centro+Virtudes (%)</label>
                    <input type="number" step="0.0001" min="0" max="100" name="comision_colaborador_pct" value="{{ number_format($comisionColaboradorPct, 4, '.', '') }}" required>
                </div>
            </div>
        </div>

        <div class="nomina-card" style="margin-top:16px; max-width:960px;">
            <h3>Comisiones de ventas propias</h3>
            <p class="muted">Ventas propias {{ number_format($comisionTelefoniaPct, 2) }}% telefonía · sin Honor/Samsung {{ number_format($comisionTelefoniaSinHsPct, 2) }}% · resto {{ number_format($comisionOtrosPct, 2) }}%.</p>
            <div class="nomina-form-grid">
                <div class="field">
                    <label>Telefonía (%)</label>
                    <input type="number" step="0.0001" min="0" max="100" name="comision_telefonia_pct" value="{{ number_format($comisionTelefoniaPct, 4, '.', '') }}" required>
                </div>
                <div class="field">
                    <label>Telefonía sin Honor/Samsung (%)</label>
                    <input type="number" step="0.0001" min="0" max="100" name="comision_telefonia_sin_hs_pct" value="{{ number_format($comisionTelefoniaSinHsPct, 4, '.', '') }}" required>
                </div>
                <div class="field">
                    <label>Resto de categorías (%)</label>
                    <input type="number" step="0.0001" min="0" max="100" name="comision_otros_pct" value="{{ number_format($comisionOtrosPct, 4, '.', '') }}" required>
                </div>
                <div class="field">
                    <label>Participación Servicio Técnico (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="comision_servicio_tecnico_pct" value="{{ number_format($comisionServicioTecnicoPct, 2, '.', '') }}" required>
                </div>
                <div class="field">
                    <label>Retención sobre comisión (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="retencion_comision_pct" value="{{ number_format($retencionComisionPct, 2, '.', '') }}" required>
                </div>
            </div>
        </div>

        <div style="margin-top:14px; max-width:960px; display:flex; justify-content:flex-end;">
            <button class="btn primary" type="submit">Guardar</button>
        </div>
    </form>

    <div class="nomina-card" style="margin-top:16px;">
        <h3>Grupos de comisión (ventas propias)</h3>
        <p class="muted">Telefonía al {{ number_format($comisionTelefoniaPct, 2) }}% (sin Honor/Samsung {{ number_format($comisionTelefoniaSinHsPct, 2) }}%). Todo lo demás, incluidas categorías desconocidas, al {{ number_format($comisionOtrosPct, 2) }}%.</p>
        <div class="nomina-split">
            <div>
                <h4>Telefonía</h4>
                <ul>
                    @forelse($categoriasTelefonia as $item)
                        <li>{{ $item->categoria }}</li>
                    @empty
                        <li class="muted">Sin categorías. Corre las migraciones.</li>
                    @endforelse
                </ul>
            </div>
            <div>
                <h4>Otros</h4>
                <ul>
                    @forelse($categoriasOtros as $item)
                        <li>{{ $item->categoria }}</li>
                    @empty
                        <li class="muted">Sin categorías. Corre las migraciones.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
