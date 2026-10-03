@php
    $filas = $filas ?? collect();
    $puede = $puede ?? false;
    $campo = $campo ?? null;
    $campoDe = $campoDe ?? null;
    $etiqueta = $etiqueta ?? fn ($fila) => $fila->motivo ?: '—';
@endphp
<div class="table-wrap" style="margin-top:8px;">
    <table class="data-table">
        <thead>
            <tr>
                @if($puede)
                    <th style="width:36px;"></th>
                @endif
                <th>Empleado</th>
                <th>Detalle</th>
                <th>Monto</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($filas as $fila)
                <tr>
                    @if($puede)
                        <td>
                            <input
                                type="checkbox"
                                name="{{ $campoDe ? $campoDe($fila) : $campo }}[]"
                                value="{{ $fila->id }}"
                                checked
                            >
                        </td>
                    @endif
                    <td>{{ $fila->empleado?->nombre() ?? '—' }}</td>
                    <td>{{ $etiqueta($fila) }}</td>
                    <td>${{ number_format((float) $fila->monto, 2) }}</td>
                    <td>{{ $fila->estado }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $puede ? 5 : 4 }}" class="muted">Nada cargado en esta quincena.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
