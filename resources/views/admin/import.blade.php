@extends('layouts.app')

@section('title', 'Importar ExelMultiSede')

@section('content')
<div class="panel">
    <h1 style="margin-top:0;">Importar ExelMultiSede</h1>
    <p class="muted">
        Sube el archivo <strong>ExelMultiSede (2).xlsx</strong> (hoja BDD). Reemplaza productos, stock y ventas en la base de datos,
        igual que «Cargar reporte multisede» en <code>requisiciones.py</code>.
    </p>

    <form method="POST" action="{{ route('admin.import.store') }}" enctype="multipart/form-data" style="max-width:520px;margin-top:20px;">
        @csrf
        <div style="margin-bottom:16px;">
            <label>Archivo Excel (.xlsx)</label>
            <input type="file" name="excel" accept=".xlsx,.xls" required style="width:100%;">
        </div>
        <button type="submit" class="btn" id="import-btn">Importar a la base de datos</button>
        <a href="{{ route('admin.dashboard') }}" class="btn secondary" style="margin-left:8px;">Cancelar</a>
    </form>

    <p id="import-loading" class="muted" style="display:none;margin-top:16px;">
        Importando… puede tardar 1–2 minutos con archivos grandes.
    </p>

    <p class="muted" style="margin-top:12px;">
        Archivos grandes (~12.000 productos) pueden tardar <strong>1–2 minutos</strong>. No cierre la página hasta ver el mensaje de confirmación.
    </p>
</div>
@endsection

@push('scripts')
<script>
document.querySelector('form[action="{{ route('admin.import.store') }}"]')?.addEventListener('submit', () => {
    const btn = document.getElementById('import-btn');
    const msg = document.getElementById('import-loading');
    if (btn) { btn.disabled = true; btn.textContent = 'Importando…'; }
    if (msg) msg.style.display = 'block';
});
</script>
@endpush
