import Utils from '../utils';

const PriceComparison = (() => {
    let initialized = false;
    let searchProductsUrl, byCityUrl, byIssuerUrl;
    let searchInput, searchResults, resultsList, productPicker, selectedProductBar, selectedProductName, btnChangeProduct;
    let resultsWrapper, emptyState, resultsTitle, tableHead, tableBody, btnBackToCities;
    let debounceTimer = null;
    let chartInstance = null;

    let mode = 'city';
    let currentProduct = null;

    const getThemeColors = () => {
        const style = getComputedStyle(document.documentElement);
        return {
            primary: style.getPropertyValue('--color-primary').trim(),
            secondaryForeground: style.getPropertyValue('--color-secondary-foreground').trim(),
            border: style.getPropertyValue('--color-border').trim(),
        };
    };

    const renderChart = (rows, labels) => {
        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }

        const el = document.getElementById('priceComparisonChart');
        if (rows.length === 0) {
            el.innerHTML = '<p class="text-sm text-secondary-foreground py-4 w-full text-center">Sem dados suficientes.</p>';
            return;
        }

        el.innerHTML = '';
        const colors = getThemeColors();

        chartInstance = new ApexCharts(el, {
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
        chartInstance.render();
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

    let lastCityRows = [];

    const showCityMode = (rows) => {
        mode = 'city';
        lastCityRows = rows;

        resultsTitle.textContent = 'Ranking por cidade';
        btnBackToCities.classList.add('hidden');

        renderChart(rows, rows.map(r => `${r.city}/${r.state}`));
        renderCityTable(rows);
    };

    const showIssuerMode = (rows, city, state) => {
        mode = 'issuer';

        resultsTitle.textContent = `Mercados em ${city}/${state}`;
        btnBackToCities.classList.remove('hidden');

        renderChart(rows, rows.map(r => r.issuer_name));
        renderIssuerTable(rows);
    };

    const toggleResults = (hasProduct) => {
        resultsWrapper.classList.toggle('hidden', !hasProduct);
        emptyState.classList.toggle('hidden', hasProduct);
    };

    const fetchByCity = (product) => {
        Utils.http(`${byCityUrl}?product=${encodeURIComponent(product)}`).then(rows => {
            toggleResults(true);
            showCityMode(rows);
        });
    };

    const fetchByIssuer = (product, city, state) => {
        const params = new URLSearchParams({ product, city, state });
        Utils.http(`${byIssuerUrl}?${params.toString()}`).then(rows => {
            toggleResults(true);
            showIssuerMode(rows, city, state);
        });
    };

    const selectProduct = (product) => {
        currentProduct = product;

        productPicker.classList.add('hidden');
        selectedProductBar.classList.remove('hidden');
        selectedProductName.textContent = product;

        searchResults.classList.add('hidden');
        resultsList.innerHTML = '';
        searchInput.value = '';

        fetchByCity(product);
    };

    const changeProduct = () => {
        currentProduct = null;

        productPicker.classList.remove('hidden');
        selectedProductBar.classList.add('hidden');
        toggleResults(false);

        searchInput.focus();
    };

    const fetchProductOptions = (query) => {
        Utils.http(`${searchProductsUrl}?q=${encodeURIComponent(query)}`).then(data => {
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

    const handleResultsClick = (e) => {
        const row = e.target.closest('[data-product]');
        if (!row) return;

        selectProduct(decodeURIComponent(row.dataset.product));
    };

    const handleTableClick = (e) => {
        const drillBtn = e.target.closest('[data-drill-city]');
        if (!drillBtn || mode !== 'city' || !currentProduct) return;

        const row = lastCityRows[parseInt(drillBtn.dataset.drillCity)];
        if (row) fetchByIssuer(currentProduct, row.city, row.state);
    };

    const handleBackToCities = () => {
        if (currentProduct) fetchByCity(currentProduct);
    };

    return {
        init: () => {
            if (initialized) return;
            initialized = true;

            ({ searchProductsUrl, byCityUrl, byIssuerUrl } = window.pageConfig);

            searchInput = document.getElementById('searchInput');
            searchResults = document.getElementById('searchResults');
            resultsList = document.getElementById('resultsList');
            productPicker = document.getElementById('productPicker');
            selectedProductBar = document.getElementById('selectedProductBar');
            selectedProductName = document.getElementById('selectedProductName');
            btnChangeProduct = document.getElementById('btnChangeProduct');
            resultsWrapper = document.getElementById('resultsWrapper');
            emptyState = document.getElementById('emptyState');
            resultsTitle = document.getElementById('resultsTitle');
            tableHead = document.getElementById('resultsTableHead');
            tableBody = document.getElementById('resultsTableBody');
            btnBackToCities = document.getElementById('btnBackToCities');

            searchInput.addEventListener('input', handleSearchInput);
            resultsList.addEventListener('click', handleResultsClick);
            btnChangeProduct.addEventListener('click', changeProduct);
            tableBody.addEventListener('click', handleTableClick);
            btnBackToCities.addEventListener('click', handleBackToCities);
        }
    };
})();

export default PriceComparison;
