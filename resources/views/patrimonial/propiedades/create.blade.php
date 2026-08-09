@extends('layouts.app')
@section('title', 'Nueva Propiedad')
@section('content')

<style>
.pat-form-wrap { max-width: 800px; margin: 32px auto; padding: 0 20px; }
.pat-form-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
.pat-form-header { background: linear-gradient(135deg, #1a4480, #2563eb); padding: 24px 28px; color: #fff; }
.pat-form-header h1 { margin: 0; font-size: 1.3rem; font-weight: 700; }
.pat-form-header p { margin: 4px 0 0; opacity: 0.8; font-size: 0.85rem; }
.pat-form-body { padding: 28px; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
.form-grid.full { grid-template-columns: 1fr; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-size: 0.82rem; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; }
.form-group input, .form-group select, .form-group textarea {
    padding: 9px 12px;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.92rem;
    font-family: inherit;
    color: #1e293b;
    transition: border-color 0.18s;
    background: #fff;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}
.form-group textarea { resize: vertical; min-height: 80px; }
.form-section-title { font-size: 0.85rem; font-weight: 700; color: #334155; text-transform: uppercase; letter-spacing: 0.5px; margin: 24px 0 14px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0; }
.pat-btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 22px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; text-decoration: none; cursor: pointer; border: none; transition: all 0.18s; }
.pat-btn-primary { background: #2563eb; color: #fff; }
.pat-btn-primary:hover { background: #1a4480; }
.pat-btn-outline { background: #fff; color: #334155; border: 1px solid #e2e8f0; }
.pat-btn-outline:hover { border-color: #94a3b8; }
.error-msg { color: #dc2626; font-size: 0.78rem; margin-top: 2px; }

@media (max-width: 600px) {
    .form-grid { grid-template-columns: 1fr; }
}
</style>

<div class="pat-form-wrap">
    <div style="margin-bottom:14px;">
        <a href="{{ route('patrimonial.propiedades.index') }}" style="color:#2563eb; text-decoration:none; font-size:0.88rem;">
            ← Volver a Propiedades
        </a>
    </div>

    <div class="pat-form-card">
        <div class="pat-form-header">
            <h1>🏘️ Nueva Propiedad</h1>
            <p>Completa los datos de la ficha de la propiedad</p>
        </div>
        <div class="pat-form-body">
            <form action="{{ route('patrimonial.propiedades.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="form-section-title">📋 Información Básica</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Código Interno *</label>
                        <input type="text" name="codigo" value="{{ old('codigo') }}" placeholder="PAT-041" required>
                        @error('codigo') <span class="error-msg">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group">
                        <label>Nombre de la Propiedad *</label>
                        <input type="text" name="nombre" value="{{ old('nombre') }}" placeholder="Casa Cristal" required>
                        @error('nombre') <span class="error-msg">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group">
                        <label>Tipo *</label>
                        <select name="tipo" required>
                            <option value="">Seleccionar...</option>
                            @foreach(['casa', 'apartamento', 'local', 'galpón', 'terreno', 'condominio', 'vehículo', 'otro'] as $t)
                                <option value="{{ $t }}" {{ old('tipo') == $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                            @endforeach
                        </select>
                        @error('tipo') <span class="error-msg">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group">
                        <label>Estado Actual *</label>
                        <select name="estado" required>
                            @foreach(['disponible'=>'Disponible','alquilado'=>'Alquilado','uso_propio'=>'Uso Propio','remodelacion'=>'Remodelación','no_disponible'=>'No Disponible'] as $v => $l)
                                <option value="{{ $v }}" {{ old('estado', 'disponible') == $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-section-title">📍 Ubicación</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Dirección</label>
                        <input type="text" name="direccion" value="{{ old('direccion') }}" placeholder="Calle / Sector / Ciudad">
                    </div>
                    <div class="form-group">
                        <label>Ubicación / Zona</label>
                        <input type="text" name="ubicacion" value="{{ old('ubicacion') }}" placeholder="Centro, Doral, Sambil...">
                    </div>
                </div>

                <div class="form-section-title">👤 Responsables</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Propietario</label>
                        <input type="text" name="propietario" value="{{ old('propietario') }}" placeholder="Nombre del propietario">
                    </div>
                    <div class="form-group">
                        <label>Responsable Operativo</label>
                        <input type="text" name="responsable" value="{{ old('responsable') }}" placeholder="Encargado de la propiedad">
                    </div>
                </div>

                <div class="form-section-title">💰 Datos Financieros</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Fecha de Adquisición</label>
                        <input type="date" name="fecha_adquisicion" value="{{ old('fecha_adquisicion') }}">
                    </div>
                    <div class="form-group">
                        <label>Valor / Inversión Inicial ($)</label>
                        <input type="number" name="valor_inversion" value="{{ old('valor_inversion') }}" min="0" step="0.01" placeholder="0.00">
                    </div>
                </div>

                <div class="form-section-title">📸 Fotos y Notas</div>
                <div class="form-grid full">
                    <div class="form-group">
                        <label>Fotos (múltiples)</label>
                        <input type="file" name="fotos[]" multiple accept="image/*" style="padding: 6px;">
                    </div>
                    <div class="form-group">
                        <label>Observaciones</label>
                        <textarea name="observaciones" placeholder="Notas adicionales sobre la propiedad...">{{ old('observaciones') }}</textarea>
                    </div>
                </div>

                <div style="display:flex; gap:12px; justify-content:flex-end; margin-top:24px; padding-top:20px; border-top:1px solid #f1f5f9;">
                    <a href="{{ route('patrimonial.propiedades.index') }}" class="pat-btn pat-btn-outline">Cancelar</a>
                    <button type="submit" class="pat-btn pat-btn-primary">💾 Guardar Propiedad</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
