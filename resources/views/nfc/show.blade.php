@extends('layouts.app')

@section('title', 'NFC · '.$tarjeta->cliente_nombre)

@section('content')
<div class="panel nomina-page" style="max-width:880px;margin:0 auto;">
    <div class="panel-header-flex">
        <div>
            <a href="{{ route('nfc.index') }}" class="muted" style="font-size:.82rem;">← Tarjetas NFC</a>
            <h1 style="margin:4px 0 0;">{{ $tarjeta->cliente_nombre }}</h1>
            <p class="muted" style="margin:4px 0 0;">
                {{ $tarjeta->etiquetaEstado() }}
                @if($tarjeta->uid) · UID {{ $tarjeta->uid }} @endif
                @if($tarjeta->asignador) · por {{ $tarjeta->asignador->name }} @endif
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            @if($tarjeta->isActiva())
                <form method="POST" action="{{ route('nfc.desactivar', $tarjeta) }}" onsubmit="return confirm('¿Desactivar esta tarjeta?')">
                    @csrf
                    <button class="btn secondary" type="submit">Desactivar</button>
                </form>
            @else
                <form method="POST" action="{{ route('nfc.reactivar', $tarjeta) }}">
                    @csrf
                    <button class="btn primary" type="submit">Reactivar</button>
                </form>
            @endif
        </div>
    </div>

    <div class="nomina-card" style="margin-top:16px;">
        <h3 style="margin-top:0;">URL para grabar en la NFC</h3>
        <p class="muted" style="margin:0 0 10px;">
            Escribe esta dirección en el chip (NDEF URL). Al acercar el teléfono abrirá la ficha y pedirá usuario si no hay sesión.
        </p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <input id="nfc-url" readonly value="{{ $urlNfc }}" style="flex:1;min-width:220px;font-family:monospace;font-size:.9rem;">
            <button type="button" class="btn primary" id="nfc-copy">Copiar URL</button>
            <button type="button" class="btn secondary" id="nfc-write">Escribir en NFC</button>
        </div>
        <p id="nfc-msg" class="muted" style="margin:8px 0 0;font-size:.85rem;"></p>
    </div>

    <div class="nomina-card" style="margin-top:16px;">
        <h3 style="margin-top:0;">Datos del cliente</h3>
        <form method="POST" action="{{ route('nfc.update', $tarjeta) }}">
            @csrf
            @method('PUT')
            <div class="filter-bar" style="flex-wrap:wrap;">
                <div class="field field-wide">
                    <label>Nombre *</label>
                    <input name="cliente_nombre" value="{{ old('cliente_nombre', $tarjeta->cliente_nombre) }}" required>
                </div>
                <div class="field">
                    <label>Cédula</label>
                    <input name="cliente_cedula" value="{{ old('cliente_cedula', $tarjeta->cliente_cedula) }}">
                </div>
                <div class="field">
                    <label>Teléfono</label>
                    <input name="cliente_telefono" value="{{ old('cliente_telefono', $tarjeta->cliente_telefono) }}">
                </div>
                <div class="field">
                    <label>Correo</label>
                    <input type="email" name="cliente_email" value="{{ old('cliente_email', $tarjeta->cliente_email) }}">
                </div>
                <div class="field">
                    <label>UID del chip</label>
                    <input name="uid" value="{{ old('uid', $tarjeta->uid) }}" style="text-transform:uppercase;" placeholder="Opcional">
                </div>
                <div class="field field-wide">
                    <label>Notas</label>
                    <textarea name="notas" rows="3" style="width:100%;">{{ old('notas', $tarjeta->notas) }}</textarea>
                </div>
            </div>
            <button class="btn primary" type="submit" style="margin-top:12px;">Guardar</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const url = document.getElementById('nfc-url')?.value || '';
    const msg = document.getElementById('nfc-msg');
    const setMsg = (t) => { if (msg) msg.textContent = t; };

    document.getElementById('nfc-copy')?.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(url);
            setMsg('URL copiada.');
        } catch (e) {
            document.getElementById('nfc-url')?.select();
            setMsg('Selecciona y copia manualmente (Ctrl+C).');
        }
    });

    document.getElementById('nfc-write')?.addEventListener('click', async () => {
        if (!('NDEFReader' in window)) {
            setMsg('Este navegador no soporta Web NFC. Usa Chrome en Android, o copia la URL y grábala con una app NFC.');
            return;
        }
        try {
            setMsg('Acerca la tarjeta NFC al teléfono…');
            const ndef = new NDEFReader();
            await ndef.write({ records: [{ recordType: 'url', data: url }] });
            setMsg('URL escrita en la tarjeta. Ya puedes probar acercándola de nuevo.');
        } catch (e) {
            setMsg('No se pudo escribir: ' + (e?.message || 'permiso denegado o chip no compatible.'));
        }
    });
})();
</script>
@endpush
