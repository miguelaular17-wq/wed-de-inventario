@extends('layouts.app')
@section('title', 'Conciliaciones Bancarias')
@section('content')
<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0" style="font-weight:700; color:#1e293b;">Conciliaciones Bancarias</h2>
        <button class="btn btn-primary" style="border-radius:8px;">+ Nueva Conciliación</button>
    </div>
    <div class="card shadow-sm border-0" style="border-radius:12px;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead style="background-color:#f8fafc;">
                        <tr>
                            <th>Banco</th>
                            <th>Período</th>
                            <th class="text-end">Saldo Banco</th>
                            <th class="text-end">Saldo Sistema</th>
                            <th class="text-end">Diferencia</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($conciliaciones as $con)
                            <tr>
                                <td>{{ $con->banco }}</td>
                                <td>{{ $con->fecha_inicio }} al {{ $con->fecha_fin }}</td>
                                <td class="text-end">{{ number_format($con->saldo_banco, 2) }}</td>
                                <td class="text-end">{{ number_format($con->saldo_sistema, 2) }}</td>
                                <td class="text-end">{{ number_format($con->diferencia, 2) }}</td>
                                <td>
                                    <span class="badge {{ $con->estado == 'conciliado' ? 'bg-success' : 'bg-warning text-dark' }}">
                                        {{ ucfirst($con->estado) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No hay conciliaciones registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
