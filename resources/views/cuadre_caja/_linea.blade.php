@php
    $clave = $l['clave'];
    $pos = (bool) $l['punto_venta'];
    $extra = $extra ?? false;
    $usd = (bool) $l['en_dolares'];
    $lotesGuardados = $l['lotes'] ?? [];
    $lotesIniciales = session()->hasOldInput() ? array_values(old('lotes.'.$clave, [])) : $lotesGuardados;
    $lotesIniciales = array_values(array_filter($lotesIniciales, fn ($x) => trim((string) ($x['lote'] ?? '')) !== '' || trim((string) ($x['monto'] ?? '')) !== ''));
    $conciliados = collect($lotesGuardados)->where('conciliado', true)->map(fn ($x) => ($x['banco'] ?? '').'|'.mb_strtoupper($x['lote'], 'UTF-8'))->all();
    $sumaLotes = round(array_sum(array_map(fn ($x) => (float) ($x['monto'] ?? 0), $lotesIniciales)), 2);
    $montoPos = $lotesIniciales ? number_format($sumaLotes, 2, '.', '') : '';
    $cantidad = $editable ? count($lotesIniciales) : count($lotesGuardados);
    $abierto = $editable && session()->hasOldInput() && $pos && ! $extra;
    if ($pos) {
        [$bancoPos, $titularPos] = app(\App\Services\CuadreCajaService::class)->bancoTitularPuntoVenta($l['cuenta'], (string) $cuadre->sede);
        $bancosLote = array_values(array_unique(array_merge([$bancoPos], $bancosPos ?? [])));
    }
@endphp
<tr class="cc-linea {{ $pos ? 'cc-pos' : '' }} {{ $abierto ? 'is-abierto' : '' }} {{ $extra ? 'cc-extra' : '' }} {{ $filaDif($l['diferencia_usd']) }}"
    data-clave="{{ $clave }}" data-pos="{{ $pos ? 1 : 0 }}" data-usd="{{ $usd ? 1 : 0 }}"
    data-suite="{{ $l['suite'] }}" data-suite-usd="{{ $l['suite_usd'] }}" @if($extra) style="display:none;" @endif>
    <td class="cc-col-forma">
        <div class="cc-forma">
            <strong>{{ $l['forma_pago'] ?: $l['codigo_metodo'] }}</strong>
            <span class="cc-moneda {{ $usd ? 'is-usd' : 'is-bs' }}">{{ $usd ? 'USD' : 'Bs' }}</span>
            @if($extra)<span class="cc-moneda is-extra">No declarada</span>@endif
        </div>
        @if($l['cuenta'])<div class="cc-cuenta">{{ $l['cuenta'] }}</div>@endif
    </td>
    <td class="cc-num cc-suite">{{ $extra ? '—' : ($monto($l['suite'], $usd) ?: '—') }}</td>
    <td class="cc-num cc-suite-usd">{{ $dinero($l['suite_usd']) }}</td>
    <td class="cc-num is-tes">
        @if($pos && ($editable || $lotesGuardados))
            @if($editable)
                <input type="hidden" name="montos[{{ $clave }}]" class="cc-monto" value="{{ $montoPos }}" @disabled($extra)>
            @endif
            <div class="cc-pos-suma">
                <strong>{{ $editable ? ($lotesIniciales ? 'Bs '.number_format($sumaLotes, 2, ',', '.') : '—') : ($monto($l['tesoreria'], $usd) ?: '—') }}</strong>
                <button type="button" class="cc-lotes-toggle" aria-expanded="{{ $abierto ? 'true' : 'false' }}" title="Ver o anotar los lotes del punto">
                    <span class="cc-pos-n">{{ $cantidad === 0 ? 'Anotar lotes' : ($cantidad === 1 ? '1 lote' : $cantidad.' lotes') }}</span>
                    <span class="cc-flecha">▾</span>
                </button>
            </div>
        @elseif($editable)
            <div class="cc-input-fila">
                <label class="cc-input {{ $usd ? 'is-usd' : 'is-bs' }}">
                    <span>{{ $usd ? '$' : 'Bs' }}</span>
                    <input type="number" step="0.01" min="0" name="montos[{{ $clave }}]" class="cc-monto" placeholder="0,00" inputmode="decimal"
                           value="{{ $extra ? '' : old('montos.'.$clave, $valor($l['tesoreria'])) }}" @disabled($extra)>
                </label>
                @unless($extra)
                    <button type="button" class="cc-igual" title="Usar lo que declaró Suite">=</button>
                @endunless
            </div>
        @else
            {{ $monto($l['tesoreria'], $usd) ?: '—' }}
        @endif
    </td>
    <td class="cc-num is-tes cc-tes-usd">{{ $dinero($l['tesoreria_usd']) }}</td>
    <td class="cc-num cc-dif">{!! $pildoraDif($l['diferencia_usd']) !!}</td>
</tr>
@if($pos && ($editable || $lotesGuardados))
    <tr class="cc-lotes-fila" data-para="{{ $clave }}" @unless($abierto) style="display:none;" @endunless>
        <td colspan="6">
            <div class="cc-lotes">
                <div class="cc-lotes-cab">
                    <strong>🧾 Lotes del punto</strong>
                    <span>Van a Tesorería como lote POS de titular <b>{{ $titularPos }}</b> del {{ $cuadre->fecha_cierre->format('d/m/Y') }}</span>
                </div>
                @if($editable)
                    <div class="cc-lotes-lista" data-siguiente="{{ count($lotesIniciales) }}">
                        @foreach($lotesIniciales as $i => $lt)
                            @php $bancoLt = strtoupper((string) ($lt['banco'] ?? '')) ?: $bancoPos; @endphp
                            <div class="cc-lote">
                                <label class="cc-input cc-lote-banco"><span>Banco</span>
                                    <select name="lotes[{{ $clave }}][{{ $i }}][banco]">
                                        @foreach($bancosLote as $b)
                                            <option value="{{ $b }}" @selected($b === $bancoLt)>{{ $b }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="cc-input cc-lote-num"><span>Lote</span>
                                    <input type="text" name="lotes[{{ $clave }}][{{ $i }}][lote]" value="{{ $lt['lote'] ?? '' }}" maxlength="64" placeholder="N.º" autocomplete="off">
                                </label>
                                <label class="cc-input is-bs"><span>Bs</span>
                                    <input type="number" step="0.01" min="0" name="lotes[{{ $clave }}][{{ $i }}][monto]" value="{{ $lt['monto'] ?? '' }}" class="cc-lote-monto" placeholder="0,00" inputmode="decimal">
                                </label>
                                <button type="button" class="cc-lote-quitar" title="Quitar lote">✕</button>
                                @if(in_array($bancoLt.'|'.mb_strtoupper((string) ($lt['lote'] ?? ''), 'UTF-8'), $conciliados, true))
                                    <span class="cc-moneda is-usd" title="El banco ya concilió este lote">Conciliado</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="cc-lotes-pie">
                        <button type="button" class="cc-lote-agregar">＋ Agregar lote</button>
                        <span class="cc-lotes-falta"></span>
                    </div>
                    <template class="cc-lote-plantilla">
                        <div class="cc-lote">
                            <label class="cc-input cc-lote-banco"><span>Banco</span>
                                <select name="lotes[{{ $clave }}][__I__][banco]">
                                    @foreach($bancosLote as $b)
                                        <option value="{{ $b }}" @selected($b === $bancoPos)>{{ $b }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="cc-input cc-lote-num"><span>Lote</span>
                                <input type="text" name="lotes[{{ $clave }}][__I__][lote]" maxlength="64" placeholder="N.º" autocomplete="off">
                            </label>
                            <label class="cc-input is-bs"><span>Bs</span>
                                <input type="number" step="0.01" min="0" name="lotes[{{ $clave }}][__I__][monto]" class="cc-lote-monto" placeholder="0,00" inputmode="decimal">
                            </label>
                            <button type="button" class="cc-lote-quitar" title="Quitar lote">✕</button>
                        </div>
                    </template>
                @else
                    <div class="cc-lotes-chips">
                        @foreach($lotesGuardados as $lt)
                            <span class="cc-lote-chip">
                                {{ $lt['banco'] ?: $bancoPos }} · Lote <b>{{ $lt['lote'] }}</b> · Bs {{ number_format($lt['monto'], 2, ',', '.') }}
                                @if($lt['conciliado'])<span class="cc-moneda is-usd">Conciliado</span>@endif
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </td>
    </tr>
@endif
