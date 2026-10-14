@extends('layouts.app')

@section('title', 'Mensajería')

@section('content')
<div class="panel" style="max-width:820px;">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:16px;">
        <div>
            <h1 style="margin:0;">Mensajería</h1>
            <p class="lead" style="margin:6px 0 0;">Envía un SMS por Interconectados a un número o a una lista.</p>
        </div>
        <button type="button" id="abrir-sms-config" title="Configuración" aria-label="Configuración" style="border:1px solid var(--border);background:#fff;border-radius:10px;width:40px;height:40px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;color:#1a4480;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="3"/>
                <path d="M12 2v3M12 19v3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M2 12h3M19 12h3M4.9 19.1 7 17M17 7l2.1-2.1"/>
            </svg>
        </button>
    </div>

    @if(session('error'))
        <div class="errors">{{ session('error') }}</div>
    @endif
    @if(session('success'))
        <div class="success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('mensajeria.enviar') }}" id="form-sms">
        @csrf
        <div style="display:flex;gap:8px;margin-bottom:14px;">
            <label style="display:flex;align-items:center;gap:6px;">
                <input type="radio" name="modo" value="uno" @checked(old('modo', 'uno') !== 'masivo')>
                Un número
            </label>
            <label style="display:flex;align-items:center;gap:6px;">
                <input type="radio" name="modo" value="masivo" @checked(old('modo') === 'masivo')>
                Masivo
            </label>
        </div>

        <div id="bloque-uno" style="margin-bottom:12px;">
            <label for="telefono" style="display:block;font-weight:600;margin-bottom:4px;">Número</label>
            <input id="telefono" name="telefono" value="{{ old('telefono') }}" placeholder="04141234567" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;">
            @error('telefono')<div class="muted">{{ $message }}</div>@enderror
        </div>

        <div id="bloque-masivo" style="margin-bottom:12px;" hidden>
            <label for="numeros" style="display:block;font-weight:600;margin-bottom:4px;">Números</label>
            <textarea id="numeros" name="numeros" rows="6" placeholder="Uno por línea, o separados por coma" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;">{{ old('numeros') }}</textarea>
            <p class="muted" style="margin:4px 0 0;">Hasta 300 números. Sirve 0414…, 0412… o 58414…</p>
        </div>

        <div style="margin-bottom:12px;">
            <label for="mensaje" style="display:block;font-weight:600;margin-bottom:4px;">Mensaje</label>
            <textarea id="mensaje" name="mensaje" rows="4" maxlength="320" required style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;">{{ old('mensaje') }}</textarea>
            <p class="muted" style="margin:4px 0 0;"><span id="cuenta-mensaje">0</span>/320</p>
            @error('mensaje')<div class="muted">{{ $message }}</div>@enderror
        </div>

        <button class="btn" type="submit">Enviar</button>
    </form>

    @if($resultados !== [])
        <h2 style="margin:22px 0 8px;font-size:1.05rem;">Resultado</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Estado</th>
                        <th>Respuesta</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($resultados as $fila)
                        <tr>
                            <td>{{ $fila['telefono'] }}</td>
                            <td>{{ $fila['ok'] ? 'Enviado' : 'No enviado' }}</td>
                            <td>{{ $fila['respuesta'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div id="modal-sms-config" class="modal-overlay" hidden>
    <div class="panel modal-box" role="dialog" aria-modal="true" aria-labelledby="modal-sms-titulo" style="max-width:440px;width:100%;">
        <h2 id="modal-sms-titulo" style="margin:0;">Configuración</h2>
        <p class="muted" style="margin:6px 0 14px;">Usuario y clave de Interconectados. La API es http://www.interconectados.net/api2/</p>
        <form method="POST" action="{{ route('mensajeria.configuracion') }}">
            @csrf
            <label for="user" style="display:block;font-weight:600;margin-bottom:4px;">Usuario API</label>
            <input id="user" name="user" value="{{ old('user', $usuarioConfigurado) }}" autocomplete="off" required style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;margin-bottom:12px;">
            <label for="password" style="display:block;font-weight:600;margin-bottom:4px;">Clave API</label>
            <input id="password" name="password" type="password" autocomplete="new-password" placeholder="{{ $claveConfigurada ? 'Dejar en blanco para no cambiarla' : 'Clave de Interconectados' }}" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;">
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                <button type="button" class="btn secondary" id="cerrar-sms-config">Cerrar</button>
                <button class="btn" type="submit">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('form-sms');
    const uno = document.getElementById('bloque-uno');
    const masivo = document.getElementById('bloque-masivo');
    const mensaje = document.getElementById('mensaje');
    const cuenta = document.getElementById('cuenta-mensaje');
    function modo() {
        const masivoOn = form.querySelector('input[name="modo"]:checked').value === 'masivo';
        uno.hidden = masivoOn;
        masivo.hidden = !masivoOn;
    }
    form.querySelectorAll('input[name="modo"]').forEach(function (el) { el.addEventListener('change', modo); });
    mensaje.addEventListener('input', function () { cuenta.textContent = String(mensaje.value.length); });
    cuenta.textContent = String(mensaje.value.length);
    modo();

    const modal = document.getElementById('modal-sms-config');
    document.getElementById('abrir-sms-config').addEventListener('click', function () { modal.hidden = false; });
    document.getElementById('cerrar-sms-config').addEventListener('click', function () { modal.hidden = true; });
    modal.addEventListener('click', function (e) { if (e.target === modal) modal.hidden = true; });
})();
</script>
@endsection
