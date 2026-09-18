@extends('layouts.app')

@section('title', 'Recompensas NFC')

@section('content')
<div class="panel nomina-page nfc-show">
    <div class="nfc-hero">
        <div class="nfc-hero-main">
            <a href="{{ route('nfc.index') }}" class="nfc-back">← Tarjetas NFC</a>
            <div class="nfc-hero-title-row">
                <h1>Recompensas</h1>
            </div>
            <p class="nfc-hero-meta">
                <span>Define premios y cuántos puntos cuesta cada uno. Se canjean desde la ficha de la tarjeta.</span>
            </p>
        </div>
    </div>

    @if(session('status'))
        <div class="nfc-flash">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="nfc-flash is-error">{{ $errors->first() }}</div>
    @endif

    <section class="nomina-card nfc-section" style="margin-top:16px;">
        <h3>Nueva recompensa</h3>
        <form method="POST" action="{{ route('nfc.recompensas.store') }}">
            @csrf
            <div class="nfc-form-grid">
                <div class="field field-wide">
                    <label>Nombre *</label>
                    <input name="nombre" value="{{ old('nombre') }}" required placeholder="Ej. Café gratis">
                </div>
                <div class="field">
                    <label>Puntos que vale *</label>
                    <input type="number" name="puntos_costo" min="1" value="{{ old('puntos_costo', 100) }}" required>
                </div>
                <div class="field">
                    <label>Orden</label>
                    <input type="number" name="orden" min="0" value="{{ old('orden', 0) }}">
                </div>
                <div class="field field-wide">
                    <label>Descripción</label>
                    <input name="descripcion" value="{{ old('descripcion') }}" placeholder="Detalle opcional">
                </div>
                <div class="field">
                    <label class="nfc-check-label">
                        <input type="hidden" name="activa" value="0">
                        <input type="checkbox" name="activa" value="1" checked>
                        Activa (disponible para canje)
                    </label>
                </div>
            </div>
            <button class="btn primary" type="submit" style="margin-top:12px;">Crear recompensa</button>
        </form>
    </section>

    <section class="nomina-card nfc-section" style="margin-top:16px;">
        <h3>Catálogo ({{ $recompensas->count() }})</h3>
        @forelse($recompensas as $r)
            <div class="nfc-reward-admin {{ $r->activa ? '' : 'is-off' }}">
                <form method="POST" action="{{ route('nfc.recompensas.update', $r) }}" class="nfc-reward-admin-form">
                    @csrf
                    @method('PUT')
                    <div class="nfc-form-grid">
                        <div class="field field-wide">
                            <label>Nombre</label>
                            <input name="nombre" value="{{ $r->nombre }}" required>
                        </div>
                        <div class="field">
                            <label>Puntos que vale</label>
                            <input type="number" name="puntos_costo" min="1" value="{{ $r->puntos_costo }}" required>
                        </div>
                        <div class="field">
                            <label>Orden</label>
                            <input type="number" name="orden" min="0" value="{{ $r->orden }}">
                        </div>
                        <div class="field field-wide">
                            <label>Descripción</label>
                            <input name="descripcion" value="{{ $r->descripcion }}">
                        </div>
                        <div class="field">
                            <label class="nfc-check-label">
                                <input type="hidden" name="activa" value="0">
                                <input type="checkbox" name="activa" value="1" @checked($r->activa)>
                                Activa
                            </label>
                        </div>
                    </div>
                    <div class="nfc-reward-admin-actions">
                        <button class="btn primary" type="submit">Guardar</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('nfc.recompensas.destroy', $r) }}" onsubmit="return confirm('¿Eliminar «{{ $r->nombre }}»?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn secondary" type="submit">Eliminar</button>
                </form>
            </div>
        @empty
            <p class="muted" style="margin:0;">Aún no hay recompensas. Crea la primera arriba.</p>
        @endforelse
    </section>
</div>
@endsection
