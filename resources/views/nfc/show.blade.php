@extends('layouts.app')

@section('title', 'NFC · '.$tarjeta->cliente_nombre)

@section('content')
<div class="panel nomina-page nfc-show">
    <div class="nfc-hero">
        <div class="nfc-hero-main">
            <a href="{{ route('nfc.index') }}" class="nfc-back">← Tarjetas NFC</a>
            <div class="nfc-hero-title-row">
                <h1>{{ $tarjeta->cliente_nombre }}</h1>
                <span class="nfc-badge {{ $tarjeta->isActiva() ? 'is-active' : 'is-inactive' }}">
                    {{ $tarjeta->etiquetaEstado() }}
                </span>
            </div>
            <p class="nfc-hero-meta">
                @if($tarjeta->uid)
                    <span>UID <code>{{ $tarjeta->uid }}</code></span>
                @endif
                @if($tarjeta->cliente_cedula)
                    <span>Cédula {{ $tarjeta->cliente_cedula }}</span>
                @endif
                @if($tarjeta->asignador)
                    <span>Asignada por {{ $tarjeta->asignador->name }}</span>
                @endif
                @if($tarjeta->ultimo_acceso_at)
                    <span>Último acceso {{ $tarjeta->ultimo_acceso_at->format('d/m/Y H:i') }}</span>
                @endif
            </p>
        </div>
        <div class="nfc-hero-actions">
            <a class="btn secondary" href="{{ route('nfc.recompensas.index') }}">Recompensas</a>
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

    @if(session('status'))
        <div class="nfc-flash">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="nfc-flash is-error">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="nfc-balance-grid">
        <article class="nfc-balance-card nfc-balance-saldo">
            <header>
                <span class="nfc-balance-label">Saldo disponible</span>
                <strong class="nfc-balance-value">${{ number_format((float) $tarjeta->saldo, 2) }}</strong>
            </header>
            <div class="nfc-dual-actions">
                <form method="POST" action="{{ route('nfc.recargar', $tarjeta) }}" class="nfc-action-form">
                    @csrf
                    <div class="nfc-action-fields nfc-action-stack">
                        <div class="field">
                            <label>Recargar ($)</label>
                            <input type="number" name="monto" step="0.01" min="0.01" placeholder="0.00" required>
                        </div>
                        <div class="field">
                            <label>Concepto</label>
                            <input name="concepto" placeholder="Opcional">
                        </div>
                    </div>
                    <button class="btn primary" type="submit">+ Dinero</button>
                </form>
                <form method="POST" action="{{ route('nfc.restar_saldo', $tarjeta) }}" class="nfc-action-form" onsubmit="return confirm('¿Restar este monto del saldo?')">
                    @csrf
                    <div class="nfc-action-fields nfc-action-stack">
                        <div class="field">
                            <label>Restar ($)</label>
                            <input type="number" name="monto" step="0.01" min="0.01" placeholder="0.00" required>
                        </div>
                        <div class="field">
                            <label>Concepto</label>
                            <input name="concepto" placeholder="Opcional · consumo">
                        </div>
                    </div>
                    <button class="btn secondary" type="submit">− Dinero</button>
                </form>
            </div>
        </article>

        <article class="nfc-balance-card nfc-balance-puntos">
            <header>
                <span class="nfc-balance-label">Puntos</span>
                <strong class="nfc-balance-value">{{ number_format((int) $tarjeta->puntos) }}</strong>
            </header>
            <div class="nfc-dual-actions">
                <form method="POST" action="{{ route('nfc.puntos', $tarjeta) }}" class="nfc-action-form">
                    @csrf
                    <div class="nfc-action-fields nfc-action-stack">
                        <div class="field">
                            <label>Sumar puntos</label>
                            <input type="number" name="puntos" step="1" min="1" placeholder="0" required>
                        </div>
                        <div class="field">
                            <label>Concepto</label>
                            <input name="concepto" placeholder="Opcional">
                        </div>
                    </div>
                    <button class="btn primary" type="submit">+ Puntos</button>
                </form>
                <form method="POST" action="{{ route('nfc.restar_puntos', $tarjeta) }}" class="nfc-action-form" onsubmit="return confirm('¿Restar estos puntos?')">
                    @csrf
                    <div class="nfc-action-fields nfc-action-stack">
                        <div class="field">
                            <label>Restar puntos</label>
                            <input type="number" name="puntos" step="1" min="1" placeholder="0" required>
                        </div>
                        <div class="field">
                            <label>Concepto</label>
                            <input name="concepto" placeholder="Opcional">
                        </div>
                    </div>
                    <button class="btn secondary" type="submit">− Puntos</button>
                </form>
            </div>
        </article>
    </div>

    <section class="nomina-card nfc-section nfc-rewards" style="margin-top:16px;">
        <div class="nfc-section-head">
            <div>
                <h3>Recompensas</h3>
                <p class="muted nfc-section-desc">Canjea puntos por una recompensa. El costo se descuenta al confirmar.</p>
            </div>
            <a class="btn secondary" href="{{ route('nfc.recompensas.index') }}">Administrar catálogo</a>
        </div>
        @if($recompensas->isEmpty())
            <p class="muted" style="margin:0;">No hay recompensas activas. Crea algunas en el catálogo.</p>
        @else
            <div class="nfc-rewards-grid">
                @foreach($recompensas as $r)
                    @php $alcanza = (int) $tarjeta->puntos >= (int) $r->puntos_costo; @endphp
                    <article class="nfc-reward-card {{ $alcanza ? '' : 'is-locked' }}">
                        <div class="nfc-reward-body">
                            <strong>{{ $r->nombre }}</strong>
                            @if($r->descripcion)
                                <p>{{ $r->descripcion }}</p>
                            @endif
                            <span class="nfc-reward-cost">{{ number_format((int) $r->puntos_costo) }} pts</span>
                        </div>
                        <form method="POST" action="{{ route('nfc.canjear', $tarjeta) }}" onsubmit="return confirm('¿Canjear «{{ $r->nombre }}» por {{ $r->puntos_costo }} puntos?')">
                            @csrf
                            <input type="hidden" name="recompensa_id" value="{{ $r->id }}">
                            <button class="btn {{ $alcanza ? 'primary' : 'secondary' }}" type="submit" @disabled(! $alcanza)>
                                {{ $alcanza ? 'Canjear' : 'Sin puntos' }}
                            </button>
                        </form>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <div class="nfc-layout">
        <section class="nomina-card nfc-section">
            <h3>URL para grabar en la NFC</h3>
            <p class="muted nfc-section-desc">
                Escribe esta dirección en el chip (NDEF URL). Al acercar el teléfono abrirá la ficha y pedirá usuario si no hay sesión.
            </p>
            <div class="nfc-url-row">
                <input id="nfc-url" readonly value="{{ $urlNfc }}" class="nfc-url-input">
                <button type="button" class="btn primary" id="nfc-copy">Copiar URL</button>
                <button type="button" class="btn secondary" id="nfc-write">Escribir en NFC</button>
            </div>
            <p id="nfc-msg" class="muted nfc-msg"></p>
        </section>

        <section class="nomina-card nfc-section">
            <h3>Datos del cliente</h3>
            <form method="POST" action="{{ route('nfc.update', $tarjeta) }}">
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
                    <div class="field">
                        <label>UID del chip</label>
                        <input name="uid" value="{{ old('uid', $tarjeta->uid) }}" style="text-transform:uppercase;" placeholder="Opcional">
                    </div>
                    <div class="field field-wide">
                        <label>Notas</label>
                        <textarea name="notas" rows="3">{{ old('notas', $tarjeta->notas) }}</textarea>
                    </div>
                </div>
                <button class="btn primary" type="submit" style="margin-top:14px;">Guardar datos</button>
            </form>
        </section>
    </div>

    <section class="nomina-card nfc-section" style="margin-top:16px;">
        <h3>Movimientos recientes</h3>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Detalle</th>
                        <th class="num">Monto</th>
                        <th class="num">Puntos</th>
                        <th class="num">Saldo</th>
                        <th class="num">Pts</th>
                        <th>Por</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movimientos as $m)
                        <tr>
                            <td>{{ $m->created_at?->format('d/m/Y H:i') }}</td>
                            <td>
                                <span class="nfc-mov-tipo nfc-mov-{{ \Illuminate\Support\Str::slug($m->tipo, '-') }}">{{ $m->etiquetaTipo() }}</span>
                            </td>
                            <td>{{ $m->concepto ?: '—' }}</td>
                            <td class="num">
                                @if((float) $m->monto != 0)
                                    {{ (float) $m->monto > 0 ? '+' : '' }}${{ number_format((float) $m->monto, 2) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="num">
                                @if((int) $m->puntos != 0)
                                    {{ (int) $m->puntos > 0 ? '+' : '' }}{{ number_format((int) $m->puntos) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="num">${{ number_format((float) $m->saldo_despues, 2) }}</td>
                            <td class="num">{{ number_format((int) $m->puntos_despues) }}</td>
                            <td>{{ $m->registrador?->name ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="muted">Aún no hay recargas ni puntos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
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
