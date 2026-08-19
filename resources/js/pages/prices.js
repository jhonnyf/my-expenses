import Utils from '../utils';

const dateFull = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });

const Prices = (() => {
    let initialized = false;
    let searchUrl, historyUrl, byCityUrl, byIssuerUrl;
    let searchInput, searchResults, resultsList, productPicker, selectedProductBar, selectedProductName, btnChangeProduct;
    let emptyState, productSections;
    let historyDataWrapper, historyEmptyState;
    let resultsTitle, tableHead, tableBody, btnBackToCities;
    let debounceTimer = null;
    let historyChartInstance = null;
    let comparisonChartInstance = null;

    let comparisonMode = 'city';
    let currentProduct = null;
    let lastCityRows = [];

    const getThemeColors = () => {
        const style = getComputedStyle(document.documentElement);
        return {
            primary: style.getPropertyValue('--color-primary').trim(),
            secondaryForeground: style.getPropertyValue('--color-secondary-foreground').trim(),
            border: style.getPropertyValue('--color-border').trim(),
            background: style.getPropertyValue('--color-background').trim(),
        };
    };

    // ---------- Meu Histórico ----------

    const renderHistorySummary = (summary) => {
        const variationColor = summary.variation_pct > 20
            ? 'text-red-500'
            : summary.variation_pct < 5 ? 'text-green-500' : 'text-yellow-500';

        document.getElementById('summaryCards').innerHTML = `
            <div class="kt-card flex-row items-center gap-4 p-5">
                <div class="flex items-center justify-center size-10 rounded-xl bg-success/10 shrink-0">
                    <i class="ki-filled ki-arrow-down text-success text-xl"></i>
                </div>
                <div class="flex flex-col gap-0.5 min-w-0">
                    <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums truncate">R$ ${Utils.formatCurrency(summary.min_price)}</span>
                    <span class="text-xs font-normal text-secondary-foreground">Menor Preço</span>
                </div>
            </div>
            <div class="kt-card flex-row items-center gap-4 p-5">
                <div class="flex items-center justify-center size-10 rounded-xl bg-destructive/10 shrink-0">
                    <i class="ki-filled ki-arrow-up text-destructive text-xl"></i>
                </div>
                <div class="flex flex-col gap-0.5 min-w-0">
                    <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums truncate">R$ ${Utils.formatCurrency(summary.max_price)}</span>
                    <span class="text-xs font-normal text-secondary-foreground">Maior Preço</span>
                </div>
            </div>
            <div class="kt-card flex-row items-center gap-4 p-5">
                <div class="flex items-center justify-center size-10 rounded-xl bg-primary/10 shrink-0">
                    <i class="ki-filled ki-dollar text-primary text-xl"></i>
                </div>
                <div class="flex flex-col gap-0.5 min-w-0">
                    <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums truncate">R$ ${Utils.formatCurrency(summary.avg_price)}</span>
                    <span class="text-xs font-normal text-secondary-foreground">Preço Médio</span>
                </div>
            </div>
            <div class="kt-card flex-row items-center gap-4 p-5">
                <div class="flex items-center justify-center size-10 rounded-xl bg-warning/10 shrink-0">
                    <i class="ki-filled ki-chart text-warning text-xl"></i>
                </div>
                <div class="flex flex-col gap-0.5 min-w-0">
                    <span class="text-lg lg:text-xl font-semibold ${variationColor} tabular-nums">${summary.variation_pct.toFixed(1)}%</span>
                    <span class="text-xs font-normal text-secondary-foreground">Variação</span>
                </div>
            </div>`;
    };

    const renderHistoryChart = (timeline, summary) => {
        const el = document.getElementById('priceChart');

        if (historyChartInstance) {
            historyChartInstance.destroy();
            historyChartInstance = null;
        }

        if (timeline.length === 0) {
            el.innerHTML = '<p class="text-sm text-secondary-foreground py-4 w-full text-center">Sem dados.</p>';
            return;
        }

        el.innerHTML = '';
        const colors = getThemeColors();
        const { min_price: minPrice, max_price: maxPrice } = summary;

        const data = timeline.map(entry => ({
            x: new Date(entry.issued_at).getTime(),
            y: parseFloat(entry.unit_price),
            issuer: Utils.escapeHtml(entry.issuer_name),
        }));

        const minIndex = data.reduce((best, p, i) => (p.y < data[best].y ? i : best), 0);
        const maxIndex = data.reduce((best, p, i) => (p.y > data[best].y ? i : best), 0);

        historyChartInstance = new ApexCharts(el, {
            series: [{ name: 'Preço unitário', data }],
            chart: {
                type: 'area',
                height: '100%',
                fontFamily: 'Inter, sans-serif',
                toolbar: { show: false },
                zoom: { enabled: false },
            },
            colors: [colors.primary],
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2.5 },
            fill: {
                type: 'gradient',
                gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05, stops: [0, 90, 100] },
            },
            markers: {
                size: 0,
                strokeWidth: 2,
                strokeColors: colors.background,
                hover: { size: 6 },
                discrete: [
                    { seriesIndex: 0, dataPointIndex: minIndex, fillColor: '#22c55e', strokeColor: colors.background, size: 5 },
                    ...(maxIndex !== minIndex ? [{ seriesIndex: 0, dataPointIndex: maxIndex, fillColor: '#ef4444', strokeColor: colors.background, size: 5 }] : []),
                ],
            },
            xaxis: {
                type: 'datetime',
                labels: { style: { colors: colors.secondaryForeground, fontSize: '11px' }, datetimeUTC: false },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: {
                labels: {
                    style: { colors: colors.secondaryForeground, fontSize: '11px' },
                    formatter: v => `R$ ${Utils.formatCurrency(v)}`,
                },
            },
            grid: { borderColor: colors.border, strokeDashArray: 4, padding: { left: 8, right: 8 } },
            tooltip: {
                x: { format: 'dd/MM/yyyy' },
                y: {
                    formatter: (value, opts) => {
                        const point = opts.w.config.series[opts.seriesIndex].data[opts.dataPointIndex];
                        const tag = point.y === minPrice ? ' (menor preço)' : (point.y === maxPrice ? ' (maior preço)' : '');
                        return `R$ ${Utils.formatCurrency(value)} — ${point.issuer}${tag}`;
                    },
                },
                theme: false,
                style: { fontSize: '12px' },
            },
        });
        historyChartInstance.render();
    };

    const renderHistoryTable = (timeline, summary) => {
        const tbody = document.getElementById('priceTableBody');
        const cardsBody = document.getElementById('priceCardsBody');
        const count = timeline.length;
        document.getElementById('entryCount').textContent = `${count} ${count === 1 ? 'registro' : 'registros'}`;

        if (count === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-secondary-foreground py-6">Nenhum registro.</td></tr>';
            cardsBody.innerHTML = '<p class="text-center text-secondary-foreground py-6 text-sm">Nenhum registro.</p>';
            return;
        }

        const { max_price: maxPrice, min_price: minPrice } = summary;

        const rows = timeline.map(entry => {
            const price = parseFloat(entry.unit_price);
            const isMin = price === minPrice;
            const isMax = price === maxPrice;
            const rowClass = isMin ? 'bg-green-50 dark:bg-green-500/5' : (isMax ? 'bg-red-50 dark:bg-red-500/5' : '');
            const priceClass = isMin ? 'text-green-600' : (isMax ? 'text-red-600' : '');
            const badge = isMin
                ? '<span class="kt-badge kt-badge-success kt-badge-outline kt-badge-sm ms-2">menor</span>'
                : (isMax ? '<span class="kt-badge kt-badge-destructive kt-badge-outline kt-badge-sm ms-2">maior</span>' : '');
            const date = dateFull.format(new Date(entry.issued_at));
            const qty = parseFloat(entry.quantity);
            const qtyFormatted = qty % 1 === 0 ? qty.toFixed(0) : qty.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');

            return {
                rowClass, priceClass, badge, date, qtyFormatted,
                price, issuerName: Utils.escapeHtml(entry.issuer_name), unit: Utils.escapeHtml(entry.unit || '—'),
            };
        });

        tbody.innerHTML = rows.map(r => `
                <tr class="${r.rowClass} transition-colors hover:bg-accent/60">
                    <td class="text-sm text-secondary-foreground">${r.date}</td>
                    <td class="text-sm font-medium text-foreground">${r.issuerName}</td>
                    <td class="text-right font-semibold font-mono text-sm ${r.priceClass}">R$ ${Utils.formatCurrency(r.price)}${r.badge}</td>
                    <td class="text-right font-mono text-sm">${r.qtyFormatted.replace('.', ',')}</td>
                    <td class="text-center text-secondary-foreground text-sm">${r.unit}</td>
                </tr>`).join('');

        cardsBody.innerHTML = rows.map(r => `
                <div class="rounded-xl border border-border p-4 flex flex-col gap-2 ${r.rowClass}">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-medium text-foreground">${r.issuerName}</span>
                        <span class="text-sm font-semibold font-mono ${r.priceClass}">R$ ${Utils.formatCurrency(r.price)}${r.badge}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2 pt-2 border-t border-border/60 text-xs text-secondary-foreground">
                        <span>${r.date}</span>
                        <span>Qtd: ${r.qtyFormatted.replace('.', ',')} ${r.unit}</span>
                    </div>
                </div>`).join('');
    };

    const renderHistoryEmptyState = () => {
        historyDataWrapper.classList.add('hidden');
        historyEmptyState.classList.remove('hidden');
    };

    const loadHistory = (product) => {
        document.getElementById('productTitle').textContent = product;

        Utils.http(`${historyUrl}?description=${encodeURIComponent(product)}`)
            .then(data => {
                const editBtn = document.getElementById('productAliasEditBtn');
                if (editBtn) {
                    editBtn.dataset.description = product;
                    editBtn.dataset.canonicalName = product;
                    editBtn.dataset.descriptions = JSON.stringify(data.descriptions?.length ? data.descriptions : [product]);
                }

                if (data.timeline.length === 0) {
                    renderHistoryEmptyState();
                    return;
                }

                historyEmptyState.classList.add('hidden');
                historyDataWrapper.classList.remove('hidden');
                renderHistorySummary(data.summary);
                renderHistoryChart(data.timeline, data.summary);
                renderHistoryTable(data.timeline, data.summary);
            });
    };

    const handleAliasUpdated = (e) => {
        const titleEl = document.getElementById('productTitle');
        if (titleEl && !productSections.classList.contains('hidden')) {
            titleEl.textContent = e.detail.display_name;
        }
    };

    // ---------- Comparativo entre Cidades/Mercados ----------

    const renderComparisonChart = (rows, labels) => {
        if (comparisonChartInstance) {
            comparisonChartInstance.destroy();
            comparisonChartInstance = null;
        }

        const el = document.getElementById('priceComparisonChart');
        if (rows.length === 0) {
            el.innerHTML = '<p class="text-sm text-secondary-foreground py-4 w-full text-center">Sem dados suficientes.</p>';
            return;
        }

        el.innerHTML = '';
        const colors = getThemeColors();

        comparisonChartInstance = new ApexCharts(el, {
            series: [{ name: 'Menor preço', data: rows.map(r => parseFloat(r.min_price)) }],
            chart: {
                type: 'bar',
                height: '100%',
                fontFamily: 'Inter, sans-serif',
                toolbar: { show: false },
            },
            plotOptions: { bar: { horizontal: true, borderRadius: 4, distributed: false } },
            colors: [colors.primary],
            dataLabels: {
                enabled: true,
                formatter: v => `R$ ${Utils.formatCurrency(v)}`,
                style: { colors: [colors.secondaryForeground] },
                offsetX: 30,
            },
            xaxis: {
                categories: labels,
                labels: {
                    style: { colors: colors.secondaryForeground, fontSize: '11px' },
                    formatter: v => `R$ ${Utils.formatCurrency(v)}`,
                },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: { labels: { style: { colors: colors.secondaryForeground, fontSize: '11px' } } },
            grid: { borderColor: colors.border, strokeDashArray: 4 },
            tooltip: {
                y: { formatter: v => `R$ ${Utils.formatCurrency(v)}` },
                theme: false,
            },
        });
        comparisonChartInstance.render();
    };

    const renderCityTable = (rows) => {
        tableHead.innerHTML = `
            <th class="text-start text-xs font-semibold text-secondary-foreground uppercase tracking-wide">Cidade/Estado</th>
            <th class="text-end text-xs font-semibold text-secondary-foreground uppercase tracking-wide">Menor Preço</th>
            <th class="text-end text-xs font-semibold text-secondary-foreground uppercase tracking-wide">Preço Médio</th>
            <th class="text-end text-xs font-semibold text-secondary-foreground uppercase tracking-wide">Amostras</th>
            <th></th>`;

        tableBody.innerHTML = rows.map((row, index) => `
            <tr class="transition-colors duration-150 hover:bg-accent/60">
                <td class="text-sm text-foreground font-medium">${Utils.escapeHtml(row.city)}/${Utils.escapeHtml(row.state)}</td>
                <td class="text-end text-sm font-semibold text-primary tabular-nums">R$ ${Utils.formatCurrency(row.min_price)}</td>
                <td class="text-end text-sm text-secondary-foreground tabular-nums">R$ ${Utils.formatCurrency(row.avg_price)}</td>
                <td class="text-end text-sm text-secondary-foreground tabular-nums">${row.sample_count}</td>
                <td class="text-end">
                    <button type="button" class="kt-btn kt-btn-sm kt-btn-outline" data-drill-city="${index}">
                        Ver mercados <i class="ki-filled ki-black-right-line"></i>
                    </button>
                </td>
            </tr>`).join('');
    };

    const renderIssuerTable = (rows) => {
        tableHead.innerHTML = `
            <th class="text-start text-xs font-semibold text-secondary-foreground uppercase tracking-wide">Mercado</th>
            <th class="text-end text-xs font-semibold text-secondary-foreground uppercase tracking-wide">Menor Preço</th>
            <th class="text-end text-xs font-semibold text-secondary-foreground uppercase tracking-wide">Preço Médio</th>
            <th class="text-end text-xs font-semibold text-secondary-foreground uppercase tracking-wide">Amostras</th>`;

        tableBody.innerHTML = rows.map(row => `
            <tr class="transition-colors duration-150 hover:bg-accent/60">
                <td class="text-sm text-foreground font-medium">${Utils.escapeHtml(row.issuer_name)}</td>
                <td class="text-end text-sm font-semibold text-primary tabular-nums">R$ ${Utils.formatCurrency(row.min_price)}</td>
                <td class="text-end text-sm text-secondary-foreground tabular-nums">R$ ${Utils.formatCurrency(row.avg_price)}</td>
                <td class="text-end text-sm text-secondary-foreground tabular-nums">${row.sample_count}</td>
            </tr>`).join('');
    };

    const showCityMode = (rows) => {
        comparisonMode = 'city';
        lastCityRows = rows;

        resultsTitle.textContent = 'Comparativo por Cidade';
        btnBackToCities.classList.add('hidden');

        renderComparisonChart(rows, rows.map(r => `${r.city}/${r.state}`));
        renderCityTable(rows);
    };

    const showIssuerMode = (rows, city, state) => {
        comparisonMode = 'issuer';

        resultsTitle.textContent = `Mercados em ${city}/${state}`;
        btnBackToCities.classList.remove('hidden');

        renderComparisonChart(rows, rows.map(r => r.issuer_name));
        renderIssuerTable(rows);
    };

    const fetchByCity = (product) => {
        Utils.http(`${byCityUrl}?product=${encodeURIComponent(product)}`).then(rows => showCityMode(rows));
    };

    const fetchByIssuer = (product, city, state) => {
        const params = new URLSearchParams({ product, city, state });
        Utils.http(`${byIssuerUrl}?${params.toString()}`).then(rows => showIssuerMode(rows, city, state));
    };

    const handleTableClick = (e) => {
        const drillBtn = e.target.closest('[data-drill-city]');
        if (!drillBtn || comparisonMode !== 'city' || !currentProduct) return;

        const row = lastCityRows[parseInt(drillBtn.dataset.drillCity)];
        if (row) fetchByIssuer(currentProduct, row.city, row.state);
    };

    const handleBackToCities = () => {
        if (currentProduct) fetchByCity(currentProduct);
    };

    // ---------- Busca / seleção de produto ----------

    const fetchProductOptions = (query) => {
        Utils.http(`${searchUrl}?q=${encodeURIComponent(query)}`).then(data => {
            if (data.length === 0) {
                resultsList.innerHTML = '<div class="px-4 py-3 text-sm text-secondary-foreground">Nenhum produto encontrado.</div>';
            } else {
                resultsList.innerHTML = data.map(item => `
                    <div class="flex items-center gap-3 px-4 py-3 hover:bg-accent/30 cursor-pointer transition-colors"
                         data-product="${encodeURIComponent(item.name)}">
                        <div class="flex items-center justify-center size-9 rounded-lg bg-primary/10 text-primary shrink-0">
                            <i class="ki-filled ki-basket text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-foreground truncate">${Utils.escapeHtml(item.name)}</p>
                            <p class="text-xs text-secondary-foreground mt-0.5">
                                ${item.sample_count} ${item.sample_count === 1 ? 'amostra' : 'amostras'} &middot; a partir de R$ ${Utils.formatCurrency(item.min_price)}
                            </p>
                        </div>
                        <i class="ki-filled ki-arrow-right text-muted-foreground ms-2 shrink-0"></i>
                    </div>`).join('');
            }

            searchResults.classList.remove('hidden');
        });
    };

    const handleSearchInput = () => {
        clearTimeout(debounceTimer);
        const query = searchInput.value.trim();

        if (query.length < 2) {
            searchResults.classList.add('hidden');
            resultsList.innerHTML = '';
            return;
        }

        debounceTimer = setTimeout(() => fetchProductOptions(query), 300);
    };

    const selectProduct = (product) => {
        currentProduct = product;

        productPicker.classList.add('hidden');
        selectedProductBar.classList.remove('hidden');
        selectedProductName.textContent = product;

        searchResults.classList.add('hidden');
        resultsList.innerHTML = '';
        searchInput.value = '';

        emptyState.classList.add('hidden');
        productSections.classList.remove('hidden');

        loadHistory(product);
        fetchByCity(product);
    };

    const changeProduct = () => {
        currentProduct = null;

        productPicker.classList.remove('hidden');
        selectedProductBar.classList.add('hidden');
        productSections.classList.add('hidden');
        emptyState.classList.remove('hidden');

        searchInput.focus();
    };

    const handleResultsClick = (e) => {
        const row = e.target.closest('[data-product]');
        if (!row) return;
        selectProduct(decodeURIComponent(row.dataset.product));
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            ({ searchUrl, historyUrl, byCityUrl, byIssuerUrl } = window.pageConfig);

            searchInput = document.getElementById('searchInput');
            searchResults = document.getElementById('searchResults');
            resultsList = document.getElementById('resultsList');
            productPicker = document.getElementById('productPicker');
            selectedProductBar = document.getElementById('selectedProductBar');
            selectedProductName = document.getElementById('selectedProductName');
            btnChangeProduct = document.getElementById('btnChangeProduct');
            emptyState = document.getElementById('emptyState');
            productSections = document.getElementById('productSections');
            historyDataWrapper = document.getElementById('historyDataWrapper');
            historyEmptyState = document.getElementById('historyEmptyState');
            resultsTitle = document.getElementById('resultsTitle');
            tableHead = document.getElementById('resultsTableHead');
            tableBody = document.getElementById('resultsTableBody');
            btnBackToCities = document.getElementById('btnBackToCities');

            searchInput.addEventListener('input', handleSearchInput);
            resultsList.addEventListener('click', handleResultsClick);
            btnChangeProduct.addEventListener('click', changeProduct);
            tableBody.addEventListener('click', handleTableClick);
            btnBackToCities.addEventListener('click', handleBackToCities);
            document.addEventListener('product-alias:updated', handleAliasUpdated);

            const params = new URLSearchParams(window.location.search);
            const q = params.get('q');
            if (q) {
                searchInput.value = q;
                fetchProductOptions(q);
            }
        }
    };
})();

export default Prices;
