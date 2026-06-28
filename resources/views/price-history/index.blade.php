@extends('layout.main')
@section('page-module', 'price-history')

@section('content')

    {{-- PAGE HEADER --}}
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Histórico de Preços</h1>
                <div class="flex items-center gap-2 text-sm font-normal text-secondary-foreground">
                    Acompanhe a evolução dos preços
                </div>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed">
        <div class="grid gap-5 lg:gap-7.5">

            {{-- BUSCA --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">Buscar Produto</h3>
                </div>
                <div class="kt-card-content pb-5">
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
            </div>

            {{-- DETALHE DO PRODUTO --}}
            <div id="productDetail" style="display:none;" class="grid gap-5 lg:gap-7.5">

                {{-- Visão Geral --}}
                <div class="kt-card">
                    <div class="kt-card-header">
                        <h3 class="kt-card-title" id="productTitle"></h3>
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
                        <div id="priceChart" style="height: 220px;"></div>
                    </div>
                </div>

                {{-- Todas as Compras --}}
                <div class="kt-card kt-card-grid">
                    <div class="kt-card-header">
                        <h3 class="kt-card-title">Histórico</h3>
                        <span id="entryCount" class="text-xs text-secondary-foreground"></span>
                    </div>
                    <div class="kt-card-table">
                        <div class="kt-scrollable-x-auto">
                            <table class="kt-table kt-table-border table-fixed">
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
                </div>

            </div>

        </div>
    </div>

    @push('scripts')
    <script>
        window.pageConfig = Object.assign(window.pageConfig || {}, {
            searchUrl: '{{ route("price-history.search") }}',
            showUrl: '{{ route("price-history.show") }}',
        });
    </script>
    @endpush

@endsection
