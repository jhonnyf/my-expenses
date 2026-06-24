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
        window.pageConfig = {
            searchUrl: '{{ route("price-history.search") }}',
            showUrl: '{{ route("price-history.show") }}',
        };
    </script>
    @push('scripts') @vite('resources/js/pages/price-history.js') @endpush
@endsection
