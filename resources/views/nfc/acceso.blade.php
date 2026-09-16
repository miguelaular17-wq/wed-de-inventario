@extends('layouts.app')

@section('title', 'Cliente · '.$tarjeta->cliente_nombre)

@section('content')
<div class="panel nomina-page" style="max-width:880px;margin:0 auto;">
    <div class="panel-header-flex">
        <div>
            <p class="muted" style="margin:0;font-size:.82rem;">Acceso por tarjeta NFC</p>
            <h1 style="margin:4px 0 0;">{{ $tarjeta->cliente_nombre }}</h1>
            <p class="muted" style="margin:4px 0 0;">
                @if($tarjeta->cliente_cedula) Cédula {{ $tarjeta->cliente_cedula }} @endif
                @if($tarjeta->cliente_telefono) · {{ $tarjeta->cliente_telefono }} @endif
            </p>
        </div>
        <div>
            <a class="btn secondary" href="{{ route('nfc.show', $tarjeta) }}">Gestión NFC</a>
        </div>
    </div>

    <div class="nomina-card" style="margin-top:16px;">
        <h3 style="margin-top:0;">Ver / editar datos</h3>
        <form method="POST" action="{{ route('nfc.acceso.update', $tarjeta->token) }}">
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
                <div class="field field-wide">
                    <label>Notas</label>
                    <textarea name="notas" rows="3" style="width:100%;">{{ old('notas', $tarjeta->notas) }}</textarea>
                </div>
            </div>
            <button class="btn primary" type="submit" style="margin-top:12px;">Guardar cambios</button>
        </form>
    </div>

    <p class="muted" style="margin-top:20px;font-size:.8rem;">
        Último acceso: {{ $tarjeta->ultimo_acceso_at?->format('d/m/Y H:i') ?: 'ahora' }}
        · Usuario: {{ auth()->user()->name }}
    </p>
</div>
@endsection
