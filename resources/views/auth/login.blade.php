@extends('layouts.app')

@section('title', 'Iniciar sesión — Nexo PD')

@section('content')
@php
    $sedesLogin = config('inventario.sedes_locales', []);
@endphp

<div class="nexo-login">
    <aside class="nexo-login-visual">
        <div class="nexo-login-overlay"></div>
        <div class="nexo-login-brand">
            <p class="nexo-login-kicker">Palacio de los Detalles</p>
            <h2>Nexo <span>PD</span></h2>
            <p class="nexo-login-tagline">El punto de unión de ventas, inventario y requisiciones en todas tus sedes.</p>
            @if ($sedesLogin)
                <div class="nexo-login-sedes">
                    @foreach ($sedesLogin as $sede)
                        <span>{{ config('inventario.display.'.$sede, $sede) }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </aside>

    <div class="nexo-login-form">
        <div class="nexo-login-glow" aria-hidden="true"></div>
        <div class="nexo-login-card">
            <div class="nexo-login-mobile-brand">
                Nexo <span>PD</span>
            </div>
            <div class="auth-card-header nexo-login-header">
                <img src="{{ asset('logo.png') }}" alt="Palacio de los Detalles" class="nexo-login-logo">
                <h1>Bienvenido a Nexo PD</h1>
                <p>Accede a ventas, inventario y requisiciones de tu sede.</p>
            </div>

            @if ($errors->any())
                <div class="nexo-login-errors">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="nexo-login-fields">
                @csrf
                <div class="auth-field">
                    <label for="email">Correo electrónico</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="ejemplo@correo.com" required autofocus>
                </div>

                <div class="auth-field nexo-login-password">
                    <label for="password">Contraseña</label>
                    <div class="nexo-login-password-wrap">
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                        <button type="button" id="toggle-password" class="password-toggle-btn" aria-label="Mostrar contraseña">
                            <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="eye-off-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </button>
                    </div>
                </div>

                <label class="auth-remember">
                    <input type="checkbox" name="remember" value="1">
                    <span>Recordarme</span>
                </label>

                <button type="submit" class="btn nexo-login-submit">Entrar a Nexo PD</button>
            </form>

            <div class="nexo-login-quick">
                <p>Acceso rápido</p>
                <div class="nexo-login-quick-row">
                    <a href="{{ route('vendedor.dashboard') }}" class="nexo-login-chip">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"></path></svg>
                        Existencias
                    </a>
                    <button type="button" id="btn-q-pedir" class="nexo-login-chip">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                        Q Pedir
                    </button>
                </div>
            </div>

            <div class="auth-footer nexo-login-footer">
                ¿No tienes cuenta? <a href="{{ route('register') }}">Regístrate aquí</a>
            </div>
        </div>
    </div>
</div>

{{-- Modal Q Pedir --}}
<div id="q-pedir-modal" class="q-pedir-overlay" hidden>
    <div class="q-pedir-modal" role="dialog" aria-labelledby="q-pedir-title" aria-modal="true">
        <div class="q-pedir-header">
            <h2 id="q-pedir-title">Q Pedir — Solicitar producto</h2>
            <button type="button" id="q-pedir-close" class="q-pedir-close" aria-label="Cerrar">&times;</button>
        </div>
        <p style="margin:0 0 16px;color:var(--muted);font-size:0.88rem;">
            Busca el producto que necesitas y envíalo al equipo de compras.
        </p>

        <div class="auth-field">
            <label for="q-pedir-search">Buscar producto</label>
            <input type="search" id="q-pedir-search" placeholder="Código o nombre del producto..." autocomplete="off">
        </div>

        <div id="q-pedir-results" class="q-pedir-results"></div>

        <div id="q-pedir-selected" class="q-pedir-selected" hidden>
            <div style="font-size:0.8rem;font-weight:600;color:var(--muted);text-transform:uppercase;margin-bottom:8px;">Producto seleccionado</div>
            <div id="q-pedir-selected-info" style="font-weight:600;margin-bottom:12px;"></div>
            
            <div id="q-pedir-manual-fields" hidden>
                <div class="auth-field" style="margin-bottom:12px;">
                    <label for="q-pedir-categoria">Categoría (Obligatorio)</label>
                    <select id="q-pedir-categoria" style="width: 100%; padding: 11px 14px; border: 1.5px solid var(--border); border-radius: 8px; font-size: 0.92rem;" required>
                        <option value="">-- Seleccione una categoría --</option>
                    </select>
                </div>
            </div>

            <div class="auth-field">
                <label for="q-pedir-solicitante">Tu nombre (opcional)</label>
                <input type="text" id="q-pedir-solicitante" placeholder="Ej: Juan Pérez" maxlength="120">
            </div>
            <div class="auth-field">
                <label for="q-pedir-sede">Sede</label>
                <select id="q-pedir-sede" style="width: 100%; padding: 11px 14px; border: 1.5px solid var(--border); border-radius: 8px; font-size: 0.92rem;">
                    <option value="">-- Seleccione una sede --</option>
                    <option value="DORAL">DORAL</option>
                    <option value="CENTRO">CENTRO</option>
                    <option value="ZAMORA">ZAMORA</option>
                    <option value="SAMBIL">SAMBIL</option>
                    <option value="VIRTUDES">VIRTUDES</option>
                </select>
            </div>
            <div class="auth-field">
                <label for="q-pedir-notas">Notas (opcional)</label>
                <input type="text" id="q-pedir-notas" placeholder="Cantidad, urgencia..." maxlength="500">
            </div>
            <button type="button" id="q-pedir-guardar" class="btn auth-btn" style="margin-top:8px;">Guardar solicitud</button>
        </div>

        <div id="q-pedir-message" class="q-pedir-message" hidden></div>
    </div>
</div>
@endsection

@push('head')
<style>
    html, body { height: 100%; overflow: hidden; }
    main {
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        height: 100vh;
        display: flex;
        flex-direction: column;
    }

    .nexo-login {
        display: flex;
        flex: 1;
        min-height: 100vh;
        width: 100%;
    }

    .nexo-login-visual {
        display: none;
        flex: 1.15;
        position: relative;
        background: url('{{ asset('login_bg.png') }}') center/cover no-repeat;
    }

    .nexo-login-overlay {
        position: absolute;
        inset: 0;
        background:
            linear-gradient(160deg, rgba(6, 182, 212, 0.45) 0%, rgba(192, 38, 211, 0.42) 55%, rgba(15, 23, 42, 0.55) 100%);
    }

    .nexo-login-brand {
        position: absolute;
        left: 48px;
        right: 48px;
        bottom: 52px;
        z-index: 2;
        color: #fff;
        max-width: 480px;
        padding: 28px 32px;
        border-radius: 22px;
        background: rgba(15, 23, 42, 0.58);
        border: 1px solid rgba(255, 255, 255, 0.16);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
        box-shadow: 0 24px 48px rgba(0, 0, 0, 0.35);
    }

    .nexo-login-kicker {
        margin: 0 0 10px;
        font-size: 0.78rem;
        font-weight: 600;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: #f5c542;
    }

    .nexo-login-brand h2 {
        margin: 0 0 12px;
        font-size: 3rem;
        font-weight: 700;
        letter-spacing: -0.04em;
        line-height: 1.05;
    }

    .nexo-login-brand h2 span {
        background: linear-gradient(135deg, #f5c542, #f9a8d4 45%, #e879f9);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .nexo-login-tagline {
        margin: 0;
        font-size: 1.02rem;
        line-height: 1.55;
        color: #e2e8f0;
        max-width: 420px;
    }

    .nexo-login-sedes {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 18px;
    }

    .nexo-login-sedes span {
        padding: 5px 11px;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        color: #fff;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.18);
    }

    .nexo-login-form {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 32px 24px;
        position: relative;
        overflow: auto;
        background:
            radial-gradient(circle at 18% 18%, rgba(34, 211, 238, 0.16), transparent 32%),
            radial-gradient(circle at 88% 82%, rgba(232, 121, 249, 0.18), transparent 36%),
            #f8fafc;
    }

    .nexo-login-glow {
        position: absolute;
        width: 280px;
        height: 280px;
        border-radius: 50%;
        background: rgba(192, 38, 211, 0.16);
        filter: blur(40px);
        pointer-events: none;
        top: 18%;
        right: 12%;
    }

    .nexo-login-card {
        position: relative;
        width: 100%;
        max-width: 420px;
        padding: 40px 36px 28px;
        border-radius: 22px;
        background: #fff;
        border: 1px solid rgba(192, 38, 211, 0.12);
        box-shadow:
            0 20px 50px rgba(15, 23, 42, 0.08),
            0 0 0 1px rgba(245, 197, 66, 0.08);
    }

    .nexo-login-mobile-brand {
        display: none;
        text-align: center;
        font-size: 1.35rem;
        font-weight: 700;
        letter-spacing: -0.03em;
        margin-bottom: 12px;
        color: #0f172a;
    }

    .nexo-login-mobile-brand span {
        background: linear-gradient(135deg, #f5c542, #e879f9);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .nexo-login-header {
        margin-bottom: 24px;
    }

    .nexo-login-logo {
        width: 76px;
        height: 76px;
        object-fit: contain;
        border-radius: 50%;
        margin-bottom: 16px;
        box-shadow: 0 8px 24px rgba(192, 38, 211, 0.25);
    }

    .nexo-login-header h1 {
        margin: 0;
        font-size: 1.55rem;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: -0.03em;
    }

    .nexo-login-header p {
        margin: 8px 0 0;
        font-size: 0.92rem;
        color: #64748b;
    }

    .nexo-login-errors {
        background: #fef2f2;
        border-left: 4px solid #ef4444;
        color: #991b1b;
        padding: 12px 16px;
        margin-bottom: 20px;
        border-radius: 8px;
        font-size: 0.9rem;
    }

    .nexo-login-fields {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .nexo-login .auth-field label {
        text-transform: none;
        letter-spacing: 0;
        font-size: 0.86rem;
        color: #475569;
    }

    .nexo-login .auth-field input {
        padding: 12px 14px;
        border-radius: 10px;
        background: #f8fafc;
    }

    .nexo-login .auth-field input:focus {
        border-color: #c026d3;
        box-shadow: 0 0 0 4px rgba(192, 38, 211, 0.12);
    }

    .nexo-login-password-wrap {
        position: relative;
        display: flex;
        align-items: center;
    }

    .nexo-login-password-wrap input {
        padding-right: 44px;
        width: 100%;
    }

    .nexo-login .auth-remember input {
        accent-color: #c026d3;
    }

    .btn.nexo-login-submit {
        width: 100%;
        margin-top: 4px;
        padding: 13px 16px;
        border: none;
        border-radius: 12px;
        color: #fff;
        font-size: 1rem;
        font-weight: 650;
        cursor: pointer;
        background: linear-gradient(135deg, #c026d3 0%, #9333ea 58%, #ca8a04 160%);
        box-shadow: 0 8px 20px rgba(192, 38, 211, 0.28);
        transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
    }

    .btn.nexo-login-submit:hover {
        background: linear-gradient(135deg, #a21caf 0%, #7e22ce 58%, #a16207 160%);
        transform: translateY(-1px);
        box-shadow: 0 10px 24px rgba(192, 38, 211, 0.36);
    }

    .nexo-login-quick {
        margin-top: 22px;
        padding-top: 18px;
        border-top: 1px solid #e2e8f0;
        text-align: center;
    }

    .nexo-login-quick p {
        margin: 0 0 10px;
        font-size: 0.78rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #94a3b8;
    }

    .nexo-login-quick-row {
        display: flex;
        gap: 8px;
    }

    .nexo-login-chip {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 9px 10px;
        border-radius: 999px;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #475569;
        font-size: 0.82rem;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        font-family: inherit;
        transition: border-color 0.15s, color 0.15s, background 0.15s;
    }

    .nexo-login-chip:hover {
        border-color: #e879f9;
        color: #86198f;
        background: #fdf4ff;
    }

    .nexo-login-footer {
        margin-top: 18px;
        border-top: none;
        padding-top: 0;
    }

    .nexo-login-footer a {
        color: #c026d3;
    }

    .nexo-login-footer a:hover {
        color: #a21caf;
    }

    @media (min-width: 900px) {
        .nexo-login-visual { display: block; }
    }

    @media (max-width: 899px) {
        html, body { overflow: auto; }
        .nexo-login-mobile-brand { display: block; }
        .nexo-login-card { padding: 28px 22px 22px; }
    }

    .auth-card-container {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: calc(100vh - 180px);
        padding: 20px 0;
    }
    .auth-panel {
        max-width: 440px;
        width: 100%;
        margin: 0 auto;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08), 0 1px 3px rgba(15, 23, 42, 0.04);
        padding: 32px 32px 28px;
        border: 1px solid var(--border);
        background: #fff;
    }
    .auth-card-header {
        text-align: center;
        margin-bottom: 28px;
    }
    .auth-icon-circle {
        width: 54px;
        height: 54px;
        border-radius: 50%;
        background: var(--blue-light);
        color: var(--blue);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16px;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
    }
    .auth-icon-circle svg {
        width: 24px;
        height: 24px;
    }
    .auth-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .auth-field label {
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: var(--muted);
    }
    .auth-field input {
        width: 100%;
        padding: 11px 14px;
        border: 1.5px solid var(--border);
        border-radius: 8px;
        font-size: 0.92rem;
        transition: border-color 0.2s, box-shadow 0.2s;
        font-family: inherit;
    }
    .auth-field input:focus {
        outline: none;
        border-color: var(--blue);
        box-shadow: 0 0 0 4px rgba(26, 68, 128, 0.12);
    }
    .auth-remember {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        user-select: none;
    }
    .auth-remember input[type="checkbox"] {
        width: 17px;
        height: 17px;
        accent-color: var(--blue);
        cursor: pointer;
    }
    .auth-remember span {
        font-size: 0.88rem;
        color: var(--muted);
    }
    .auth-btn {
        background: linear-gradient(135deg, var(--blue) 0%, #2563a8 100%);
        padding: 12px;
        font-size: 0.95rem;
        font-weight: 600;
        border-radius: 8px;
        color: #fff;
        box-shadow: 0 4px 12px rgba(26, 68, 128, 0.15);
        transition: all 0.2s ease;
        margin-top: 8px;
    }
    .auth-btn:hover {
        background: linear-gradient(135deg, #153a6e 0%, #1d4ed8 100%);
        box-shadow: 0 6px 16px rgba(26, 68, 128, 0.22);
        transform: translateY(-1px);
    }
    .auth-btn:active {
        transform: translateY(0);
    }
    .auth-footer {
        margin-top: 24px;
        text-align: center;
        font-size: 0.88rem;
        color: var(--muted);
        border-top: 1px solid var(--border);
        padding-top: 18px;
    }
    .auth-footer a {
        color: var(--blue);
        font-weight: 600;
        text-decoration: none;
        transition: color 0.15s;
    }
    .auth-footer a:hover {
        color: #2563a8;
        text-decoration: underline;
    }
    .password-toggle-btn {
        position: absolute;
        right: 12px;
        background: none;
        border: none;
        cursor: pointer;
        color: var(--muted);
        padding: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        outline: none;
        user-select: none;
        transition: color 0.2s ease;
        z-index: 10;
    }
    .password-toggle-btn:hover {
        color: var(--blue);
    }
    .q-pedir-btn {
        background: #fff;
        color: var(--blue);
        border: 2px solid var(--blue);
        padding: 10px 24px;
        font-size: 0.92rem;
        font-weight: 600;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        width: 100%;
        max-width: 280px;
    }
    .q-pedir-btn:hover {
        background: var(--blue-light);
    }
    .q-pedir-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        padding: 20px;
    }
    .q-pedir-overlay[hidden] {
        display: none;
    }
    .q-pedir-modal {
        background: #fff;
        border-radius: 16px;
        padding: 28px;
        max-width: 520px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.2);
        border: 1px solid var(--border);
    }
    .q-pedir-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    .q-pedir-header h2 {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 700;
        color: #1e293b;
    }
    .q-pedir-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--muted);
        line-height: 1;
        padding: 4px 8px;
    }
    .q-pedir-results {
        margin: 12px 0;
        max-height: 220px;
        overflow-y: auto;
        border: 1px solid var(--border);
        border-radius: 8px;
    }
    .q-pedir-results:empty {
        display: none;
    }
    .q-pedir-result-item {
        padding: 10px 14px;
        cursor: pointer;
        border-bottom: 1px solid var(--border);
        transition: background 0.15s;
    }
    .q-pedir-result-item:last-child {
        border-bottom: none;
    }
    .q-pedir-result-item:hover {
        background: var(--blue-light);
    }
    .q-pedir-result-item strong {
        display: block;
        font-size: 0.9rem;
    }
    .q-pedir-result-item span {
        font-size: 0.78rem;
        color: var(--muted);
        font-family: monospace;
    }
    .q-pedir-stock {
        display: inline-block;
        margin-left: 6px;
        padding: 1px 6px;
        border-radius: 4px;
        font-size: 0.72rem;
        font-weight: 600;
        font-family: inherit;
    }
    .q-pedir-stock.zero {
        background: #fef2f2;
        color: #dc2626;
    }
    .q-pedir-stock.ok {
        background: #f0fdf4;
        color: #16a34a;
    }
    .q-pedir-selected {
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid var(--border);
    }
    .q-pedir-message {
        margin-top: 16px;
        padding: 12px;
        border-radius: 8px;
        font-size: 0.88rem;
    }
    .q-pedir-message.success {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #86efac;
    }
    .q-pedir-message.error {
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
</style>
@endpush

@push('scripts')
<script>
(function() {
    function setupPasswordToggle(inputId, toggleId) {
        const input = document.getElementById(inputId);
        const btn = document.getElementById(toggleId);
        if (!input || !btn) return;

        const eyeIcon = btn.querySelector('.eye-icon');
        const eyeOffIcon = btn.querySelector('.eye-off-icon');

        btn.addEventListener('click', function() {
            if (input.type === 'password') {
                input.type = 'text';
                eyeIcon.style.display = 'none';
                eyeOffIcon.style.display = 'block';
                btn.setAttribute('aria-label', 'Ocultar contraseña');
            } else {
                input.type = 'password';
                eyeIcon.style.display = 'block';
                eyeOffIcon.style.display = 'none';
                btn.setAttribute('aria-label', 'Mostrar contraseña');
            }
        });
    }

    setupPasswordToggle('password', 'toggle-password');

    // Q Pedir modal
    const modal = document.getElementById('q-pedir-modal');
    const btnOpen = document.getElementById('btn-q-pedir');
    const btnClose = document.getElementById('q-pedir-close');
    const searchInput = document.getElementById('q-pedir-search');
    const resultsEl = document.getElementById('q-pedir-results');
    const selectedPanel = document.getElementById('q-pedir-selected');
    const selectedInfo = document.getElementById('q-pedir-selected-info');
    const btnGuardar = document.getElementById('q-pedir-guardar');
    const messageEl = document.getElementById('q-pedir-message');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    let selectedProduct = null;
    let searchTimeout = null;

    function openModal() {
        modal.hidden = false;
        searchInput.focus();
        resetModal();
        cargarCategorias();
    }
    
    let categoriasCargadas = false;
    async function cargarCategorias() {
        if (categoriasCargadas) return;
        try {
            const res = await fetch('{{ route("pedidos.categorias") }}');
            const data = await res.json();
            const select = document.getElementById('q-pedir-categoria');
            data.categorias.forEach(cat => {
                const opt = document.createElement('option');
                opt.value = cat;
                opt.textContent = cat;
                select.appendChild(opt);
            });
            categoriasCargadas = true;
        } catch (e) {
            console.error('Error cargando categorias', e);
        }
    }

    function closeModal() {
        modal.hidden = true;
        resetModal();
    }

    function resetModal() {
        searchInput.value = '';
        resultsEl.innerHTML = '';
        selectedPanel.hidden = true;
        messageEl.hidden = true;
        selectedProduct = null;
        document.getElementById('q-pedir-solicitante').value = '';
        document.getElementById('q-pedir-sede').value = '';
        document.getElementById('q-pedir-notas').value = '';
        document.getElementById('q-pedir-categoria').value = '';
        document.getElementById('q-pedir-manual-fields').hidden = true;
    }

    function showMessage(text, type) {
        messageEl.textContent = text;
        messageEl.className = 'q-pedir-message ' + type;
        messageEl.hidden = false;
    }

    btnOpen?.addEventListener('click', openModal);
    btnClose?.addEventListener('click', closeModal);
    modal?.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });

    searchInput?.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const q = this.value.trim();
        if (q.length < 2) {
            resultsEl.innerHTML = '';
            return;
        }
        searchTimeout = setTimeout(async function() {
            try {
                const res = await fetch('{{ route("pedidos.search") }}?q=' + encodeURIComponent(q));
                const data = await res.json();
                resultsEl.innerHTML = '';
                if (!data.productos || data.productos.length === 0) {
                    resultsEl.innerHTML = `
                        <div style="padding:12px;color:var(--muted);font-size:0.85rem;margin-bottom:10px;">No se encontraron productos.</div>
                        <button type="button" id="btn-manual-prod" class="btn auth-btn" style="width:100%;font-size:0.85rem;padding:8px;">Agregar manualmente: "${escapeHtml(q.toUpperCase())}"</button>
                    `;
                    document.getElementById('btn-manual-prod').addEventListener('click', function() {
                        selectedProduct = {
                            producto: q.toUpperCase(),
                            codigo: 'MANUAL',
                            isManual: true
                        };
                        selectedInfo.textContent = selectedProduct.producto + ' (MANUAL)';
                        selectedPanel.hidden = false;
                        document.getElementById('q-pedir-manual-fields').hidden = false;
                    });
                    return;
                }
                data.productos.forEach(function(p) {
                    const div = document.createElement('div');
                    div.className = 'q-pedir-result-item';
                    const stock = p.stock !== undefined ? parseInt(p.stock, 10) : null;
                    const stockBadge = stock !== null
                        ? '<span class="q-pedir-stock ' + (stock === 0 ? 'zero' : 'ok') + '">Stock: ' + stock + '</span>'
                        : '';
                    div.innerHTML = '<strong>' + escapeHtml(p.producto) + stockBadge + '</strong><span>' + escapeHtml(p.codigo) + (p.categoria ? ' · ' + escapeHtml(p.categoria) : '') + '</span>';
                    div.addEventListener('click', function() {
                        selectedProduct = p;
                        const stockTxt = stock !== null ? ' — Stock: ' + stock : '';
                        selectedInfo.textContent = p.producto + ' (' + p.codigo + ')' + stockTxt;
                        selectedPanel.hidden = false;
                        document.getElementById('q-pedir-manual-fields').hidden = true;
                        resultsEl.innerHTML = '';
                        searchInput.value = p.producto;
                        messageEl.hidden = true;
                    });
                    resultsEl.appendChild(div);
                });
            } catch (err) {
                showMessage('Error al buscar productos. Intenta de nuevo.', 'error');
            }
        }, 300);
    });

    btnGuardar?.addEventListener('click', async function() {
        if (!selectedProduct) return;
        const solicitante = document.getElementById('q-pedir-solicitante').value.trim();
        const sede = document.getElementById('q-pedir-sede').value;
        const notas = document.getElementById('q-pedir-notas').value.trim();
        const categoria = document.getElementById('q-pedir-categoria').value;

        if (selectedProduct.isManual && !categoria) {
            showMessage('Por favor, selecciona una categoría para el producto nuevo.', 'error');
            return;
        }

        btnGuardar.disabled = true;
        btnGuardar.textContent = 'Guardando...';

        try {
            const bodyData = {
                producto_id: selectedProduct.id || null,
                codigo: selectedProduct.codigo || 'N/A',
                producto: selectedProduct.producto,
                categoria: selectedProduct.isManual ? categoria : (selectedProduct.categoria || null),
                proveedor: selectedProduct.proveedor || null,
                solicitante: solicitante,
                sede: sede,
                notas: notas
            };

            const res = await fetch('{{ route("pedidos.store") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(bodyData)
            });
            const data = await res.json();
            if (res.ok && data.ok) {
                showMessage(data.message, 'success');
                selectedPanel.hidden = true;
                searchInput.value = '';
            } else {
                const errMsg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Error al guardar.');
                showMessage(errMsg, 'error');
            }
        } catch (err) {
            showMessage('Error de conexión. Intenta de nuevo.', 'error');
        } finally {
            btnGuardar.disabled = false;
            btnGuardar.textContent = 'Guardar solicitud';
        }
    });

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }
})();
</script>
@endpush
