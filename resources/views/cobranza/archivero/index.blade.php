@extends('layouts.app')
@section('title', 'Archivero · Cobranza')
@section('content')
<style>
    .ar-page { max-width: 1200px; margin: 0 auto; padding: 20px; }
    .ar-cab { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
    .ar-migas { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; font-size: .92rem; margin-bottom: 12px; }
    .ar-migas a { color: #1d4ed8; text-decoration: none; font-weight: 600; }
    .ar-migas span.sep { color: #94a3b8; }
    .ar-barra { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 14px; }
    .ar-barra input[type=search] { flex: 1; min-width: 240px; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; }
    .ar-btn { padding: 8px 14px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; color: #334155; font-weight: 600; cursor: pointer; text-decoration: none; font-size: .88rem; }
    .ar-btn.primario { background: var(--blue, #1e3a8a); color: #fff; border-color: var(--blue, #1e3a8a); }
    .ar-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 10px; margin-bottom: 18px; }
    .ar-carpeta { display: flex; gap: 10px; align-items: center; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; text-decoration: none; color: #0f172a; }
    .ar-carpeta:hover { border-color: #93c5fd; background: #f8fafc; }
    .ar-carpeta .i { font-size: 1.6rem; }
    .ar-carpeta .n { font-weight: 700; font-size: .88rem; line-height: 1.25; word-break: break-word; }
    .ar-carpeta .c { font-size: .75rem; color: #64748b; }
    .ar-tabla { width: 100%; border-collapse: collapse; background: #fff; font-size: .88rem; }
    .ar-tabla th { text-align: left; font-size: .72rem; text-transform: uppercase; color: #64748b; padding: 9px 12px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .ar-tabla td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; }
    .ar-tabla tr:hover td { background: #f8fafc; }
    .ar-tabla a.nombre { color: #0f172a; font-weight: 600; text-decoration: none; }
    .ar-tabla a.nombre:hover { color: #1d4ed8; text-decoration: underline; }
    .ar-subir { display: none; padding: 14px; border: 1px dashed #93c5fd; border-radius: 10px; background: #eff6ff; margin-bottom: 14px; }
    .ar-subir.abierto { display: block; }
</style>
<div class="ar-page">
    <div class="ar-cab">
        <div>
            <h2 style="margin:0; color: var(--blue); font-weight: 800;">🗄️ Archivero</h2>
            <p class="muted" style="margin:4px 0 0;">Documentos de Cobranza: contratos, títulos, relaciones, alquileres…</p>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <button type="button" class="ar-btn" onclick="document.getElementById('ar-carpeta-form').classList.toggle('abierto')">📁 Nueva carpeta</button>
            <button type="button" class="ar-btn primario" onclick="document.getElementById('ar-subir-form').classList.toggle('abierto')">⬆ Subir archivos</button>
        </div>
    </div>

    @if(session('success'))
        <div style="background:#d1e7dd; color:#0f5132; padding:10px 14px; border-radius:8px; margin-bottom:12px;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div style="background:#fee2e2; color:#991b1b; padding:10px 14px; border-radius:8px; margin-bottom:12px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('cobranza.archivero.subir') }}" enctype="multipart/form-data" id="ar-subir-form" class="ar-subir">
        @csrf
        <input type="hidden" name="carpeta" value="{{ $carpeta }}">
        <div style="font-weight:700; margin-bottom:6px;">Subir a: {{ $carpeta !== '' ? $carpeta : 'Archivero (raíz)' }}</div>
        <input type="file" name="archivos[]" multiple required>
        <button type="submit" class="ar-btn primario" style="margin-left:8px;">Guardar</button>
        <div class="muted" style="font-size:.78rem; margin-top:6px;">Hasta 30 archivos por vez, de máximo 50 MB cada uno.</div>
    </form>
    <form method="POST" action="{{ route('cobranza.archivero.carpeta') }}" id="ar-carpeta-form" class="ar-subir">
        @csrf
        <input type="hidden" name="carpeta" value="{{ $carpeta }}">
        <div style="font-weight:700; margin-bottom:6px;">Nueva carpeta dentro de: {{ $carpeta !== '' ? $carpeta : 'Archivero (raíz)' }}</div>
        <input type="text" name="nombre" required maxlength="120" placeholder="Nombre de la carpeta" style="padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; width:280px;">
        <button type="submit" class="ar-btn primario" style="margin-left:8px;">Crear</button>
    </form>

    <form method="GET" action="{{ route('cobranza.archivero.index') }}" class="ar-barra">
        <input type="search" name="q" value="{{ $q }}" placeholder="🔍 Buscar en todo el archivero (nombre del archivo o de la carpeta)…">
        <button type="submit" class="ar-btn primario">Buscar</button>
        @if($q !== '')<a href="{{ route('cobranza.archivero.index', array_filter(['carpeta' => $carpeta])) }}" class="ar-btn">✕ Limpiar</a>@endif
    </form>

    @if($q === '')
        <div class="ar-migas">
            <a href="{{ route('cobranza.archivero.index') }}">🗄️ Archivero</a>
            @foreach($migas as $m)
                <span class="sep">›</span>
                @if($loop->last)<strong>{{ $m['nombre'] }}</strong>@else<a href="{{ route('cobranza.archivero.index', ['carpeta' => $m['ruta']]) }}">{{ $m['nombre'] }}</a>@endif
            @endforeach
        </div>

        @if($subcarpetas->isNotEmpty())
            <div class="ar-grid">
                @foreach($subcarpetas as $s)
                    <a href="{{ route('cobranza.archivero.index', ['carpeta' => $s['ruta']]) }}" class="ar-carpeta">
                        <span class="i">📁</span>
                        <span><span class="n">{{ $s['nombre'] }}</span><br><span class="c">{{ number_format($s['archivos']) }} {{ $s['archivos'] === 1 ? 'archivo' : 'archivos' }}</span></span>
                    </a>
                @endforeach
            </div>
        @endif
    @else
        <p class="muted" style="margin:0 0 10px;">{{ $archivos->count() >= 300 ? 'Primeros 300 resultados' : $archivos->count().' resultados' }} para «{{ $q }}».</p>
    @endif

    @if($archivos->isNotEmpty())
        <div class="panel" style="padding:0; overflow:auto;">
            <table class="ar-tabla">
                <thead><tr><th>Nombre</th>@if($q !== '')<th>Carpeta</th>@endif<th style="width:100px;">Tamaño</th><th style="width:120px;">Fecha</th><th style="width:60px;"></th></tr></thead>
                <tbody>
                    @foreach($archivos as $a)
                        <tr>
                            <td><a class="nombre" href="{{ route('cobranza.archivero.ver', $a) }}" target="_blank" rel="noopener">{{ $a->icono() }} {{ $a->nombre }}</a></td>
                            @if($q !== '')<td><a href="{{ route('cobranza.archivero.index', ['carpeta' => $a->carpeta]) }}" style="color:#1d4ed8; font-size:.82rem;">{{ $a->carpeta ?: 'Raíz' }}</a></td>@endif
                            <td class="muted">{{ $a->tamanoTexto() }}</td>
                            <td class="muted">{{ ($a->modificado_en ?? $a->created_at)?->format('d/m/Y') }}</td>
                            <td style="text-align:right;">
                                <form method="POST" action="{{ route('cobranza.archivero.borrar', $a) }}" onsubmit="return confirm('¿Borrar «{{ addslashes($a->nombre) }}» del archivero?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" title="Borrar" style="border:0; background:#fee2e2; color:#b91c1c; border-radius:6px; padding:3px 8px; cursor:pointer; font-weight:700;">×</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @elseif($subcarpetas->isEmpty())
        <p class="muted" style="text-align:center; padding:40px; background:#fff; border:1px solid #e2e8f0; border-radius:10px;">
            {{ $q !== '' ? 'No se encontró nada con esa búsqueda.' : 'Esta carpeta está vacía. Usa «Subir archivos» para agregar documentos.' }}
        </p>
    @endif
</div>
@endsection
