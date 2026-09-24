@extends('layout.main')
@section('page-module', 'category-uncategorized')

@section('content')

    @php $isPro = auth()->user()->isPro(); @endphp

    {{-- PAGE HEADER --}}
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Itens sem categoria</h1>
                <div class="flex items-center gap-2 text-sm font-normal text-secondary-foreground">
                    <a href="{{ route('categories.index', $filters) }}" class="hover:text-primary transition-colors">Categorias</a>
                    <i class="ki-filled ki-right text-xs"></i>
                    <span>Revisar</span>
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <a href="{{ route('categories.index', $filters) }}" class="kt-btn kt-btn-outline">
                    <i class="ki-filled ki-arrow-left"></i> Voltar
                </a>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed">
        <div class="grid gap-5 lg:gap-7.5">

            @include('partials._period-filter', ['periodFilterAction' => route('categories.uncategorized'), 'filters' => $filters])

            <div class="kt-card kt-card-grid min-w-full">
                <div class="kt-card-header flex-wrap gap-3 py-3 lg:py-0">
                    <h3 class="kt-card-title">
                        <span id="uncategorizedRemaining">{{ number_format($summary['count']) }}</span>
                        {{ $summary['count'] == 1 ? 'item' : 'itens' }} · R$ {{ number_format($summary['total'], 2, ',', '.') }}
                    </h3>
                    <p class="text-xs text-secondary-foreground">
                        Escolha a categoria de cada item: o sistema aprende e aplica o mesmo a compras futuras.
                        @if($isPro) Use <i class="ki-filled ki-artificial-intelligence"></i> para a IA sugerir. @endif
                    </p>
                </div>

                <div id="uncategorizedEmpty" class="{{ $items->isEmpty() ? '' : 'hidden' }}">
                    <div class="flex flex-col items-center justify-center py-16 text-center">
                        <i class="ki-filled ki-check-circle text-5xl text-green-600/40 mb-4"></i>
                        <p class="text-sm font-medium text-foreground mb-1">Nenhum item sem categoria</p>
                        <p class="text-xs text-secondary-foreground">Tudo neste período já está categorizado.</p>
                        <a href="{{ route('categories.index', $filters) }}" class="kt-btn kt-btn-primary kt-btn-sm mt-4">Voltar às categorias</a>
                    </div>
                </div>

                @if($items->isNotEmpty())
                    <div class="kt-card-content p-0 flex flex-col" id="uncategorizedList">
                        @foreach($items as $item)
                            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 px-5 py-3 border-b border-border last:border-b-0"
                                 data-item-row="{{ $item->id }}">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-foreground leading-snug line-clamp-2" title="{{ $item->description }}">
                                        {{ $item->canonical_name ?? $item->description }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-secondary-foreground">
                                        {{ $item->issuer_name ?? '—' }} · {{ \Carbon\Carbon::parse($item->invoice_issued_at)->format('d/m/Y') }}
                                        · <span class="tabular-nums">{{ rtrim(rtrim(number_format($item->quantity, 4, ',', '.'), '0'), ',') }} {{ $item->unit }}
                                        × R$ {{ number_format($item->unit_price, 2, ',', '.') }}</span>
                                        · <a href="{{ route('my-purchases.detail', $item->invoice_id) }}" class="text-primary hover:underline">Ver nota</a>
                                    </p>
                                </div>
                                <div class="flex items-center gap-4 shrink-0">
                                    <span class="text-sm font-semibold font-mono text-foreground tabular-nums">R$ {{ number_format($item->total_price, 2, ',', '.') }}</span>
                                    <div class="item-category-cell w-full lg:w-64">
                                        <div class="flex items-center gap-1.5">
                                            <span class="category-dot size-2 rounded-full shrink-0" style="background-color: #94a3b8"></span>
                                            <select data-action="assign-category" data-item-id="{{ $item->id }}"
                                                    data-kt-select="true" data-kt-select-dropdown-strategy="fixed" data-kt-select-placeholder="Sem categoria"
                                                    class="text-xs bg-accent border border-border rounded-md px-2 py-1 cursor-pointer w-full focus:outline-none focus:ring-1 focus:ring-primary">
                                                <option value="" data-color="#94a3b8" selected>Sem categoria</option>
                                                @foreach($categories as $cat)
                                                    <option value="{{ $cat->id }}" data-color="{{ $cat->color }}">{{ $cat->name }}</option>
                                                @endforeach
                                            </select>
                                            @if($isPro)
                                                <button type="button" data-action="suggest-category-ai" data-item-id="{{ $item->id }}"
                                                        class="kt-btn kt-btn-ghost kt-btn-xs shrink-0" title="Sugerir categoria com IA">
                                                    <i class="ki-filled ki-artificial-intelligence"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($items->hasPages())
                    <div class="kt-card-footer justify-center md:justify-between flex-col md:flex-row gap-3 text-secondary-foreground text-sm font-medium">
                        <span class="order-2 md:order-1">
                            Exibindo {{ $items->firstItem() }}–{{ $items->lastItem() }} de {{ $items->total() }} itens
                        </span>
                        <div class="flex items-center gap-2 order-1 md:order-2">
                            {{ $items->links('vendor.pagination.metronic') }}
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
        assignCategoryUrl: '{{ route("categories.assign-item") }}',
        suggestItemCategoryUrl: '{{ $isPro ? route("categories.suggest-item-category") : "" }}',
    });
</script>
@endpush
