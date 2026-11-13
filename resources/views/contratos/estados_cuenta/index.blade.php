@extends('layouts.app')
@section('title', 'Estados de cuenta · Contratos')
@section('content')
<div style="padding: 20px; max-width: 1100px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:16px;">
        <div>
            <h2 style="color: var(--blue); font-weight: 700; margin: 0;">📑 Estados de cuenta</h2>
            <p class="muted" style="margin:4px 0 0;">Personas que responden por varios contratos (intermediario o responsable).</p>
        </div>
        <a href="{{ route('contratos.lista') }}" style="padding: 8px 16px; background: #e0f2fe; color: #0284c7; border-radius: 6px; text-decoration: none; font-weight: 500;">← Contratos</a>
    </div>

    @if(session('success'))
        <div style="background:#d1e7dd; color:#0f5132; padding:12px 16px; border-radius:8px; margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div style="background:#fee2e2; color:#991b1b; padding:12px 16px; border-radius:8px; margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    <div class="panel" style="padding:16px; margin-bottom:18px;">
        <h3 style="margin:0 0 10px; font-size:1rem;">Nueva persona</h3>
        <form method="POST" action="{{ route('contratos.estados_cuenta.store') }}" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            @csrf
            <div><label style="display:block; font-size:.8rem; font-weight:600;">Nombre *</label>
                <input type="text" name="nombre" required maxlength="200" placeholder="Ej. DIECEL ZÚÑIGA" style="padding:8px 10px; border:1px solid #ccc; border-radius:6px; width:260px;"></div>
            <div><label style="display:block; font-size:.8rem; font-weight:600;">Cédula / RIF</label>
                <input type="text" name="documento" maxlength="40" placeholder="V24.306.133" style="padding:8px 10px; border:1px solid #ccc; border-radius:6px; width:160px;"></div>
            <div><label style="display:block; font-size:.8rem; font-weight:600;">Teléfono</label>
                <input type="text" name="telefono" maxlength="60" style="padding:8px 10px; border:1px solid #ccc; border-radius:6px; width:150px;"></div>
            <button type="submit" style="padding:9px 16px; background:#059669; color:#fff; border:0; border-radius:6px; font-weight:700; cursor:pointer;">+ Crear</button>
        </form>
    </div>

    <div class="panel" style="padding:0; overflow:hidden;">
        <table class="data-table" style="width:100%;">
            <thead><tr><th>Persona</th><th>Cédula / RIF</th><th style="text-align:right;">Contratos</th><th style="text-align:right;">Total deuda</th><th></th></tr></thead>
            <tbody>
                @forelse($intermediarios as $i)
                    <tr>
                        <td><a href="{{ route('contratos.estados_cuenta.show', $i) }}" style="font-weight:700; color:var(--blue);">{{ $i->nombre }}</a>
                            @if($i->telefono)<div class="muted" style="font-size:.8rem;">{{ $i->telefono }}</div>@endif</td>
                        <td>{{ $i->documento ?: '—' }}</td>
                        <td style="text-align:right;">{{ $i->_contratos }}</td>
                        <td style="text-align:right; font-weight:700; color:#b91c1c;">${{ number_format($i->_deuda, 2) }}</td>
                        <td style="text-align:right; white-space:nowrap;">
                            <a href="{{ route('contratos.estados_cuenta.show', $i) }}" style="padding:5px 10px; background:#e0f2fe; color:#0369a1; border-radius:6px; text-decoration:none; font-weight:600; font-size:.85rem;">Ver</a>
                            <a href="{{ route('contratos.estados_cuenta.pdf', $i) }}" target="_blank" rel="noopener" style="padding:5px 10px; background:var(--blue); color:#fff; border-radius:6px; text-decoration:none; font-weight:600; font-size:.85rem;">PDF</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted" style="text-align:center; padding:28px;">Aún no hay personas. Crea una arriba y agrégale sus contratos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
