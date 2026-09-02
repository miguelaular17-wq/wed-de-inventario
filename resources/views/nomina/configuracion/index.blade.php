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

    <div class="nomina-card" style="margin-top:16px; max-width:900px;">
        <h3>Inasistencias y horas extras</h3>
        <p class="muted">Valor por día: salario mensual ÷ 30. Horas extras: trabajador $1.00 y supervisor $1.50 (se pueden cambiar aquí).</p>
        <form method="POST" action="{{ route('nomina.configuracion.update') }}" class="nomina-form-grid">
            @csrf @method('PUT')
            <div class="field">
                <label>Hora extra trabajador</label>
                <input type="number" step="0.01" min="0" name="valor_hora_extra_trabajador" value="{{ number_format($valorHoraExtraTrabajador ?? $valorHoraExtra, 2, '.', '') }}" required>
            </div>
            <div class="field">
                <label>Hora extra supervisor</label>
                <input type="number" step="0.01" min="0" name="valor_hora_extra_supervisor" value="{{ number_format($valorHoraExtraSupervisor ?? 1.5, 2, '.', '') }}" required>
            </div>
            <div class="field">
                <label>Descuento de respaldo para datos antiguos (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="descuento_venta_pct" value="{{ number_format($descuentoVentaPct, 2, '.', '') }}" required>
            </div>
            <div class="field">
                <label>Comisión supervisor de sede (%)</label>
                <input type="number" step="0.0001" min="0" max="100" name="comision_supervisor_pct" value="{{ number_format($comisionSupervisorPct, 4, '.', '') }}" required>
            </div>
            <div class="field">
                <label>Comisión supervisor de equipo / Marketing (%)</label>
                <input type="number" step="0.0001" min="0" max="100" name="comision_marketing_pct" value="{{ number_format($comisionMarketingPct, 4, '.', '') }}" required>
            </div>
            <div class="field">
                <label>Comisión Nunes — venta total sede (%)</label>
                <input type="number" step="0.0001" min="0" max="100" name="comision_nunes_pct" value="{{ number_format($comisionNunesPct, 4, '.', '') }}" required>
            </div>
            <div class="field">
                <label>Telefonía (%)</label>
                <input type="number" step="0.0001" min="0" max="100" name="comision_telefonia_pct" value="{{ number_format($comisionTelefoniaPct, 4, '.', '') }}" required>
            </div>
            <div class="field">
                <label>Resto de categorías (%)</label>
                <input type="number" step="0.0001" min="0" max="100" name="comision_otros_pct" value="{{ number_format($comisionOtrosPct, 4, '.', '') }}" required>
            </div>
            <div class="field">
                <label>Retención sobre comisión (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="retencion_comision_pct" value="{{ number_format($retencionComisionPct, 2, '.', '') }}" required>
            </div>
            <div class="field">
                <label>Participación Servicio Técnico (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="comision_servicio_tecnico_pct" value="{{ number_format($comisionServicioTecnicoPct, 2, '.', '') }}" required>
            </div>
            <div class="field field-wide muted">Ventas propias y Movistar: venta neta (cantidad × precio neto, igual que gerencial y Profit). Movistar no incluye facturas ST de Virtudes ni Movistar. Técnico: comisiona ST solo en Virtudes/Movistar; 058 contra esas líneas. Nunes: {{ number_format($comisionNunesPct, 2) }}% sobre venta neta total de la sede Nunes.</div>
            <div class="field" style="display:flex; align-items:flex-end;">
                <button class="btn primary" type="submit">Guardar</button>
            </div>
        </form>
    </div>

    <div class="nomina-card" style="margin-top:16px;">
        <h3>Grupos de comisión (ventas propias)</h3>
        <p class="muted">Telefonía al {{ number_format($comisionTelefoniaPct, 2) }}%. Todo lo demás, incluidas categorías desconocidas, al {{ number_format($comisionOtrosPct, 2) }}%.</p>
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
