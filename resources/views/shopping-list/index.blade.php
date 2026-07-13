@extends('layout.main')
@section('page-module', 'shopping-list')

@section('content')

    {{-- PAGE HEADER --}}
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Lista de Compras</h1>
                <div class="flex items-center gap-2 text-sm font-normal text-secondary-foreground">
                    {{ $lists->count() }} {{ $lists->count() == 1 ? 'lista salva' : 'listas salvas' }} &middot; Monte sua lista com os melhores preços
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <button class="kt-btn kt-btn-primary" id="btnNew" data-action="new-list" style="display:none;">
                    <i class="ki-filled ki-plus"></i>
                    Nova Lista
                </button>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed">
        <div class="grid gap-5 lg:gap-7.5">

            <div class="grid lg:grid-cols-3 gap-5 lg:gap-7.5">

                {{-- Coluna principal --}}
                <div class="lg:col-span-2 flex flex-col gap-5">

                    {{-- Nome da lista --}}
                    <div class="kt-card hidden" id="listNameCard">
                        <div class="kt-card-content py-4 px-5">
                            <div class="flex items-center gap-3">
                                <div class="flex-1 min-w-0">
                                    <label class="text-xs font-medium text-secondary-foreground">Nome da lista</label>
                                    <input type="text"
                                           id="listName"
                                           class="kt-input mt-1.5 w-full"
                                           placeholder="Lista de compras {{ now()->format('d/m/Y') }}" />
                                </div>
                                <button data-action="save-name" class="kt-btn kt-btn-sm kt-btn-outline mt-5 shrink-0">
                                    <i class="ki-filled ki-check"></i>
                                    Salvar
                                </button>
                            </div>
                            <p class="text-xs text-secondary-foreground mt-2">
                                <i class="ki-filled ki-information-2 text-primary me-0.5"></i>
                                Os itens são salvos automaticamente
                            </p>
                        </div>
                    </div>

                    {{-- Busca de produto --}}
                    <div class="kt-card">
                        <div class="kt-card-header">
                            <h3 class="kt-card-title">Buscar Produto</h3>
                        </div>
                        <div class="kt-card-content pb-5">
                            <label class="kt-input w-full">
                                <i class="ki-filled ki-magnifier"></i>
                                <input type="text"
                                       id="searchInput"
                                       placeholder="Digite o nome do produto (mín. 2 caracteres)"
                                       autocomplete="off" />
                            </label>
                            <div id="searchResults" class="hidden mt-3">
                                <div class="border border-border rounded-lg overflow-hidden">
                                    <div class="bg-accent/40 px-4 py-2 text-xs font-semibold text-secondary-foreground uppercase tracking-wide">
                                        Resultados — menor preço
                                    </div>
                                    <div id="resultsList" class="divide-y divide-border max-h-80 overflow-y-auto"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Container da lista --}}
                    <div id="shoppingListContainer" style="display:none;" class="flex flex-col gap-5">

                        {{-- A Comprar --}}
                        <div>
                            <div class="flex items-center gap-2 mb-4">
                                <i class="ki-filled ki-basket text-primary text-lg"></i>
                                <h2 class="text-lg font-semibold text-foreground">A Comprar</h2>
                                <span id="totalPending" class="kt-badge kt-badge-primary kt-badge-sm ms-1">0</span>
                            </div>
                            <div id="pendingList" class="grid gap-5"></div>
                            <div id="emptyPending" class="hidden kt-card mt-2">
                                <div class="kt-card-content py-10 text-center">
                                    <i class="ki-filled ki-check-circle text-4xl text-success/40 mb-3 block"></i>
                                    <p class="text-sm text-secondary-foreground">Todos os itens foram comprados!</p>
                                </div>
                            </div>
                        </div>

                        {{-- Comprados --}}
                        <div id="purchasedSection" style="display:none;">
                            <div class="flex items-center gap-2 mb-4">
                                <i class="ki-filled ki-check-circle text-green-500 text-lg"></i>
                                <h2 class="text-lg font-semibold text-foreground">Comprados</h2>
                                <span id="totalPurchased" class="kt-badge kt-badge-success kt-badge-sm ms-1">0</span>
                            </div>
                            <div id="purchasedList" class="grid gap-5"></div>
                        </div>

                        {{-- Resumo --}}
                        <div class="kt-card">
                            <div class="kt-card-content py-4 px-5">
                                <div class="flex items-center justify-between gap-3 mb-3">
                                    <span class="text-sm font-semibold text-foreground">Total estimado</span>
                                    <span id="totalPrice" class="text-xl font-bold text-primary tabular-nums">R$ 0,00</span>
                                </div>
                                <div class="kt-progress h-1.5">
                                    <div id="summaryProgress" class="kt-progress-indicator" style="width: 0%"></div>
                                </div>
                                <div class="flex items-center justify-between mt-2">
                                    <span id="summaryProgressLabel" class="text-xs text-secondary-foreground">0 de 0 itens comprados</span>
                                    <span id="summaryProgressPct" class="text-xs font-semibold text-secondary-foreground tabular-nums">0%</span>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

                {{-- Sidebar: Listas salvas --}}
                <div class="lg:col-span-1">
                    <div class="kt-card">
                        <div class="kt-card-header">
                            <h3 class="kt-card-title">Listas Salvas</h3>
                            <div class="kt-card-toolbar">
                                <button data-action="new-list" class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm" title="Nova lista">
                                    <i class="ki-filled ki-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="kt-card-content pb-2">
                            <div id="savedLists" class="divide-y divide-border">
                                @forelse($lists as $list)
                                    <div class="flex items-center gap-3 py-3 px-1 group rounded-lg transition-colors hover:bg-accent/40" id="saved-list-{{ $list->id }}">
                                        <div class="flex items-center justify-center size-9 rounded-lg bg-primary/10 text-primary shrink-0">
                                            <i class="ki-filled ki-basket text-sm"></i>
                                        </div>
                                        <button data-load-list="{{ $list->id }}" class="flex-1 text-left min-w-0">
                                            <p class="text-sm font-semibold text-foreground truncate group-hover:text-primary transition-colors">
                                                {{ $list->name }}
                                            </p>
                                            <p class="text-xs text-secondary-foreground">
                                                {{ $list->items_count }} {{ $list->items_count === 1 ? 'item' : 'itens' }}
                                                &middot; R$ {{ number_format($list->items_total ?? 0, 2, ',', '.') }}
                                                &middot; {{ $list->updated_at->format('d/m/Y') }}
                                            </p>
                                        </button>
                                        <button data-delete-list="{{ $list->id }}"
                                                class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm opacity-0 group-hover:opacity-100 text-muted-foreground hover:text-destructive shrink-0">
                                            <i class="ki-filled ki-trash text-sm"></i>
                                        </button>
                                    </div>
                                @empty
                                    <div class="flex flex-col items-center justify-center py-10 text-center" id="noListsMsg">
                                        <i class="ki-filled ki-basket text-4xl text-secondary-foreground/30 mb-3"></i>
                                        <p class="text-sm text-secondary-foreground">Nenhuma lista salva.</p>
                                        <p class="text-xs text-secondary-foreground/70 mt-1">Busque um produto para começar.</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>

@endsection

@push('scripts')
<script>
    window.pageConfig = Object.assign(window.pageConfig || {}, {
        baseUrl: '{{ url("shopping-list") }}',
        searchUrl: '{{ route("shopping-list.search") }}',
    });
</script>
@endpush
