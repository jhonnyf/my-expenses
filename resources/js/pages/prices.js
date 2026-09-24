import Utils from '../utils';

const dateFull = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
const dateShort = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });

const MONTHLY_DEFAULT_THRESHOLD = 60;
const NEW_LIST_VALUE = 'new';

const Prices = (() => {
    let initialized = false;
    let searchUrl, historyUrl, byCityUrl, byIssuerUrl, unitsUrl, shoppingListUrl, freshDays;
    let shoppingLists = [];
    let favoriteProducts = [];
    let searchInput, searchResults, resultsList, productPicker, selectedProductBar, selectedProductName, btnChangeProduct;
    let emptyState, productSections;
    let historyDataWrapper, historyEmptyState;
    let resultsTitle, tableHead, tableBody, btnBackToCities;
    let debounceTimer = null;
    let historyChartInstance = null;
    let comparisonChartInstance = null;

    let comparisonMode = 'city';
    let currentProduct = null;
    let currentUnit = null;
    let currentCity = null;
    let historyData = null;
    let chartMode = 'purchase';
    let lastCityRows = [];
    let lastIssuerRows = [];
    let pendingListItem = null;

    const { showFlash, errorMessage } = Utils;

    const money = (value) => `R$ ${Utils.formatCurrency(value)}`;

    const getThemeColors = () => {
        const style = getComputedStyle(document.documentElement);
        return {
            primary: style.getPropertyValue('--color-primary').trim(),
            secondaryForeground: style.getPropertyValue('--color-secondary-foreground').trim(),
            border: style.getPropertyValue('--color-border').trim(),
            background: style.getPropertyValue('--color-background').trim(),
        };
    };

    const withUnit = (params) => {
        if (currentUnit) params.set('unit', currentUnit);
        return params;
    };

    const failed = (fallback) => (error) => showFlash(errorMessage(error, fallback), 'error');

    // ▲/▼ colorido de uma variação percentual (subir = ruim para quem compra)
    const changeBadge = (pct) => {
        if (pct === null || pct === undefined || pct === 0) return '<span class="text-secondary-foreground">—</span>';

        const up = pct > 0;
        return `<span class="${up ? 'text-red-500' : 'text-green-600'} tabular-nums">${up ? '▲' : '▼'} ${Math.abs(pct).toFixed(1).replace('.', ',')}%</span>`;
    };

    const staleBadge = (isStale) => (isStale
        ? `<span class="kt-badge kt-badge-light kt-badge-warning kt-badge-sm ms-1" title="A compra mais recente tem mais de ${freshDays} dias">antigo</span>`
        : '');

    // ---------- Meu Histórico ----------

    const statCard = (icon, tint, color, value, label, sub = '') => `
        <div class="kt-card flex-row items-center gap-4 p-5">
            <div class="flex items-center justify-center size-10 rounded-xl ${tint} shrink-0">
                <i class="ki-filled ${icon} ${color} text-xl"></i>
            </div>
            <div class="flex flex-col gap-0.5 min-w-0">
                <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums truncate">${value}</span>
                <span class="text-xs font-normal text-secondary-foreground truncate">${label}</span>
                ${sub ? `<span class="text-xs tabular-nums">${sub}</span>` : ''}
            </div>
        </div>`;

    const renderHistorySummary = (summary) => {
        const last = summary.last_price;

        document.getElementById('summaryCards').innerHTML = `
            ${statCard('ki-dollar', 'bg-primary/10', 'text-primary', last !== null ? money(last) : '—', 'Último preço',
                summary.previous_price !== null ? `${changeBadge(summary.change_pct)} <span class="text-secondary-foreground">vs. compra anterior</span>` : '')}
            ${statCard('ki-arrow-down', 'bg-green-500/10', 'text-green-600', money(summary.min_price), 'Menor preço')}
            ${statCard('ki-arrow-up', 'bg-red-500/10', 'text-red-500', money(summary.max_price), 'Maior preço')}
            ${statCard('ki-chart', 'bg-yellow-500/10', 'text-yellow-600', money(summary.avg_price), 'Preço médio',
                summary.median_price !== null ? `<span class="text-secondary-foreground">mediana ${money(summary.median_price)}</span>` : '')}
            <div class="col-span-2 lg:col-span-4 flex flex-wrap items-center gap-x-6 gap-y-1 text-xs text-secondary-foreground">
                <span>Há 30 dias: ${changeBadge(summary.change_30d_pct)}</span>
                <span>Há 90 dias: ${changeBadge(summary.change_90d_pct)}</span>
                <span title="(maior − menor) ÷ menor, em todo o histórico">Amplitude do histórico: <span class="tabular-nums text-foreground">${summary.spread_pct.toFixed(1).replace('.', ',')}%</span></span>
            </div>`;
    };

    const medianAnnotation = (summary, colors) => (summary.median_price
        ? {
            yaxis: [{
                y: summary.median_price,
                borderColor: colors.secondaryForeground,
                strokeDashArray: 4,
                label: { text: `mediana ${money(summary.median_price)}`, position: 'left', offsetX: 60, style: { color: '#fff', background: colors.secondaryForeground, fontSize: '10px' } },
            }],
        }
        : {});

    const baseChartOptions = (colors) => ({
        chart: { height: '100%', fontFamily: 'Inter, sans-serif', toolbar: { show: false }, zoom: { enabled: false } },
        dataLabels: { enabled: false },
        yaxis: { labels: { style: { colors: colors.secondaryForeground, fontSize: '11px' }, formatter: v => money(v) } },
        grid: { borderColor: colors.border, strokeDashArray: 4, padding: { left: 8, right: 8 } },
    });

    const purchaseChartOptions = (timeline, summary, colors) => {
        const data = timeline.map(entry => ({
            x: new Date(entry.issued_at).getTime(),
            y: parseFloat(entry.unit_price),
            issuer: Utils.escapeHtml(entry.issuer_name),
        }));

        const minIndex = data.reduce((best, p, i) => (p.y < data[best].y ? i : best), 0);
        const maxIndex = data.reduce((best, p, i) => (p.y > data[best].y ? i : best), 0);
        const base = baseChartOptions(colors);

        return {
            ...base,
            series: [{ name: 'Preço unitário', data }],
            chart: { ...base.chart, type: 'area' },
            colors: [colors.primary],
            stroke: { curve: 'smooth', width: 2.5 },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05, stops: [0, 90, 100] } },
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
            annotations: medianAnnotation(summary, colors),
            tooltip: {
                x: { format: 'dd/MM/yyyy' },
                y: {
                    formatter: (value, opts) => {
                        const point = opts.w.config.series[opts.seriesIndex].data[opts.dataPointIndex];
                        const tag = point.y === summary.min_price ? ' (menor preço)' : (point.y === summary.max_price ? ' (maior preço)' : '');
                        return `${money(value)} — ${point.issuer}${tag}`;
                    },
                },
                theme: false,
                style: { fontSize: '12px' },
            },
        };
    };

    const monthLabel = (month) => {
        const [year, mon] = month.split('-');
        return new Date(year, mon - 1).toLocaleDateString('pt-BR', { month: 'short', year: '2-digit' });
    };

    const monthlyChartOptions = (monthly, summary, colors) => {
        const base = baseChartOptions(colors);

        return {
            ...base,
            series: [
                { name: 'Média', data: monthly.map(m => m.avg) },
                { name: 'Menor', data: monthly.map(m => m.min) },
                { name: 'Maior', data: monthly.map(m => m.max) },
            ],
            chart: { ...base.chart, type: 'line' },
            colors: [colors.primary, '#22c55e', '#ef4444'],
            stroke: { curve: 'smooth', width: [3, 1.5, 1.5], dashArray: [0, 4, 4] },
            markers: { size: 3 },
            xaxis: {
                categories: monthly.map(m => monthLabel(m.month)),
                labels: { style: { colors: colors.secondaryForeground, fontSize: '11px' } },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            annotations: medianAnnotation(summary, colors),
            legend: { position: 'top', fontSize: '11px', labels: { colors: colors.secondaryForeground } },
            tooltip: { y: { formatter: v => money(v) }, theme: false },
        };
    };

    const renderHistoryChart = () => {
        const el = document.getElementById('priceChart');

        if (historyChartInstance) {
            historyChartInstance.destroy();
            historyChartInstance = null;
        }

        const { timeline, monthly, summary } = historyData;
        const useMonthly = chartMode === 'month';

        if ((useMonthly ? monthly : timeline).length === 0) {
            el.innerHTML = '<p class="text-sm text-secondary-foreground py-4 w-full text-center">Sem dados.</p>';
            return;
        }

        el.innerHTML = '';
        const colors = getThemeColors();

        historyChartInstance = new ApexCharts(el, useMonthly
            ? monthlyChartOptions(monthly, summary, colors)
            : purchaseChartOptions(timeline, summary, colors));
        historyChartInstance.render();
    };

    const setChartMode = (mode) => {
        chartMode = mode;

        document.querySelectorAll('[data-chart-mode]').forEach(btn => {
            const active = btn.dataset.chartMode === mode;
            btn.classList.toggle('kt-btn-primary', active);
            btn.classList.toggle('kt-btn-outline', !active);
        });

        if (historyData) renderHistoryChart();
    };

    const renderHistoryTable = (timeline, summary, meta) => {
        const tbody = document.getElementById('priceTableBody');
        const cardsBody = document.getElementById('priceCardsBody');
        const count = timeline.length;
        document.getElementById('entryCount').textContent = meta.truncated
            ? `${count} compras mais recentes de ${meta.total_entries}`
            : `${count} ${count === 1 ? 'registro' : 'registros'}`;

        if (count === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-secondary-foreground py-6">Nenhum registro.</td></tr>';
            cardsBody.innerHTML = '<p class="text-center text-secondary-foreground py-6 text-sm">Nenhum registro.</p>';
            return;
        }

        const { max_price: maxPrice, min_price: minPrice } = summary;

        // Mais recentes primeiro: é o preço de agora que se quer ver.
        const rows = [...timeline].reverse().map(entry => {
            const price = parseFloat(entry.unit_price);
            const isMin = price === minPrice;
            const isMax = price === maxPrice;
            const qty = parseFloat(entry.quantity);
            const qtyFormatted = qty % 1 === 0 ? qty.toFixed(0) : qty.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');

            return {
                rowClass: isMin ? 'bg-green-50 dark:bg-green-500/5' : (isMax ? 'bg-red-50 dark:bg-red-500/5' : ''),
                priceClass: isMin ? 'text-green-600' : (isMax ? 'text-red-600' : ''),
                badge: isMin
                    ? '<span class="kt-badge kt-badge-success kt-badge-outline kt-badge-sm ms-2">menor</span>'
                    : (isMax ? '<span class="kt-badge kt-badge-destructive kt-badge-outline kt-badge-sm ms-2">maior</span>' : ''),
                date: dateFull.format(new Date(entry.issued_at)),
                qtyFormatted: qtyFormatted.replace('.', ','),
                price,
                issuerName: Utils.escapeHtml(entry.issuer_name),
                unit: Utils.escapeHtml(entry.unit || '—'),
            };
        });

        tbody.innerHTML = rows.map(r => `
                <tr class="${r.rowClass} transition-colors hover:bg-accent/60">
                    <td class="text-sm text-secondary-foreground">${r.date}</td>
                    <td class="text-sm font-medium text-foreground">${r.issuerName}</td>
                    <td class="text-right font-semibold font-mono text-sm ${r.priceClass}">${money(r.price)}${r.badge}</td>
                    <td class="text-right font-mono text-sm">${r.qtyFormatted}</td>
                    <td class="text-center text-secondary-foreground text-sm">${r.unit}</td>
                </tr>`).join('');

        cardsBody.innerHTML = rows.map(r => `
                <div class="rounded-xl border border-border p-4 flex flex-col gap-2 ${r.rowClass}">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-medium text-foreground">${r.issuerName}</span>
                        <span class="text-sm font-semibold font-mono ${r.priceClass}">${money(r.price)}${r.badge}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2 pt-2 border-t border-border/60 text-xs text-secondary-foreground">
                        <span>${r.date}</span>
                        <span>Qtd: ${r.qtyFormatted} ${r.unit}</span>
                    </div>
                </div>`).join('');
    };

    const renderHistoryEmptyState = () => {
        historyDataWrapper.classList.add('hidden');
        historyEmptyState.classList.remove('hidden');
    };

    const loadHistory = (product) => {
        document.getElementById('productTitle').textContent = product;

        Utils.http(`${historyUrl}?${withUnit(new URLSearchParams({ description: product }))}`)
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

                historyData = data;
                historyEmptyState.classList.add('hidden');
                historyDataWrapper.classList.remove('hidden');
                renderHistorySummary(data.summary);
                renderHistoryTable(data.timeline, data.summary, data);

                // Muito histórico: a série mensal é mais legível que centenas de pontos.
                setChartMode(data.total_entries > MONTHLY_DEFAULT_THRESHOLD && data.monthly.length > 1 ? 'month' : 'purchase');
            })
            .catch(failed('Não foi possível carregar o histórico.'));
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

        // Barras de preço atual; as de preço antigo (> freshDays) ficam em cinza.
        comparisonChartInstance = new ApexCharts(el, {
            series: [{ name: 'Preço atual', data: rows.map(r => ({ x: labels[rows.indexOf(r)], y: parseFloat(r.price), fillColor: r.is_stale ? colors.secondaryForeground : colors.primary })) }],
            chart: { type: 'bar', height: '100%', fontFamily: 'Inter, sans-serif', toolbar: { show: false } },
            plotOptions: { bar: { horizontal: true, borderRadius: 4, distributed: false } },
            colors: [colors.primary],
            dataLabels: {
                enabled: true,
                formatter: v => money(v),
                style: { colors: [colors.secondaryForeground] },
                offsetX: 30,
            },
            xaxis: {
                labels: { style: { colors: colors.secondaryForeground, fontSize: '11px' }, formatter: v => money(v) },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: { labels: { style: { colors: colors.secondaryForeground, fontSize: '11px' } } },
            grid: { borderColor: colors.border, strokeDashArray: 4 },
            tooltip: { y: { formatter: v => money(v) }, theme: false },
        });
        comparisonChartInstance.render();
    };

    const headCell = (label, align = 'end') => `<th class="text-${align} text-xs font-semibold text-secondary-foreground uppercase tracking-wide">${label}</th>`;

    const renderCityTable = (rows) => {
        tableHead.innerHTML = `
            ${headCell('Cidade/Estado', 'start')}
            ${headCell('Preço atual')}
            ${headCell('Mais barato em', 'start')}
            ${headCell('Menor histórico')}
            ${headCell('Amostras')}
            <th></th>`;

        tableBody.innerHTML = rows.map((row, index) => `
            <tr class="transition-colors duration-150 hover:bg-accent/60 ${row.is_user_city ? 'bg-primary/5' : ''}">
                <td class="text-sm text-foreground font-medium">
                    ${Utils.escapeHtml(row.city)}/${Utils.escapeHtml(row.state)}
                    ${row.is_user_city ? '<span class="kt-badge kt-badge-light kt-badge-primary kt-badge-sm ms-1">sua cidade</span>' : ''}
                </td>
                <td class="text-end text-sm font-semibold text-primary tabular-nums">
                    ${money(row.price)}${staleBadge(row.is_stale)}
                    <span class="block text-xs font-normal text-secondary-foreground">${dateShort.format(new Date(row.issued_at))}</span>
                </td>
                <td class="text-sm text-secondary-foreground">${Utils.escapeHtml(row.cheapest_issuer_name)}
                    ${row.issuer_count > 1 ? `<span class="block text-xs">${row.issuer_count} mercados</span>` : ''}</td>
                <td class="text-end text-sm text-secondary-foreground tabular-nums" title="Menor preço já registrado (referência histórica)">${money(row.min_price)}</td>
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
            ${headCell('Mercado', 'start')}
            ${headCell('Preço atual')}
            ${headCell('Distância')}
            ${headCell('Preço médio')}
            ${headCell('Amostras')}
            <th></th>`;

        tableBody.innerHTML = rows.map((row, index) => `
            <tr class="transition-colors duration-150 hover:bg-accent/60">
                <td class="text-sm text-foreground font-medium">${Utils.escapeHtml(row.issuer_name)}</td>
                <td class="text-end text-sm font-semibold text-primary tabular-nums">
                    ${money(row.price)}${staleBadge(row.is_stale)}
                    <span class="block text-xs font-normal text-secondary-foreground">${dateShort.format(new Date(row.issued_at))}</span>
                </td>
                <td class="text-end text-sm text-secondary-foreground tabular-nums">${row.distance_km !== null ? `${String(row.distance_km).replace('.', ',')} km` : '—'}</td>
                <td class="text-end text-sm text-secondary-foreground tabular-nums">${money(row.avg_price)}</td>
                <td class="text-end text-sm text-secondary-foreground tabular-nums">${row.sample_count}</td>
                <td class="text-end">
                    <button type="button" class="kt-btn kt-btn-sm kt-btn-outline" data-add-to-list="${index}"
                            data-kt-modal-toggle="#addToListModal" title="Adicionar à lista de compras com este preço">
                        <i class="ki-filled ki-basket"></i> Lista
                    </button>
                </td>
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
        lastIssuerRows = rows;

        resultsTitle.textContent = `Mercados em ${city}/${state}`;
        btnBackToCities.classList.remove('hidden');

        renderComparisonChart(rows, rows.map(r => r.issuer_name));
        renderIssuerTable(rows);
    };

    const fetchByCity = (product) => {
        Utils.http(`${byCityUrl}?${withUnit(new URLSearchParams({ product }))}`)
            .then(rows => showCityMode(rows))
            .catch(failed('Não foi possível carregar o comparativo por cidade.'));
    };

    const fetchByIssuer = (product, city, state) => {
        currentCity = { city, state };

        Utils.http(`${byIssuerUrl}?${withUnit(new URLSearchParams({ product, city, state }))}`)
            .then(rows => showIssuerMode(rows, city, state))
            .catch(failed('Não foi possível carregar os mercados.'));
    };

    const handleTableClick = (e) => {
        const drillBtn = e.target.closest('[data-drill-city]');
        if (drillBtn && comparisonMode === 'city' && currentProduct) {
            const row = lastCityRows[parseInt(drillBtn.dataset.drillCity, 10)];
            if (row) fetchByIssuer(currentProduct, row.city, row.state);
            return;
        }

        const addBtn = e.target.closest('[data-add-to-list]');
        if (addBtn && comparisonMode === 'issuer') {
            prepareAddToList(lastIssuerRows[parseInt(addBtn.dataset.addToList, 10)]);
        }
    };

    const handleBackToCities = () => {
        if (currentProduct) fetchByCity(currentProduct);
    };

    // ---------- Adicionar à lista de compras ----------

    const fillListOptions = () => {
        const select = document.getElementById('addToListSelect');
        select.innerHTML = '';

        shoppingLists.forEach(list => {
            const option = document.createElement('option');
            option.value = list.id;
            option.textContent = list.name;
            select.appendChild(option);
        });

        const fresh = document.createElement('option');
        fresh.value = NEW_LIST_VALUE;
        fresh.textContent = '+ Nova lista';
        select.appendChild(fresh);
    };

    const prepareAddToList = (row) => {
        if (!row) return;

        pendingListItem = {
            description: currentProduct,
            unit_price: row.price,
            unit: currentUnit || null,
            issuer_id: row.issuer_id,
            issuerName: row.issuer_name,
        };

        document.getElementById('addToListProduct').textContent = currentProduct;
        document.getElementById('addToListDetail').textContent = `${row.issuer_name} · ${money(row.price)}${currentUnit ? ` / ${currentUnit}` : ''}`;
        document.getElementById('addToListError').classList.add('hidden');
        fillListOptions();
    };

    const setAddToListError = (message) => {
        const el = document.getElementById('addToListError');
        el.textContent = message;
        el.classList.remove('hidden');
    };

    const confirmAddToList = async () => {
        if (!pendingListItem) return;

        const select = document.getElementById('addToListSelect');
        const { issuerName, ...payload } = pendingListItem;

        try {
            let listId = select.value;
            let listName = select.options[select.selectedIndex].textContent;

            if (listId === NEW_LIST_VALUE) {
                const created = await Utils.http(shoppingListUrl, { method: 'POST', body: {} });
                shoppingLists.unshift({ id: created.id, name: created.name });
                listId = created.id;
                listName = created.name;
            }

            await Utils.http(`${shoppingListUrl}/${listId}/items`, { method: 'POST', body: { ...payload, quantity: 1 } });

            window.KTModal?.getInstance(document.getElementById('addToListModal'))?.hide();
            showFlash(`${payload.description} (${issuerName}) adicionado à lista "${listName}".`);
        } catch (error) {
            setAddToListError(errorMessage(error, 'Não foi possível adicionar à lista.'));
        }
    };

    // ---------- Busca / seleção de produto ----------

    const fetchProductOptions = (query) => {
        Utils.http(`${searchUrl}?q=${encodeURIComponent(query)}`).then(data => {
            if (data.length === 0) {
                resultsList.innerHTML = '<div class="px-4 py-3 text-sm text-secondary-foreground">Nenhum produto encontrado.</div>';
            } else {
                resultsList.innerHTML = data.map(item => {
                    const current = item.current_min_price !== null && item.current_min_price !== undefined;
                    const price = current ? item.current_min_price : item.min_price;
                    const last = item.last_purchased_at ? dateShort.format(new Date(item.last_purchased_at)) : '';

                    return `
                    <div class="flex items-center gap-3 px-4 py-3 hover:bg-accent/30 cursor-pointer transition-colors"
                         data-product="${encodeURIComponent(item.name)}">
                        <div class="flex items-center justify-center size-9 rounded-lg bg-primary/10 text-primary shrink-0">
                            <i class="ki-filled ki-basket text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-foreground truncate">${Utils.escapeHtml(item.name)}</p>
                            <p class="text-xs text-secondary-foreground mt-0.5">
                                ${item.sample_count} ${item.sample_count === 1 ? 'amostra' : 'amostras'}
                                &middot; ${current ? 'preço atual a partir de' : 'a partir de'} ${money(price)}${current ? '' : ' <span class="kt-badge kt-badge-light kt-badge-warning kt-badge-sm ms-1">antigo</span>'}
                                ${last ? `&middot; última compra em ${last}` : ''}
                            </p>
                        </div>
                        <i class="ki-filled ki-arrow-right text-muted-foreground ms-2 shrink-0"></i>
                    </div>`;
                }).join('');
            }

            searchResults.classList.remove('hidden');
        }).catch(failed('Não foi possível buscar produtos.'));
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

    // ---------- Produto selecionado: unidade e alerta ----------

    const updateFavoriteButton = (product) => {
        const btn = document.getElementById('btnFavoriteProduct');
        const isFavorite = favoriteProducts.includes(product);

        btn.dataset.description = product;
        btn.dataset.unit = currentUnit || '';
        btn.querySelector('i').classList.toggle('text-destructive', isFavorite);
        btn.title = isFavorite ? 'Remover aviso de queda de preço' : 'Avisar quando o preço cair';
    };

    // Vários tamanhos/unidades (KG, UN) não se comparam: pergunta qual, com a mais comum já escolhida.
    const loadUnits = (product) => Utils.http(`${unitsUrl}?${new URLSearchParams({ product })}`).then(units => {
        const select = document.getElementById('unitSelect');
        select.innerHTML = '';

        units.forEach(({ unit, sample_count: count }) => {
            const option = document.createElement('option');
            option.value = unit;
            option.textContent = `${unit || 'sem unidade'} (${count})`;
            select.appendChild(option);
        });

        select.classList.toggle('hidden', units.length < 2);
        currentUnit = units.length > 0 && units[0].unit !== '' ? units[0].unit : null;
    });

    const handleUnitChange = (e) => {
        currentUnit = e.target.value || null;
        updateFavoriteButton(currentProduct);
        loadHistory(currentProduct);
        fetchByCity(currentProduct);
    };

    const selectProduct = async (product) => {
        currentProduct = product;

        productPicker.classList.add('hidden');
        selectedProductBar.classList.remove('hidden');
        selectedProductBar.classList.add('flex');
        selectedProductName.textContent = product;

        searchResults.classList.add('hidden');
        resultsList.innerHTML = '';
        searchInput.value = '';

        emptyState.classList.add('hidden');
        productSections.classList.remove('hidden');

        try {
            await loadUnits(product);
        } catch {
            currentUnit = null;
        }

        updateFavoriteButton(product);
        loadHistory(product);
        fetchByCity(product);
    };

    const changeProduct = () => {
        currentProduct = null;
        currentUnit = null;
        historyData = null;

        productPicker.classList.remove('hidden');
        selectedProductBar.classList.add('hidden');
        selectedProductBar.classList.remove('flex');
        productSections.classList.add('hidden');
        emptyState.classList.remove('hidden');

        searchInput.focus();
    };

    // O coração é tratado globalmente (Utils.initFavoriteProduct); aqui só se mantém a lista local em dia.
    const handleFavoriteToggled = ({ detail: { description, isFavorite } }) => {
        favoriteProducts = isFavorite
            ? [...new Set([...favoriteProducts, description])]
            : favoriteProducts.filter(name => name !== description);
    };

    const handleResultsClick = (e) => {
        const row = e.target.closest('[data-product]');
        if (!row) return;
        selectProduct(decodeURIComponent(row.dataset.product));
    };

    const handleClick = (e) => {
        const modeBtn = e.target.closest('[data-chart-mode]');
        if (modeBtn) {
            setChartMode(modeBtn.dataset.chartMode);
            return;
        }

        if (e.target.closest('[data-action="confirm-add-to-list"]')) confirmAddToList();
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            ({ searchUrl, historyUrl, byCityUrl, byIssuerUrl, unitsUrl, shoppingListUrl, freshDays } = window.pageConfig);
            shoppingLists = [...(window.pageConfig.shoppingLists ?? [])];
            favoriteProducts = [...(window.pageConfig.favoriteProducts ?? [])];

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
            document.getElementById('unitSelect').addEventListener('change', handleUnitChange);
            document.addEventListener('click', handleClick);
            document.addEventListener('favorite-product:toggled', handleFavoriteToggled);
            document.addEventListener('product-alias:updated', handleAliasUpdated);

            const params = new URLSearchParams(window.location.search);
            const q = params.get('q');
            if (q) {
                searchInput.value = q;
                fetchProductOptions(q);
            }

            // Vindo de outra tela (ex.: detalhe do emissor): abre direto no histórico do produto.
            const product = params.get('product');
            if (product) selectProduct(product);
        }
    };
})();

export default Prices;
