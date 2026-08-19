@extends('layout.main')
@section('page-module', 'prices,product-alias')

@section('content')

    {{-- PAGE HEADER --}}
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Preços</h1>
                <div class="flex items-center gap-2 text-sm font-normal text-secondary-foreground">
                    Acompanhe a evolução dos seus preços e compare entre cidades e mercados
                </div>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed">
        <div class="grid gap-5 lg:gap-7.5">

            {{-- BUSCA / SELEÇÃO DE PRODUTO --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">Selecionar Produto</h3>
                </div>
                <div class="kt-card-content pb-5">
                    <div id="productPicker">
                        <label class="kt-input w-full">
                            <i class="ki-filled ki-magnifier"></i>
                            <input type="text" id="searchInput"
                                   placeholder="Digite o nome do produto (mín. 2 caracteres)"
                                   autocomplete="off" />
                        </label>
                        <div id="searchResults" class="hidden mt-3">
                            <div class="border border-border rounded-lg overflow-hidden">
                                <div class="bg-accent/40 px-4 py-2 text-xs font-semibold text-secondary-foreground uppercase tracking-wide">
                                    Produtos encontrados
                                </div>
                                <div id="resultsList" class="divide-y divide-border max-h-80 overflow-y-auto"></div>
                            </div>
                        </div>
                    </div>

                    <div id="selectedProductBar" class="hidden flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 min-w-0">
                            <i class="ki-filled ki-chart-line-star text-primary shrink-0"></i>
                            <span class="text-sm text-foreground font-medium truncate" id="selectedProductName"></span>
                        </div>
                        <button type="button" id="btnChangeProduct" class="kt-btn kt-btn-sm kt-btn-outline shrink-0">
                            <i class="ki-filled ki-arrows-loop"></i>
                            Trocar produto
                        </button>
                    </div>
                </div>
            </div>

            <div id="emptyState" class="kt-card">
                <div class="kt-card-content py-14 text-center">
                    <i class="ki-filled ki-chart-line-star text-4xl text-secondary-foreground/30 mb-3 block"></i>
                    <p class="text-sm text-secondary-foreground">Busque e selecione um produto para ver seu histórico de preços e comparar entre cidades e mercados.</p>
                </div>
            </div>

            {{-- SEÇÕES DO PRODUTO SELECIONADO --}}
            <div id="productSections" class="hidden grid gap-5 lg:gap-7.5">

                {{-- MEU HISTÓRICO --}}
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-semibold text-foreground">Meu Histórico</h2>
                </div>

                <div id="historyDataWrapper" class="grid gap-5 lg:gap-7.5">

                    {{-- Visão Geral --}}
                    <div class="kt-card">
                        <div class="kt-card-header">
                            <div class="flex items-center gap-1.5 min-w-0">
                                <h3 class="kt-card-title truncate" id="productTitle"></h3>
                                <button type="button" id="productAliasEditBtn"
                                        data-action="edit-product-alias"
                                        data-kt-modal-toggle="#productAliasModal"
                                        class="shrink-0 text-muted-foreground hover:text-primary transition-colors"
                                        title="Editar nome do produto">
                                    <i class="ki-filled ki-pencil text-sm"></i>
                                </button>
                            </div>
                        </div>
                        <div class="kt-card-content pb-5">
                            <div id="summaryCards" class="grid grid-cols-2 lg:grid-cols-4 gap-5"></div>
                        </div>
                    </div>

                    {{-- Evolução de Preço --}}
                    <div class="kt-card">
                        <div class="kt-card-header">
                            <h3 class="kt-card-title">Evolução de Preço</h3>
                        </div>
                        <div class="kt-card-content pb-4">
                            <div id="priceChart" style="height: 260px;"></div>
                        </div>
                    </div>

                    {{-- Todas as Compras --}}
                    <div class="kt-card kt-card-grid">
                        <div class="kt-card-header">
                            <h3 class="kt-card-title">Histórico</h3>
                            <span id="entryCount" class="text-xs text-secondary-foreground"></span>
                        </div>
                        {{-- DESKTOP (lg+): tabela --}}
                        <div class="kt-card-table hidden lg:block">
                            <div class="kt-scrollable-x-auto">
                                <table class="kt-table kt-table-border table-auto">
                                    <thead>
                                        <tr>
                                            <th class="min-w-[120px]">Data</th>
                                            <th class="min-w-[200px]">Emitente</th>
                                            <th class="min-w-[110px] text-end">Preço Unit.</th>
                                            <th class="min-w-[90px] text-end">Qtd</th>
                                            <th class="min-w-[60px] text-center">Un.</th>
                                        </tr>
                                    </thead>
                                    <tbody id="priceTableBody">
                                        <tr>
                                            <td colspan="5">
                                                <div class="flex flex-col items-center justify-center py-12 text-center">
                                                    <i class="ki-filled ki-chart-line text-4xl text-secondary-foreground/30 mb-3"></i>
                                                    <p class="text-sm text-secondary-foreground">Selecione um produto para ver o histórico.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- MOBILE (< lg): cards --}}
                        <div id="priceCardsBody" class="kt-card-content lg:hidden grid gap-3 p-5">
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <i class="ki-filled ki-chart-line text-4xl text-secondary-foreground/30 mb-3"></i>
                                <p class="text-sm text-secondary-foreground">Selecione um produto para ver o histórico.</p>
                            </div>
                        </div>
                    </div>

                </div>

                <div id="historyEmptyState" class="hidden kt-card">
                    <div class="kt-card-content py-14 text-center">
                        <i class="ki-filled ki-basket text-4xl text-secondary-foreground/30 mb-3 block"></i>
                        <p class="text-sm text-secondary-foreground">Você ainda não comprou este produto.</p>
                    </div>
                </div>

                {{-- COMPARATIVO ENTRE CIDADES/MERCADOS --}}
                <div class="flex items-center gap-2 mt-2">
                    <button type="button" id="btnBackToCities" class="kt-btn kt-btn-sm kt-btn-outline hidden">
                        <i class="ki-filled ki-black-left-line"></i>
                        Voltar para cidades
                    </button>
                    <h2 class="text-base font-semibold text-foreground" id="resultsTitle">Comparativo por Cidade</h2>
                </div>

                <div class="kt-card">
                    <div class="kt-card-content pt-5">
                        <div id="priceComparisonChart" class="h-[320px]"></div>
                    </div>
                </div>

                <div class="kt-card">
                    <div class="kt-card-content p-0">
                        <div class="kt-scrollable-x-auto">
                            <table class="kt-table table-auto kt-table-border w-full">
                                <thead>
                                    <tr id="resultsTableHead"></tr>
                                </thead>
                                <tbody id="resultsTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>

    @include('product-alias._alias-modal')

@endsection

@push('scripts')
<script>
    window.pageConfig = Object.assign(window.pageConfig || {}, {
        searchUrl: '{{ route("prices.search") }}',
        historyUrl: '{{ route("prices.history") }}',
        byCityUrl: '{{ route("prices.by-city") }}',
        byIssuerUrl: '{{ route("prices.by-issuer") }}',
        productAliasStoreUrl: '{{ route("product-aliases.store") }}',
        productAliasMergeUrl: '{{ route("product-aliases.merge") }}',
        productAliasDismissUrl: '{{ route("product-aliases.dismiss") }}',
        productAliasSuggestionsUrl: '{{ route("product-aliases.suggestions") }}',
        productAliasAiSuggestUrl: '{{ route("product-aliases.ai-suggest-name") }}',
    });
</script>
@endpush
