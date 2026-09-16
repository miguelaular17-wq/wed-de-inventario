@php
    $totalesPorGrupo = $totalesPorGrupo ?? collect();
    $tasaBcv = $tasaBcv ?? 0;
    $tasaBcvEtiqueta = $tasaBcvEtiqueta ?? 'Tasa BCV';
    $filtroTargets = $filtroTargets ?? [];
@endphp
@if($totalesPorGrupo->isNotEmpty())
<div class="nomina-card" style="margin-top:16px;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
        <div>
            <h3 style="margin:0;">Totales por sede y área</h3>
            <p class="muted" style="margin:4px 0 0;">
                Asignaciones, deducciones y total pagado en divisas (USD) y bolívares.
                {{ $tasaBcvEtiqueta }}: <strong>{{ number_format($tasaBcv, 2) }}</strong>.
            </p>
        </div>
        <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
            @if(!empty($pdfRoute))
                <a class="btn secondary" href="{{ $pdfRoute }}">PDF con ventas netas</a>
            @endif
            <label class="field" style="margin:0;min-width:220px;">
                <span style="font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase;">Ver sede o área</span>
                <select class="filtro-sede-area" data-targets="{{ implode(',', $filtroTargets) }}" style="margin-top:4px;width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;font-weight:600;">
                    <option value="">Todas</option>
                    @foreach($totalesPorGrupo as $grupo)
                        <option value="{{ $grupo['clave'] }}">{{ $grupo['etiqueta'] }} · {{ $grupo['nombre'] }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>
    <div class="table-wrap" style="margin-top:12px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Sede / área</th>
                    <th>Personas</th>
                    <th>Asignaciones</th>
                    <th>Deducciones</th>
                    <th>Total pagado USD</th>
                    <th>Total pagado Bs</th>
                </tr>
            </thead>
            <tbody>
                @foreach($totalesPorGrupo as $grupo)
                    <tr>
                        <td>{{ $grupo['etiqueta'] }}</td>
                        <td><strong>{{ $grupo['nombre'] }}</strong></td>
                        <td>{{ $grupo['empleados'] }}</td>
                        <td>${{ number_format($grupo['asignaciones'], 2) }}</td>
                        <td>${{ number_format($grupo['deducciones'], 2) }}</td>
                        <td><strong>${{ number_format($grupo['pagar_usd'], 2) }}</strong></td>
                        <td>Bs {{ number_format($grupo['pagar_bs'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2"><strong>Total general</strong></td>
                    <td><strong>{{ $totalesPorGrupo->sum('empleados') }}</strong></td>
                    <td><strong>${{ number_format($totalesPorGrupo->sum('asignaciones'), 2) }}</strong></td>
                    <td><strong>${{ number_format($totalesPorGrupo->sum('deducciones'), 2) }}</strong></td>
                    <td><strong>${{ number_format($totalesPorGrupo->sum('pagar_usd'), 2) }}</strong></td>
                    <td><strong>Bs {{ number_format($totalesPorGrupo->sum('pagar_bs'), 2) }}</strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endif

@once
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('select.filtro-sede-area').forEach(function (select) {
            const ids = (select.getAttribute('data-targets') || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
            select.addEventListener('change', function () {
                const clave = select.value;
                ids.forEach(function (id) {
                    const table = document.getElementById(id);
                    if (!table) return;
                    table.querySelectorAll('tbody tr[data-grupo-clave]').forEach(function (row) {
                        row.style.display = (!clave || row.getAttribute('data-grupo-clave') === clave) ? '' : 'none';
                    });
                });
            });
        });
    });
    </script>
    @endpush
@endonce
