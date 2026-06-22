@extends('layouts.app')

@section('title', 'Iniciar sesión')

@section('content')
<div class="auth-card-container">
    <div class="panel auth-panel">
        <div class="auth-card-header">
            <div class="auth-icon-circle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
            </div>
            <h1 style="margin: 0; font-size: 1.6rem; font-weight: 700; color: #1e293b;">Iniciar sesión</h1>
            <p class="muted" style="margin: 6px 0 0; font-size: 0.88rem;">Accede a Ventas, Inventario y Requisiciones de tu sede.</p>
        </div>

        <form method="POST" action="{{ route('login.store') }}" style="display: flex; flex-direction: column; gap: 16px;">
            @csrf
            <div class="auth-field">
                <label for="email">Correo Electrónico</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="ejemplo@correo.com" required autofocus>
            </div>
            <div class="auth-field" style="position: relative;">
                <label for="password">Contraseña</label>
                <div style="position: relative; display: flex; align-items: center; width: 100%;">
                    <input type="password" id="password" name="password" placeholder="••••••••" required style="padding-right: 40px;">
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
            
            <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 4px;">
                <label class="auth-remember">
                    <input type="checkbox" name="remember" value="1">
                    <span>Recordarme</span>
                </label>
            </div>

            <button type="submit" class="btn auth-btn">Entrar al Sistema</button>
        </form>

        <div class="auth-footer">
            ¿No tienes cuenta? <a href="{{ route('register') }}">Regístrate aquí</a>
        </div>
    </div>
</div>
@endsection

@push('head')
<style>
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
})();
</script>
@endpush
