@extends('layout.main')

@section('content')
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Histórico de Preços</h1>
                <p class="text-sm text-secondary-foreground">Acompanhe a evolução dos preços dos seus produtos</p>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed space-y-5 pb-10">

        {{-- Busca --}}
        <div class="kt-card">
            <div class="kt-card-content py-5 px-5">
                <div class="flex flex-col gap-3">
                    <label class="text-sm font-medium text-foreground">Buscar produto</label>
                    <label class="kt-input w-full">
                        <i class="ki-filled ki-magnifier"></i>
                        <input type="text" id="searchInput" placeholder="Digite o nome do produto (mín. 2 caracteres)" autocomplete="off" />
                    </label>

                    <div id="searchResults" class="hidden">
                        <div class="border border-border rounded-lg overflow-hidden mt-1">
                            <div class="bg-accent/40 px-4 py-2 text-xs font-semibold text-secondary-foreground uppercase tracking-wide">
                                Produtos encontrados
                            </div>
                            <div id="resultsList" class="divide-y divide-border max-h-80 overflow-y-auto"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Detalhe do produto --}}
        <div id="productDetail" style="display:none;" class="space-y-5">

            <div class="flex items-center gap-2">
                <i class="ki-filled ki-chart-line-up text-primary text-lg"></i>
                <h2 id="productTitle" class="text-lg font-semibold text-foreground"></h2>
            </div>

            {{-- Cards resumo --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-5" id="summaryCards"></div>

            {{-- Gráfico --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-chart-line text-primary me-1"></i> Evolução de Preço
                    </h3>
                </div>
                <div class="kt-card-content pb-5">
                    <div id="priceChart" class="flex items-end gap-2 h-48"></div>
                </div>
            </div>

            {{-- Tabela --}}
            <div class="kt-card kt-card-grid">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-document text-primary me-1"></i> Todas as Compras
                    </h3>
                    <span id="entryCount" class="text-xs text-secondary-foreground"></span>
                </div>
                <div class="kt-card-content">
                    <div class="kt-scrollable-x-auto">
                        <table class="kt-table table-auto kt-table-border">
                            <thead>
                                <tr>
                                    <th class="min-w-[120px]">Data</th>
                                    <th class="min-w-[200px]">Emitente</th>
                                    <th class="min-w-[110px] text-right">Preço Unit.</th>
                                    <th class="min-w-[90px] text-right">Qtd</th>
                                    <th class="min-w-[60px] text-center">Un.</th>
                                </tr>
                            </thead>
                            <tbody id="priceTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        let searchTimeout = null;
        const searchInput = document.getElementById('searchInput');
        const searchResults = document.getElementById('searchResults');
        const resultsList = document.getElementById('resultsList');
        const productDetail = document.getElementById('productDetail');

        // Auto-load from query string
        document.addEventListener('DOMContentLoaded', function () {
            const params = new URLSearchParams(window.location.search);
            const q = params.get('q');
            if (q) {
                searchInput.value = q;
                fetchResults(q);
            }
        });

        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            if (query.length < 2) {
                searchResults.classList.add('hidden');
                resultsList.innerHTML = '';
                return;
            }
            searchTimeout = setTimeout(() => fetchResults(query), 300);
        });

        function fetchResults(query) {
            fetch(`{{ route('price-history.search') }}?q=${encodeURIComponent(query)}`)
                .then(r => r.json())
                .then(data => {
                    resultsList.innerHTML = '';
                    if (data.length === 0) {
                        resultsList.innerHTML = '<div class="px-4 py-3 text-sm text-secondary-foreground">Nenhum produto encontrado.</div>';
                    } else {
                        data.forEach(item => {
                            const min = parseFloat(item.min_price).toFixed(2).replace('.', ',');
                            const max = parseFloat(item.max_price).toFixed(2).replace('.', ',');
                            const encoded = encodeURIComponent(item.description);
                            resultsList.innerHTML += `
                                <div class="flex items-center justify-between px-4 py-3 hover:bg-accent/30 cursor-pointer transition-colors"
                                     onclick="loadProduct('${encoded}')">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-foreground truncate">${item.description}</p>
                                        <p class="text-xs text-secondary-foreground mt-0.5">
                                            ${item.purchase_count}x comprado &middot; R$ ${min} — R$ ${max}
                                        </p>
                                    </div>
                                    <i class="ki-filled ki-arrow-right text-muted-foreground ms-2"></i>
                                </div>`;
                        });
                    }
                    searchResults.classList.remove('hidden');
                });
        }

        function loadProduct(encodedDesc) {
            const description = decodeURIComponent(encodedDesc);
            searchResults.classList.add('hidden');
            document.getElementById('productTitle').textContent = description;

            fetch(`{{ route('price-history.show') }}?description=${encodeURIComponent(description)}`)
                .then(r => r.json())
                .then(data => {
                    renderSummary(data.summary);
                    renderChart(data.timeline, data.summary);
                    renderTable(data.timeline, data.summary);
                    productDetail.style.display = 'block';
                });
        }

        function fmt(val) {
            return parseFloat(val).toFixed(2).replace('.', ',');
        }

        function renderSummary(summary) {
            const variationColor = summary.variation_pct > 20 ? 'text-red-500' : (summary.variation_pct < 5 ? 'text-green-500' : 'text-yellow-500');
            document.getElementById('summaryCards').innerHTML = `
                <div class="kt-card">
                    <div class="kt-card-content py-4 px-5">
                        <p class="text-xs text-secondary-foreground">Menor Preço</p>
                        <p class="text-2xl font-bold text-green-500 mt-1">R$ ${fmt(summary.min_price)}</p>
                    </div>
                </div>
                <div class="kt-card">
                    <div class="kt-card-content py-4 px-5">
                        <p class="text-xs text-secondary-foreground">Maior Preço</p>
                        <p class="text-2xl font-bold text-red-500 mt-1">R$ ${fmt(summary.max_price)}</p>
                    </div>
                </div>
                <div class="kt-card">
                    <div class="kt-card-content py-4 px-5">
                        <p class="text-xs text-secondary-foreground">Preço Médio</p>
                        <p class="text-2xl font-bold text-primary mt-1">R$ ${fmt(summary.avg_price)}</p>
                    </div>
                </div>
                <div class="kt-card">
                    <div class="kt-card-content py-4 px-5">
                        <p class="text-xs text-secondary-foreground">Variação</p>
                        <p class="text-2xl font-bold ${variationColor} mt-1">${summary.variation_pct.toFixed(1)}%</p>
                    </div>
                </div>`;
        }

        function renderChart(timeline, summary) {
            const chart = document.getElementById('priceChart');
            if (timeline.length === 0) {
                chart.innerHTML = '<p class="text-sm text-secondary-foreground py-4 w-full text-center">Sem dados.</p>';
                return;
            }

            const maxPrice = summary.max_price || 1;
            const minPrice = summary.min_price;
            let html = '';

            timeline.forEach(entry => {
                const price = parseFloat(entry.unit_price);
                const height = (price / maxPrice) * 100;
                const date = new Date(entry.issued_at).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
                const isMin = price === minPrice;
                const isMax = price === maxPrice;
                const barColor = isMin ? 'bg-green-500' : (isMax ? 'bg-red-500' : 'bg-primary/80 hover:bg-primary');

                html += `
                    <div class="flex-1 flex flex-col items-center gap-1" title="${entry.issuer_name} — R$ ${fmt(price)}">
                        <span class="text-xs font-mono text-secondary-foreground">R$ ${fmt(price)}</span>
                        <div class="w-full ${barColor} rounded-t-md transition-all" style="height: ${Math.max(height, 4)}%"></div>
                        <span class="text-xs text-secondary-foreground">${date}</span>
                    </div>`;
            });

            chart.innerHTML = html;
        }

        function renderTable(timeline, summary) {
            const tbody = document.getElementById('priceTableBody');
            const count = timeline.length;
            document.getElementById('entryCount').textContent = `${count} ${count === 1 ? 'registro' : 'registros'}`;

            if (count === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-secondary-foreground py-6">Nenhum registro.</td></tr>';
                return;
            }

            const minPrice = summary.min_price;
            const maxPrice = summary.max_price;

            let html = '';
            timeline.forEach(entry => {
                const price = parseFloat(entry.unit_price);
                const isMin = price === minPrice;
                const isMax = price === maxPrice;
                const rowClass = isMin ? 'bg-green-50 dark:bg-green-500/5' : (isMax ? 'bg-red-50 dark:bg-red-500/5' : '');
                const priceClass = isMin ? 'text-green-600' : (isMax ? 'text-red-600' : '');
                const date = new Date(entry.issued_at).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                const qty = parseFloat(entry.quantity);
                const qtyFormatted = qty % 1 === 0 ? qty.toFixed(0) : qty.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');

                html += `
                    <tr class="${rowClass}">
                        <td class="text-sm text-secondary-foreground">${date}</td>
                        <td class="text-sm font-medium text-foreground">${entry.issuer_name}</td>
                        <td class="text-right font-semibold font-mono text-sm ${priceClass}">R$ ${fmt(price)}</td>
                        <td class="text-right font-mono text-sm">${qtyFormatted.replace('.', ',')}</td>
                        <td class="text-center text-secondary-foreground text-sm">${entry.unit || '—'}</td>
                    </tr>`;
            });

            tbody.innerHTML = html;
        }
    </script>
@endsection
