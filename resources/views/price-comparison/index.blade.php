@extends('layout.main')
@section('page-module', 'price-comparison')

@section('content')

    {{-- PAGE HEADER --}}
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Comparativo de Preços</h1>
                <div class="flex items-center gap-2 text-sm font-normal text-secondary-foreground">
                    Descubra em qual cidade e mercado um produto está mais barato
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
                                    Produtos encontrados — escolha um pra comparar
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

            {{-- RESULTADOS --}}
            <div id="resultsWrapper" class="hidden">

                <div class="flex items-center gap-2 mb-4">
                    <button type="button" id="btnBackToCities" class="kt-btn kt-btn-sm kt-btn-outline hidden">
                        <i class="ki-filled ki-black-left-line"></i>
                        Voltar para cidades
                    </button>
                    <h3 class="text-base font-semibold text-foreground" id="resultsTitle">Ranking por cidade</h3>
                </div>

                <div class="kt-card mb-5 lg:mb-7.5">
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

            <div id="emptyState" class="kt-card">
                <div class="kt-card-content py-14 text-center">
                    <i class="ki-filled ki-chart-line-star text-4xl text-secondary-foreground/30 mb-3 block"></i>
                    <p class="text-sm text-secondary-foreground">Busque e selecione um produto para comparar preços entre cidades e mercados.</p>
                </div>
            </div>

        </div>
    </div>

@endsection

@push('scripts')
<script>
    window.pageConfig = Object.assign(window.pageConfig || {}, {
        searchProductsUrl: '{{ route("price-comparison.search-products") }}',
        byCityUrl: '{{ route("price-comparison.by-city") }}',
        byIssuerUrl: '{{ route("price-comparison.by-issuer") }}',
    });
</script>
@endpush
