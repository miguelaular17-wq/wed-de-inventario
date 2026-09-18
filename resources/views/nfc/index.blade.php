@extends('layouts.app')

@section('title', 'Tarjetas NFC')

@section('content')
<div class="panel nomina-page">
    <div class="panel-header-flex">
        <div>
            <h1 style="margin:0;">Tarjetas NFC</h1>
            <p class="muted" style="margin:4px 0 0;">
                Asigna una tarjeta a un cliente y graba en el chip la URL de su ficha. Al acercarla, pedirá iniciar sesión para ver o editar.
            </p>
        </div>
        <div>
            <a class="btn secondary" href="{{ route('nfc.recompensas.index') }}">Recompensas</a>
        </div>
    </div>

    <div class="nomina-kpis">
        <div class="nomina-kpi"><span>Activas</span><strong>{{ $kpis['activas'] }}</strong></div>
        <div class="nomina-kpi"><span>Inactivas</span><strong>{{ $kpis['inactivas'] }}</strong></div>
        <div class="nomina-kpi"><span>Total</span><strong>{{ $kpis['total'] }}</strong></div>
    </div>

    <div class="nomina-card" style="margin-top:16px;">
        <h3>Asignar tarjeta a cliente</h3>
        <form method="POST" action="{{ route('nfc.store') }}" class="filter-bar" style="flex-wrap:wrap;align-items:flex-end;">
            @csrf
            <div class="field field-wide">
                <label>Nombre del cliente *</label>
                <input name="cliente_nombre" value="{{ old('cliente_nombre') }}" required placeholder="Nombre completo" autofocus>
            </div>
            <div class="field">
                <label>Cédula</label>
                <input name="cliente_cedula" value="{{ old('cliente_cedula') }}" placeholder="V-12345678">
            </div>
            <div class="field">
                <label>Teléfono</label>
                <input name="cliente_telefono" value="{{ old('cliente_telefono') }}" placeholder="0412…">
            </div>
            <div class="field">
                <label>Correo</label>
                <input type="email" name="cliente_email" value="{{ old('cliente_email') }}" placeholder="opcional">
            </div>
            <div class="field">
                <label>UID del chip (opcional)</label>
                <input name="uid" value="{{ old('uid') }}" placeholder="Hex del NFC si lo leíste" style="text-transform:uppercase;">
            </div>
            <div class="field field-wide">
                <label>Notas</label>
                <input name="notas" value="{{ old('notas') }}" placeholder="Detalle interno">
            </div>
            <div class="field" style="display:flex;align-items:flex-end;">
                <button class="btn primary" type="submit">Asignar y generar URL</button>
            </div>
        </form>
    </div>

    <form method="GET" class="filter-bar" style="margin-top:16px;">
        <div class="field field-wide">
            <label>Buscar</label>
            <input type="text" name="q" value="{{ $q }}" placeholder="Nombre, cédula, teléfono, token o UID">
        </div>
        <div class="field" style="display:flex;align-items:flex-end;">
            <button class="btn primary" type="submit">Buscar</button>
        </div>
    </form>

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Cédula</th>
                    <th>Teléfono</th>
                    <th>UID</th>
                    <th class="num">Saldo</th>
                    <th class="num">Puntos</th>
                    <th>Estado</th>
                    <th>Asignada</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($tarjetas as $t)
                    <tr>
                        <td>
                            <a href="{{ route('nfc.show', $t) }}"><strong>{{ $t->cliente_nombre }}</strong></a>
                            <div class="muted" style="font-size:.75rem;">token {{ $t->token }}</div>
                        </td>
                        <td>{{ $t->cliente_cedula ?: '—' }}</td>
                        <td>{{ $t->cliente_telefono ?: '—' }}</td>
                        <td style="font-family:monospace;font-size:.85rem;">{{ $t->uid ?: '—' }}</td>
                        <td class="num">${{ number_format((float) ($t->saldo ?? 0), 2) }}</td>
                        <td class="num">{{ number_format((int) ($t->puntos ?? 0)) }}</td>
                        <td>{{ $t->etiquetaEstado() }}</td>
                        <td>{{ $t->asignada_at?->format('d/m/Y H:i') ?: '—' }}</td>
                        <td><a class="btn secondary" href="{{ route('nfc.show', $t) }}">Abrir</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted">Aún no hay tarjetas asignadas.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div style="margin-top:12px;">{{ $tarjetas->links() }}</div>
    </div>
</div>
@endsection
