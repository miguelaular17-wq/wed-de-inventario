@extends('layouts.app')

@section('title', 'Cliente · '.$tarjeta->cliente_nombre)

@section('content')
<div class="panel nomina-page nfc-show">
    <div class="nfc-hero">
        <div class="nfc-hero-main">
            <p class="muted" style="margin:0;font-size:.82rem;">Acceso por tarjeta NFC</p>
            <div class="nfc-hero-title-row">
                <h1>{{ $tarjeta->cliente_nombre }}</h1>
                <span class="nfc-badge is-active">Activa</span>
            </div>
            <p class="nfc-hero-meta">
                @if($tarjeta->cliente_cedula)
                    <span>Cédula {{ $tarjeta->cliente_cedula }}</span>
                @endif
                @if($tarjeta->cliente_telefono)
                    <span>{{ $tarjeta->cliente_telefono }}</span>
                @endif
            </p>
        </div>
        <div class="nfc-hero-actions">
            <a class="btn secondary" href="{{ route('nfc.show', $tarjeta) }}">Gestión NFC</a>
        </div>
    </div>

    <div class="nfc-acceso-balances">
        <article class="nfc-balance-card nfc-balance-saldo">
            <header>
                <span class="nfc-balance-label">Saldo disponible</span>
                <strong class="nfc-balance-value">${{ number_format((float) $tarjeta->saldo, 2) }}</strong>
            </header>
        </article>
        <article class="nfc-balance-card nfc-balance-puntos">
            <header>
                <span class="nfc-balance-label">Puntos</span>
                <strong class="nfc-balance-value">{{ number_format((int) $tarjeta->puntos) }}</strong>
            </header>
        </article>
    </div>

    <div class="nomina-card nfc-section" style="margin-top:16px;">
        <h3>Ver / editar datos</h3>
        <form method="POST" action="{{ route('nfc.acceso.update', $tarjeta->token) }}">
            @csrf
            @method('PUT')
            <div class="nfc-form-grid">
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
                    <textarea name="notas" rows="3">{{ old('notas', $tarjeta->notas) }}</textarea>
                </div>
            </div>
            <button class="btn primary" type="submit" style="margin-top:14px;">Guardar cambios</button>
        </form>
    </div>

    <p class="muted" style="margin-top:20px;font-size:.8rem;">
        Último acceso: {{ $tarjeta->ultimo_acceso_at?->format('d/m/Y H:i') ?: 'ahora' }}
        · Usuario: {{ auth()->user()->name }}
    </p>
</div>
@endsection
