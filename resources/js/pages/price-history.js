import Utils from '../utils';

const dateFull = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });

const PriceHistory = (() => {
    let initialized = false;
    let searchUrl, showUrl;
    let searchInput, searchResults, resultsList, productDetail;
    let debounceTimer = null;
    let priceChartInstance = null;

    const getThemeColors = () => {
        const style = getComputedStyle(document.documentElement);
        return {
            primary: style.getPropertyValue('--color-primary').trim(),
            secondaryForeground: style.getPropertyValue('--color-secondary-foreground').trim(),
            border: style.getPropertyValue('--color-border').trim(),
            background: style.getPropertyValue('--color-background').trim(),
        };
    };

    const renderSummary = (summary) => {
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

    const renderChart = (timeline, summary) => {
        const el = document.getElementById('priceChart');

        if (priceChartInstance) {
            priceChartInstance.destroy();
            priceChartInstance = null;
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
            issuer: entry.issuer_name,
        }));

        const minIndex = data.reduce((best, p, i) => (p.y < data[best].y ? i : best), 0);
        const maxIndex = data.reduce((best, p, i) => (p.y > data[best].y ? i : best), 0);

        priceChartInstance = new ApexCharts(el, {
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
        priceChartInstance.render();
    };

    const renderTable = (timeline, summary) => {
        const tbody = document.getElementById('priceTableBody');
        const count = timeline.length;
        document.getElementById('entryCount').textContent = `${count} ${count === 1 ? 'registro' : 'registros'}`;

        if (count === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-secondary-foreground py-6">Nenhum registro.</td></tr>';
            return;
        }

        const { max_price: maxPrice, min_price: minPrice } = summary;

        tbody.innerHTML = timeline.map(entry => {
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

            return `
                <tr class="${rowClass} transition-colors hover:bg-accent/40">
                    <td class="text-sm text-secondary-foreground">${date}</td>
                    <td class="text-sm font-medium text-foreground">${entry.issuer_name}</td>
                    <td class="text-right font-semibold font-mono text-sm ${priceClass}">R$ ${Utils.formatCurrency(price)}${badge}</td>
                    <td class="text-right font-mono text-sm">${qtyFormatted.replace('.', ',')}</td>
                    <td class="text-center text-secondary-foreground text-sm">${entry.unit || '—'}</td>
                </tr>`;
        }).join('');
    };

    const loadProduct = (encodedDesc) => {
        const description = decodeURIComponent(encodedDesc);
        searchResults.classList.add('hidden');
        document.getElementById('productTitle').textContent = description;

        Utils.http(`${showUrl}?description=${encodeURIComponent(description)}`)
            .then(data => {
                renderSummary(data.summary);
                renderChart(data.timeline, data.summary);
                renderTable(data.timeline, data.summary);
                productDetail.style.display = 'block';
            });
    };

    const fetchResults = (query) => {
        Utils.http(`${searchUrl}?q=${encodeURIComponent(query)}`)
            .then(data => {
                if (data.length === 0) {
                    resultsList.innerHTML = '<div class="px-4 py-3 text-sm text-secondary-foreground">Nenhum produto encontrado.</div>';
                } else {
                    resultsList.innerHTML = data.map(item => {
                        const min = Utils.formatCurrency(item.min_price);
                        const max = Utils.formatCurrency(item.max_price);
                        return `
                            <div class="flex items-center gap-3 px-4 py-3 hover:bg-accent/30 cursor-pointer transition-colors"
                                 data-product="${encodeURIComponent(item.description)}">
                                <div class="flex items-center justify-center size-9 rounded-lg bg-primary/10 text-primary shrink-0">
                                    <i class="ki-filled ki-chart-line-star text-sm"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-foreground truncate">${item.description}</p>
                                    <p class="text-xs text-secondary-foreground mt-0.5">
                                        ${item.purchase_count}x comprado &middot; R$ ${min} — R$ ${max}
                                    </p>
                                </div>
                                <i class="ki-filled ki-arrow-right text-muted-foreground ms-2 shrink-0"></i>
                            </div>`;
                    }).join('');
                }
                searchResults.classList.remove('hidden');
            });
    };

    const handleInput = () => {
        clearTimeout(debounceTimer);
        const query = searchInput.value.trim();

        if (query.length < 2) {
            searchResults.classList.add('hidden');
            resultsList.innerHTML = '';
            return;
        }

        debounceTimer = setTimeout(() => fetchResults(query), 300);
    };

    const handleResultsClick = (e) => {
        const row = e.target.closest('[data-product]');
        if (!row) return;
        loadProduct(row.dataset.product);
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            ({ searchUrl, showUrl } = window.pageConfig);
            searchInput = document.getElementById('searchInput');
            searchResults = document.getElementById('searchResults');
            resultsList = document.getElementById('resultsList');
            productDetail = document.getElementById('productDetail');

            searchInput.addEventListener('input', handleInput);
            resultsList.addEventListener('click', handleResultsClick);

            const params = new URLSearchParams(window.location.search);
            const q = params.get('q');
            if (q) {
                searchInput.value = q;
                fetchResults(q);
            }
        }
    };
})();

export default PriceHistory;
