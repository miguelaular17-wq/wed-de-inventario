@extends('layouts.app')

@section('title', 'Visita · '.$visita->negocio_nombre)

@section('content')
@php $esImagen = fn ($u) => (bool) preg_match('/\.(jpe?g|png|gif|webp|heic)(\?|$)/i', (string) $u); @endphp
<div class="panel" style="max-width:980px; margin:0 auto;">
    <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center;">
        <div>
            <h1 style="margin:0;">{{ $visita->negocio_nombre }}</h1>
            <p class="muted" style="margin:4px 0 0;">Visita del {{ $visita->fecha->format('d/m/Y') }} · {{ $visita->usuario?->name }} · {{ $visita->etiquetaResultado() }}</p>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('ventas_mayor.visitas.index') }}" class="btn secondary">← Hoja de ruta</a>
            @if($visita->cliente_id)
                <a href="{{ route('ventas_mayor.create', ['cliente_id' => $visita->cliente_id]) }}" class="btn primary">+ Factura a este cliente</a>
            @else
                <form method="POST" action="{{ route('ventas_mayor.visitas.cliente', $visita) }}" onsubmit="@if(! $visita->negocio_documento) const d = prompt('RIF o cédula del negocio para registrarlo como cliente:'); if (!d) return false; this.documento.value = d; @endif return true;">
                    @csrf
                    <input type="hidden" name="documento">
                    <button type="submit" class="btn primary">Registrar como cliente y facturar</button>
                </form>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert success" style="margin-top:12px;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert error" style="margin-top:12px;">⚠️ {{ $errors->first() }}</div>
    @endif

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:12px; margin-top:14px;">
        <div class="panel" style="padding:12px; border-left:4px solid #2563eb;">
            <div class="muted" style="font-size:.72rem; font-weight:700;">NEGOCIO</div>
            <div style="font-weight:700;">{{ $visita->negocio_nombre }} @if($visita->cliente_id)<span class="tag ok" style="font-size:.65rem;">Cliente</span>@endif</div>
            <div style="font-size:.85rem;">{{ collect([$visita->negocio_documento, $visita->negocio_telefono])->filter()->implode(' · ') }}</div>
            <div class="muted" style="font-size:.82rem;">{{ collect([$visita->negocio_direccion, $visita->zona])->filter()->implode(' · ') }}</div>
            @if($visita->latitud && $visita->longitud)
                <a href="https://www.google.com/maps?q={{ $visita->latitud }},{{ $visita->longitud }}" target="_blank" rel="noopener" style="font-size:.8rem;">📍 Ver en el mapa</a>
            @endif
        </div>
        <div class="panel" style="padding:12px; border-left:4px solid #7c3aed;">
            <div class="muted" style="font-size:.72rem; font-weight:700;">ATENDIÓ</div>
            <div style="font-weight:700;">{{ $visita->atendio_nombre }}</div>
            <div style="font-size:.85rem;">{{ collect([$visita->atendio_cargo, $visita->atendio_documento ? 'C.I. '.$visita->atendio_documento : null, $visita->atendio_telefono])->filter()->implode(' · ') }}</div>
        </div>
        <div class="panel" style="padding:12px; border-left:4px solid #f59e0b;">
            <div class="muted" style="font-size:.72rem; font-weight:700;">RESULTADO</div>
            <div style="font-weight:700;">{{ $visita->etiquetaResultado() }}</div>
            <div style="font-size:.85rem;">Próxima visita: {{ $visita->proxima_visita?->format('d/m/Y') ?? '—' }}</div>
        </div>
    </div>

    @if($visita->observaciones)
        <p style="margin-top:12px;"><strong>Observaciones:</strong> {{ $visita->observaciones }}</p>
    @endif

    <div style="display:grid; grid-template-columns:minmax(260px,1fr) 2fr; gap:14px; margin-top:14px;">
        <div>
            <h3 style="margin:0 0 6px;">Firma</h3>
            <div style="border:1px solid #e2e8f0; border-radius:10px; padding:8px; background:#fff;">
                <img src="{{ $visita->firma_url }}" alt="Firma" style="width:100%; max-height:180px; object-fit:contain;">
                <div class="muted" style="font-size:.75rem; text-align:center; border-top:1px solid #e2e8f0; padding-top:4px;">{{ $visita->atendio_nombre }}</div>
            </div>
        </div>
        <div>
            <h3 style="margin:0 0 6px;">Evidencia ({{ count($visita->evidencias ?? []) }})</h3>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                @foreach($visita->evidencias ?? [] as $url)
                    <a href="{{ $url }}" target="_blank" rel="noopener">
                        @if($esImagen($url))
                            <img src="{{ $url }}" alt="Evidencia" style="width:160px; height:160px; object-fit:cover; border-radius:10px; border:1px solid #e2e8f0;">
                        @else
                            <div style="width:160px; height:160px; display:flex; align-items:center; justify-content:center; background:#f1f5f9; border-radius:10px; font-weight:700;">📄 Archivo</div>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    @if($anteriores->isNotEmpty())
        <h3 style="margin:18px 0 6px;">Otras visitas a este negocio</h3>
        <ul style="margin:0; padding-left:18px; font-size:.88rem;">
            @foreach($anteriores as $a)
                <li><a href="{{ route('ventas_mayor.visitas.show', $a) }}">{{ $a->fecha->format('d/m/Y') }}</a> · {{ $a->etiquetaResultado() }} · atendió {{ $a->atendio_nombre }}</li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
