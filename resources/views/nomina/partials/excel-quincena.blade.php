@php
    /** @var \App\Services\Nomina\QuincenaMovimientosExcelService $excelSvc */
    $excelSvc = app(\App\Services\Nomina\QuincenaMovimientosExcelService::class);
    $opcionesQuincena = $excelSvc->opcionesQuincena();
    $fechaRef = $fecha ?? now()->toDateString();
    $quincenaSel = $excelSvc->quincenaDe($fechaRef);
@endphp
<form
    method="GET"
    action="{{ $excelRoute }}"
    style="display:inline-flex;align-items:flex-end;gap:8px;flex-wrap:wrap;margin:0;"
>
    <div class="field" style="margin:0;">
        <label for="excel-quincena-{{ md5($excelRoute) }}" style="font-size:.75rem;">Quincena Excel</label>
        <select
            id="excel-quincena-{{ md5($excelRoute) }}"
            name="inicio"
            style="min-width:210px;padding:7px 10px;border-radius:8px;border:1px solid var(--border);background:#fff;"
        >
            @foreach($opcionesQuincena as $opc)
                <option
                    value="{{ $opc['inicio'] }}"
                    @selected($opc['inicio'] === $quincenaSel['inicio']->toDateString())
                >
                    {{ $opc['etiqueta'] }}@if($opc['es_actual']) (actual)@endif
                </option>
            @endforeach
        </select>
    </div>
    <button class="btn secondary" type="submit">Descargar Excel</button>
</form>
