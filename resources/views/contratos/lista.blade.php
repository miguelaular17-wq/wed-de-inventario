@extends('layouts.app')
@section('title', 'Lista de Contratos')
@section('content')
<div style="padding: 20px; max-width: 1400px; margin: 0 auto;">

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
        <h2 style="color: var(--blue); font-weight: 700; margin: 0;">📋 Contratos</h2>
        <div style="display: flex; gap: 10px;">
            <a href="{{ route('contratos.index') }}" style="padding: 8px 16px; background: #e0f2fe; color: #0284c7; border-radius: 6px; text-decoration: none; font-weight: 500;">← Dashboard</a>
            <a href="{{ route('contratos.create') }}" style="padding: 8px 16px; background: #059669; color: white; border-radius: 6px; text-decoration: none; font-weight: 600;">+ Nuevo</a>
        </div>
    </div>

    @if(session('success'))
        <div style="background: #d1e7dd; color: #0f5132; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">{{ session('success') }}</div>
    @endif

    {{-- Filtros --}}
    <div class="panel" style="padding: 16px; margin-bottom: 20px;">
        <form method="GET" action="{{ route('contratos.lista') }}" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
            <div>
                <label style="display: block; font-size: 0.8rem; font-weight: 500; margin-bottom: 3px;">Buscar</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Cliente, contrato, contacto..." style="padding: 7px 12px; border: 1px solid #ccc; border-radius: 6px; width: 250px;">
            </div>
            <div>
                <label style="display: block; font-size: 0.8rem; font-weight: 500; margin-bottom: 3px;">Sede</label>
                <select name="sede" style="padding: 7px 12px; border: 1px solid #ccc; border-radius: 6px;">
                    <option value="">Todas</option>
                    @foreach($sedes as $s)
                        <option value="{{ $s }}" {{ request('sede') == $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 0.8rem; font-weight: 500; margin-bottom: 3px;">Asesor</label>
                <select name="responsable_id" style="padding: 7px 12px; border: 1px solid #ccc; border-radius: 6px;">
                    <option value="">Todos</option>
                    @foreach($asesores as $a)
                        <option value="{{ $a->id }}" {{ request('responsable_id') == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" style="padding: 7px 20px; background: var(--blue); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Buscar</button>
        </form>
    </div>

    {{-- Tabla --}}
    <div class="panel" style="padding: 0; overflow: hidden;">
        <div class="table-wrap">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Contrato</th>
                        <th>Cliente</th>
                        <th>Contacto</th>
                        <th style="text-align: right;">Capital</th>
                        <th style="text-align: right;">Cuota</th>
                        <th style="text-align: right;">Saldo Pend.</th>
                        <th style="text-align: center;">Días Atraso</th>
                        <th style="text-align: center;">Estatus</th>
                        <th style="text-align: center;">Próx. Venc.</th>
                        <th>Asesor</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contratos as $c)
                        @php
                            $estatus = $c->_estatus_general;
                            $rowColor = match($estatus) {
                                'VENCIDO' => 'background: #fef2f2;',
                                'PAGADO' => 'background: #f0fdf4;',
                                default => '',
                            };
                        @endphp
                        <tr style="{{ $rowColor }}">
                            <td>
                                <a href="{{ route('contratos.show', $c->id) }}" style="font-weight: 600; color: var(--blue); text-decoration: none;">
                                    {{ $c->numero_contrato }}
                                </a>
                            </td>
                            <td style="font-weight: 500;">{{ $c->cliente }}</td>
                            <td style="color: #64748b; font-size: 0.9rem;">{{ $c->contacto ?: '—' }}</td>
                            <td style="text-align: right; font-weight: 500;">${{ number_format($c->capital, 2) }}</td>
                            <td style="text-align: right;">${{ number_format($c->cuota_fija, 2) }}</td>
                            <td style="text-align: right; font-weight: 600; color: {{ $c->_saldo_pendiente > 0 ? '#dc2626' : '#059669' }};">
                                ${{ number_format($c->_saldo_pendiente, 2) }}
                            </td>
                            <td style="text-align: center;">
                                @if($c->_dias_atraso > 0)
                                    <span style="background: #fef2f2; color: #dc2626; padding: 3px 8px; border-radius: 10px; font-size: 0.8rem; font-weight: 600;">{{ $c->_dias_atraso }}d</span>
                                @else
                                    <span style="color: #94a3b8;">—</span>
                                @endif
                            </td>
                            <td style="text-align: center;">
                                @php
                                    $badge = match($estatus) {
                                        'VENCIDO' => ['bg' => '#dc2626', 'text' => 'VENCIDO'],
                                        'PAGADO' => ['bg' => '#059669', 'text' => 'PAGADO'],
                                        default => ['bg' => '#3b82f6', 'text' => 'ACTIVO'],
                                    };
                                @endphp
                                <span style="background: {{ $badge['bg'] }}; color: white; padding: 3px 10px; border-radius: 10px; font-size: 0.75rem; font-weight: 700;">{{ $badge['text'] }}</span>
                            </td>
                            <td style="text-align: center; font-size: 0.85rem;">
                                {{ $c->_proxima_cuota?->fecha_vencimiento?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td style="font-size: 0.85rem; color: #475569;">{{ $c->responsable?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" style="text-align: center; padding: 40px; color: #94a3b8;">No hay contratos registrados. <a href="{{ route('contratos.create') }}">Crear uno</a> o importar desde Excel.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div style="margin-top: 16px;">{{ $contratos->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
