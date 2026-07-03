@extends('layouts.app')
@section('title', 'Flujo de Caja')
@section('content')
<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0" style="font-weight:700; color:#1e293b;">Flujo de Caja</h2>
        <button class="btn btn-primary" style="border-radius:8px;">+ Nuevo Movimiento</button>
    </div>
    <div class="card shadow-sm border-0" style="border-radius:12px;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead style="background-color:#f8fafc;">
                        <tr>
                            <th>Fecha</th>
                            <th>Concepto</th>
                            <th>Cuenta</th>
                            <th>Tipo</th>
                            <th class="text-end">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movimientos as $mov)
                            <tr>
                                <td>{{ $mov->fecha }}</td>
                                <td>{{ $mov->concepto }}</td>
                                <td>{{ $mov->cuenta ?: '-' }}</td>
                                <td>
                                    <span class="badge {{ $mov->tipo == 'ingreso' ? 'bg-success' : 'bg-danger' }}">
                                        {{ ucfirst($mov->tipo) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    {{ number_format($mov->monto, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No hay movimientos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
