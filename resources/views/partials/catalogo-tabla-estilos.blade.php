main:has(.cat-page) {
    max-width: none;
    width: 100%;
    padding: 20px 28px 40px;
}
.cat-page {
    width: 100%;
}
.cat-search {
    display: flex;
    gap: 12px;
    align-items: center;
    margin: 0 0 18px;
    flex-wrap: wrap;
}
.cat-dias-label {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 0.78rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    white-space: nowrap;
}
.cat-dias-label select {
    min-width: 140px;
    padding: 10px 12px;
    border-radius: 10px;
    border: 1.5px solid var(--border);
    background: #fff;
    color: var(--text);
    font-size: 0.92rem;
    font-weight: 500;
    text-transform: none;
    letter-spacing: normal;
}
.cat-dias-label select:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
.cat-search-field {
    position: relative;
    flex: 1;
}
.cat-search-field span {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    color: #64748b;
}
.cat-search-field input {
    width: 100%;
    padding: 12px 14px 12px 42px;
    border-radius: 10px;
    border: 1.5px solid var(--border);
    font-size: 1rem;
    background: #fff;
    color: var(--text);
}
.cat-search-field input:focus {
    outline: none;
    border-color: var(--blue);
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
}
.cat-search a {
    font-size: 0.88rem;
    color: var(--muted);
    text-decoration: none;
    white-space: nowrap;
}

.cat-panel {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: var(--shadow);
}
.cat-table-wrap { overflow: visible; border: none; }
.cat-table { width: 100%; }
.cat-table th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f8fafc;
    font-size: 0.82rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 14px 20px;
}
.cat-table td {
    vertical-align: middle;
    padding: 16px 20px;
}

.cat-product { display: flex; flex-direction: column; align-items: flex-start; gap: 6px; }
.cat-product-fila { display: flex; gap: 14px; align-items: center; }
.cat-foto-link { flex-shrink: 0; display: block; }
.cat-foto { width: 72px; height: 72px; object-fit: contain; border-radius: 10px; border: 1px solid #e2e8f0; background: #fff; display: block; }
.cat-foto-link[href] .cat-foto:hover { border-color: #93c5fd; box-shadow: 0 2px 8px rgba(37, 99, 235, 0.15); }
.cat-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 0.8rem;
    color: #2563eb;
    background: #eff6ff;
    padding: 2px 8px;
    border-radius: 6px;
}
.cat-name { font-weight: 650; color: #0f172a; line-height: 1.35; font-size: 0.98rem; }
.cat-tag {
    display: inline-block;
    font-size: 0.72rem;
    font-weight: 600;
    color: #64748b;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    padding: 2px 9px;
}

.cat-stock { display: flex; align-items: center; gap: 16px; }
.cat-stock-global {
    min-width: 78px;
    text-align: center;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 12px;
    padding: 10px 12px;
}
.cat-stock-global strong {
    display: block;
    font-size: 1.35rem;
    color: #1d4ed8;
    line-height: 1.1;
}
.cat-stock-global span {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #64748b;
}
.cat-stock-ventas-total {
    min-width: 78px;
    text-align: center;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    padding: 10px 12px;
}
.cat-stock-ventas-total strong {
    display: block;
    font-size: 1.35rem;
    color: #166534;
    line-height: 1.1;
}
.cat-stock-ventas-total span {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #64748b;
}
.cat-stock-sedes {
    display: grid;
    grid-template-columns: repeat(3, minmax(110px, 1fr));
    gap: 8px;
    flex: 1;
}
.cat-sede {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 4px;
    padding: 8px 10px;
    border-radius: 10px;
    font-size: 0.78rem;
    font-weight: 600;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #64748b;
}
.cat-sede-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    width: 100%;
}
.cat-sede b { font-size: 0.95rem; }
.cat-sede.has-stock {
    background: #f0fdf4;
    border-color: #bbf7d0;
    color: #166534;
}
.cat-sede.no-stock b { color: #94a3b8; font-weight: 500; }
.cat-sede-jrz {
    border-color: #93c5fd;
    background: #eff6ff;
    color: #1e3a8a;
}
.cat-sede-con-ventas {
    min-height: 52px;
}
.cat-sede-ventas {
    display: block;
    width: 100%;
    margin-top: 1px;
    padding-top: 4px;
    border-top: 1px dashed #cbd5e1;
    font-size: 0.72rem;
    font-weight: 700;
    color: #334155;
    line-height: 1.25;
}
.cat-sede-jrz .cat-sede-ventas {
    border-top-color: #bfdbfe;
    color: #1d4ed8;
}
.cat-sede.has-stock .cat-sede-ventas {
    color: #166534;
}
.cat-sede-jrz.has-stock .cat-sede-ventas {
    color: #1d4ed8;
}
.cat-sede-ventas em {
    font-style: normal;
    font-weight: 500;
    color: #64748b;
    margin-left: 2px;
}

.cat-prices {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 10px 12px;
    align-items: start;
}
.cat-prices div { display: flex; flex-direction: column; gap: 2px; }
.cat-prices span {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: #94a3b8;
}
.cat-prices strong { font-size: 1.05rem; }
.cat-prices em {
    font-style: normal;
    font-size: 0.72rem;
    color: #64748b;
}
.price-unit { color: #16a34a; }
.price-desc { color: #0284c7; }
.price-mayor { color: #7c3aed; }
.price-empty { color: #94a3b8; }
.cat-cashea-btn {
    grid-column: 1 / -1;
    margin-top: 4px;
    border: 1px solid #ddd6fe;
    background: #f5f3ff;
    color: #6d28d9;
    border-radius: 8px;
    padding: 8px 10px;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
}
.cat-cashea-btn:hover { background: #ede9fe; }

@media (max-width: 1100px) {
    .cat-stock-sedes { grid-template-columns: repeat(2, minmax(100px, 1fr)); }
}
@media (max-width: 900px) {
    .cat-page { padding: 0 16px 32px; }
    .cat-table thead { display: none; }
    .cat-table, .cat-table tbody, .cat-table tr, .cat-table td { display: block; width: 100%; }
    .cat-table tr { padding: 16px; border-bottom: 1px solid var(--border); }
    .cat-table td { padding: 8px 0; }
    .cat-stock { flex-direction: column; align-items: stretch; }
    .cat-prices { margin-top: 4px; }
}
