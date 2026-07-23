@extends('layouts.app')

@section('title', 'Iniciar sesión')

@section('content')
<style>
    body, html { margin: 0; padding: 0; height: 100%; overflow: hidden; }
    main { max-width: 100% !important; padding: 0 !important; margin: 0 !important; height: 100vh; display: flex; flex-direction: column; }
    .split-layout { display: flex; flex: 1; height: 100vh; width: 100%; }
    .split-image { flex: 1.2; display: none; background: url('{{ asset('login_bg.png') }}') center/cover no-repeat; position: relative; }
    .split-form-container { flex: 1; display: flex; align-items: center; justify-content: center; background: #f8fafc; padding: 40px; position: relative; }
    
    @media (min-width: 900px) {
        .split-image { display: block; }
    }
</style>

<div class="split-layout">
    <div class="split-image">
        <!-- Overlay sutil -->
        <div style="position: absolute; inset: 0; background: linear-gradient(135deg, rgba(37,99,235,0.4) 0%, rgba(139,92,246,0.3) 100%);"></div>
        <div style="position: absolute; bottom: 60px; left: 60px; color: white; z-index: 2; background: rgba(15, 23, 42, 0.65); padding: 40px; border-radius: 24px; backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);">
            <h2 style="font-size: 3.5rem; font-weight: 700; margin: 0 0 15px; letter-spacing: -1px; line-height: 1.1; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">Inventario<br>Multisede</h2>
            <p style="font-size: 1.15rem; max-width: 450px; margin: 0; opacity: 0.95; line-height: 1.6; color: #e2e8f0;">Gestión integral de ventas, inventario y requisiciones en tiempo real para todas tus sucursales.</p>
        </div>
    </div>
    
    <div class="split-form-container">
        <div class="panel auth-panel" style="width: 100%; max-width: 440px; box-shadow: 0 20px 40px rgba(0,0,0,0.08); border-radius: 20px; padding: 48px; border: 1px solid rgba(255,255,255,0.8); background: #ffffff;">
            <div class="auth-card-header" style="text-align: center; margin-bottom: 32px;">
                <div style="margin-bottom: 20px;">
                    <img src="{{ asset('logo.png') }}" alt="Logo" style="max-height: 70px;">
                </div>
                <h1 style="margin: 0; font-size: 1.8rem; font-weight: 700; color: #1e293b;">Iniciar sesión</h1>
                <p class="muted" style="margin: 8px 0 0; font-size: 0.95rem;">Accede a Ventas, Inventario y Requisiciones de tu sede.</p>
            </div>

            <form method="POST" action="{{ route('login.store') }}" style="display: flex; flex-direction: column; gap: 20px;">
                @csrf
                <div class="auth-field">
                    <label for="email" style="font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 6px; display: block;">CORREO ELECTRÓNICO</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="ejemplo@correo.com" required autofocus style="width: 100%; padding: 12px 16px; border-radius: 8px; border: 1px solid #cbd5e1; background: #f8fafc; font-size: 1rem; transition: border-color 0.2s;">
                </div>
                
                <div class="auth-field" style="position: relative;">
                    <label for="password" style="font-weight: 600; font-size: 0.85rem; color: #475569; margin-bottom: 6px; display: block;">CONTRASEÑA</label>
                    <div style="position: relative; display: flex; align-items: center; width: 100%;">
                        <input type="password" id="password" name="password" placeholder="••••••••" required style="width: 100%; padding: 12px 16px; border-radius: 8px; border: 1px solid #cbd5e1; background: #f8fafc; font-size: 1rem; transition: border-color 0.2s;">
                        <button type="button" id="toggle-password" class="password-toggle-btn" aria-label="Mostrar contraseña" style="position: absolute; right: 12px; background: none; border: none; color: #64748b; cursor: pointer; padding: 4px;">
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
                
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <label class="auth-remember" style="display: flex; align-items: center; gap: 8px; font-size: 0.9rem; color: #475569; cursor: pointer;">
                        <input type="checkbox" name="remember" value="1" style="width: 16px; height: 16px; accent-color: var(--blue);">
                        <span>Recordarme</span>
                    </label>
                </div>

                <button type="submit" class="btn auth-btn" style="width: 100%; padding: 14px; background: linear-gradient(135deg, #2563eb, #4f46e5); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; margin-top: 8px; box-shadow: 0 4px 12px rgba(37,99,235,0.3);">Entrar al Sistema</button>
            </form>

            <div style="margin-top: 24px; text-align: center; position: relative;">
                <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none;"><div style="height: 1px; width: 100%; background: #e2e8f0;"></div></div>
                <span style="background: #ffffff; padding: 0 12px; color: #64748b; font-size: 0.85rem; position: relative; font-weight: 500;">O</span>
            </div>

            <div style="margin-top: 24px; text-align: center;">
                <p class="muted" style="margin: 0 0 12px; font-size: 0.85rem; color: #64748b; font-weight: 500;">Acceso rápido sin iniciar sesión:</p>
                <div style="display: flex; gap: 12px;">
                    <a href="{{ route('vendedor.dashboard') }}" style="flex: 1; padding: 14px 10px; background: #f8fafc; border: 1.5px solid #cbd5e1; color: #334155; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s; text-decoration: none; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);" onmouseover="this.style.borderColor='#3b82f6'; this.style.color='#1e293b'; this.style.background='#f1f5f9';" onmouseout="this.style.borderColor='#cbd5e1'; this.style.color='#334155'; this.style.background='#f8fafc';">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"></path></svg>
                        Ver Existencias
                    </a>
                    <button type="button" id="btn-q-pedir" class="btn q-pedir-btn" style="flex: 1; padding: 14px 10px; background: #f8fafc; border: 1.5px solid #cbd5e1; color: #334155; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);" onmouseover="this.style.borderColor='#10b981'; this.style.color='#1e293b'; this.style.background='#f1f5f9';" onmouseout="this.style.borderColor='#cbd5e1'; this.style.color='#334155'; this.style.background='#f8fafc';">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                        Solicitar (Q Pedir)
                    </button>
                </div>
            </div>

            <div class="auth-footer" style="margin-top: 32px; text-align: center; font-size: 0.95rem; color: #475569;">
                ¿No tienes cuenta? <a href="{{ route('register') }}" style="color: #2563eb; font-weight: 600; text-decoration: none;">Regístrate aquí</a>
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
            <div class="auth-field">
                <label for="q-pedir-solicitante">Tu nombre (opcional)</label>
                <input type="text" id="q-pedir-solicitante" placeholder="Ej: Juan Pérez" maxlength="120">
            </div>
            <div class="auth-field">
                <label for="q-pedir-notas">Notas (opcional)</label>
                <input type="text" id="q-pedir-notas" placeholder="Cantidad, urgencia, sede..." maxlength="500">
            </div>
            <button type="button" id="q-pedir-guardar" class="btn auth-btn" style="margin-top:8px;">Guardar solicitud</button>
        </div>

        <div id="q-pedir-message" class="q-pedir-message" hidden></div>
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
        document.getElementById('q-pedir-notas').value = '';
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
                    resultsEl.innerHTML = '<div style="padding:12px;color:var(--muted);font-size:0.85rem;">No se encontraron productos.</div>';
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
        btnGuardar.disabled = true;
        btnGuardar.textContent = 'Guardando...';
        try {
            const res = await fetch('{{ route("pedidos.store") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    producto_id: selectedProduct.id,
                    codigo: selectedProduct.codigo,
                    producto: selectedProduct.producto,
                    categoria: selectedProduct.categoria || null,
                    proveedor: selectedProduct.proveedor || null,
                    solicitante: document.getElementById('q-pedir-solicitante').value.trim() || null,
                    notas: document.getElementById('q-pedir-notas').value.trim() || null
                })
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
