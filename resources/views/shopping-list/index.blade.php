@extends('layout.main')

@section('content')
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Lista de Compras</h1>
                <p class="text-sm text-secondary-foreground">
                    Busque produtos e monte sua lista com os melhores preços
                </p>
            </div>
            <div class="flex items-center gap-2.5">
                <button onclick="newList()" class="kt-btn kt-btn-outline" id="btnNew" style="display:none;">
                    <i class="ki-filled ki-plus"></i> Nova Lista
                </button>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed space-y-5 pb-10">

        <div class="grid lg:grid-cols-3 gap-5">

            {{-- Coluna principal --}}
            <div class="lg:col-span-2 space-y-5">

                {{-- Nome da lista --}}
                <div class="kt-card" id="listNameCard" style="display:none;">
                    <div class="kt-card-content py-4 px-5">
                        <div class="flex items-center gap-3">
                            <div class="flex-1">
                                <label class="text-xs font-medium text-secondary-foreground">Nome da lista</label>
                                <input type="text"
                                       id="listName"
                                       class="kt-input mt-1 w-full"
                                       placeholder="Lista de compras {{ now()->format('d/m/Y') }}" />
                            </div>
                            <button onclick="saveName()" class="kt-btn kt-btn-sm kt-btn-outline mt-4">
                                <i class="ki-filled ki-check"></i>
                            </button>
                        </div>
                        <p class="text-xs text-secondary-foreground mt-2">
                            <i class="ki-filled ki-information-2 text-primary"></i>
                            Os itens são salvos automaticamente
                        </p>
                    </div>
                </div>

                {{-- Busca --}}
                <div class="kt-card">
                    <div class="kt-card-content py-5 px-5">
                        <div class="flex flex-col gap-3">
                            <label class="text-sm font-medium text-foreground">Buscar produto</label>
                            <div class="relative">
                                <label class="kt-input w-full">
                                    <i class="ki-filled ki-magnifier"></i>
                                    <input type="text"
                                           id="searchInput"
                                           placeholder="Digite o nome do produto (mín. 2 caracteres)"
                                           autocomplete="off" />
                                </label>
                            </div>

                            {{-- Resultados --}}
                            <div id="searchResults" class="hidden">
                                <div class="border border-border rounded-lg overflow-hidden mt-1">
                                    <div class="bg-accent/40 px-4 py-2 text-xs font-semibold text-secondary-foreground uppercase tracking-wide">
                                        Resultados — ordenados por menor preço
                                    </div>
                                    <div id="resultsList" class="divide-y divide-border max-h-80 overflow-y-auto"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Lista agrupada por Issuer --}}
                <div id="shoppingListContainer" style="display:none;">
                    {{-- Pendentes --}}
                    <div class="flex items-center gap-2 mb-4">
                        <i class="ki-filled ki-basket text-primary text-lg"></i>
                        <h2 class="text-lg font-semibold text-foreground">A Comprar</h2>
                        <span id="totalPending" class="kt-badge kt-badge-primary kt-badge-sm ms-2">0</span>
                    </div>

                    <div id="pendingList" class="grid gap-5"></div>

                    <div id="emptyPending" class="kt-card mt-2" style="display:none;">
                        <div class="kt-card-content py-6 text-center text-secondary-foreground text-sm">
                            Todos os itens foram comprados!
                        </div>
                    </div>

                    {{-- Comprados --}}
                    <div id="purchasedSection" class="mt-8" style="display:none;">
                        <div class="flex items-center gap-2 mb-4">
                            <i class="ki-filled ki-check-circle text-green-500 text-lg"></i>
                            <h2 class="text-lg font-semibold text-foreground">Comprados</h2>
                            <span id="totalPurchased" class="kt-badge kt-badge-success kt-badge-sm ms-2">0</span>
                        </div>

                        <div id="purchasedList" class="grid gap-5"></div>
                    </div>

                    {{-- Resumo --}}
                    <div class="kt-card mt-5">
                        <div class="kt-card-content py-4 px-5">
                            <div class="flex justify-between items-center">
                                <span class="font-semibold text-foreground">Total estimado</span>
                                <span id="totalPrice" class="text-xl font-bold text-primary">R$ 0,00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Coluna lateral: Listas salvas --}}
            <div class="space-y-5">
                <div class="kt-card">
                    <div class="kt-card-header">
                        <h3 class="kt-card-title">
                            <i class="ki-filled ki-folder text-primary me-1"></i> Listas Salvas
                        </h3>
                    </div>
                    <div class="kt-card-content pb-4">
                        <div id="savedLists" class="divide-y divide-border">
                            @forelse($lists as $list)
                                <div class="flex items-center justify-between py-2.5 px-1 group" id="saved-list-{{ $list->id }}">
                                    <button data-load-list="{{ $list->id }}" class="flex-1 text-left min-w-0">
                                        <p class="text-sm font-medium text-foreground truncate group-hover:text-primary transition-colors">
                                            {{ $list->name }}
                                        </p>
                                        <p class="text-xs text-secondary-foreground">
                                            {{ $list->items_count }} {{ $list->items_count === 1 ? 'item' : 'itens' }}
                                            &middot; {{ $list->updated_at->format('d/m/Y') }}
                                        </p>
                                    </button>
                                    <button data-delete-list="{{ $list->id }}"
                                            class="text-muted-foreground hover:text-destructive transition-colors ms-2 opacity-0 group-hover:opacity-100">
                                        <i class="ki-filled ki-trash text-sm"></i>
                                    </button>
                                </div>
                            @empty
                                <p class="text-sm text-secondary-foreground py-3" id="noListsMsg">Nenhuma lista salva.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <script>
        window.pageConfig = {
            baseUrl: '{{ url("shopping-list") }}',
            searchUrl: '{{ route("shopping-list.search") }}',
        };
    </script>
    @section('page-module', 'shopping-list')
@endsection
