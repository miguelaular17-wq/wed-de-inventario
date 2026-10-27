@extends('layouts.app')

@section('title', 'Finanzas · Configuración')

@section('content')
@php
    $puedeEditar = auth()->user()?->canAccess('finanzas.editar');
    $campo = 'width:100%; padding:7px 10px; border:1px solid #cbd5e1; border-radius:6px; background:white;';
    $paneles = [
        \App\Models\FinanzasCatalogo::TIPO_GASTO => ['Tipos de gasto', '#1a4273', 'Se suman a los ' . count($tiposSistema) . ' tipos del sistema en Flujo de Caja (egresos e ingresos) y en Cuentas por pagar.'],
        \App\Models\FinanzasCatalogo::BENEFICIARIO => ['Beneficiarios', '#0f766e', 'Aparecen en el selector de beneficiario de los egresos, junto a los proveedores del inventario.'],
        \App\Models\FinanzasCatalogo::CUENTA => ['Empresas y cuentas', '#7c3aed', 'Banco + empresa (titular). Aparecen en «Banco y Titular» de Flujo de Caja.'],
    ];
@endphp
<div style="max-width: 1200px; margin: 0 auto;">
    <h1 style="margin: 0 0 4px;">Configuración de Finanzas</h1>
    <p class="muted" style="margin: 0 0 18px;">Agrega opciones a los formularios sin tocar el sistema. Lo desactivado deja de aparecer en los formularios, pero los movimientos que ya lo usan no cambian.</p>

    @if($errors->any())<div class="alert error" style="margin-bottom:12px;">{{ $errors->first() }}</div>@endif

    @if(! $listo)
        <div class="panel" style="padding:16px; color:#9a3412;">Falta crear la tabla de configuración: ejecuta las migraciones (<code>php artisan migrate</code>).</div>
    @else
    @if($sinMigrar)
        <div class="panel" style="padding:12px 14px; margin-bottom:14px; background:#fff7ed; border:1px solid #fdba74; color:#9a3412; font-size:.86rem;">
            Los tipos de gasto y cuentas del sistema todavía no están en esta lista (siguen saliendo en los formularios igual).
            Para poder verlos y desactivarlos aquí, ejecuta una vez: <code>php artisan db:seed --class=FinanzasCatalogoSeeder</code>
        </div>
    @endif
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 16px; align-items: start;">
        @foreach($paneles as $tipo => [$titulo, $color, $ayuda])
            @php $lista = $items->get($tipo, collect()); @endphp
            <div class="panel" style="padding: 16px; border-top: 4px solid {{ $color }};">
                <h3 style="margin: 0 0 4px; color: {{ $color }};">{{ $titulo }}</h3>
                <p class="muted" style="margin: 0 0 12px; font-size: .82rem;">{{ $ayuda }}</p>

                @if($puedeEditar)
                <form method="POST" action="{{ route('finanzas.configuracion.store') }}" style="display:grid; gap:8px; margin-bottom: 14px;">
                    @csrf
                    <input type="hidden" name="tipo" value="{{ $tipo }}">
                    @if($tipo === \App\Models\FinanzasCatalogo::TIPO_GASTO)
                        <div style="display:grid; grid-template-columns: 90px 1fr; gap:8px;">
                            <input type="text" name="codigo" inputmode="numeric" placeholder="Código" value="{{ old('tipo') === $tipo ? old('codigo') : '' }}" style="{{ $campo }}">
                            <input type="text" name="nombre" required maxlength="200" placeholder="Ej. GASTOS DE VIGILANCIA" value="{{ old('tipo') === $tipo ? old('nombre') : '' }}" style="{{ $campo }}">
                        </div>
                        <small class="muted">Queda como «102 - GASTOS DE VIGILANCIA». El último código del sistema es {{ \Illuminate\Support\Str::before(last($tiposSistema), ' ') }}.</small>
                    @elseif($tipo === \App\Models\FinanzasCatalogo::BENEFICIARIO)
                        <input type="text" name="nombre" required maxlength="200" placeholder="Nombre o razón social" value="{{ old('tipo') === $tipo ? old('nombre') : '' }}" style="{{ $campo }}">
                    @else
                        <input type="text" name="banco" required list="bancosSistema" maxlength="120" placeholder="Banco (ej. Banesco, BNC, Binance)" value="{{ old('tipo') === $tipo ? old('banco') : '' }}" style="{{ $campo }}">
                        <datalist id="bancosSistema">@foreach($bancos as $b)<option value="{{ $b }}">@endforeach</datalist>
                        <input type="text" name="nombre" required maxlength="200" placeholder="Empresa / titular (ej. Tecno House)" value="{{ old('tipo') === $tipo ? old('nombre') : '' }}" style="{{ $campo }}">
                        <select name="categoria" required style="{{ $campo }}">
                            @foreach($categorias as $cat)
                                <option value="{{ $cat }}" @selected(old('categoria') === $cat)>{{ $cat }}</option>
                            @endforeach
                        </select>
                    @endif
                    <button type="submit" class="btn primary" style="justify-self:start; padding:7px 16px;">Agregar</button>
                </form>
                @endif

                <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:6px;">
                    <span style="font-size:.78rem; font-weight:700; text-transform:uppercase; color:#64748b;">En la lista ({{ $lista->where('activo', true)->count() }} activos{{ $lista->where('activo', false)->count() ? ' · ' . $lista->where('activo', false)->count() . ' desactivados' : '' }})</span>
                    @if($lista->count() > 8)
                        <input type="search" placeholder="Buscar…" oninput="const q = this.value.toLowerCase(); this.closest('.panel').querySelectorAll('[data-item]').forEach(el => el.style.display = el.dataset.item.includes(q) ? '' : 'none');"
                               style="width:150px; padding:4px 8px; border:1px solid #cbd5e1; border-radius:6px; font-size:.8rem;">
                    @endif
                </div>
                <div style="max-height: 420px; overflow-y: auto;">
                @forelse($lista as $item)
                    <div data-item="{{ mb_strtolower($item->banco . ' ' . $item->nombre . ' ' . $item->categoria) }}" style="display:flex; justify-content:space-between; align-items:center; gap:8px; padding:6px 0; border-top:1px solid #eef2f7; {{ $item->activo ? '' : 'opacity:.5;' }}">
                        <div style="font-size:.86rem;">
                            @if($tipo === \App\Models\FinanzasCatalogo::CUENTA)
                                <strong>{{ $item->banco }}</strong> · {{ $item->nombre }}
                                <div class="muted" style="font-size:.72rem;">{{ $item->categoria }}</div>
                            @else
                                {{ $item->nombre }}
                            @endif
                            @if($item->origen !== 'manual')<span class="muted" style="font-size:.7rem;"> · {{ $item->origen === 'sistema' ? 'del sistema' : 'ya usado en egresos' }}</span>@endif
                            @unless($item->activo)<span style="font-size:.72rem; color:#b91c1c; font-weight:700;"> · desactivado</span>@endunless
                        </div>
                        @if($puedeEditar)
                            <form method="POST" action="{{ route('finanzas.configuracion.toggle', $item) }}">
                                @csrf
                                <button type="submit" style="font-size:.75rem; padding:3px 8px; border-radius:4px; cursor:pointer; border:1px solid {{ $item->activo ? '#fecaca' : '#bbf7d0' }}; background:{{ $item->activo ? '#fee2e2' : '#dcfce7' }}; color:{{ $item->activo ? '#b91c1c' : '#166534' }};">
                                    {{ $item->activo ? 'Desactivar' : 'Activar' }}
                                </button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="muted" style="font-size:.82rem;">Todavía no hay nada agregado.</div>
                @endforelse
                </div>

                @if($tipo === \App\Models\FinanzasCatalogo::TIPO_GASTO)
                    <details style="margin-top:10px;">
                        <summary style="cursor:pointer; font-size:.8rem; color:#475569;">Ver los {{ count($tiposSistema) }} tipos del sistema</summary>
                        <div style="max-height:220px; overflow:auto; font-size:.78rem; color:#475569; margin-top:6px;">
                            @foreach($tiposSistema as $t)<div>{{ $t }}</div>@endforeach
                        </div>
                    </details>
                @endif
            </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
