{{-- Responsive compartido · Ventas al mayor (móvil / tablet) --}}
<style>
/* —— Base móvil del módulo —— */
.vm-page-head {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    align-items: flex-start;
}
.vm-page-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}
.vm-filtros {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: flex-end;
}
.vm-filtros .vm-filtro {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: .72rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
}
.vm-filtros input,
.vm-filtros select {
    padding: 9px 11px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 1rem;
    min-height: 42px;
    background: #fff;
}

/* Tablas → tarjetas en móvil */
@media (max-width: 760px) {
    .vm-m-cards thead { display: none; }
    .vm-m-cards,
    .vm-m-cards tbody,
    .vm-m-cards tr,
    .vm-m-cards td {
        display: block;
        width: 100%;
        box-sizing: border-box;
    }
    .vm-m-cards tr {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px 14px;
        margin-bottom: 10px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }
    .vm-m-cards td {
        padding: 6px 0;
        border: 0 !important;
        text-align: left !important;
        white-space: normal !important;
    }
    .vm-m-cards td::before {
        content: attr(data-label);
        display: block;
        font-size: .68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #94a3b8;
        margin-bottom: 2px;
    }
    .vm-m-cards td[data-label=""]::before,
    .vm-m-cards td:not([data-label])::before { display: none; }
    .vm-m-cards .vm-m-acciones {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        padding-top: 8px;
        margin-top: 4px;
        border-top: 1px dashed #e2e8f0;
    }
    .vm-m-cards .vm-m-acciones .btn { flex: 1; min-width: 110px; text-align: center; justify-content: center; }
}

@media (max-width: 640px) {
    main:has(.cat-page),
    main:has(.cm-page),
    main:has(.vm-mobile-root) {
        padding-left: 12px !important;
        padding-right: 12px !important;
        padding-top: 12px !important;
    }

    .vm-page-head h1 { font-size: 1.25rem; line-height: 1.25; }
    .vm-page-head .lead,
    .vm-page-head .muted { font-size: .82rem; }
    .vm-page-actions {
        width: 100%;
    }
    .vm-page-actions .btn {
        flex: 1 1 calc(50% - 8px);
        text-align: center;
        justify-content: center;
        min-height: 42px;
        font-size: .85rem;
        padding: 10px 12px;
    }

    .vm-filtros { width: 100%; }
    .vm-filtros .vm-filtro,
    .vm-filtros input,
    .vm-filtros select,
    .vm-filtros .btn { width: 100%; min-width: 0; }

    /* Stock · catálogo tabla */
    main:has(.cat-page) { padding: 12px !important; }
    .cat-page { padding: 0 !important; }
    .cat-search { gap: 8px; margin-bottom: 12px; }
    .cat-search-field { flex: 1 1 100%; }
    .cat-dias-label { flex: 1 1 100%; }
    .cat-dias-label select { width: 100%; min-width: 0; }
    .cat-table tr { padding: 12px !important; }
    .cat-product-fila { gap: 10px; align-items: flex-start; }
    .cat-foto { width: 56px; height: 56px; }
    .cat-stock-sedes { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
    .cat-prices { grid-template-columns: 1fr 1fr !important; }
    .vm-acciones { grid-template-columns: 1fr !important; }
    .vm-acciones .vm-editar,
    .vm-acciones .vm-facturar { width: 100%; text-align: center; }

    /* Modal ficha */
    .vm-modal { padding: 8px; align-items: stretch; }
    .vm-modal-box { margin: 8px auto; padding: 16px 14px 18px; max-width: none; border-radius: 14px; }
    .vm-ficha-top { flex-direction: column; align-items: center; text-align: center; padding-right: 0; }
    .vm-ficha-top img { width: 120px; height: 120px; }
    .vm-ficha-datos,
    .vm-ficha-stock,
    .vm-campos { grid-template-columns: 1fr !important; }
    .vm-campos input,
    .vm-campos select { font-size: 16px; min-height: 44px; }

    /* Nueva factura */
    .vm-grid { grid-template-columns: 1fr !important; gap: 12px; }
    .vm-lado { position: static !important; }
    .vm-card { padding: 12px; }
    .vm-row > div { min-width: 100% !important; flex: 1 1 100% !important; }
    .vm-card input,
    .vm-card select,
    .vm-card textarea { font-size: 16px; min-height: 42px; }
    .vm-pills { flex-direction: column; }
    .vm-items-wrap { overflow: visible; }
    .vm-items thead { display: none; }
    .vm-items,
    .vm-items tbody,
    .vm-items tr,
    .vm-items td { display: block; width: 100%; }
    .vm-items tr {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 10px;
        margin-bottom: 8px;
        background: #fff;
    }
    .vm-items td { padding: 4px 0; text-align: left !important; }
    .vm-items td::before {
        content: attr(data-label);
        display: inline-block;
        min-width: 88px;
        font-size: .68rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #94a3b8;
        margin-right: 8px;
    }
    .vm-items td[data-label=""]::before { display: none; }
    .vm-items input { text-align: left !important; width: 100%; font-size: 16px; }
    .vm-resultados { max-height: 50vh; }

    /* Catálogo gráfico */
    .cm-filtros { flex-direction: column; align-items: stretch; }
    .cm-filtros label { width: 100%; }
    .cm-filtros input,
    .cm-filtros select { min-width: 0; width: 100%; font-size: 16px; }
    .cm-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 10px; }
    .cm-foto { height: 140px; }
    .cm-foto img { max-height: 140px; }
    .cm-nombre { font-size: .82rem; min-height: 2.4em; }
    .cm-precio strong { font-size: 1.1rem; }

    /* Factura show */
    .vf-cab { gap: 10px; }
    .vf-marca { width: 100%; }
    .vf-marca img { width: 48px; height: 48px; }
    .vf-pago-form {
        flex-direction: column !important;
        align-items: stretch !important;
    }
    .vf-pago-form > div { width: 100% !important; min-width: 0 !important; }
    .vf-pago-form input,
    .vf-pago-form select { width: 100% !important; font-size: 16px; min-height: 42px; box-sizing: border-box; }
    .vf-pago-cards > div { width: 100% !important; max-width: none; }

    /* Visitas */
    .hr-row > div { min-width: 100% !important; flex: 1 1 100% !important; }
    .hr-card input,
    .hr-card select,
    .hr-card textarea { font-size: 16px; min-height: 42px; }
    .hr-visita-grid { grid-template-columns: 1fr !important; }
    #hr-firma { height: 220px; }
    #hr-previas img { width: 72px; height: 72px; }
}

@media (max-width: 420px) {
    .cm-grid { grid-template-columns: 1fr !important; }
    .cat-stock-sedes { grid-template-columns: 1fr !important; }
    .vm-page-actions .btn { flex: 1 1 100%; }
}
</style>
