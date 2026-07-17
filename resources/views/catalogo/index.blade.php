@extends('layouts.app')

@section('title', 'Catálogo de Productos')

@section('content')
<style>
    .catalogo-header {
        background-color: var(--blue, #1e3a8a);
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .catalogo-header h1 {
        margin: 0;
        font-size: 1.5rem;
    }
    .catalogo-badge {
        background-color: rgba(255,255,255,0.2);
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.9rem;
    }
    .filters-bar {
        background: var(--surface, #ffffff);
        padding: 20px;
        border-radius: 8px;
        border: 1px solid var(--border, #e2e8f0);
        margin-bottom: 20px;
    }
    .filters-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: end;
    }
    .filter-item {
        display: flex;
        flex-direction: column;
        flex: 1;
        min-width: 180px;
    }
    .filter-item label {
        font-size: 0.8rem;
        color: var(--muted, #64748b);
        margin-bottom: 5px;
    }
    .filter-input {
        width: 100%;
        padding: 8px 12px;
        border-radius: 6px;
        border: 1px solid var(--border, #cbd5e1);
        font-size: 0.9rem;
        background: var(--surface, #fff);
        color: var(--text, #333);
        box-sizing: border-box;
    }
    .filter-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid var(--border, #f1f5f9);
        justify-content: space-between;
    }
    .btn-custom {
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: 500;
        cursor: pointer;
        border: none;
        font-size: 0.9rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .btn-primary-custom {
        background-color: var(--blue, #1e3a8a);
        color: white;
    }
    .btn-danger-custom {
        background-color: #dc2626;
        color: white;
    }
    .btn-light-custom {
        background-color: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
    }
    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
        color: var(--text, #333);
    }
    
    .product-card {
        background: var(--surface, #f8fafc);
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .product-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    }
    .product-img-wrapper {
        width: 100%;
        padding-top: 100%;
        position: relative;
        background: white;
        border-bottom: 1px solid var(--border, #e2e8f0);
    }
    .product-img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: contain;
        padding: 10px;
        box-sizing: border-box;
    }
    .product-info {
        padding: 15px;
        display: flex;
        flex-direction: column;
        flex: 1;
    }
    .product-category {
        font-size: 0.75rem;
        color: var(--muted, #64748b);
        text-transform: uppercase;
        font-weight: 600;
        margin-bottom: 4px;
    }
    .product-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text, #0f172a);
        margin-bottom: 8px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        height: 2.7em;
    }
    .product-code {
        font-size: 0.8rem;
        color: var(--muted, #475569);
        margin-bottom: 12px;
    }
    .price-box {
        display: flex;
        justify-content: space-between;
        margin-top: auto;
        margin-bottom: 10px;
        background: white;
        padding: 8px;
        border-radius: 6px;
        border: 1px solid var(--border, #f1f5f9);
    }
    .price-label {
        font-size: 0.7rem;
        color: var(--muted, #64748b);
        display: block;
    }
    .price-value {
        font-weight: bold;
        color: var(--text, #0f172a);
    }
    .stock-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: bold;
    }
    .stock-green {
        background-color: #dcfce7;
        color: #166534;
    }
    .stock-red {
        background-color: #fee2e2;
        color: #991b1b;
    }
    
    .catalogo-grid {
        display: grid;
        gap: 20px;
        grid-template-columns: repeat(2, 1fr);
    }
    @media (min-width: 768px) {
        .catalogo-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    @media (min-width: 1024px) {
        .catalogo-grid {
            grid-template-columns: repeat(4, 1fr);
        }
    }
    @media (min-width: 1280px) {
        .catalogo-grid {
            grid-template-columns: repeat(5, 1fr);
        }
    }
    
    .empty-state {
        text-align: center;
        padding: 50px 20px;
        background: white;
        border-radius: 8px;
        border: 1px dashed var(--border, #cbd5e1);
        color: var(--muted, #64748b);
    }
    
    .pdf-actions {
        background-color: #fff1f2;
        border: 1px solid #fecdd3;
        border-radius: 8px;
        padding: 15px;
        margin-top: 15px;
        display: none;
    }
    
    /* Paginación Custom */
    .pagination {
        display: flex;
        padding-left: 0;
        list-style: none;
        gap: 5px;
        margin: 0;
        align-items: center;
    }
    .page-item .page-link {
        padding: 8px 14px;
        border: 1px solid var(--border, #cbd5e1);
        border-radius: 6px;
        background: white;
        color: var(--blue, #1e3a8a);
        text-decoration: none;
        transition: all 0.2s;
        font-weight: 500;
        font-size: 0.9rem;
    }
    .page-item.active .page-link {
        background: var(--blue, #1e3a8a);
        color: white;
        border-color: var(--blue, #1e3a8a);
    }
    .page-item.disabled .page-link {
        color: #94a3b8;
        pointer-events: none;
        background: #f8fafc;
    }
    .page-link:hover {
        background: #f1f5f9;
    }
</style>

<div>
    <div class="catalogo-header" style="display: flex; justify-content: space-between; align-items: center; padding: 15px 20px;">
        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="{{ route('inventario.index') }}" style="background: rgba(255,255,255,0.2); color: white; padding: 5px 12px; border-radius: 4px; text-decoration: none; font-size: 0.9rem; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                ← Volver
            </a>
            <h1 style="margin: 0; font-size: 1.5rem;">Catálogo de Productos</h1>
        </div>
        <div class="catalogo-badge">
            {{ $productos->total() }} productos encontrados
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-bar">
        <form action="{{ route('catalogo.index') }}" method="GET" id="form-filtros">
            <div class="filters-grid">
                <div class="filter-item">
                    <label>Buscar</label>
                    <input type="text" name="search" class="filter-input" placeholder="Nombre o código..." value="{{ request('search') }}">
                </div>
                <div class="filter-item">
                    <label>Categoría</label>
                    <select name="categoria" id="categoria" class="filter-input">
                        <option value="todas">Todas</option>
                        @foreach($categorias as $cat)
                            <option value="{{ $cat }}" {{ request('categoria') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-item">
                    <label>Subcategoría</label>
                    <select name="subcategoria" id="subcategoria" class="filter-input">
                        <option value="todas">Todas</option>
                        @foreach($subcategorias as $sub)
                            <option value="{{ $sub }}" {{ request('subcategoria') == $sub ? 'selected' : '' }}>{{ $sub }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-item">
                    <label>Sede (Existencia)</label>
                    <select name="sede" class="filter-input">
                        <option value="todas">Global (Todas)</option>
                        @foreach($sedes as $s)
                            <option value="{{ $s }}" {{ request('sede') == $s ? 'selected' : '' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-item" style="min-width: 80px; flex: 0.5;">
                    <label>Mostrar</label>
                    <select name="per_page" class="filter-input">
                        <option value="24" {{ request('per_page') == 24 ? 'selected' : '' }}>24</option>
                        <option value="48" {{ request('per_page') == 48 ? 'selected' : '' }}>48</option>
                        <option value="72" {{ request('per_page') == 72 ? 'selected' : '' }}>72</option>
                        <option value="96" {{ request('per_page') == 96 ? 'selected' : '' }}>96</option>
                    </select>
                </div>
            </div>
            
            <div class="filter-actions">
                <div style="display: flex; gap: 15px; align-items: center;">
                    <button type="submit" class="btn-custom btn-primary-custom" id="btn-buscar">
                        Buscar
                    </button>
                    <a href="{{ route('catalogo.index') }}" class="btn-custom btn-light-custom" title="Limpiar Filtros">
                        Limpiar
                    </a>
                    <div class="checkbox-item">
                        <input type="checkbox" id="solo_existencia" name="solo_existencia" value="1" {{ request('solo_existencia') ? 'checked' : '' }}>
                        <label for="solo_existencia" style="margin: 0;">Solo con existencia</label>
                    </div>
                </div>
                
                <div>
                    <button type="button" class="btn-custom btn-danger-custom" onclick="document.getElementById('pdf-panel').style.display = 'block';">
                        Exportar a PDF
                    </button>
                </div>
            </div>
            
            <!-- Panel PDF (Oculto por defecto en lugar de modal Bootstrap) -->
            <div class="pdf-actions" id="pdf-panel">
                <h4 style="margin-top: 0; color: #be123c;">Descargar Catálogo (PDF)</h4>
                <p style="font-size: 0.9rem; color: #881337; margin-bottom: 15px;">Selecciona cómo deseas descargar el catálogo (se aplicarán los filtros actuales):</p>
                
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <button type="button" onclick="descargarPDF('page')" class="btn-custom btn-danger-custom">
                        Descargar Página Actual ({{ count($productos) }} prod.)
                    </button>
                    <button type="button" onclick="descargarPDF('all')" class="btn-custom btn-danger-custom" style="background-color: #991b1b;">
                        Descargar TODO ({{ $productos->total() }} prod.)
                    </button>
                    <button type="button" class="btn-custom btn-light-custom" onclick="document.getElementById('pdf-panel').style.display = 'none';">
                        Cancelar
                    </button>
                </div>
                <p style="font-size: 0.8rem; color: #be123c; margin-top: 10px; margin-bottom: 0;">* Nota: Descargar TODO puede tardar varios minutos dependiendo de la cantidad de productos.</p>
            </div>
            
            <!-- Panel de Carga -->
            <div class="pdf-actions" id="pdf-loader" style="background-color: #f8fafc; border-color: #cbd5e1; text-align: center;">
                <div style="margin-bottom: 15px;">
                    <svg class="spinner" style="width:40px; height:40px; color:#1e3a8a; animation: spin 1s linear infinite;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <style>@keyframes spin { 100% { transform: rotate(360deg); } }</style>
                </div>
                <h4 style="margin-top: 0; color: #1e3a8a;">Generando y Subiendo a Supabase...</h4>
                <p style="font-size: 0.9rem; color: #475569; margin-bottom: 0;">Por favor espera, este proceso puede tomar unos segundos.</p>
            </div>

            <!-- Panel de Éxito -->
            <div class="pdf-actions" id="pdf-success" style="background-color: #dcfce7; border-color: #86efac;">
                <h4 style="margin-top: 0; color: #166534;">✅ Catálogo generado correctamente</h4>
                
                <div style="background: white; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1; margin-bottom: 15px; font-size: 0.85rem; word-break: break-all;">
                    <a href="#" id="pdf-success-link" target="_blank" style="color: #2563eb; text-decoration: underline;">https://enlace...</a>
                </div>
                
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" id="btn-share-open" class="btn-custom" style="background: #166534; color: white;">
                        Abrir Catálogo
                    </button>
                    <button type="button" id="btn-share-copy" class="btn-custom btn-light-custom">
                        Copiar Enlace
                    </button>
                    <button type="button" id="btn-share-wa" class="btn-custom" style="background: #25D366; color: white;">
                        WhatsApp
                    </button>
                    <button type="button" id="btn-share-mail" class="btn-custom" style="background: #ea4335; color: white;">
                        Correo
                    </button>
                    <button type="button" class="btn-custom btn-light-custom" onclick="document.getElementById('pdf-success').style.display = 'none';">
                        Cerrar
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Catálogo Grid -->
    @if($productos->isEmpty())
        <div class="empty-state">
            <h3 style="margin-bottom: 5px;">No se encontraron productos</h3>
            <p style="margin: 0;">Intenta cambiar los filtros o los términos de búsqueda.</p>
        </div>
    @else
        <div class="catalogo-grid">
            @foreach($productos as $prod)
                <div class="product-card">
                    <div class="product-img-wrapper" style="position: relative;">
                    @php
                        $codigos = explode('/', $prod->codigo);
                        if(count($codigos) === 1) {
                            $codigos = explode(' ', $prod->codigo);
                        }
                        $primary_code = trim($codigos[0]);
                        
                        $base_url = "https://hbhqbmzixgcvxkilwsau.supabase.co/storage/v1/object/public/imagenes_producto/imagenes/";
                        $jpg_url = $base_url . rawurlencode($primary_code) . ".jpg";
                        $png_url = $base_url . rawurlencode($primary_code) . ".png";
                        $no_image_url = "https://hbhqbmzixgcvxkilwsau.supabase.co/storage/v1/object/public/imagenes_producto/imagenes/no-image.jpg";
                    @endphp
                    <img src="{{ $jpg_url }}" 
                         alt="{{ $prod->descripcion }}" 
                         class="product-img"
                         loading="lazy"
                         onerror="if(this.dataset.triedPng !== 'true') { this.dataset.triedPng = 'true'; this.src='{{ $png_url }}'; } else { this.src='{{ $no_image_url }}'; this.onerror=null; }">
                    
                    @if(auth()->check() && auth()->user()->role === 'vendedor')
                        <button type="button" 
                                onclick="pedirUrlImagen('{{ $primary_code }}')"
                                style="position: absolute; top: 5px; right: 5px; background: rgba(255,255,255,0.9); border: 1px solid #cbd5e1; border-radius: 4px; padding: 2px 6px; font-size: 0.75rem; cursor: pointer; color: #1e3a8a; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                            🔗 Actualizar
                        </button>
                    @endif
                </div>
                    <div class="product-info">
                        <div class="product-category">{{ $prod->categoria ?? 'Sin Categoría' }}</div>
                        <div class="product-title" title="{{ $prod->descripcion }}">{{ $prod->descripcion }}</div>
                        <div class="product-code">Cód: <strong>{{ $prod->codigo }}</strong></div>
                        
                        <div class="price-box" style="display: flex; justify-content: space-between; gap: 5px;">
                            <div style="flex: 1; text-align: left;">
                                <span class="price-label" style="font-size: 0.7rem;">P. Unidad</span>
                                <span class="price-value">${{ number_format($prod->precio_unidad, 2) }}</span>
                            </div>
                            <div style="flex: 1; text-align: center;">
                                <span class="price-label" style="font-size: 0.7rem;">P. Mayor</span>
                                <span class="price-value">${{ number_format($prod->precio_mayor, 2) }}</span>
                            </div>
                            <div style="flex: 1; text-align: right;">
                                <span class="price-label" style="font-size: 0.7rem;">Divisa (-30%)</span>
                                <span class="price-value" style="color: #16a34a;">${{ number_format($prod->precio_unidad * 0.70, 2) }}</span>
                            </div>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
                            <span style="color: var(--muted, #64748b); font-size: 0.8rem;">Existencia:</span>
                            @if($prod->existencia > 0)
                                <span class="stock-badge stock-green">{{ number_format($prod->existencia, 0) }} unds</span>
                            @else
                                <span class="stock-badge stock-red">Agotado</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        
        <div style="margin-top: 30px; display: flex; justify-content: center;">
            {{ $productos->links('pagination::bootstrap-4') }}
        </div>
    @endif
</div>

<script>
    const catSubcatMap = @json($catSubcatMap);
    const allSubcategorias = @json($subcategorias);
    let selectedSubcat = '{{ request('subcategoria', 'todas') }}';

    const categoriaSelect = document.getElementById('categoria');
    const subcategoriaSelect = document.getElementById('subcategoria');

    function updateSubcategorias() {
        const cat = categoriaSelect.value;
        let options = [];

        if (cat === 'todas') {
            options = allSubcategorias;
        } else {
            options = catSubcatMap[cat] || [];
        }

        subcategoriaSelect.innerHTML = '<option value="todas">Todas</option>';
        
        options.forEach(sub => {
            const opt = document.createElement('option');
            opt.value = sub;
            opt.textContent = sub;
            if (sub === selectedSubcat) {
                opt.selected = true;
            }
            subcategoriaSelect.appendChild(opt);
        });
    }

    categoriaSelect.addEventListener('change', function() {
        // Reset subcategory selection to "todas" when category changes manually
        subcategoriaSelect.value = 'todas';
        selectedSubcat = 'todas'; 
        updateSubcategorias();
    });
    
    // --- NUEVA LÓGICA DE PDF ASÍNCRONA ---
    async function descargarPDF(scope) {
        let btnPanel = document.getElementById('pdf-panel');
        let loaderPanel = document.getElementById('pdf-loader');
        let successPanel = document.getElementById('pdf-success');
        
        btnPanel.style.display = 'none';
        successPanel.style.display = 'none';
        loaderPanel.style.display = 'block';

        let form = document.getElementById('form-filtros');
        let url = new URL("{{ route('catalogo.pdf') }}", window.location.origin);
        
        let formData = new FormData(form);
        for (let pair of formData.entries()) {
            if (pair[1] && pair[1] !== 'todas') {
                url.searchParams.append(pair[0], pair[1]);
            }
        }
        
        let currentUrlParams = new URLSearchParams(window.location.search);
        if (currentUrlParams.has('page') && scope === 'page') {
            url.searchParams.append('page', currentUrlParams.get('page'));
        }
        
        url.searchParams.append('pdf_scope', scope);

        try {
            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const data = await response.json();
            
            loaderPanel.style.display = 'none';
            
            if (response.ok && data.success) {
                // Configurar Modal de Éxito
                document.getElementById('pdf-success-link').href = data.url;
                document.getElementById('pdf-success-link').textContent = data.url;
                
                // Configurar botones de compartir
                document.getElementById('btn-share-open').onclick = () => window.open(data.url, '_blank');
                document.getElementById('btn-share-copy').onclick = () => {
                    navigator.clipboard.writeText(data.url);
                    alert('¡Enlace copiado al portapapeles!');
                };
                
                let encodedUrl = encodeURIComponent(data.url);
                document.getElementById('btn-share-wa').onclick = () => window.open(`https://api.whatsapp.com/send?text=${encodedUrl}`, '_blank');
                document.getElementById('btn-share-mail').onclick = () => window.location.href = `mailto:?subject=Catálogo de Productos&body=${encodedUrl}`;
                
                // Mostrar panel de éxito
                successPanel.style.display = 'block';
            } else {
                alert('Error al generar catálogo: ' + (data.error || 'Error desconocido'));
                btnPanel.style.display = 'block';
            }
        } catch (error) {
            console.error('Fetch error:', error);
            loaderPanel.style.display = 'none';
            btnPanel.style.display = 'block';
            alert('Ocurrió un error de conexión al generar el catálogo.');
        }
    }

    // --- LÓGICA PARA ACTUALIZAR IMAGEN VÍA URL ---
    async function pedirUrlImagen(codigo) {
        const nuevaUrl = prompt('Pega aquí el enlace de la nueva imagen (debe terminar en .jpg o .png):');
        if (!nuevaUrl || nuevaUrl.trim() === '') return;
        
        try {
            // Mostrar estado de carga usando un toast o alert temporal
            window.showStatusMessage ? window.showStatusMessage('Descargando y subiendo imagen...', false) : console.log('Subiendo...');
            
            const response = await fetch("{{ route('catalogo.upload_image') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    codigo: codigo,
                    imagen_url: nuevaUrl
                })
            });
            
            const data = await response.json();
            
            if (response.ok && data.success) {
                alert('¡Imagen actualizada exitosamente!');
                window.location.reload(); // Recargar para ver la nueva imagen
            } else {
                alert('Error al actualizar: ' + (data.error || 'Error desconocido'));
            }
        } catch (error) {
            console.error('Fetch error:', error);
            alert('Ocurrió un error al intentar conectarse con el servidor.');
        }
    }

    document.getElementById('form-filtros').addEventListener('submit', function(e) {
        // Only show loading if we are submitting the main form, not exporting to PDF
        if(e.submitter && e.submitter.name === 'pdf_scope') {
            return;
        }
        let btn = document.getElementById('btn-buscar');
        btn.innerHTML = 'Buscando...';
        btn.disabled = true;
    });
</script>
@endsection
