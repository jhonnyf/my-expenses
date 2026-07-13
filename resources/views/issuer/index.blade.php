@extends('layout.main')
@section('page-module', 'issuer-favorite,issuer-list')

@section('content')

    {{-- PAGE HEADER --}}
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Emissores</h1>
                <div class="flex items-center gap-2 text-sm font-normal text-secondary-foreground">
                    {{ $records->total() }} {{ $records->total() == 1 ? 'emissor encontrado' : 'emissores encontrados' }}
                </div>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed">
        <div class="grid gap-5 lg:gap-7.5">

            <div class="grid grid-cols-2 lg:grid-cols-3 gap-5 lg:gap-7.5">

                <div class="kt-card flex-row items-center gap-4 p-5">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-primary/10 shrink-0">
                        <i class="ki-filled ki-shop text-primary text-xl"></i>
                    </div>
                    <div class="flex flex-col gap-0.5 min-w-0">
                        <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums">{{ $records->total() }}</span>
                        <span class="text-xs font-normal text-secondary-foreground">Emissores</span>
                    </div>
                </div>

                <div class="kt-card flex-row items-center gap-4 p-5">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-yellow-500/10 shrink-0">
                        <i class="ki-filled ki-star text-yellow-500 text-xl"></i>
                    </div>
                    <div class="flex flex-col gap-0.5 min-w-0">
                        <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums">{{ $favoriteIds->count() }}</span>
                        <span class="text-xs font-normal text-secondary-foreground">Favoritos</span>
                    </div>
                </div>

                <div class="kt-card flex-row items-center gap-4 p-5">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-green-500/10 shrink-0">
                        <i class="ki-filled ki-dollar text-green-600 text-xl"></i>
                    </div>
                    <div class="flex flex-col gap-0.5 min-w-0">
                        <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums truncate">
                            R$ {{ number_format($totalSpent, 2, ',', '.') }}
                        </span>
                        <span class="text-xs font-normal text-secondary-foreground">Total Gasto</span>
                    </div>
                </div>

            </div>

            <div class="kt-card kt-card-grid min-w-full">

                <div class="kt-card-header flex-wrap gap-2">
                    <div class="flex items-center gap-2.5">
                        <h3 class="kt-card-title">Lista de Emissores</h3>
                        <span id="issuerFavoritesBadge" class="kt-badge kt-badge-warning kt-badge-outline kt-badge-sm {{ $favoriteIds->isEmpty() ? 'hidden' : '' }}">
                            <i class="ki-filled ki-star text-2xs"></i>
                            <span id="issuerFavoritesCount">{{ $favoriteIds->count() }}</span>
                            <span id="issuerFavoritesLabel">{{ $favoriteIds->count() == 1 ? 'favorito' : 'favoritos' }}</span>
                        </span>
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="kt-input max-w-56">
                            <i class="ki-filled ki-magnifier"></i>
                            <input type="text" id="issuerSearchInput" placeholder="Buscar emissor..." autocomplete="off" />
                        </label>
                    </div>
                </div>

                <div class="kt-card-table">
                    <div class="kt-scrollable-x-auto">
                        <table class="kt-table kt-table-border table-fixed" id="issuersTable">
                            <thead>
                                <tr>
                                    <th class="w-[70px]"></th>
                                    <th class="min-w-[260px]">Emissor</th>
                                    <th class="min-w-[150px]">CNPJ</th>
                                    <th class="min-w-[180px]">Localização</th>
                                    <th class="min-w-[90px] text-center">Compras</th>
                                    <th class="min-w-[130px] text-end">Total Gasto</th>
                                    <th class="w-[80px] text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($records->items() as $item)
                                    @php $isFav = $favoriteIds->contains($item->id); @endphp
                                    <tr class="issuer-row transition-colors duration-150 hover:bg-accent/40 {{ $isFav ? 'bg-yellow-500/5' : '' }}">
                                        <td class="text-center py-2.5 {{ $isFav ? 'shadow-[inset_3px_0_0_0_#eab308]' : '' }}">
                                            <button
                                                data-favorite-id="{{ $item->id }}"
                                                title="{{ $isFav ? 'Remover dos favoritos' : 'Adicionar aos favoritos' }}"
                                                class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm favorite-btn transition-transform duration-200 hover:scale-110">
                                                <i class="ki-filled ki-star text-base transition-colors duration-200 {{ $isFav ? 'text-yellow-500' : 'hover:text-yellow-500' }}"></i>
                                            </button>
                                        </td>
                                        <td class="py-2.5">
                                            <div class="flex items-center gap-3">
                                                <div class="issuer-avatar flex items-center justify-center size-9 rounded-lg shrink-0 font-semibold text-sm uppercase transition-all duration-200
                                                    {{ $isFav ? 'bg-yellow-500/10 text-yellow-600 ring-2 ring-yellow-400/40' : 'bg-primary/10 text-primary' }}">
                                                    {{ strtoupper(substr($item->name, 0, 2)) }}
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="text-sm font-semibold text-foreground truncate issuer-name">{{ $item->name }}</p>
                                                    @if($item->neighborhood)
                                                        <p class="text-xs text-secondary-foreground truncate">{{ $item->neighborhood }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-2.5">
                                            <span class="text-sm text-foreground font-mono">
                                                {{ preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $item->cnpj) }}
                                            </span>
                                        </td>
                                        <td class="py-2.5">
                                            @if($item->city || $item->state)
                                                <div class="flex items-center gap-1.5">
                                                    <i class="ki-filled ki-geolocation text-sm text-muted-foreground shrink-0"></i>
                                                    <span class="text-sm text-foreground truncate">
                                                        {{ implode(' — ', array_filter([$item->city, $item->state])) }}
                                                    </span>
                                                </div>
                                            @else
                                                <span class="text-sm text-secondary-foreground">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center py-2.5">
                                            @if($item->purchase_count > 0)
                                                <span class="kt-badge kt-badge-secondary kt-badge-outline kt-badge-sm tabular-nums">{{ $item->purchase_count }}</span>
                                            @else
                                                <span class="text-sm text-secondary-foreground">—</span>
                                            @endif
                                        </td>
                                        <td class="text-end py-2.5">
                                            @if($item->total_spent)
                                                <span class="text-sm font-semibold font-mono text-foreground tabular-nums">R$ {{ number_format($item->total_spent, 2, ',', '.') }}</span>
                                            @else
                                                <span class="text-sm text-secondary-foreground">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center py-2.5">
                                            <a href="{{ route('issuers.detail', ['id' => $item->id]) }}" class="kt-btn kt-btn-sm kt-btn-ghost kt-btn-icon transition-transform duration-200 hover:scale-110" title="Ver detalhes">
                                                <i class="ki-filled ki-eye text-base"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7">
                                            <div class="flex flex-col items-center justify-center py-16 text-center">
                                                <i class="ki-filled ki-shop text-5xl text-secondary-foreground/30 mb-4"></i>
                                                <p class="text-sm font-medium text-foreground mb-1">Nenhum emissor encontrado</p>
                                                <p class="text-xs text-secondary-foreground">Importe uma NF-e para registrar emissores.</p>
                                                <a href="{{ route('my-purchases.upload.form') }}" class="kt-btn kt-btn-primary kt-btn-sm mt-4">
                                                    <i class="ki-filled ki-file-up"></i>
                                                    Importar NF-e
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                                @if($records->isNotEmpty())
                                    <tr id="issuerNoSearchResults" class="hidden">
                                        <td colspan="7">
                                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                                <i class="ki-filled ki-magnifier text-4xl text-secondary-foreground/30 mb-3"></i>
                                                <p class="text-sm text-secondary-foreground">Nenhum emissor corresponde à busca.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($records->hasPages())
                    <div class="kt-card-footer justify-center md:justify-between flex-col md:flex-row gap-3 text-secondary-foreground text-sm font-medium">
                        <span class="order-2 md:order-1">
                            Exibindo {{ $records->firstItem() }}–{{ $records->lastItem() }} de {{ $records->total() }} emissores
                        </span>
                        <div class="flex items-center gap-2 order-1 md:order-2">
                            {{ $records->links() }}
                        </div>
                    </div>
                @endif

            </div>

        </div>
    </div>

@endsection

@push('scripts')
    <script>
        window.pageConfig = Object.assign(window.pageConfig || {}, {
            issuerBaseUrl: '{{ url("issuers") }}',
        });
    </script>
@endpush
