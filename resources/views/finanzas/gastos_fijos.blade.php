@extends('layouts.app')
@section('title', 'Gastos Fijos')
@section('content')

<meta name="csrf-token" content="{{ csrf_token() }}">

<style>
    .gf-page {
        padding: 24px;
        font-family: 'Inter', sans-serif;
        background: #f1f5f9;
        min-height: 100vh;
    }

    /* ── Header ── */
    .gf-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 12px;
    }
    .gf-title {
        margin: 0;
        font-size: 1.6rem;
        color: #0f172a;
        font-weight: 800;
        letter-spacing: -0.5px;
    }
    .gf-title span { color: #2563eb; }

    /* ── Notifications Panel ── */
    .gf-notif-panel {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        margin-bottom: 24px;
        box-shadow: 0 4px 20px rgba(15,23,42,0.06), 0 1px 3px rgba(15,23,42,0.04);
        overflow: hidden;
    }
    .gf-notif-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 22px;
        background: linear-gradient(135deg, #1e3a5f 0%, #0f2847 100%);
        color: #e0eaff;
        cursor: pointer;
        user-select: none;
        transition: background 0.2s;
    }
    .gf-notif-header:hover {
        background: linear-gradient(135deg, #234670 0%, #132f52 100%);
    }
    .gf-notif-header-left {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        font-size: 0.92rem;
    }
    .gf-notif-header-left svg { flex-shrink: 0; }
    .gf-notif-count-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        height: 24px;
        padding: 0 7px;
        border-radius: 12px;
        background: #ef4444;
        color: #fff;
        font-size: 0.75rem;
        font-weight: 800;
        animation: pulse-badge 2s infinite;
    }
    @keyframes pulse-badge {
        0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0.4); }
        50% { box-shadow: 0 0 0 6px rgba(239,68,68,0); }
    }
    .gf-notif-toggle {
        font-size: 0.75rem;
        color: #93b8e8;
        display: flex;
        align-items: center;
        gap: 5px;
        font-weight: 600;
        letter-spacing: 0.3px;
    }
    .gf-notif-toggle svg {
        transition: transform 0.25s ease;
    }
    .gf-notif-panel.collapsed .gf-notif-toggle svg {
        transform: rotate(-90deg);
    }
    .gf-notif-body {
        max-height: 600px;
        overflow: hidden;
        transition: max-height 0.35s cubic-bezier(.4,0,.2,1), opacity 0.25s ease;
    }
    .gf-notif-panel.collapsed .gf-notif-body {
        max-height: 0;
        opacity: 0;
    }
    .gf-notif-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.84rem;
    }
    .gf-notif-table thead th {
        background: #f8fafc;
        color: #64748b;
        padding: 8px 14px;
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e2e8f0;
        text-align: left;
        white-space: nowrap;
    }
    .gf-notif-table thead th:last-child { text-align: center; }
    .gf-notif-table thead th.th-monto { text-align: right; }
    .gf-notif-table tbody tr {
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.12s;
    }
    .gf-notif-table tbody tr:hover {
        background: #f8fafc;
    }
    .gf-notif-table tbody tr.notif-urgente {
        background: #fef2f2;
    }
    .gf-notif-table tbody tr.notif-urgente:hover {
        background: #fee2e2;
    }
    .gf-notif-table tbody td {
        padding: 10px 14px;
        vertical-align: middle;
    }
    .gf-notif-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        white-space: nowrap;
    }
    .gf-notif-badge.hoy {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        color: #fff;
        animation: pulse-badge 2s infinite;
    }
    .gf-notif-badge.proximo {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: #fff;
    }
    .gf-notif-badge.semanal {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: #fff;
    }
    .notif-servicio {
        font-weight: 700;
        color: #0f172a;
        font-size: 0.85rem;
    }
    .notif-empresa {
        color: #64748b;
        font-size: 0.78rem;
    }
    .notif-grupo {
        font-size: 0.72rem;
        color: #94a3b8;
        font-weight: 600;
    }
    .notif-monto {
        text-align: right;
        font-weight: 800;
        font-size: 0.92rem;
        color: #0f172a;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .gf-btn-pagar {
        background: linear-gradient(135deg, #10b981, #059669);
        border: none;
        color: white;
        border-radius: 8px;
        padding: 6px 14px;
        cursor: pointer;
        font-size: 0.75rem;
        font-weight: 700;
        transition: all 0.18s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        box-shadow: 0 2px 6px rgba(16,185,129,0.3);
        letter-spacing: 0.3px;
    }
    .gf-btn-pagar:hover {
        background: linear-gradient(135deg, #059669, #047857);
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(16,185,129,0.35);
    }
    .td-accion { text-align: center; }
    .gf-notif-summary {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 16px;
        padding: 10px 22px;
        background: #f8fafc;
        border-top: 2px solid #e2e8f0;
        font-size: 0.78rem;
        color: #64748b;
    }
    .gf-notif-summary strong {
        font-size: 0.95rem;
        color: #0f172a;
    }
    @media (max-width: 768px) {
        .gf-notif-table thead { display: none; }
        .gf-notif-table tbody tr {
            display: flex;
            flex-wrap: wrap;
            padding: 10px 14px;
            gap: 6px;
            align-items: center;
        }
        .gf-notif-table tbody td {
            padding: 2px 4px;
            border: none;
        }
        .notif-monto { margin-left: auto; }
    }

    /* ── Tabs ── */
    .gf-tabs {
        display: flex;
        gap: 4px;
        margin-bottom: 0;
        flex-wrap: wrap;
    }
    .gf-tab {
        padding: 11px 22px;
        border: none;
        background: #dde3ee;
        color: #5a6982;
        font-weight: 600;
        font-size: 0.9rem;
        border-radius: 10px 10px 0 0;
        cursor: pointer;
        transition: all 0.22s cubic-bezier(.4,0,.2,1);
        font-family: 'Inter', sans-serif;
        position: relative;
        bottom: -1px;
        display: flex;
        align-items: center;
        gap: 7px;
        letter-spacing: 0.1px;
    }
    .gf-tab:hover { background: #c8d3e6; color: #1e293b; transform: translateY(-1px); }
    .gf-tab.active {
        background: #ffffff;
        color: #1d4ed8;
        box-shadow: 0 -3px 12px rgba(37,99,235,0.13);
        border: 1px solid #d1daf0;
        border-bottom: 2px solid #ffffff;
        z-index: 2;
    }
    .gf-tab .tab-icon { font-size: 1.05em; }

    /* ── Sede Filter Bar ── */
    .gf-sede-filter {
        display: flex;
        align-items: center;
        gap: 12px;
        background: linear-gradient(135deg, #f0f4fb 0%, #e8eef8 100%);
        padding: 12px 20px;
        border-bottom: 1px solid #dde3ee;
    }
    .gf-sede-filter-label {
        font-size: 0.72rem;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .gf-sede-filter-label svg { flex-shrink: 0; }
    .gf-sede-select {
        flex: 1;
        max-width: 480px;
        padding: 8px 14px;
        border: 1.5px solid #cbd5e1;
        border-radius: 10px;
        font-size: 0.85rem;
        font-weight: 600;
        color: #1e293b;
        background: #ffffff;
        font-family: 'Inter', sans-serif;
        cursor: pointer;
        transition: all 0.18s ease;
        box-shadow: 0 1px 4px rgba(0,0,0,0.06);
        appearance: none;
        -webkit-appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        padding-right: 34px;
    }
    .gf-sede-select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59,130,246,0.12), 0 1px 4px rgba(0,0,0,0.06);
    }
    .gf-sede-select:hover {
        border-color: #93c5fd;
    }
    .gf-sede-count {
        font-size: 0.75rem;
        color: #94a3b8;
        white-space: nowrap;
        font-weight: 600;
    }
    .gf-sede-count strong {
        color: #3b82f6;
    }

    /* ── Table Wrapper ── */
    .gf-table-wrapper {
        background: #ffffff;
        border-radius: 0 14px 14px 14px;
        box-shadow: 0 8px 32px rgba(15,23,42,0.09), 0 1px 3px rgba(15,23,42,0.04);
        border: 1px solid #dde3ee;
        overflow: hidden;
        animation: fadeInUp 0.3s cubic-bezier(.4,0,.2,1);
    }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(10px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .gf-table-title {
        background: linear-gradient(135deg, #1a3560 0%, #0e2040 60%, #0a1830 100%);
        color: #e0eaff;
        padding: 14px 24px;
        font-size: 0.88rem;
        font-weight: 700;
        text-align: center;
        letter-spacing: 0.7px;
        text-transform: uppercase;
        border-bottom: 2px solid #2a4a7f;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    .gf-table-title::before {
        content: '';
        display: inline-block;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #60a5fa;
        box-shadow: 0 0 8px #60a5fa;
    }
    .gf-table-scroll {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    /* ── Table ── */
    .gf-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.845rem;
        min-width: 900px;
    }
    .gf-table thead th {
        background: linear-gradient(180deg, #1e3f72 0%, #17305a 100%);
        color: #c8d9f5;
        padding: 11px 13px;
        text-align: center;
        font-weight: 700;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
        border-right: 1px solid #254d8a;
        border-bottom: none;
        position: sticky;
        top: 0;
        z-index: 1;
    }
    .gf-table thead th:first-child { border-left: none; text-align: left; padding-left: 16px; }
    .gf-table thead th.th-month {
        background: linear-gradient(180deg, #17305a 0%, #112444 100%);
        color: #93b8e8;
        font-size: 0.72rem;
        border-right: 1px solid #1e3a5f;
    }
    .gf-table thead th.th-costo {
        background: linear-gradient(180deg, #1a3d72 0%, #142e59 100%);
        color: #7dd3fc;
    }
    .gf-table tbody td {
        padding: 9px 12px;
        border-right: 1px solid #eef0f6;
        border-bottom: 1px solid #eef0f6;
        color: #334155;
        vertical-align: middle;
    }
    .gf-table tbody tr:nth-child(even) { background: #f7f9fd; }
    .gf-table tbody tr:hover { background: #eff4ff; transition: background 0.12s; }

    /* Column-specific styles */
    .gf-table .col-sede {
        font-weight: 700;
        color: #1a3a7a;
        font-size: 0.72rem;
        max-width: 160px;
        min-width: 120px;
        background: #eef3ff;
        border-right: 2px solid #c7d7f8;
        line-height: 1.3;
        vertical-align: middle;
        padding: 8px 10px;
    }
    /* Hide repeated sede name in non-first rows of same group */
    .gf-table tr.gasto-row:not(.sede-first) .col-sede {
        color: transparent;
        font-size: 0;
        user-select: none;
    }
    .gf-table tr.gasto-row:not(.sede-first) .col-sede::after {
        content: '│';
        color: #c7d7f8;
        font-size: 0.9rem;
        display: block;
        text-align: center;
    }
    /* Sede group visual separator */
    .gf-table .sede-first td {
        border-top: 2.5px solid #3b82f6 !important;
    }
    .gf-table .sede-first .col-sede {
        background: linear-gradient(180deg, #dbeafe 0%, #eef3ff 100%);
        position: relative;
    }
    .gf-table .sede-first .col-sede::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 3px;
        background: linear-gradient(180deg, #3b82f6, #6366f1);
        border-radius: 0 2px 2px 0;
    }
    .sede-name-main {
        font-weight: 700;
        font-size: 0.73rem;
        color: #1a3a7a;
        line-height: 1.25;
    }
    .sede-name-sub {
        font-weight: 600;
        font-size: 0.65rem;
        color: #64748b;
        margin-top: 2px;
        line-height: 1.2;
        text-transform: uppercase;
        letter-spacing: 0.2px;
    }
    .gf-table .col-servicio {
        font-weight: 600;
        color: #0f172a;
        white-space: nowrap;
        padding-left: 14px;
    }
    .gf-table .col-fecha {
        text-align: center;
        font-size: 0.8rem;
        white-space: nowrap;
    }
    .gf-fecha-badge {
        display: inline-block;
        padding: 2px 9px;
        border-radius: 12px;
        background: #e0f2fe;
        color: #0369a1;
        font-weight: 600;
        font-size: 0.77rem;
        border: 1px solid #bae6fd;
    }
    .gf-table .col-empresa {
        color: #64748b;
        font-size: 0.8rem;
        font-style: italic;
    }
    .gf-table .col-costo {
        text-align: right;
        font-weight: 700;
        color: #0f172a;
        white-space: nowrap;
        background: rgba(37,99,235,0.045);
        font-variant-numeric: tabular-nums;
        border-right: 2px solid #d1daf0;
    }
    .gf-table .col-mes {
        text-align: right;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
        font-size: 0.83rem;
    }
    .gf-table .col-mes.has-value { color: #1e3a7a; font-weight: 600; }
    .gf-table .col-mes.no-value { color: #d1d5db; }

    /* Editable cells */
    .gf-table td.editable {
        cursor: default;
        transition: background 0.18s, outline 0.18s, box-shadow 0.18s;
        position: relative;
        user-select: none;
    }
    .gf-table td.editable::after {
        content: '✏';
        position: absolute;
        right: 4px;
        top: 4px;
        font-size: 0.6rem;
        color: #c7d3e8;
        opacity: 0;
        transition: opacity 0.15s;
        line-height: 1;
    }
    .gf-table td.editable:hover::after { opacity: 1; }
    .gf-table td.editable:hover {
        background: #fef9e7 !important;
        outline: 2px dashed #f59e0b;
        outline-offset: -2px;
        z-index: 2;
        cursor: pointer;
    }
    .gf-table td.editable.editing {
        background: #fffde7 !important;
        outline: 2px solid #f59e0b;
        box-shadow: 0 0 0 4px rgba(245,158,11,0.12);
        outline-offset: -2px;
        z-index: 10;
        padding: 0 !important;
    }
    .gf-table td.editable.saving {
        opacity: 0.55;
        pointer-events: none;
    }
    .gf-table td.editable.success { animation: flashGreen 1.1s ease; }
    .gf-table td.editable.error   { animation: flashRed   1.1s ease; }
    @keyframes flashGreen { 0%,20% { background-color: #bbf7d0 !important; } 100% { background-color: transparent; } }
    @keyframes flashRed   { 0%,20% { background-color: #fecaca !important; } 100% { background-color: transparent; } }

    /* Inline edit input inside cell */
    .gf-inline-input {
        width: 100%;
        height: 100%;
        min-height: 34px;
        border: none;
        outline: none;
        background: transparent;
        font-family: 'Inter', sans-serif;
        font-size: 0.845rem;
        font-weight: 700;
        color: #0f172a;
        text-align: right;
        padding: 6px 10px;
        box-sizing: border-box;
        font-variant-numeric: tabular-nums;
        display: block;
    }
    .gf-inline-input:focus { outline: none; }
    /* Text inline input (servicio, empresa) */
    .gf-inline-input.text {
        text-align: left;
        font-weight: 600;
        font-size: 0.84rem;
        font-variant-numeric: normal;
    }

    /* Total row */
    .gf-table .total-row {
        background: linear-gradient(135deg, #1a3560 0%, #0e2040 100%) !important;
    }
    .gf-table .total-row td {
        color: #e0eaff !important;
        font-weight: 700 !important;
        padding: 13px 12px;
        border-color: #2a4a7f !important;
        font-size: 0.86rem;
        font-variant-numeric: tabular-nums;
    }
    .gf-table .total-row td:first-child { color: #93b8e8 !important; }

    /* Add row */
    .tr-add-sede td {
        padding: 7px 10px !important;
        background: #f7f9fd !important;
        border: none !important;
        border-top: 1.5px dashed #d1daf0 !important;
    }
    .btn-add-sede {
        background: none;
        border: 1.5px dashed #93c5fd;
        color: #3b82f6;
        font-size: 0.79rem;
        font-weight: 600;
        padding: 5px 14px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.18s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-family: 'Inter', sans-serif;
    }
    .btn-add-sede:hover {
        background: #eff6ff;
        border-color: #2563eb;
        color: #1d4ed8;
        box-shadow: 0 2px 8px rgba(37,99,235,0.12);
    }

    /* (sede group separator moved to col-sede styles above) */

    /* Delete button */
    .col-accion { text-align: center; width: 36px; padding: 4px !important; }
    .btn-del-row {
        background: none;
        border: none;
        color: #d1d9e6;
        cursor: pointer;
        padding: 4px 7px;
        border-radius: 6px;
        font-size: 0.95rem;
        transition: color 0.15s, background 0.15s, transform 0.1s;
        line-height: 1;
    }
    .btn-del-row:hover { color: #ef4444; background: #fee2e2; transform: scale(1.15); }

    /* ── Modal ── */
    .gf-modal-bg {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(10, 18, 38, 0.65);
        backdrop-filter: blur(3px);
        z-index: 10000;
        align-items: center;
        justify-content: center;
    }
    .gf-modal-bg.open { display: flex; }
    .gf-modal {
        background: #fff;
        border-radius: 16px;
        padding: 30px 34px;
        width: min(500px, 95vw);
        box-shadow: 0 24px 64px rgba(0,0,0,0.28), 0 4px 12px rgba(0,0,0,0.1);
        animation: modalSlideIn 0.25s cubic-bezier(.4,0,.2,1);
    }
    @keyframes modalSlideIn {
        from { opacity: 0; transform: scale(0.96) translateY(12px); }
        to   { opacity: 1; transform: scale(1)    translateY(0); }
    }
    .gf-modal h3 {
        margin: 0 0 22px 0;
        font-size: 1.12rem;
        font-weight: 800;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .gf-modal h3::before {
        content: '';
        display: inline-block;
        width: 4px;
        height: 22px;
        border-radius: 3px;
        background: linear-gradient(135deg, #3b82f6, #6366f1);
    }
    .gf-modal label {
        display: block;
        font-size: 0.75rem;
        font-weight: 700;
        color: #475569;
        margin-bottom: 5px;
        margin-top: 16px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .gf-modal input, .gf-modal select {
        width: 100%;
        padding: 9px 13px;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.9rem;
        background: #f8fafc;
        box-sizing: border-box;
        font-family: 'Inter', sans-serif;
        transition: border-color 0.18s, box-shadow 0.18s;
    }
    .gf-modal input:focus, .gf-modal select:focus {
        outline: none;
        border-color: #3b82f6;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(59,130,246,0.12);
    }
    .gf-modal-actions {
        display: flex;
        gap: 10px;
        margin-top: 24px;
        justify-content: flex-end;
    }
    .btn-modal-cancel {
        padding: 9px 20px;
        background: #f1f5f9;
        border: none;
        border-radius: 8px;
        color: #64748b;
        font-weight: 600;
        cursor: pointer;
        font-family: 'Inter', sans-serif;
        transition: background 0.15s;
    }
    .btn-modal-cancel:hover { background: #e2e8f0; }
    .btn-modal-save {
        padding: 9px 22px;
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        border: none;
        border-radius: 8px;
        color: #fff;
        font-weight: 700;
        cursor: pointer;
        font-family: 'Inter', sans-serif;
        box-shadow: 0 4px 12px rgba(37,99,235,0.28);
        transition: opacity 0.15s, transform 0.15s;
    }
    .btn-modal-save:hover { opacity: 0.92; transform: translateY(-1px); }
    .btn-modal-save:disabled { opacity: 0.55; pointer-events: none; }

    /* ── Responsive ── */
    @media (max-width: 768px) {
        .gf-page { padding: 12px; }
        .gf-tabs { gap: 3px; }
        .gf-tab { padding: 9px 14px; font-size: 0.82rem; }
        .gf-notif-list { grid-template-columns: 1fr; }
    }
</style>

<div class="gf-page">
    <!-- Header -->
    <div class="gf-header">
        <h1 class="gf-title"><span>📋</span> Gastos <span>Fijos</span></h1>
        <div style="display:flex;align-items:center;gap:8px;color:#64748b;font-size:0.9rem;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Mostrando datos hasta <strong style="color:#2563eb;margin-left:4px;">{{ $nombresMeses[$mesActual - 1] }} 2026</strong>
        </div>
    </div>

    <!-- Notifications Panel -->
    @if(count($notificaciones) > 0)
    @php $totalNotif = collect($notificaciones)->sum('costo'); @endphp
    <div class="gf-notif-panel" id="notifPanel">
        <div class="gf-notif-header" onclick="document.getElementById('notifPanel').classList.toggle('collapsed')">
            <div class="gf-notif-header-left">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                Facturas Próximas a Pagar
                <span class="gf-notif-count-badge">{{ count($notificaciones) }}</span>
            </div>
            <div class="gf-notif-toggle">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
            </div>
        </div>
        <div class="gf-notif-body">
            <table class="gf-notif-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Servicio</th>
                        <th>Empresa</th>
                        <th>Grupo</th>
                        <th class="th-monto">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($notificaciones as $notif)
                    <tr class="{{ $notif['urgente'] ? 'notif-urgente' : '' }}">
                        <td>
                            <span class="gf-notif-badge {{ $notif['tipo'] }}">
                                @if($notif['tipo'] === 'hoy')⚡ HOY
                                @elseif($notif['tipo'] === 'proximo')📅 Día {{ $notif['dia'] ?? '' }}
                                @else🔄 {{ $notif['fecha'] }}
                                @endif
                            </span>
                        </td>
                        <td class="notif-servicio">{{ $notif['servicio'] }}</td>
                        <td class="notif-empresa">{{ $notif['empresa'] }}</td>
                        <td class="notif-grupo">{{ $notif['tabla'] }}</td>
                        <td class="notif-monto">$ {{ number_format($notif['costo'], 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="gf-notif-summary">
                Total pendiente: <strong>$ {{ number_format($totalNotif, 2) }}</strong>
            </div>
        </div>
    </div>
    @endif

    <!-- Tabs -->
    <div class="gf-tabs">
        <button class="gf-tab active" onclick="showTab(0, this)">🏢 Grupo Inmobiliario</button>
        <button class="gf-tab" onclick="showTab(1, this)">🏬 Palacio / Nunes / Euronissi</button>
        <button class="gf-tab" onclick="showTab(2, this)">👤 Directivo</button>
    </div>

    <!-- Tables -->
    @foreach($tablas as $tIndex => $tabla)
    <div class="gf-table-wrapper" id="tabla-{{ $tIndex }}" style="{{ $tIndex > 0 ? 'display:none' : '' }}">
        <div class="gf-table-title">{{ $tabla['titulo'] }}</div>
        
        @if($tabla['tiene_sede'])
            @php
                $sedesList = collect($tabla['filas'])->pluck('sede')->filter()->unique()->values();
                $sedesCount = $sedesList->count();
            @endphp
            <div class="gf-sede-filter">
                <span class="gf-sede-filter-label">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/></svg>
                    Filtrar Sede
                </span>
                <select class="gf-sede-select" onchange="showSubTab({{ $tIndex }}, this.value, this)">
                    @foreach($sedesList as $sedeName)
                        @php
                            $sedeShort = $sedeName;
                            // Crear nombre corto para el dropdown
                            $sedeShort = preg_replace('/,\s*C\.?A\.?\s*/i', ' — ', $sedeShort);
                            $sedeShort = preg_replace('/\s+J\d{9,}/', '', $sedeShort);
                        @endphp
                        <option value="{{ addslashes($sedeName) }}">{{ $sedeShort }}</option>
                    @endforeach
                </select>
                <span class="gf-sede-count">
                    <strong>{{ count($tabla['filas']) }}</strong> gastos
                </span>
            </div>
        @endif

        <div class="gf-table-scroll">
            <table class="gf-table">
                <thead>
                    <tr>
                        @if($tabla['tiene_sede'])
                            <th class="th-sede">SEDE</th>
                        @endif
                        <th>SERVICIO</th>
                        <th>FECHA</th>
                        <th>EMPRESA</th>
                        <th>COSTO ESTIMADO</th>
                        @for($m = 0; $m < $mesActual; $m++)
                            <th>{{ $nombresMeses[$m] }}</th>
                        @endfor
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @php $lastSede = '___NONE___'; @endphp
                    @foreach($tabla['filas'] as $fIndex => $fila)
                        @php
                            $isSedeFirst = $tabla['tiene_sede'] && !empty($fila['sede']) && $fila['sede'] !== $lastSede;
                            if ($isSedeFirst) $lastSede = $fila['sede'];
                        @endphp
                        <tr class="gasto-row {{ $isSedeFirst ? 'sede-first' : '' }}" 
                            data-sede="{{ $lastSede }}"
                            data-costo="{{ $fila['costo'] ?? 0 }}"
                            @for($m = 0; $m < $mesActual; $m++)
                                data-mes-{{ $m }}="{{ $fila['meses'][$m] ?? 0 }}"
                            @endfor
                        >
                            @if($tabla['tiene_sede'])
                                @php
                                    $sedeDisplay = $fila['sede'] ?? '';
                                    // Nombre limpio para la celda de sede
                                    $sedeClean = preg_replace('/\s+J\d{9,}/', '', $sedeDisplay);
                                    $sedeParts = preg_split('/,\s*C\.?A\.?\s*/i', $sedeClean, 2);
                                    $sedeName1 = trim($sedeParts[0] ?? '');
                                    $sedeName2 = isset($sedeParts[1]) ? trim($sedeParts[1]) : '';
                                @endphp
                                <td class="col-sede">
                                    <div class="sede-name-main">{{ $sedeName1 }}</div>
                                    @if($sedeName2)
                                        <div class="sede-name-sub">{{ $sedeName2 }}</div>
                                    @endif
                                </td>
                            @endif
                            <td class="col-servicio editable" data-type="servicio" data-fidx="{{ $fila['id'] }}" data-original="{{ $fila['servicio'] }}" ondblclick="startInlineTextEdit(this)">{{ $fila['servicio'] }}</td>
                            <td class="col-fecha editable" ondblclick="openFechaModal({{ $fila['id'] }}, this)" data-type="fecha" data-fidx="{{ $fila['id'] }}">{{ $fila['fecha'] }}</td>
                            <td class="col-empresa editable" data-type="empresa" data-fidx="{{ $fila['id'] }}" data-original="{{ $fila['empresa'] }}" ondblclick="startInlineTextEdit(this)">{{ $fila['empresa'] }}</td>
                            <td class="col-costo editable {{ $fila['costo'] > 0 ? 'has-value' : 'no-value' }}" data-type="costo" data-tidx="{{ $tIndex }}" data-fidx="{{ $fila['id'] }}" data-original="{{ $fila['costo'] > 0 ? number_format($fila['costo'], 2, '.', '') : '' }}" ondblclick="startInlineEdit(this)">
                                @if($fila['costo'] > 0)
                                    {{ number_format($fila['costo'], 2, '.', '') }}
                                @else
                                    
                                @endif
                            </td>
                            @for($m = 0; $m < $mesActual; $m++)
                                @php $val = $fila['meses'][$m] ?? null; @endphp
                                <td class="col-mes editable {{ $val !== null ? 'has-value' : 'no-value' }}" data-type="monto" data-tidx="{{ $tIndex }}" data-fidx="{{ $fila['id'] }}" data-midx="{{ $m }}" data-original="{{ $val !== null ? number_format($val, 2, '.', '') : '' }}" ondblclick="startInlineEdit(this)">
                                    {{ $val !== null ? number_format($val, 2, '.', '') : '' }}
                                </td>
                            @endfor
                            <td class="col-accion">
                                <button class="btn-del-row" onclick="deleteRow({{ $fila['id'] }}, this)" title="Eliminar fila">✖</button>
                            </td>
                        </tr>
                    @endforeach

                    {{-- Add Row --}}
                    <tr class="tr-add-sede">
                        <td colspan="{{ 4 + ($tabla['tiene_sede'] ? 1 : 0) + $mesActual + 1 }}">
                            <button class="btn-add-sede" onclick="openAddModal({{ $tIndex }}, {{ $tabla['tiene_sede'] ? 'true' : 'false' }})">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                                Añadir Nuevo Gasto
                            </button>
                        </td>
                    </tr>

                    {{-- Total Row --}}
                    <tr class="total-row">
                        @if($tabla['tiene_sede'])
                            <td class="td-sede-total"></td>
                        @endif
                        <td colspan="3" style="text-align:center;text-transform:uppercase;letter-spacing:0.5px;">
                            TOTAL {{ $tabla['titulo_corto'] }}
                        </td>
                        <td class="col-costo total-col-costo" style="color:#fff!important;">
                            $ {{ number_format(collect($tabla['filas'])->sum('costo'), 2) }}
                        </td>
                        @for($m = 0; $m < $mesActual; $m++)
                            @php $totalMes = collect($tabla['filas'])->sum(fn($f) => $f['meses'][$m] ?? 0); @endphp
                            <td class="total-mes-{{ $m }}" style="text-align:right;">
                                $ {{ number_format($totalMes, 2) }}
                            </td>
                        @endfor
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
</div>

<!-- Modal para Agregar Gasto -->
<div class="gf-modal-bg" id="modalAddGasto">
    <div class="gf-modal">
        <h3>Añadir Nuevo Gasto</h3>
        <input type="hidden" id="modal-tidx" value="">
        
        <div id="modal-sede-container" style="display:none;">
            <label>Sede (Opcional)</label>
            <select id="modal-sede-select" style="margin-bottom: 8px;">
                <option value="">-- Seleccionar Sede --</option>
                <option value="OTRA">Otra (Escribir nueva)</option>
            </select>
            <input type="text" id="modal-sede" placeholder="Ej: INVERSIONES DORAL PARAGUANÁ..." style="display:none; margin-top: 8px;">
        </div>

        <label>Servicio</label>
        <input type="text" id="modal-servicio" placeholder="Ej: INTERNET, CONDOMINIO..." required>

        <label>Empresa / Responsable (Opcional)</label>
        <input type="text" id="modal-empresa" placeholder="Ej: AIRTEK, CORPOELEC...">

        <label>Fecha de Pago (Opcional)</label>
        <select id="add-fecha-tipo" style="margin-bottom: 12px;" onchange="toggleAddFechaInputs()">
            <option value="dia">Un día específico del mes</option>
            <option value="rango">Un rango de días en el mes</option>
            <option value="semana">Un día de la semana</option>
            <option value="libre">Texto Libre</option>
        </select>

        <div id="add-fecha-input-dia">
            <input type="number" id="add-fecha-val-dia" min="1" max="31" placeholder="Día del mes (Ej: 15)">
        </div>

        <div id="add-fecha-input-rango" style="display:none; gap: 8px; flex-direction: column;">
            <input type="number" id="add-fecha-val-rango-1" min="1" max="31" placeholder="Desde el día (Ej: 1)" style="margin-bottom: 8px;">
            <input type="number" id="add-fecha-val-rango-2" min="1" max="31" placeholder="Hasta el día (Ej: 5)">
        </div>

        <div id="add-fecha-input-semana" style="display:none;">
            <select id="add-fecha-val-semana">
                <option value="Lunes">Todos los Lunes</option>
                <option value="Martes">Todos los Martes</option>
                <option value="Miercoles">Todos los Miércoles</option>
                <option value="Jueves">Todos los Jueves</option>
                <option value="Viernes">Todos los Viernes</option>
                <option value="Sabado">Todos los Sábados</option>
                <option value="Domingo">Todos los Domingos</option>
            </select>
        </div>

        <div id="add-fecha-input-libre" style="display:none;">
            <input type="text" id="add-fecha-val-libre" placeholder="Ej: Aviso de SUMITCA">
        </div>

        <label>Costo Estimado ($)</label>
        <input type="number" id="modal-costo" step="0.01" min="0" placeholder="0.00">

        <div class="gf-modal-actions">
            <button class="btn-modal-cancel" onclick="closeAddModal()">Cancelar</button>
            <button class="btn-modal-save" onclick="saveNewRow(this)">Guardar Gasto</button>
        </div>
    </div>
</div>

<!-- Modal para Configurar Fecha -->
<div class="gf-modal-bg" id="modalFechaGasto">
    <div class="gf-modal">
        <h3>Configurar Fecha de Pago</h3>
        <input type="hidden" id="modal-fecha-fidx" value="">
        <input type="hidden" id="modal-fecha-cell" value="">
        
        <label>Tipo de Fecha</label>
        <select id="modal-fecha-tipo" style="margin-bottom: 12px;" onchange="toggleFechaInputs()">
            <option value="dia">Un día específico del mes</option>
            <option value="rango">Un rango de días en el mes</option>
            <option value="semana">Un día de la semana</option>
            <option value="libre">Texto Libre</option>
        </select>

        <div id="fecha-input-dia">
            <label>Día del mes (Ej: 15)</label>
            <input type="number" id="fecha-val-dia" min="1" max="31" placeholder="Ej: 15">
        </div>

        <div id="fecha-input-rango" style="display:none;">
            <label>Desde el día</label>
            <input type="number" id="fecha-val-rango-1" min="1" max="31" placeholder="Ej: 1">
            <label>Hasta el día</label>
            <input type="number" id="fecha-val-rango-2" min="1" max="31" placeholder="Ej: 5">
        </div>

        <div id="fecha-input-semana" style="display:none;">
            <label>Día de la semana</label>
            <select id="fecha-val-semana">
                <option value="Lunes">Todos los Lunes</option>
                <option value="Martes">Todos los Martes</option>
                <option value="Miercoles">Todos los Miércoles</option>
                <option value="Jueves">Todos los Jueves</option>
                <option value="Viernes">Todos los Viernes</option>
                <option value="Sabado">Todos los Sábados</option>
                <option value="Domingo">Todos los Domingos</option>
            </select>
        </div>

        <div id="fecha-input-libre" style="display:none;">
            <label>Texto Libre</label>
            <input type="text" id="fecha-val-libre" placeholder="Ej: Aviso de SUMITCA">
        </div>

        <div class="gf-modal-actions">
            <button class="btn-modal-cancel" onclick="closeFechaModal()">Cancelar</button>
            <button class="btn-modal-save" onclick="saveFechaModal(this)">Guardar Fecha</button>
        </div>
    </div>
</div>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

function showTab(index, btn) {
    document.querySelectorAll('.gf-table-wrapper').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.gf-tab').forEach(el => el.classList.remove('active'));
    
    const target = document.getElementById('tabla-' + index);
    if (target) {
        target.style.display = '';
        target.style.animation = 'none';
        target.offsetHeight; 
        target.style.animation = 'fadeIn 0.3s ease';
    }
    if (btn) btn.classList.add('active');
}

function showSubTab(tIdx, sedeName, btn) {
    const wrapper = document.getElementById('tabla-' + tIdx);
    if (!wrapper) return;

    // If btn is a select element, update the dropdown value
    const sedeSelect = wrapper.querySelector('.gf-sede-select');
    if (sedeSelect && sedeSelect !== btn) {
        sedeSelect.value = sedeName;
    }

    const isAll = (sedeName === 'all');
    
    // Toggle Sede column visibility
    const thSede = wrapper.querySelector('.th-sede');
    const tdSedeTotal = wrapper.querySelector('.td-sede-total');
    if (thSede) thSede.style.display = isAll ? '' : 'none';
    if (tdSedeTotal) tdSedeTotal.style.display = isAll ? '' : 'none';

    // Filter rows and calculate totals
    const tbody = wrapper.querySelector('tbody');
    const rows = tbody.querySelectorAll('tr.gasto-row');
    
    let sumCosto = 0;
    let visibleCount = 0;
    // We get mesActual from blade logic, max 12
    const mesActual = {{ $mesActual }};
    let sumMeses = new Array(mesActual).fill(0);

    rows.forEach(row => {
        const rowSede = row.getAttribute('data-sede');
        if (isAll || rowSede === sedeName) {
            row.style.display = '';
            visibleCount++;
            
            // Hide/show the sede column cell for this row
            const colSede = row.querySelector('.col-sede');
            if (colSede) colSede.style.display = isAll ? '' : 'none';
            
            // Add to totals
            sumCosto += window.parseLocalNumber(row.getAttribute('data-costo')) || 0;
            for (let m = 0; m < mesActual; m++) {
                sumMeses[m] += window.parseLocalNumber(row.getAttribute('data-mes-' + m)) || 0;
            }
        } else {
            row.style.display = 'none';
        }
    });

    // Update visible count
    const countEl = wrapper.querySelector('.gf-sede-count strong');
    if (countEl) countEl.innerText = visibleCount;

    // Update total row
    const totalRow = wrapper.querySelector('.total-row');
    if (totalRow) {
        const formatMoney = (val) => '$ ' + val.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        
        const colCostoTotal = totalRow.querySelector('.total-col-costo');
        if (colCostoTotal) colCostoTotal.innerText = formatMoney(sumCosto);
        
        for (let m = 0; m < mesActual; m++) {
            const totalMes = totalRow.querySelector('.total-mes-' + m);
            if (totalMes) totalMes.innerText = formatMoney(sumMeses[m]);
        }
    }
}

// ── Editable cells: doble clic → input inline → Enter/blur guarda ──
let _activeInlineCell = null;

function startInlineEdit(cell) {
    // Si ya hay otro input abierto, cerrarlo sin guardar
    if (_activeInlineCell && _activeInlineCell !== cell) {
        cancelInlineEdit(_activeInlineCell);
    }

    if (cell.classList.contains('editing')) return; // ya está abierto

    const original = cell.getAttribute('data-original') || '';
    const displayText = cell.innerText.trim();

    cell.classList.add('editing');
    _activeInlineCell = cell;

    // Guardar el texto actual como span oculto
    cell._savedHTML = cell.innerHTML;

    // Crear input
    const input = document.createElement('input');
    input.type = 'number';
    input.step = '0.01';
    input.min = '0';
    input.className = 'gf-inline-input';
    input.value = original !== '' ? original : '';
    input.placeholder = '0.00';

    cell.innerHTML = '';
    cell.appendChild(input);
    input.focus();
    input.select();

    // Enter → guardar
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            commitInlineEdit(cell, input);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            cancelInlineEdit(cell);
        }
    });

    // Blur → guardar
    input.addEventListener('blur', function() {
        // pequeño delay para que el click en otro elemento no dispare doble guardado
        setTimeout(() => {
            if (cell.classList.contains('editing')) {
                commitInlineEdit(cell, input);
            }
        }, 120);
    });
}

function cancelInlineEdit(cell) {
    cell.innerHTML = cell._savedHTML || '';
    cell.classList.remove('editing');
    _activeInlineCell = null;
}

function commitInlineEdit(cell, input) {
    if (!cell.classList.contains('editing')) return;

    const rawVal = input.value.trim();
    const original = cell.getAttribute('data-original') || '';

    // Restaurar texto visual mientras se procesa
    cell.classList.remove('editing');
    _activeInlineCell = null;

    const numVal = rawVal === '' ? null : parseFloat(rawVal);
    const displayVal = numVal !== null ? numVal.toFixed(2) : '';

    // Mostrar valor nuevo optimísticamente
    cell.innerText = displayVal;

    if (rawVal === original || (rawVal === '' && original === '')) {
        // Sin cambio
        return;
    }

    const type = cell.getAttribute('data-type');
    const tIdx = cell.getAttribute('data-tidx');
    const fIdx = cell.getAttribute('data-fidx');

    cell.classList.add('saving');

    if (type === 'monto') {
        const mIdx = cell.getAttribute('data-midx');
        fetch("{{ route('finanzas.gastos_fijos.monto') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ gasto_fijo_id: fIdx, mes_idx: mIdx, monto: numVal })
        }).then(async res => {
            if (!res.ok) throw new Error(await res.text());
            return res.json();
        }).then(data => {
            cell.classList.remove('saving');
            if (data.ok) {
                cell.setAttribute('data-original', displayVal);
                cell.classList.add('success');
                cell.innerText = displayVal;
                if (displayVal !== '') {
                    cell.classList.remove('no-value');
                    cell.classList.add('has-value');
                } else {
                    cell.classList.add('no-value');
                    cell.classList.remove('has-value');
                }
                setTimeout(() => cell.classList.remove('success'), 1100);

                // Actualizar data-attribute y totales
                const tr = cell.closest('tr');
                if (tr) {
                    tr.setAttribute('data-mes-' + mIdx, numVal || 0);
                    const sedeSelect = cell.closest('.gf-table-wrapper').querySelector('.gf-sede-select');
                    if (sedeSelect) {
                        showSubTab(tIdx, sedeSelect.value, null);
                    } else {
                        showSubTab(tIdx, 'all', null);
                    }
                }
            } else {
                cell.innerText = original;
                cell.setAttribute('data-original', original);
                cell.classList.add('error');
                setTimeout(() => cell.classList.remove('error'), 1100);
            }
        }).catch(() => {
            cell.classList.remove('saving');
            cell.innerText = original;
            cell.setAttribute('data-original', original);
            cell.classList.add('error');
            setTimeout(() => cell.classList.remove('error'), 1100);
        });

    } else if (type === 'costo') {
        fetch("{{ route('finanzas.gastos_fijos.costo') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ gasto_fijo_id: fIdx, costo: numVal })
        }).then(async res => {
            if (!res.ok) throw new Error(await res.text());
            return res.json();
        }).then(data => {
            cell.classList.remove('saving');
            if (data.ok) {
                cell.setAttribute('data-original', displayVal);
                cell.innerText = displayVal;
                cell.classList.add('success');
                setTimeout(() => cell.classList.remove('success'), 1100);

                const tr = cell.closest('tr');
                if (tr) {
                    tr.setAttribute('data-costo', numVal || 0);
                    const sedeSelect = cell.closest('.gf-table-wrapper').querySelector('.gf-sede-select');
                    if (sedeSelect) {
                        showSubTab(tIdx, sedeSelect.value, null);
                    } else {
                        showSubTab(tIdx, 'all', null);
                    }
                }
            } else {
                cell.innerText = original;
                cell.setAttribute('data-original', original);
                cell.classList.add('error');
                setTimeout(() => cell.classList.remove('error'), 1100);
            }
        }).catch(() => {
            cell.classList.remove('saving');
            cell.innerText = original;
            cell.setAttribute('data-original', original);
            cell.classList.add('error');
            setTimeout(() => cell.classList.remove('error'), 1100);
        });
    }
}

// ── Edición inline de texto (servicio, empresa) ──
function startInlineTextEdit(cell) {
    if (_activeInlineCell && _activeInlineCell !== cell) {
        cancelInlineEdit(_activeInlineCell);
    }
    if (cell.classList.contains('editing')) return;

    const original = cell.getAttribute('data-original') || cell.innerText.trim();
    cell.classList.add('editing');
    _activeInlineCell = cell;
    cell._savedHTML = cell.innerHTML;

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'gf-inline-input text';
    input.value = original;
    input.placeholder = cell.getAttribute('data-type') === 'servicio' ? 'Servicio...' : 'Empresa...';

    cell.innerHTML = '';
    cell.appendChild(input);
    input.focus();
    input.select();

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            commitInlineTextEdit(cell, input);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            cancelInlineEdit(cell);
        }
    });

    input.addEventListener('blur', function() {
        setTimeout(() => {
            if (cell.classList.contains('editing')) {
                commitInlineTextEdit(cell, input);
            }
        }, 120);
    });
}

function commitInlineTextEdit(cell, input) {
    if (!cell.classList.contains('editing')) return;

    const newVal = input.value.trim();
    const original = cell.getAttribute('data-original') || '';

    cell.classList.remove('editing');
    _activeInlineCell = null;
    cell.innerText = newVal !== '' ? newVal : original;

    if (newVal === original) return;

    const campo = cell.getAttribute('data-type');
    const fIdx  = cell.getAttribute('data-fidx');

    cell.classList.add('saving');

    fetch("{{ route('finanzas.gastos_fijos.campo') }}", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ gasto_fijo_id: fIdx, campo: campo, valor: newVal || null })
    }).then(async res => {
        if (!res.ok) throw new Error(await res.text());
        return res.json();
    }).then(data => {
        cell.classList.remove('saving');
        if (data.ok) {
            cell.setAttribute('data-original', newVal);
            cell.innerText = newVal;
            cell.classList.add('success');
            setTimeout(() => cell.classList.remove('success'), 1100);
        } else {
            cell.innerText = original;
            cell.setAttribute('data-original', original);
            cell.classList.add('error');
            setTimeout(() => cell.classList.remove('error'), 1100);
        }
    }).catch(() => {
        cell.classList.remove('saving');
        cell.innerText = original;
        cell.setAttribute('data-original', original);
        cell.classList.add('error');
        setTimeout(() => cell.classList.remove('error'), 1100);
    });
}

// Marcar Pagado logic
function marcarPagado(tIdx, fIdx, btn) {
    btn.disabled = true;
    btn.style.opacity = '0.5';
    btn.innerText = '⏳';
    
    const costo = btn.getAttribute('data-costo');

    fetch("{{ route('finanzas.gastos_fijos.pagado') }}", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ tabla_idx: tIdx, fila_idx: fIdx, costo: costo })
    }).then(async res => {
        if (!res.ok) throw new Error(await res.text());
        return res.json();
    }).then(data => {
        if (data.ok) {
            // Eliminar la notificación de la UI con animación
            const item = btn.closest('tr');
            item.style.transform = 'scale(0.9)';
            item.style.opacity = '0';
            item.style.transition = 'all 0.3s ease';
            setTimeout(() => {
                item.remove();
                // Si ya no quedan notificaciones, ocultar el panel
                if (document.querySelectorAll('.gf-notif-table tbody tr').length === 0) {
                    const notifPanel = document.querySelector('.gf-notif-panel');
                    if (notifPanel) notifPanel.style.display = 'none';
                }
            }, 300);
            
            // Recargar la página para que se muestre el monto pagado en la celda
            setTimeout(() => {
                window.location.reload();
            }, 350);
        } else {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.innerText = '✓ Pagar';
            alert("Error al marcar como pagado.");
        }
    }).catch(err => {
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.innerText = '✓ Pagar';
        console.error(err);
    });
}

// ── Lógica de Modal (Añadir Gasto) ──

document.getElementById('modal-sede-select').addEventListener('change', function() {
    const input = document.getElementById('modal-sede');
    if (this.value === 'OTRA') {
        input.style.display = 'block';
        input.focus();
    } else {
        input.style.display = 'none';
    }
});

function toggleAddFechaInputs() {
    const tipo = document.getElementById('add-fecha-tipo').value;
    document.getElementById('add-fecha-input-dia').style.display = tipo === 'dia' ? 'block' : 'none';
    document.getElementById('add-fecha-input-rango').style.display = tipo === 'rango' ? 'flex' : 'none';
    document.getElementById('add-fecha-input-semana').style.display = tipo === 'semana' ? 'block' : 'none';
    document.getElementById('add-fecha-input-libre').style.display = tipo === 'libre' ? 'block' : 'none';
}

function openAddModal(tIdx, tieneSede) {
    document.getElementById('modal-tidx').value = tIdx;
    document.getElementById('modal-sede-container').style.display = tieneSede ? 'block' : 'none';
    
    // Clear & setup sede inputs
    const selectSede = document.getElementById('modal-sede-select');
    const inputSede = document.getElementById('modal-sede');
    inputSede.value = '';
    inputSede.style.display = 'none';
    
    // Rebuild options dynamically from table
    selectSede.innerHTML = '<option value="">-- Seleccionar Sede --</option><option value="OTRA">Otra (Escribir nueva)</option>';
    if (tieneSede) {
        const sedes = new Set();
        document.querySelectorAll('#tabla-' + tIdx + ' .col-sede').forEach(td => {
            const val = td.innerText.trim();
            if (val) sedes.add(val);
        });
        sedes.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s;
            opt.innerText = s;
            selectSede.insertBefore(opt, selectSede.lastElementChild);
        });
    }
    selectSede.value = '';
    document.getElementById('modal-servicio').value = '';
    document.getElementById('modal-empresa').value = '';
    
    // Reset date fields
    document.getElementById('add-fecha-tipo').value = 'dia';
    document.getElementById('add-fecha-val-dia').value = '';
    document.getElementById('add-fecha-val-rango-1').value = '';
    document.getElementById('add-fecha-val-rango-2').value = '';
    document.getElementById('add-fecha-val-semana').value = 'Lunes';
    document.getElementById('add-fecha-val-libre').value = '';
    toggleAddFechaInputs();
    
    document.getElementById('modal-costo').value = '';

    document.getElementById('modalAddGasto').classList.add('open');
}

function closeAddModal() {
    document.getElementById('modalAddGasto').classList.remove('open');
}

function saveNewRow(btn) {
    const tIdx = document.getElementById('modal-tidx').value;
    const selectValue = document.getElementById('modal-sede-select').value;
    const sede = selectValue === 'OTRA' ? document.getElementById('modal-sede').value.trim() : selectValue;
    const servicio = document.getElementById('modal-servicio').value.trim();
    const empresa = document.getElementById('modal-empresa').value.trim();
    
    // Parse Date string
    const tipo = document.getElementById('add-fecha-tipo').value;
    let fechaStr = '';
    if (tipo === 'dia') {
        const d = document.getElementById('add-fecha-val-dia').value;
        if (!d) return alert("Ingresa el día.");
        fechaStr = d;
    } else if (tipo === 'rango') {
        const d1 = document.getElementById('add-fecha-val-rango-1').value;
        const d2 = document.getElementById('add-fecha-val-rango-2').value;
        if (!d1 || !d2) return alert("Ingresa ambos días del rango.");
        fechaStr = `${d1} - ${d2} de cada mes`;
    } else if (tipo === 'semana') {
        const s = document.getElementById('add-fecha-val-semana').value;
        fechaStr = `Todos los ${s}`;
    } else {
        fechaStr = document.getElementById('add-fecha-val-libre').value.trim();
    }
    
    const costo = window.parseLocalNumber(document.getElementById('modal-costo').value) || 0;

    if (!servicio) {
        alert("El Servicio es obligatorio.");
        return;
    }

    btn.disabled = true;
    btn.innerText = 'Guardando...';

    fetch("{{ route('finanzas.gastos_fijos.agregar') }}", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({
            tabla_idx: tIdx,
            sede: sede,
            servicio: servicio,
            empresa: empresa,
            fecha: fechaStr,
            costo: costo
        })
    }).then(async res => {
        if (!res.ok) throw new Error(await res.text());
        return res.json();
    }).then(data => {
        if (data.ok) {
            closeAddModal();
            window.location.reload(); // Recargar para ver la nueva fila correctamente renderizada
        } else {
            alert("Error al agregar el gasto.");
            btn.disabled = false;
            btn.innerText = 'Guardar Gasto';
        }
    }).catch(err => {
        console.error(err);
        alert("Error de conexión.");
        btn.disabled = false;
        btn.innerText = 'Guardar Gasto';
    });
}

// ── Lógica de Eliminar Fila ──
function deleteRow(tIdx, fIdx, customId, btn) {
    if (!confirm("¿Seguro que deseas eliminar este gasto de la lista?")) return;

    btn.disabled = true;
    btn.style.opacity = '0.5';

    fetch("{{ route('finanzas.gastos_fijos.eliminar') }}", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({
            tabla_idx: tIdx,
            fila_idx: fIdx,
            custom_id: customId
        })
    }).then(async res => {
        if (!res.ok) throw new Error(await res.text());
        return res.json();
    }).then(data => {
        if (data.ok) {
            // Ocultar la fila visualmente o recargar
            btn.closest('tr').remove();
            // Lo más seguro es recargar para que los totales cuadren
            // window.location.reload(); 
        } else {
            alert("Error al eliminar el gasto.");
            btn.disabled = false;
            btn.style.opacity = '1';
        }
    }).catch(err => {
        console.error(err);
        alert("Error de conexión.");
        btn.disabled = false;
        btn.style.opacity = '1';
    });
}

// ── Lógica Modal Fecha ──
let currentFechaCell = null;

function toggleFechaInputs() {
    const tipo = document.getElementById('modal-fecha-tipo').value;
    document.getElementById('fecha-input-dia').style.display = tipo === 'dia' ? 'block' : 'none';
    document.getElementById('fecha-input-rango').style.display = tipo === 'rango' ? 'block' : 'none';
    document.getElementById('fecha-input-semana').style.display = tipo === 'semana' ? 'block' : 'none';
    document.getElementById('fecha-input-libre').style.display = tipo === 'libre' ? 'block' : 'none';
}

window.openFechaModal = function(fIdx, cell) {
    document.getElementById('modal-fecha-fidx').value = fIdx;
    currentFechaCell = cell;
    
    // Parse current value to set defaults if possible
    const currentVal = cell.innerText.trim().toLowerCase();
    
    if (currentVal.match(/^\d+$/)) {
        document.getElementById('modal-fecha-tipo').value = 'dia';
        document.getElementById('fecha-val-dia').value = currentVal;
    } else if (currentVal.includes('-') && currentVal.includes('cada mes')) {
        document.getElementById('modal-fecha-tipo').value = 'rango';
        const parts = currentVal.match(/(\d+)\s*-\s*(\d+)/);
        if (parts) {
            document.getElementById('fecha-val-rango-1').value = parts[1];
            document.getElementById('fecha-val-rango-2').value = parts[2];
        }
    } else if (currentVal.includes('lunes') || currentVal.includes('martes') || currentVal.includes('miercoles') || currentVal.includes('jueves') || currentVal.includes('viernes') || currentVal.includes('sabado') || currentVal.includes('domingo')) {
        document.getElementById('modal-fecha-tipo').value = 'semana';
        const dias = ['Lunes','Martes','Miercoles','Jueves','Viernes','Sabado','Domingo'];
        const found = dias.find(d => currentVal.includes(d.toLowerCase()));
        if (found) document.getElementById('fecha-val-semana').value = found;
    } else {
        document.getElementById('modal-fecha-tipo').value = 'libre';
        document.getElementById('fecha-val-libre').value = cell.innerText.trim();
    }
    
    toggleFechaInputs();
    document.getElementById('modalFechaGasto').classList.add('open');
};

function closeFechaModal() {
    document.getElementById('modalFechaGasto').classList.remove('open');
    currentFechaCell = null;
}

window.saveFechaModal = function(btn) {
    const fIdx = document.getElementById('modal-fecha-fidx').value;
    const tipo = document.getElementById('modal-fecha-tipo').value;
    let fechaStr = '';

    if (tipo === 'dia') {
        const d = document.getElementById('fecha-val-dia').value;
        if (!d) return alert("Ingresa el día.");
        fechaStr = d; // "8"
    } else if (tipo === 'rango') {
        const d1 = document.getElementById('fecha-val-rango-1').value;
        const d2 = document.getElementById('fecha-val-rango-2').value;
        if (!d1 || !d2) return alert("Ingresa ambos días del rango.");
        fechaStr = `${d1} - ${d2} de cada mes`;
    } else if (tipo === 'semana') {
        const s = document.getElementById('fecha-val-semana').value;
        fechaStr = `Todos los ${s}`;
    } else {
        fechaStr = document.getElementById('fecha-val-libre').value.trim();
    }

    if (!fechaStr) {
        alert("La fecha no puede estar vacía.");
        return;
    }

    btn.disabled = true;
    btn.innerText = 'Guardando...';

    fetch("{{ route('finanzas.gastos_fijos.fecha') }}", {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ gasto_fijo_id: fIdx, fecha: fechaStr })
    }).then(async res => {
        if (!res.ok) throw new Error(await res.text());
        return res.json();
    }).then(data => {
        if (data.ok) {
            closeFechaModal();
            if (currentFechaCell) {
                currentFechaCell.innerText = fechaStr;
                currentFechaCell.classList.add('success');
                setTimeout(() => currentFechaCell.classList.remove('success'), 1000);
            }
        } else {
            alert("Error al guardar.");
        }
    }).catch(err => {
        console.error(err);
        alert("Error de conexión.");
    }).finally(() => {
        btn.disabled = false;
        btn.innerText = 'Guardar Fecha';
    });
};

document.addEventListener('DOMContentLoaded', () => {
    // Automatically trigger change event on sede selects so the first sede is shown by default
    document.querySelectorAll('.gf-sede-select').forEach(select => {
        if (select.options.length > 0) {
            select.dispatchEvent(new Event('change'));
        }
    });
});
</script>

@endsection

