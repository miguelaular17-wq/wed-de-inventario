<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="icon" type="image/png" href="{{ $logoUrl }}">
    <title>Lector QR — Nexo PD</title>
    <style>
        :root {
            --bg: #ffffff;
            --card: #f8fafc;
            --line: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
            --accent: #1a4480;
            --ok: #16a34a;
            --bad: #dc2626;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            height: 100%;
            background: var(--bg);
            color: var(--text);
            font-family: "Segoe UI", system-ui, sans-serif;
            overflow: hidden;
            -webkit-user-select: none;
            user-select: none;
            touch-action: manipulation;
        }
        button, input { font: inherit; }
        button { cursor: pointer; }
        .screen {
            display: none;
            height: 100%;
            width: 100%;
            flex-direction: column;
            background: var(--bg);
        }
        .screen.is-active { display: flex; }

        /* —— Sede —— */
        #screen-sede {
            padding: 28px 20px 24px;
            align-items: center;
            justify-content: center;
            gap: 18px;
            overflow-y: auto;
        }
        .brand-logo {
            width: 110px;
            height: 110px;
            object-fit: contain;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 12px;
        }
        .sede-title { margin: 0; font-size: 1.55rem; font-weight: 800; text-align: center; }
        .sede-sub { margin: 0; color: var(--muted); text-align: center; max-width: 420px; line-height: 1.4; }
        .sede-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
            width: min(720px, 100%);
            margin-top: 8px;
        }
        .sede-btn {
            border: 1px solid var(--line);
            background: #fff;
            color: var(--text);
            border-radius: 14px;
            padding: 22px 14px;
            font-weight: 700;
            font-size: 1.05rem;
            transition: border-color .15s, box-shadow .15s, transform .15s;
        }
        .sede-btn:active { transform: scale(0.98); }
        .sede-btn:hover {
            border-color: #93c5fd;
            box-shadow: 0 4px 14px rgba(26, 68, 128, .1);
        }

        /* —— Espera / escáner —— */
        #screen-espera {
            position: relative;
            align-items: center;
            justify-content: center;
            padding: 24px 20px 32px;
            gap: 10px;
        }
        .top-bar {
            position: absolute;
            top: 0; left: 0; right: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
        }
        .sede-pill {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: var(--accent);
            border-radius: 999px;
            padding: 8px 14px;
            font-size: .82rem;
            font-weight: 800;
            letter-spacing: .02em;
        }
        .btn-salir {
            border: 1px solid var(--line);
            background: #fff;
            color: var(--text);
            border-radius: 10px;
            padding: 8px 14px;
            font-size: .82rem;
            font-weight: 700;
        }
        .espera-logo { width: 120px; height: 120px; margin-bottom: 8px; }
        .espera-msg {
            margin: 0;
            font-size: clamp(1.4rem, 4vw, 2rem);
            font-weight: 800;
            text-align: center;
        }
        .espera-hint {
            margin: 0;
            color: var(--muted);
            font-size: 1rem;
            text-align: center;
            max-width: 420px;
            line-height: 1.4;
        }
        .espera-pulse {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            border: 3px solid #bfdbfe;
            margin: 18px 0 8px;
            animation: pulse 1.6s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: .55; box-shadow: 0 0 0 0 rgba(26,68,128,.25); }
            50% { transform: scale(1.08); opacity: 1; box-shadow: 0 0 0 14px rgba(26,68,128,0); }
        }
        .btn-escribir {
            margin-top: 18px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--accent);
            border-radius: 12px;
            padding: 14px 22px;
            font-weight: 800;
            font-size: 1rem;
        }
        .btn-escribir:hover { background: #f8fafc; }

        /* —— Manual —— */
        #manual-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 40;
            background: rgba(15, 23, 42, .45);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        #manual-modal.is-open { display: flex; }
        .manual-box {
            width: min(420px, 100%);
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 22px 18px;
        }
        .manual-box h2 { margin: 0 0 6px; font-size: 1.15rem; }
        .manual-box p { margin: 0 0 14px; color: var(--muted); font-size: .88rem; }
        .manual-box input {
            width: 100%;
            padding: 14px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: .02em;
            margin-bottom: 12px;
        }
        .manual-box input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(26, 68, 128, .15);
        }
        .manual-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .btn-ghost {
            border: 1px solid var(--line);
            background: #fff;
            border-radius: 10px;
            padding: 12px;
            font-weight: 700;
        }
        .btn-primary {
            border: none;
            background: var(--accent);
            color: #fff;
            border-radius: 10px;
            padding: 12px;
            font-weight: 800;
        }

        /* —— Producto —— */
        #screen-producto {
            padding: 10px 14px 12px;
            gap: 8px;
            overflow: hidden;
            background: #f8fafc;
        }
        .prod-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 12px;
            display: grid;
            grid-template-columns: minmax(120px, 34%) 1fr;
            gap: 14px;
            flex: 1;
            min-height: 0;
            align-items: stretch;
        }
        .prod-img-wrap {
            width: 100%;
            height: 100%;
            min-height: 140px;
            max-height: 260px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            align-self: center;
        }
        .prod-img-wrap img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .prod-right {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
            min-height: 0;
            overflow: auto;
        }
        .prod-code {
            font-family: ui-monospace, Consolas, monospace;
            color: var(--muted);
            font-size: .8rem;
            font-weight: 700;
        }
        .prod-name {
            margin: 2px 0 0;
            font-size: 1.1rem;
            font-weight: 800;
            line-height: 1.25;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .prod-meta {
            color: var(--muted);
            font-size: .78rem;
            margin-top: 2px;
        }
        .prod-stats {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
        }
        .stat {
            background: #f8fafc;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 8px 10px;
        }
        .stat span {
            display: block;
            color: var(--muted);
            font-size: .65rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            font-weight: 700;
            margin-bottom: 3px;
        }
        .stat strong { font-size: 1.05rem; display: block; }
        .stat .monto-bs {
            display: block;
            font-size: .68rem;
            font-weight: 700;
            color: var(--muted);
            margin-top: 2px;
            line-height: 1.2;
        }
        .stat.stock-ok strong { color: var(--ok); }
        .stat.stock-zero strong { color: var(--bad); }
        .tasa-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: .72rem;
            font-weight: 800;
            margin: 2px 0 4px;
        }

        .cashea-box {
            border: 1px solid #c7d2fe;
            background: #f8faff;
            border-radius: 12px;
            padding: 10px;
            display: grid;
            gap: 8px;
        }
        .cashea-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .cashea-head strong {
            font-size: .88rem;
            color: #4338ca;
            font-weight: 800;
        }
        .cashea-neto {
            font-size: .75rem;
            color: var(--muted);
            font-weight: 600;
        }
        .cashea-niveles {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
        }
        .cashea-nivel {
            border: 1px solid #c7d2fe;
            background: #fff;
            border-radius: 10px;
            padding: 8px 6px;
            text-align: center;
            cursor: pointer;
            transition: border-color .15s, background .15s;
        }
        .cashea-nivel:hover, .cashea-nivel.is-active {
            border-color: #6366f1;
            background: #eef2ff;
        }
        .cashea-nivel .n {
            display: block;
            font-size: .68rem;
            font-weight: 800;
            color: #6366f1;
        }
        .cashea-nivel .i {
            display: block;
            font-size: .92rem;
            font-weight: 800;
            color: var(--ok);
            margin-top: 2px;
        }
        .cashea-nivel .i-bs {
            display: block;
            font-size: .62rem;
            font-weight: 700;
            color: var(--muted);
            margin-top: 1px;
            line-height: 1.15;
        }
        .cashea-nivel .d {
            display: block;
            font-size: .62rem;
            color: var(--muted);
            margin-top: 1px;
        }
        .cashea-res div .monto-bs {
            display: block;
            font-size: .62rem;
            font-weight: 700;
            color: var(--muted);
            margin-top: 1px;
        }
        .cashea-neto .monto-bs {
            display: block;
            text-align: right;
            font-size: .68rem;
            color: var(--muted);
            font-weight: 700;
            margin-top: 1px;
        }
        .cashea-res {
            display: none;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 6px;
            background: #fff;
            border: 1px solid #c7d2fe;
            border-radius: 10px;
            padding: 8px;
        }
        .cashea-res.is-on { display: grid; }
        .cashea-res div span {
            display: block;
            font-size: .62rem;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: var(--muted);
            font-weight: 700;
        }
        .cashea-res div strong { font-size: .95rem; }

        .prod-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-shrink: 0;
        }
        .countdown {
            color: var(--muted);
            font-size: .9rem;
            font-weight: 700;
        }
        .countdown strong { color: var(--accent); }

        @media (max-width: 640px) {
            .prod-card {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            .prod-img-wrap {
                width: min(140px, 40vw);
                height: min(140px, 40vw);
                max-height: none;
                margin: 0 auto;
            }
            .cashea-niveles { grid-template-columns: repeat(3, 1fr); }
        }

        .toast {
            position: absolute;
            left: 50%;
            bottom: 28px;
            transform: translateX(-50%);
            background: #7f1d1d;
            color: #fff;
            padding: 12px 18px;
            border-radius: 12px;
            font-weight: 700;
            z-index: 30;
            display: none;
            max-width: 90%;
            text-align: center;
        }
        .toast.is-on { display: block; }

        /* —— PIN —— */
        #pin-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 50;
            background: rgba(15, 23, 42, .55);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        #pin-modal.is-open { display: flex; }
        .pin-box {
            width: min(360px, 100%);
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 22px 18px;
            text-align: center;
        }
        .pin-box h2 { margin: 0 0 6px; font-size: 1.15rem; }
        .pin-box p { margin: 0 0 14px; color: var(--muted); font-size: .88rem; }
        .pin-dots {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 16px;
        }
        .pin-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 2px solid #94a3b8;
            background: transparent;
        }
        .pin-dot.filled { background: var(--accent); border-color: var(--accent); }
        .pin-pad {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        .pin-key {
            border: 1px solid var(--line);
            background: #f8fafc;
            color: var(--text);
            border-radius: 12px;
            padding: 16px 0;
            font-size: 1.25rem;
            font-weight: 700;
        }
        .pin-key.muted { color: var(--muted); font-size: .95rem; }
        .pin-error {
            min-height: 1.2em;
            margin: 10px 0 0;
            color: var(--bad);
            font-size: .88rem;
            font-weight: 700;
        }
    </style>
</head>
<body>
    {{-- 1. Elegir sede --}}
    <section id="screen-sede" class="screen is-active">
        <img class="brand-logo" src="{{ $logoUrl }}" alt="Palacio de los Detalles">
        <h1 class="sede-title">Lector QR</h1>
        <p class="sede-sub">Elige la sede de esta tablet. Luego solo verás existencias de esa tienda.</p>
        <div class="sede-grid">
            @foreach ($sedes as $sede)
                <button type="button" class="sede-btn" data-sede="{{ $sede['codigo'] }}" data-label="{{ $sede['nombre'] }}">
                    {{ $sede['nombre'] }}
                </button>
            @endforeach
        </div>
    </section>

    {{-- 2. Espera del escáner --}}
    <section id="screen-espera" class="screen">
        <div class="top-bar">
            <div class="sede-pill" id="espera-sede-label">—</div>
            <button type="button" class="btn-salir" id="btn-pedir-salir">Salir</button>
        </div>
        <img class="brand-logo espera-logo" src="{{ $logoUrl }}" alt="Palacio de los Detalles">
        <div class="espera-pulse" aria-hidden="true"></div>
        <p class="espera-msg">Escanea el código</p>
        <p class="espera-hint">Usa el escáner de la tablet. La pantalla espera el código del producto.</p>
        <button type="button" class="btn-escribir" id="btn-escribir">Escribir código</button>
    </section>

    {{-- 3. Producto --}}
    <section id="screen-producto" class="screen">
        <div class="top-bar" style="position:static;padding:0 0 4px;">
            <div class="sede-pill" id="prod-sede-label">—</div>
            <button type="button" class="btn-salir" id="btn-pedir-salir-2">Salir</button>
        </div>
        <div class="prod-card">
            <div class="prod-img-wrap">
                <img id="prod-img" src="" alt="">
            </div>
            <div class="prod-right">
                <div>
                    <div class="prod-code" id="prod-codigo">—</div>
                    <h2 class="prod-name" id="prod-nombre">—</h2>
                    <div class="prod-meta" id="prod-meta">—</div>
                    <div class="tasa-pill" id="tasa-pill">Tasa BCV: —</div>
                </div>
                <div class="prod-stats">
                    <div class="stat" id="stat-stock">
                        <span>Existencia</span>
                        <strong id="prod-existencia">0</strong>
                    </div>
                    <div class="stat">
                        <span>P. unidad</span>
                        <strong id="prod-precio">—</strong>
                        <span class="monto-bs" id="prod-precio-bs"></span>
                    </div>
                    <div class="stat">
                        <span>P. mayor</span>
                        <strong id="prod-mayor">—</strong>
                        <span class="monto-bs" id="prod-mayor-bs"></span>
                    </div>
                </div>
                <div class="cashea-box">
                    <div class="cashea-head">
                        <strong>Cashea</strong>
                        <span class="cashea-neto" id="cashea-neto-wrap">
                            <span id="cashea-neto">Neto desc. —</span>
                            <span class="monto-bs" id="cashea-neto-bs"></span>
                        </span>
                    </div>
                    <div class="cashea-niveles" id="cashea-niveles"></div>
                    <div class="cashea-res" id="cashea-res">
                        <div>
                            <span>Inicial</span>
                            <strong id="cashea-inicial">—</strong>
                            <span class="monto-bs" id="cashea-inicial-bs"></span>
                        </div>
                        <div>
                            <span>Restante</span>
                            <strong id="cashea-restante">—</strong>
                            <span class="monto-bs" id="cashea-restante-bs"></span>
                        </div>
                        <div>
                            <span>3 cuotas</span>
                            <strong id="cashea-cuota">—</strong>
                            <span class="monto-bs" id="cashea-cuota-bs"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="prod-footer">
            <div class="countdown">Vuelve en <strong id="countdown-num">30</strong> s</div>
            <button type="button" class="btn-primary" id="btn-otro" style="padding:12px 18px;border-radius:12px;">Escanear otro</button>
        </div>
    </section>

    <div class="toast" id="toast"></div>

    {{-- Escribir código --}}
    <div id="manual-modal" role="dialog" aria-modal="true">
        <div class="manual-box">
            <h2>Escribir código</h2>
            <p>Ingresa el código del producto y pulsa Buscar.</p>
            <input type="text" id="manual-input" placeholder="Ej. OTG01" autocomplete="off" autocapitalize="characters" spellcheck="false">
            <div class="manual-actions">
                <button type="button" class="btn-ghost" id="manual-cancel">Cancelar</button>
                <button type="button" class="btn-primary" id="manual-ok">Buscar</button>
            </div>
        </div>
    </div>

    {{-- PIN admin --}}
    <div id="pin-modal" role="dialog" aria-modal="true" aria-labelledby="pin-title">
        <div class="pin-box">
            <h2 id="pin-title">Código de administrador</h2>
            <p>Solo con este código se puede salir del lector.</p>
            <div class="pin-dots" id="pin-dots"></div>
            <div class="pin-pad" id="pin-pad">
                <button type="button" class="pin-key" data-key="1">1</button>
                <button type="button" class="pin-key" data-key="2">2</button>
                <button type="button" class="pin-key" data-key="3">3</button>
                <button type="button" class="pin-key" data-key="4">4</button>
                <button type="button" class="pin-key" data-key="5">5</button>
                <button type="button" class="pin-key" data-key="6">6</button>
                <button type="button" class="pin-key" data-key="7">7</button>
                <button type="button" class="pin-key" data-key="8">8</button>
                <button type="button" class="pin-key" data-key="9">9</button>
                <button type="button" class="pin-key muted" data-key="cancel">Cancelar</button>
                <button type="button" class="pin-key" data-key="0">0</button>
                <button type="button" class="pin-key muted" data-key="del">⌫</button>
            </div>
            <p class="pin-error" id="pin-error"></p>
        </div>
    </div>

    <script>
(function () {
    const LOOKUP_URL = @json(route('lector_qr.lookup'));
    const UNLOCK_URL = @json(route('lector_qr.unlock'));
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const AUTO_CERRAR = {{ max(1, (int) config('lector_qr.auto_cerrar_segundos', 30)) }};
    const PIN_LEN = {{ (int) ($pinLength ?? 4) }};
    const NETO_FACTOR = {{ (float) ($netoFactor ?? 0.75) }};
    const DESC_PCT = {{ (int) ($descPct ?? 25) }};
    const CASHEA_MIN_USD = 10;
    const TASA_BCV = {{ (float) ($tasaBcv ?? 0) }};
    const CASHEA_NIVELES = [
        @foreach(($casheaLevels ?? [1 => 60, 2 => 50, 3 => 40, 4 => 40, 5 => 40, 6 => 40]) as $nivel => $pct)
        { id: {{ (int) $nivel }}, label: 'N{{ (int) $nivel }}', factor: {{ ((int) $pct) / 100 }}, pct: {{ (int) $pct }} },
        @endforeach
    ];
    let precioActual = 0;

    const screenSede = document.getElementById('screen-sede');
    const screenEspera = document.getElementById('screen-espera');
    const screenProducto = document.getElementById('screen-producto');
    const pinModal = document.getElementById('pin-modal');
    const manualModal = document.getElementById('manual-modal');
    const manualInput = document.getElementById('manual-input');
    const toast = document.getElementById('toast');
    const countdownEl = document.getElementById('countdown-num');

    let sede = sessionStorage.getItem('lector_qr_sede') || '';
    let sedeLabel = sessionStorage.getItem('lector_qr_sede_label') || '';
    let busy = false;
    let pinBuffer = '';
    let wakeLock = null;
    let autoTimer = null;
    let countdownTimer = null;
    let secondsLeft = AUTO_CERRAR;
    let wedgeBuffer = '';
    let wedgeTimer = null;

    function showScreen(el) {
        [screenSede, screenEspera, screenProducto].forEach(s => s.classList.remove('is-active'));
        el.classList.add('is-active');
    }

    function showToast(msg) {
        toast.textContent = msg;
        toast.classList.add('is-on');
        clearTimeout(showToast._t);
        showToast._t = setTimeout(() => toast.classList.remove('is-on'), 2800);
    }

    function money(n) {
        const v = Number(n) || 0;
        return '$' + v.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function moneyBs(usd) {
        if (!TASA_BCV || TASA_BCV <= 0) return '';
        const bs = (Number(usd) || 0) * TASA_BCV;
        return 'Bs. ' + bs.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function setBs(elId, usd) {
        const el = document.getElementById(elId);
        if (!el) return;
        el.textContent = moneyBs(usd);
    }

    function clearAuto() {
        clearTimeout(autoTimer);
        clearInterval(countdownTimer);
        autoTimer = null;
        countdownTimer = null;
    }

    async function enterFullscreen() {
        const root = document.documentElement;
        try {
            if (!document.fullscreenElement && root.requestFullscreen) {
                await root.requestFullscreen();
            } else if (!document.webkitFullscreenElement && root.webkitRequestFullscreen) {
                root.webkitRequestFullscreen();
            }
        } catch (e) {}
        try {
            if (navigator.wakeLock) wakeLock = await navigator.wakeLock.request('screen');
        } catch (e) {}
    }

    async function exitFullscreen() {
        try {
            if (document.fullscreenElement && document.exitFullscreen) await document.exitFullscreen();
            else if (document.webkitFullscreenElement && document.webkitExitFullscreen) document.webkitExitFullscreen();
        } catch (e) {}
        if (wakeLock) {
            try { await wakeLock.release(); } catch (e) {}
            wakeLock = null;
        }
    }

    function irAEspera() {
        clearAuto();
        document.getElementById('espera-sede-label').textContent = sedeLabel;
        showScreen(screenEspera);
        enterFullscreen();
    }

    function startCountdown() {
        clearAuto();
        secondsLeft = AUTO_CERRAR;
        countdownEl.textContent = String(secondsLeft);
        countdownTimer = setInterval(() => {
            secondsLeft -= 1;
            countdownEl.textContent = String(Math.max(0, secondsLeft));
            if (secondsLeft <= 0) clearInterval(countdownTimer);
        }, 1000);
        autoTimer = setTimeout(() => irAEspera(), AUTO_CERRAR * 1000);
    }

    async function onCodigo(rawCodigo) {
        const code = String(rawCodigo || '').trim();
        if (!code || busy) return;
        if (pinModal.classList.contains('is-open')) return;
        if (manualModal.classList.contains('is-open') && document.activeElement === manualInput) return;

        busy = true;
        try {
            const url = new URL(LOOKUP_URL, window.location.origin);
            url.searchParams.set('sede', sede);
            url.searchParams.set('codigo', code);

            const r = await fetch(url.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const j = await r.json();
            if (!r.ok || !j.ok) {
                showToast(j.error || 'Producto no encontrado');
                return;
            }
            renderProducto(j.producto);
            showScreen(screenProducto);
            startCountdown();
        } catch (e) {
            showToast('Error de conexión');
        } finally {
            busy = false;
        }
    }

    function renderProducto(p) {
        document.getElementById('prod-sede-label').textContent = p.sede_label || sedeLabel;
        document.getElementById('prod-codigo').textContent = p.codigo;
        document.getElementById('prod-nombre').textContent = p.nombre;
        const meta = [p.categoria, p.subcategoria].filter(Boolean).join(' · ');
        document.getElementById('prod-meta').textContent = meta || '—';
        document.getElementById('prod-existencia').textContent = String(p.existencia ?? 0);
        const stockEl = document.getElementById('stat-stock');
        stockEl.classList.toggle('stock-ok', (p.existencia || 0) > 0);
        stockEl.classList.toggle('stock-zero', (p.existencia || 0) <= 0);
        document.getElementById('prod-precio').textContent = money(p.precio_unidad);
        document.getElementById('prod-mayor').textContent = money(p.precio_mayor);
        setBs('prod-precio-bs', p.precio_unidad);
        setBs('prod-mayor-bs', p.precio_mayor);
        document.getElementById('tasa-pill').textContent = TASA_BCV > 0
            ? `Tasa BCV: Bs. ${Number(TASA_BCV).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 4 })}`
            : 'Tasa BCV: no disponible';

        precioActual = Number(p.precio_unidad) || 0;
        const neto = precioActual * NETO_FACTOR;
        const casheaBox = document.querySelector('.cashea-box');
        if (precioActual >= CASHEA_MIN_USD) {
            casheaBox.style.display = '';
            document.getElementById('cashea-neto').textContent = `Neto -${DESC_PCT}%: ${money(neto)}`;
            setBs('cashea-neto-bs', neto);
            renderCashea(precioActual);
        } else {
            casheaBox.style.display = 'none';
            document.getElementById('cashea-niveles').innerHTML = '';
            document.getElementById('cashea-res').classList.remove('is-on');
            setBs('cashea-neto-bs', 0);
            document.getElementById('cashea-neto-bs').textContent = '';
        }

        const img = document.getElementById('prod-img');
        img.dataset.triedPng = 'false';
        img.onerror = function () {
            if (img.dataset.triedPng !== 'true') {
                img.dataset.triedPng = 'true';
                img.src = p.imagen_png;
            } else {
                img.onerror = null;
                img.src = p.imagen_fallback;
            }
        };
        img.src = p.imagen_url;
    }

    function renderCashea(precioUnit) {
        const wrap = document.getElementById('cashea-niveles');
        const res = document.getElementById('cashea-res');
        wrap.innerHTML = '';
        res.classList.remove('is-on');

        if (precioUnit < CASHEA_MIN_USD) {
            wrap.innerHTML = `<div style="grid-column:1/-1;color:#64748b;font-size:.85rem;text-align:center;">Cashea aplica desde ${money(CASHEA_MIN_USD)}</div>`;
            return;
        }

        CASHEA_NIVELES.forEach((nivel) => {
            const inicial = precioUnit * nivel.factor;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'cashea-nivel';
            const bsInicial = moneyBs(inicial);
            btn.innerHTML =
                `<span class="n">${nivel.label}</span>` +
                `<span class="i">${money(inicial)}</span>` +
                (bsInicial ? `<span class="i-bs">${bsInicial}</span>` : '') +
                `<span class="d">${nivel.pct}% inicial</span>`;
            btn.addEventListener('click', () => {
                wrap.querySelectorAll('.cashea-nivel').forEach((el) => el.classList.remove('is-active'));
                btn.classList.add('is-active');
                const restante = precioUnit - inicial;
                const cuota = restante / 3;
                document.getElementById('cashea-inicial').textContent = money(inicial);
                document.getElementById('cashea-restante').textContent = money(restante);
                document.getElementById('cashea-cuota').textContent = money(cuota);
                setBs('cashea-inicial-bs', inicial);
                setBs('cashea-restante-bs', restante);
                setBs('cashea-cuota-bs', cuota);
                res.classList.add('is-on');
            });
            wrap.appendChild(btn);
        });
    }

    // Sede
    document.querySelectorAll('.sede-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            sede = btn.dataset.sede;
            sedeLabel = btn.dataset.label;
            sessionStorage.setItem('lector_qr_sede', sede);
            sessionStorage.setItem('lector_qr_sede_label', sedeLabel);
            irAEspera();
        });
    });

    document.getElementById('btn-otro').addEventListener('click', irAEspera);

    // Manual
    function openManual() {
        manualInput.value = '';
        manualModal.classList.add('is-open');
        setTimeout(() => manualInput.focus(), 50);
    }
    function closeManual() {
        manualModal.classList.remove('is-open');
        manualInput.blur();
    }
    document.getElementById('btn-escribir').addEventListener('click', openManual);
    document.getElementById('manual-cancel').addEventListener('click', closeManual);
    document.getElementById('manual-ok').addEventListener('click', () => {
        const v = manualInput.value.trim();
        if (!v) return;
        closeManual();
        onCodigo(v);
    });
    manualInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('manual-ok').click();
        }
        e.stopPropagation();
    });
    manualModal.addEventListener('click', (e) => {
        if (e.target === manualModal) closeManual();
    });

    // Escáner USB (ráfaga de teclas + Enter)
    document.addEventListener('keydown', (e) => {
        if (pinModal.classList.contains('is-open')) return;
        if (manualModal.classList.contains('is-open')) return;
        if (!screenEspera.classList.contains('is-active') && !screenProducto.classList.contains('is-active')) return;
        if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA')) return;

        // Si están en producto y escanean otro, cortar countdown y buscar
        if (e.key === 'Enter') {
            if (wedgeBuffer.length >= 3) {
                e.preventDefault();
                const code = wedgeBuffer;
                wedgeBuffer = '';
                if (screenProducto.classList.contains('is-active')) clearAuto();
                onCodigo(code);
            }
            return;
        }
        if (e.key.length === 1) {
            wedgeBuffer += e.key;
            clearTimeout(wedgeTimer);
            wedgeTimer = setTimeout(() => { wedgeBuffer = ''; }, 120);
        }
    });

    // PIN
    (function buildPinDots() {
        const wrap = document.getElementById('pin-dots');
        wrap.innerHTML = '';
        for (let i = 0; i < PIN_LEN; i++) {
            const d = document.createElement('div');
            d.className = 'pin-dot';
            wrap.appendChild(d);
        }
    })();

    function openPin() {
        pinBuffer = '';
        document.getElementById('pin-error').textContent = '';
        renderPinDots();
        pinModal.classList.add('is-open');
    }
    function closePin() {
        pinBuffer = '';
        pinModal.classList.remove('is-open');
    }
    function renderPinDots() {
        document.querySelectorAll('#pin-dots .pin-dot').forEach((dot, i) => {
            dot.classList.toggle('filled', i < pinBuffer.length);
        });
    }
    async function submitPin() {
        document.getElementById('pin-error').textContent = '';
        try {
            const r = await fetch(UNLOCK_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ pin: pinBuffer }),
            });
            const j = await r.json();
            if (!r.ok || !j.ok) {
                document.getElementById('pin-error').textContent = j.error || 'Código incorrecto';
                pinBuffer = '';
                renderPinDots();
                return;
            }
            clearAuto();
            sessionStorage.removeItem('lector_qr_sede');
            sessionStorage.removeItem('lector_qr_sede_label');
            await exitFullscreen();
            window.location.href = j.redirect || '/login';
        } catch (e) {
            document.getElementById('pin-error').textContent = 'Error de conexión';
        }
    }

    document.getElementById('btn-pedir-salir').addEventListener('click', openPin);
    document.getElementById('btn-pedir-salir-2').addEventListener('click', openPin);
    document.getElementById('pin-pad').addEventListener('click', (e) => {
        const key = e.target.closest('[data-key]')?.dataset.key;
        if (!key) return;
        if (key === 'cancel') { closePin(); return; }
        if (key === 'del') {
            pinBuffer = pinBuffer.slice(0, -1);
            renderPinDots();
            return;
        }
        if (pinBuffer.length >= PIN_LEN) return;
        pinBuffer += key;
        renderPinDots();
        if (pinBuffer.length === PIN_LEN) submitPin();
    });

    history.pushState({ lector: 1 }, '', location.href);
    window.addEventListener('popstate', () => {
        history.pushState({ lector: 1 }, '', location.href);
        openPin();
    });
    window.addEventListener('beforeunload', (e) => {
        if (sede) { e.preventDefault(); e.returnValue = ''; }
    });
    document.addEventListener('visibilitychange', async () => {
        if (document.visibilityState === 'visible' && sede && navigator.wakeLock) {
            try { wakeLock = await navigator.wakeLock.request('screen'); } catch (e) {}
        }
    });

    if (sede && sedeLabel) irAEspera();
})();
    </script>
</body>
</html>
