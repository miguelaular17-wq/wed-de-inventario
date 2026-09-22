@extends('layouts.app')

@section('title', 'Editar '.$orden->codigo())

@section('content')
@php
    $ev = is_array($orden->evidencias) ? $orden->evidencias : [];
    $evImgs = array_values(array_filter($ev['imagenes'] ?? []));
    $evVideo = $ev['video'] ?? null;
    $etiquetaBorrar = $orden->esGarantia() ? 'Eliminar garantía' : 'Eliminar ST';
@endphp
<div style="padding:20px;max-width:960px;margin:0 auto;">
    <a href="{{ route('servicio.ordenes.show', $orden) }}" style="color:#64748b;text-decoration:none;font-size:0.85rem;">← Volver a {{ $orden->codigo() }}</a>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin:10px 0 24px;">
        <h2 style="font-weight:700;margin:0;">Editar {{ $orden->codigo() }}</h2>
        @unless($orden->garantiaExternaBloqueada() || $orden->repuestos_descontados_at)
            <form method="POST" action="{{ route('servicio.ordenes.destroy', $orden) }}"
                onsubmit="return confirm('¿Eliminar {{ $orden->esGarantia() ? 'esta garantía' : 'este ST' }} {{ $orden->codigo() }}? Esta acción no se puede deshacer.');">
                @csrf
                @method('DELETE')
                <button class="btn secondary" type="submit" style="color:#b91c1c;border-color:#fecaca;">{{ $etiquetaBorrar }}</button>
            </form>
        @endunless
    </div>
    <div class="panel" style="padding:24px;">
        <form method="POST" action="{{ route('servicio.ordenes.update', $orden) }}" enctype="multipart/form-data" id="st-edit-form">
            @csrf
            @method('PUT')
            @include('servicio.ordenes._form')
            @include('servicio.ordenes._form_edit_extras')

            <div id="st-evidencias-edit" style="margin:24px 0;padding-top:20px;border-top:1px solid #e2e8f0;">
                <h3 style="margin:0 0 6px;">Evidencias del equipo</h3>
                <p class="muted" style="margin:0 0 12px;font-size:.85rem;">
                    Hasta 3 fotos y 1 video. Si subes fotos nuevas, reemplazan las actuales. El video nuevo reemplaza el anterior.
                </p>

                @if($evImgs !== [] || $evVideo)
                    <div style="margin-bottom:14px;padding:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
                        <strong style="font-size:.88rem;">Actuales</strong>
                        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px;">
                            @foreach($evImgs as $url)
                                <a href="{{ $url }}" target="_blank" rel="noopener" style="display:block;width:96px;height:96px;border-radius:8px;overflow:hidden;border:1px solid #e2e8f0;">
                                    <img src="{{ $url }}" alt="Evidencia" style="width:100%;height:100%;object-fit:cover;">
                                </a>
                            @endforeach
                        </div>
                        @if($evVideo)
                            <div style="margin-top:10px;max-width:360px;">
                                <video src="{{ $evVideo }}" controls style="width:100%;border-radius:8px;background:#0f172a;"></video>
                            </div>
                        @endif
                        <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:10px;font-size:.85rem;">
                            @if($evImgs !== [])
                                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                    <input type="checkbox" name="quitar_evidencia_imagenes" value="1">
                                    Quitar fotos actuales
                                </label>
                            @endif
                            @if($evVideo)
                                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                    <input type="checkbox" name="quitar_evidencia_video" value="1">
                                    Quitar video actual
                                </label>
                            @endif
                        </div>
                    </div>
                @endif

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div style="grid-column:1/-1;">
                        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Fotos (máx. 3)</label>
                        <input type="file" name="evidencia_imagenes[]" id="st-evidencia-imgs" accept="image/*" capture="environment" multiple
                            style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;background:#fff;">
                        <div class="muted" style="font-size:.78rem;margin-top:4px;">JPG/PNG · máx. 5 MB c/u</div>
                    </div>
                    <div style="grid-column:1/-1;">
                        <label style="display:block;font-weight:500;margin-bottom:4px;font-size:.9rem;">Video corto (opcional)</label>
                        <input type="file" name="evidencia_video" id="st-evidencia-video" accept="video/*" capture="environment"
                            style="width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;background:#fff;">
                        <div class="muted" style="font-size:.78rem;margin-top:4px;">MP4/MOV/WebM · máx. 20 MB</div>
                    </div>
                </div>
            </div>

            <button class="btn primary" type="submit">Guardar cambios</button>
        </form>
    </div>
</div>
<script>
(function () {
    const imgs = document.getElementById('st-evidencia-imgs');
    if (!imgs) return;
    imgs.addEventListener('change', function () {
        if (this.files && this.files.length > 3) {
            alert('Máximo 3 fotos.');
            this.value = '';
        }
    });
})();
</script>
@endsection
