@php $puedeEditar = auth()->user()?->canAccess('finanzas.editar'); @endphp
<table class="cb-table">
    <thead>
        <tr>
            <th style="width:70px;">Orden</th><th>Banco</th><th>Empresa / titular</th>
            @if($tieneNumero)<th>Nº de cuenta</th>@endif
            <th>Color TC</th><th>En Disponibilidad</th><th>Sección</th><th></th>
        </tr>
    </thead>
    <tbody>
    @foreach($lista as $c)
        @php $f = 'cta-'.$c->id; @endphp
        <tr data-fila data-banco="{{ mb_strtolower($c->banco) }}" data-disp="{{ $c->mostrar_en_principal ? 1 : 0 }}"
            data-buscar="{{ mb_strtolower($c->banco.' '.$c->titular.' '.($c->numero_cuenta ?? '')) }}">
            @if($puedeEditar)
                <td><input type="number" form="{{ $f }}" name="orden" value="{{ $c->orden }}" min="0" max="9999" style="width:62px;"></td>
                <td><strong>{{ $c->banco }}</strong></td>
                <td>{{ $c->titular }}</td>
                @if($tieneNumero)<td><input type="text" form="{{ $f }}" name="numero_cuenta" value="{{ $c->numero_cuenta }}" inputmode="numeric" maxlength="32" placeholder="—" style="width:175px; font-family:monospace;"></td>@endif
                <td style="white-space:nowrap;">
                    <input type="color" form="{{ $f }}" name="color_tc" value="{{ $c->color_tc ?: '#ffffff' }}" style="width:34px; height:26px; border:1px solid #cbd5e1; border-radius:5px; padding:1px; vertical-align:middle;">
                    <label class="cb-muted"><input type="checkbox" form="{{ $f }}" name="sin_color" value="1" @checked(! $c->color_tc)> sin color</label>
                </td>
                <td><label><input type="checkbox" form="{{ $f }}" name="mostrar_en_principal" value="1" @checked($c->mostrar_en_principal)> Sí</label></td>
                <td>
                    <select form="{{ $f }}" name="categoria_reporte" required style="max-width:230px;">
                        @if($quitadas)<option value="" selected disabled>Elige una sección…</option>@endif
                        @foreach(\App\Http\Controllers\FinanzasCuentasController::SECCIONES as $s => [$etiqueta])
                            <option value="{{ $s }}" @selected($c->categoria_reporte === $s)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </td>
                <td style="text-align:right; white-space:nowrap;">
                    <form id="{{ $f }}" method="POST" action="{{ route('finanzas.cuentas.guardar', $c) }}" style="display:inline;">@csrf<button class="cb-btn prim">Guardar</button></form>
                    @unless($quitadas)
                        <form method="POST" action="{{ route('finanzas.cuentas.quitar', $c) }}" style="display:inline;"
                              onsubmit="return confirm('¿Quitar {{ $c->banco }} · {{ $c->titular }} de Disponibilidad y del reporte? Sus saldos se conservan.');">
                            @csrf<button class="cb-btn peligro" title="Quitar del reporte">Quitar</button>
                        </form>
                    @endunless
                </td>
            @else
                <td>{{ $c->orden }}</td><td><strong>{{ $c->banco }}</strong></td><td>{{ $c->titular }}</td>
                @if($tieneNumero)<td style="font-family:monospace;">{{ $c->numero_cuenta ?: '—' }}</td>@endif
                <td>@if($c->color_tc)<span style="display:inline-block; width:22px; height:14px; border-radius:3px; background:{{ $c->color_tc }};"></span>@endif</td>
                <td>{{ $c->mostrar_en_principal ? 'Sí' : 'No' }}</td>
                <td>{{ \App\Http\Controllers\FinanzasCuentasController::SECCIONES[$c->categoria_reporte][0] ?? $c->categoria_reporte }}</td>
                <td></td>
            @endif
        </tr>
    @endforeach
    </tbody>
</table>
