@php
    $tasaBcv = $tasaBcv ?? 0;
@endphp
<div class="com-modal-card com-modal-txt">
    <div class="com-modal-head">
        <div>
            <h3 style="margin:0;">TXT del banco · {{ $periodo->etiqueta }}</h3>
            <p class="muted" style="margin:4px 0 0;">Descarga por empresa o el TXT combinado. Solo abre “Excluir personas” si quieres sacar a alguien. Al descargar se marca como PAGADO. Tasa BCV: <strong>{{ number_format($tasaBcv, 2) }}</strong>.</p>
        </div>
        <button class="btn secondary" type="button" data-cerrar-modal>Cerrar</button>
    </div>

    <form method="POST" action="{{ route('nomina.periodos.banco_todo', $periodo) }}" id="form-txt-banco">
        @csrf

        <div class="txt-acciones">
            <button class="btn primary" type="submit" id="txt-descargar-todo">Descargar TXT combinado</button>
            <span class="muted" id="txt-contador">0 seleccionados</span>
        </div>

        <div class="txt-empresas-main">
            <table class="data-table">
                <thead><tr><th>Empresa</th><th>Empleados</th><th>Nómina USD</th><th>Nómina Bs</th><th></th></tr></thead>
                <tbody>
                    @forelse($bancoPorEmpresa as $fila)
                        @if($fila->empresa)
                            <tr>
                                <td>
                                    <strong>{{ $fila->empresa->codigo }}</strong>
                                    <div class="muted" style="font-size:.78rem;">{{ $fila->empresa->nombre }}</div>
                                </td>
                                <td>{{ $fila->empleados }}</td>
                                <td>${{ number_format($fila->usd, 2) }}</td>
                                <td>Bs {{ number_format($fila->usd * $tasaBcv, 2) }}</td>
                                <td style="text-align:right;">
                                    <button class="btn primary" type="button" data-txt-empresa="{{ $fila->empresa->id }}">Descargar TXT</button>
                                </td>
                            </tr>
                        @else
                            @php $enviados = collect($enviadosSinEmpresa ?? []); @endphp
                            <tr>
                                <td colspan="5">
                                    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;">
                                        <div>
                                            <strong>Sin empresa</strong>
                                            · {{ $fila->empleados }} persona(s)
                                            · ${{ number_format($fila->usd, 2) }}
                                            · pago manual (no van en el TXT)
                                        </div>
                                        @if(!empty($enviarFinanzasRoute))
                                            {{-- formaction: no anidar otro <form> dentro del TXT (el browser descarga el TXT) --}}
                                            <button class="btn primary" type="submit"
                                                    formaction="{{ $enviarFinanzasRoute }}"
                                                    formmethod="post"
                                                    onclick="return confirm('¿Enviar estas {{ $fila->empleados }} persona(s) a Finanzas para pago manual?')">
                                                Enviar a Finanzas
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @foreach(($fila->personas ?? collect()) as $persona)
                                @php $yaEnviado = $enviados->contains((int) $persona->id); @endphp
                                <tr>
                                    <td style="padding-left:24px;">
                                        <a href="{{ route('nomina.empleados.show', ['empleado' => $persona->id, 'tab' => 'nomina']) }}">{{ $persona->nombre }}</a>
                                        <span class="muted">· {{ $persona->cedula !== '' ? $persona->cedula : 'Sin cédula' }}</span>
                                    </td>
                                    <td>1</td>
                                    <td>${{ number_format($persona->usd ?? 0, 2) }}</td>
                                    <td>Bs {{ number_format(($persona->usd ?? 0) * $tasaBcv, 2) }}</td>
                                    <td class="muted">{{ $yaEnviado ? 'Enviado a Finanzas' : 'Pendiente de enviar' }}</td>
                                </tr>
                            @endforeach
                        @endif
                    @empty
                        <tr><td colspan="5" class="muted">No hay recibos calculados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <details class="txt-excluir">
            <summary>Excluir personas del TXT</summary>
            <div class="txt-excluir-body">
                <p class="muted" style="margin:0 0 10px;">Desmarca a quien no quieras incluir. Filtra por sede o cargo para hacerlo más rápido.</p>
                <div class="txt-filtros">
                    <label>Sede
                        <select id="txt-filtro-sede">
                            <option value="">Todas</option>
                            @foreach(($filtrosTxtSedes ?? collect()) as $sede)
                                <option value="{{ $sede }}">{{ $sede }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Cargo
                        <select id="txt-filtro-cargo">
                            <option value="">Todos</option>
                            @foreach(($filtrosTxtCargos ?? collect()) as $cargo)
                                <option value="{{ $cargo }}">{{ $cargo }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="btn secondary" type="button" id="txt-marcar-visibles">Marcar visibles</button>
                    <button class="btn secondary" type="button" id="txt-desmarcar-visibles">Desmarcar visibles</button>
                </div>

                <div class="txt-lista-wrap">
                    <table class="data-table" id="tabla-txt-personas">
                        <thead>
                            <tr>
                                <th style="width:36px;"></th>
                                <th>Persona</th>
                                <th>Sede</th>
                                <th>Cargo</th>
                                <th>Empresa</th>
                                <th>USD</th>
                                <th>Bs</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($personasTxt ?? collect()) as $persona)
                                @php
                                    $puede = (bool) ($persona->descargable ?? false);
                                @endphp
                                <tr data-txt-fila
                                    data-sede="{{ $persona->sede }}"
                                    data-cargo="{{ $persona->cargo }}"
                                    data-empresa-id="{{ (int) ($persona->empresa_id ?? 0) }}"
                                    @if(! $puede) class="muted" @endif>
                                    <td>
                                        <input type="checkbox"
                                               name="empleados[]"
                                               value="{{ $persona->id }}"
                                               data-txt-check
                                               @checked($puede)
                                               @disabled(! $puede)>
                                    </td>
                                    <td>
                                        <a href="{{ route('nomina.empleados.show', ['empleado' => $persona->id, 'tab' => 'nomina']) }}">{{ $persona->nombre }}</a>
                                        <div class="muted" style="font-size:.78rem;">{{ $persona->cedula !== '' ? $persona->cedula : 'Sin cédula' }}</div>
                                    </td>
                                    <td>{{ $persona->sede }}</td>
                                    <td>{{ $persona->cargo }}</td>
                                    <td>{{ $persona->empresa !== '' ? $persona->empresa : 'Sin empresa' }}</td>
                                    <td>${{ number_format($persona->usd, 2) }}</td>
                                    <td>Bs {{ number_format($persona->usd * $tasaBcv, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="muted">No hay recibos calculados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </details>
    </form>
</div>
