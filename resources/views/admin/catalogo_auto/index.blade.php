@extends('layouts.app')

@section('title', 'Automatizador de Catálogos')

@push('head')
<style>
    .auto-grid {
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 24px;
        align-items: start;
    }
    @media (max-width: 900px) {
        .auto-grid { grid-template-columns: 1fr; }
    }

    .cat-card {
        background: #fff;
        border: 1.5px solid var(--border, #e2e8f0);
        border-radius: 14px;
        padding: 22px 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        margin-bottom: 24px;
    }
    .cat-card h3 {
        margin: 0 0 16px;
        font-size: 1rem;
        font-weight: 700;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .cat-tree { display: flex; flex-direction: column; gap: 10px; }

    .cat-block {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
    }
    .cat-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        background: #f8fafc;
        cursor: pointer;
        user-select: none;
        font-weight: 600;
        font-size: 0.9rem;
        color: #334155;
    }
    .cat-header:hover { background: #f1f5f9; }
    .cat-header input[type="checkbox"] { accent-color: var(--blue, #2563eb); width: 16px; height: 16px; cursor: pointer; }
    .cat-toggle { margin-left: auto; color: #94a3b8; font-size: 0.75rem; transition: transform .2s; }
    .cat-toggle.open { transform: rotate(90deg); }

    .subcat-list {
        display: none;
        padding: 10px 14px 12px 38px;
        gap: 8px;
        flex-direction: column;
        background: #fff;
        border-top: 1px solid #f1f5f9;
    }
    .subcat-list.visible { display: flex; }
    .subcat-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.85rem;
        color: #475569;
        cursor: pointer;
    }
    .subcat-item input[type="checkbox"] { accent-color: var(--blue, #2563eb); cursor: pointer; }

    .status-card {
        background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%);
        border-radius: 14px;
        padding: 24px;
        color: #fff;
        margin-bottom: 24px;
    }
    .status-card h3 { margin: 0 0 16px; font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; gap: 8px; }
    .status-row { margin-bottom: 10px; font-size: 0.85rem; opacity: 0.85; }
    .status-row strong { display: block; opacity: 1; font-size: 0.9rem; color: #fff; }

    .url-box {
        background: rgba(255,255,255,0.12);
        border-radius: 8px;
        padding: 10px 12px;
        margin: 14px 0;
        font-size: 0.78rem;
        word-break: break-all;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .url-box a { color: #bfdbfe; text-decoration: none; flex: 1; }
    .url-box a:hover { color: #fff; }

    .btn-copy {
        background: rgba(255,255,255,0.2);
        border: none;
        color: #fff;
        padding: 5px 10px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.75rem;
        white-space: nowrap;
        flex-shrink: 0;
        transition: background .2s;
    }
    .btn-copy:hover { background: rgba(255,255,255,0.35); }

    .auto-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: rgba(255,255,255,0.15);
        border-radius: 20px;
        padding: 4px 10px;
        font-size: 0.75rem;
        margin-top: 14px;
    }

    .generate-btn {
        width: 100%;
        padding: 10px;
        background: #fff;
        color: var(--blue, #2563eb);
        border: none;
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all .2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: 16px;
    }
    .generate-btn:hover { background: #eff6ff; transform: translateY(-1px); }
    .generate-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

    .alert-success {
        background: #f0fdf4;
        border: 1px solid #86efac;
        border-radius: 10px;
        padding: 14px 18px;
        color: #166534;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .alert-error {
        background: #fef2f2;
        border: 1px solid #fca5a5;
        border-radius: 10px;
        padding: 14px 18px;
        color: #991b1b;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .filter-toggle {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        background: #f8fafc;
        border-radius: 10px;
        margin-bottom: 16px;
        cursor: pointer;
        border: 1px solid #e2e8f0;
    }
    .filter-toggle input { accent-color: var(--blue); width: 18px; height: 18px; cursor: pointer; }
    .filter-toggle label { cursor: pointer; font-weight: 600; color: #334155; font-size: 0.9rem; }
    .filter-toggle small { color: #94a3b8; font-size: 0.8rem; margin-left: auto; }

    .spinner {
        display: none;
        width: 18px;
        height: 18px;
        border: 3px solid rgba(255,255,255,0.3);
        border-top-color: #fff;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }
    .spinner.blue {
        border: 3px solid rgba(37,99,235,0.2);
        border-top-color: #2563eb;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    
    .form-group {
        margin-bottom: 15px;
    }
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        font-size: 0.9rem;
        color: #334155;
    }
    .form-group input[type="text"] {
        width: 100%;
        padding: 10px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 0.95rem;
    }
    
    .catalog-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }
    .btn-danger-outline {
        background: transparent;
        border: 1px solid rgba(255,255,255,0.4);
        color: #fca5a5;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.2s;
        margin-top: 15px;
        display: inline-block;
    }
    .btn-danger-outline:hover {
        background: rgba(220,38,38,0.2);
        color: #fecaca;
        border-color: #fca5a5;
    }
</style>
@endpush

@section('content')
<div class="panel">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
        <a href="{{ route('admin.dashboard') }}" style="color:#94a3b8; text-decoration:none; font-size:0.85rem;">← Panel Admin</a>
    </div>

    <h1 style="margin-top:0;">🤖 Automatizador de Catálogos</h1>
    <p class="muted" style="margin-bottom:30px;">Crea múltiples catálogos dinámicos que se generarán diariamente a las 4:00 AM.</p>

    @if(session('success'))
    <div class="alert-success">
        <span>✅</span>
        <div>
            {{ session('success') }}
            @if(session('url_generada'))
            <div style="margin-top:6px; font-size:0.85rem;">
                <strong>URL del catálogo:</strong>
                <a href="{{ session('url_generada') }}" target="_blank" style="color:#16a34a;">{{ session('url_generada') }}</a>
            </div>
            @endif
        </div>
    </div>
    @endif

    @if(session('error'))
    <div class="alert-error">
        <span>❌</span>
        <span>{{ session('error') }}</span>
    </div>
    @endif

    @if($catalogos->isNotEmpty())
    <h2 style="font-size: 1.2rem; color: #1e293b; margin-bottom: 15px;">Catálogos Activos</h2>
    <div class="catalog-grid">
        @foreach($catalogos as $cat)
        <div class="status-card" style="margin-bottom: 0;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <h3>📑 {{ $cat->nombre }}</h3>
            </div>

            @if($cat->ultima_generacion)
            <div class="status-row">
                Última actualización
                <strong>{{ \Carbon\Carbon::parse($cat->ultima_generacion)->format('d/m/Y H:i') }}</strong>
            </div>
            @else
            <div class="status-row" style="opacity:0.6;">No se ha generado aún.</div>
            @endif

            @if($cat->url_supabase)
            <div class="url-box">
                <a href="{{ $cat->url_supabase }}" target="_blank" title="{{ $cat->url_supabase }}">
                    📄 {{ $cat->archivo }}
                </a>
                <button type="button" class="btn-copy" onclick="copyUrl('{{ $cat->url_supabase }}')">Copiar</button>
            </div>
            @else
            <div style="background:rgba(255,255,255,0.1); border-radius:8px; padding:12px; text-align:center; font-size:0.85rem; margin:12px 0; opacity:0.7;">
                Sin URL (espera la auto-generación o dale a generar ahora)
            </div>
            @endif

            <form method="POST" action="{{ route('admin.catalogo-auto.generate', $cat->id) }}" onsubmit="disableBtn(this)">
                @csrf
                <button type="submit" class="generate-btn">
                    <div class="spinner blue"></div>
                    <span>⚡ Generar Ahora</span>
                </button>
            </form>
            
            <form method="POST" action="{{ route('admin.catalogo-auto.destroy', $cat->id) }}" onsubmit="return confirm('¿Seguro que deseas eliminar esta automatización? El archivo en la nube no se borrará, solo dejará de actualizarse.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-danger-outline">🗑️ Eliminar automatización</button>
            </form>
        </div>
        @endforeach
    </div>
    @endif

    <div style="border-top: 1px solid #e2e8f0; margin: 30px 0; padding-top: 30px;">
        <h2 style="font-size: 1.2rem; color: #1e293b; margin-bottom: 20px;">➕ Crear Nuevo Catálogo</h2>
        
        <form method="POST" action="{{ route('admin.catalogo-auto.config') }}" id="catalogoForm">
            @csrf
            <div class="auto-grid">

                {{-- Left column: selectors --}}
                <div>
                    <div class="cat-card" style="padding-bottom: 10px;">
                        <div class="form-group">
                            <label for="nombre">Nombre del Catálogo (Ej. Escolares y Bolsos)</label>
                            <input type="text" name="nombre" id="nombre" required placeholder="Ingresa un nombre para identificarlo">
                            <small style="color: #64748b; font-size: 0.8rem; display: block; margin-top: 4px;">El nombre del archivo será generado automáticamente (ej: escolares-y-bolsos.pdf)</small>
                        </div>
                    </div>
                
                    {{-- Filter: solo existencia --}}
                    <div class="filter-toggle">
                        <input type="checkbox" name="solo_existencia" id="chk_solo_existencia" value="1" checked>
                        <label for="chk_solo_existencia">Solo productos con existencia</label>
                        <small>Recomendado</small>
                    </div>

                    <div class="cat-card">
                        <h3>
                            <span>📂</span> Categorías y Subcategorías
                            <span style="margin-left:auto; font-size:0.78rem; font-weight:400; color:#94a3b8;">
                                (sin selección = todas)
                            </span>
                        </h3>

                        <div class="cat-tree">
                            @foreach($categorias as $cat)
                            @php
                                $subcats = $catSubcatMap[$cat] ?? collect();
                            @endphp
                            <div class="cat-block">
                                <div class="cat-header" onclick="toggleCat(this)">
                                    <input type="checkbox"
                                        name="categorias[]"
                                        value="{{ $cat }}"
                                        class="chk-cat"
                                        onclick="event.stopPropagation(); syncSubcats(this)">
                                    {{ $cat }}
                                    @if($subcats->isNotEmpty())
                                    <span class="cat-toggle">▶</span>
                                    @endif
                                </div>
                                @if($subcats->isNotEmpty())
                                <div class="subcat-list">
                                    @foreach($subcats as $sub)
                                    <label class="subcat-item">
                                        <input type="checkbox"
                                            name="subcategorias[]"
                                            value="{{ $sub }}"
                                            class="chk-sub">
                                        {{ $sub }}
                                    </label>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                            @endforeach
                        </div>

                        <div style="margin-top:16px; display:flex; gap:8px;">
                            <button type="button" onclick="selectAll(true)" style="padding:5px 12px; background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; border-radius:6px; cursor:pointer; font-size:0.8rem; font-weight:600;">✔ Seleccionar todo</button>
                            <button type="button" onclick="selectAll(false)" style="padding:5px 12px; background:#f8fafc; color:#64748b; border:1px solid #e2e8f0; border-radius:6px; cursor:pointer; font-size:0.8rem;">✗ Limpiar</button>
                        </div>
                    </div>
                </div>

                {{-- Right column: info --}}
                <div>
                    <div class="cat-card" style="position: sticky; top: 20px;">
                        <h3 style="color:#2563eb;">✨ Listo para guardar</h3>
                        <p style="font-size:0.9rem; color:#475569; margin-bottom:20px;">
                            Al guardar, se creará el automatizador. Podrás generar el PDF manualmente haciendo clic en "Generar Ahora" en su tarjeta.
                        </p>
                        
                        <button type="submit" class="generate-btn" id="btnGuardar" style="background:var(--blue); color:#fff;">
                            <div class="spinner" id="guardarSpinner"></div>
                            <span id="guardarText">💾 Guardar Automatizador</span>
                        </button>
                        
                        <div class="auto-badge" style="background:#f1f5f9; color:#475569; width:100%; justify-content:center; margin-top:12px;">
                            🕓 Se actualizará automáticamente a las 4:00 AM
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleCat(header) {
    const chk = header.querySelector('.chk-cat');
    const toggle = header.querySelector('.cat-toggle');
    const subList = header.nextElementSibling;

    if (!subList || !subList.classList.contains('subcat-list')) return;

    const isVisible = subList.classList.toggle('visible');
    if (toggle) toggle.classList.toggle('open', isVisible);
}

function syncSubcats(chkCat) {
    const block = chkCat.closest('.cat-block');
    const subList = block.querySelector('.subcat-list');
    const toggle = block.querySelector('.cat-toggle');

    if (subList) {
        if (chkCat.checked) {
            subList.classList.add('visible');
            if (toggle) toggle.classList.add('open');
        }
    }
}

function selectAll(val) {
    document.querySelectorAll('.chk-cat, .chk-sub').forEach(chk => {
        chk.checked = val;
    });
    document.querySelectorAll('.subcat-list').forEach(list => {
        list.classList.toggle('visible', val);
    });
    document.querySelectorAll('.cat-toggle').forEach(toggle => {
        toggle.classList.toggle('open', val);
    });
}

function copyUrl(url) {
    navigator.clipboard.writeText(url).then(() => {
        const btn = event.target;
        btn.textContent = '✓ Copiado';
        setTimeout(() => btn.textContent = 'Copiar', 2000);
    });
}

document.getElementById('catalogoForm').addEventListener('submit', function() {
    const btn = document.getElementById('btnGuardar');
    const spinner = document.getElementById('guardarSpinner');
    const text = document.getElementById('guardarText');
    btn.disabled = true;
    spinner.style.display = 'block';
    text.textContent = 'Guardando...';
});

function disableBtn(form) {
    const btn = form.querySelector('button');
    const spinner = form.querySelector('.spinner');
    const text = form.querySelector('span');
    btn.disabled = true;
    spinner.style.display = 'block';
    text.textContent = 'Generando...';
}
</script>
@endpush
