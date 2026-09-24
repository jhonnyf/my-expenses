@extends('layout.main')
@section('page-module', 'report,product-alias')

@section('content')

    @php
        // Filtros ativos como query string (links de exportar/paginar e campos que sobrevivem ao filtro de período).
        $activeFilters = array_filter($filters, fn ($value) => $value !== null && $value !== '');
        $exportQuery = $activeFilters;
        $periodExtraFields = array_diff_key($activeFilters, ['start_date' => 1, 'end_date' => 1]);
        $hasListFilters = ($filters['issuer_id'] ?? null) || ($filters['category_id'] ?? null) || ($filters['q'] ?? '') !== '';
        $proBadge = '<span class="kt-badge kt-badge-light kt-badge-warning kt-badge-sm">Pro</span>';
    @endphp

    {{-- PAGE HEADER --}}
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-tight text-mono">Relatórios</h1>
                <div class="flex items-center gap-2 text-sm font-normal text-secondary-foreground">
                    Gastos por período, emissor, categoria e produto
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                @if($isPro)
                    <a href="{{ route('reports.pdf', $exportQuery) }}" class="kt-btn kt-btn-outline">
                        <i class="ki-filled ki-document"></i> Exportar PDF
                    </a>
                    <a href="{{ route('reports.csv', $exportQuery) }}" class="kt-btn kt-btn-outline">
                        <i class="ki-filled ki-file-down"></i> Exportar CSV
                    </a>
                    <button type="button" class="kt-btn kt-btn-outline" data-kt-modal-toggle="#reportEmailModal">
                        <i class="ki-filled ki-sms"></i> E-mail
                    </button>
                @else
                    @foreach([['ki-document', 'Exportar PDF'], ['ki-file-down', 'Exportar CSV'], ['ki-sms', 'E-mail']] as [$icon, $label])
                        <a href="{{ route('subscription.upgrade') }}" class="kt-btn kt-btn-outline" title="Recurso do plano Pro">
                            <i class="ki-filled {{ $icon }}"></i> {{ $label }} {!! $proBadge !!}
                        </a>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    <div class="kt-container-fixed">
        <div class="grid gap-5 lg:gap-7.5">

            <div id="pageFlash" role="status" class="hidden items-center gap-3 rounded-lg border border-green-500/30 bg-green-500/10 px-4 py-3">
                <i class="ki-filled ki-check-circle text-green-600 text-lg shrink-0"></i>
                <span class="text-sm text-green-600 font-medium" data-flash-text></span>
            </div>

            @include('partials._period-filter', [
                'periodFilterAction' => route('reports.index'),
                'filters' => $filters,
                'extraFields' => $periodExtraFields,
            ])

            {{-- FILTROS --}}
            <div class="kt-card">
                <form id="reportFilterForm" method="GET" action="{{ route('reports.index') }}"
                      class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 p-4">
                    <input type="hidden" name="start_date" value="{{ $filters['start_date'] }}" />
                    <input type="hidden" name="end_date" value="{{ $filters['end_date'] }}" />

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2 w-full sm:w-auto">
                            <label class="kt-input w-full sm:w-64">
                                <i class="ki-filled ki-magnifier"></i>
                                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar produto..." autocomplete="off" />
                            </label>
                            <button type="submit" class="kt-btn kt-btn-mono shrink-0">Buscar</button>
                        </div>
                        <select name="issuer_id" class="kt-select w-full sm:w-52" aria-label="Filtrar por emissor">
                            <option value="">Todos os emissores</option>
                            @foreach($issuers as $issuer)
                                <option value="{{ $issuer->id }}" @selected((string) ($filters['issuer_id'] ?? '') === (string) $issuer->id)>{{ $issuer->display_name }}</option>
                            @endforeach
                        </select>
                        <select name="category_id" class="kt-select w-full sm:w-52" aria-label="Filtrar por categoria">
                            <option value="">Todas as categorias</option>
                            <option value="none" @selected(($filters['category_id'] ?? null) === 'none')>Sem categoria</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        @if($hasListFilters)
                            <a href="{{ route('reports.index', array_filter(['start_date' => $filters['start_date'], 'end_date' => $filters['end_date']])) }}"
                               class="kt-btn kt-btn-ghost kt-btn-sm shrink-0">
                                <i class="ki-filled ki-cross"></i> Limpar
                            </a>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <label for="reportSortSelect" class="text-sm text-secondary-foreground shrink-0">Ordenar itens por</label>
                        <select name="sort" id="reportSortSelect" class="kt-select w-full sm:w-48">
                            @foreach(['recent' => 'Mais recentes', 'oldest' => 'Mais antigos', 'highest' => 'Maior valor', 'lowest' => 'Menor valor', 'name' => 'Produto (A–Z)'] as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['sort'] ?? 'recent') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>

            @isset($items)

                {{-- MINI STAT CARDS --}}
                <style>
                    .channel-stats-bg {
                        background-image: url('{{ asset('assets/media/images/2600x1600/bg-3.png') }}');
                    }
                    .dark .channel-stats-bg {
                        background-image: url('{{ asset('assets/media/images/2600x1600/bg-3-dark.png') }}');
                    }
                </style>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 lg:gap-7.5">

                    <div class="kt-card flex-col justify-between gap-6 bg-cover bg-[right_top_-1.7rem] bg-no-repeat channel-stats-bg">
                        <div class="flex items-center justify-center size-10 mt-4 ms-5 rounded-xl bg-primary/10">
                            <i class="ki-filled ki-dollar text-primary text-xl"></i>
                        </div>
                        <div class="flex flex-col gap-1 pb-4 px-5">
                            <span class="text-2xl font-semibold text-mono tabular-nums truncate">
                                R$ {{ number_format($summary->total_amount ?? 0, 2, ',', '.') }}
                            </span>
                            <span class="text-sm font-normal text-secondary-foreground">
                                {{ $summary->is_partial ? 'Total gasto (filtro)' : 'Total gasto' }}
                                @if($summary->delta_pct !== null && $summary->delta_pct != 0)
                                    <span class="text-xs tabular-nums {{ $summary->delta_pct > 0 ? 'text-destructive' : 'text-green-600' }}"
                                          title="Comparado ao período anterior, de mesma duração">
                                        · {{ $summary->delta_pct > 0 ? '▲' : '▼' }} {{ number_format(abs($summary->delta_pct), 1, ',', '.') }}%
                                    </span>
                                @endif
                            </span>
                        </div>
                    </div>

                    <div class="kt-card flex-col justify-between gap-6 bg-cover bg-[right_top_-1.7rem] bg-no-repeat channel-stats-bg">
                        <div class="flex items-center justify-center size-10 mt-4 ms-5 rounded-xl bg-violet-500/10">
                            <i class="ki-filled ki-basket text-violet-600 text-xl"></i>
                        </div>
                        <div class="flex flex-col gap-1 pb-4 px-5">
                            <span class="text-2xl font-semibold text-mono tabular-nums">
                                {{ $summary->total_items ?? 0 }}
                            </span>
                            <span class="text-sm font-normal text-secondary-foreground">Itens</span>
                        </div>
                    </div>

                    <div class="kt-card flex-col justify-between gap-6 bg-cover bg-[right_top_-1.7rem] bg-no-repeat channel-stats-bg">
                        <div class="flex items-center justify-center size-10 mt-4 ms-5 rounded-xl bg-green-500/10">
                            <i class="ki-filled ki-document text-green-600 text-xl"></i>
                        </div>
                        <div class="flex flex-col gap-1 pb-4 px-5">
                            <span class="text-2xl font-semibold text-mono tabular-nums">
                                {{ $summary->total_invoices ?? 0 }}
                            </span>
                            <span class="text-sm font-normal text-secondary-foreground">Notas</span>
                        </div>
                    </div>

                    <div class="kt-card flex-col justify-between gap-6 bg-cover bg-[right_top_-1.7rem] bg-no-repeat channel-stats-bg">
                        <div class="flex items-center justify-center size-10 mt-4 ms-5 rounded-xl bg-yellow-500/10">
                            <i class="ki-filled ki-chart text-yellow-600 text-xl"></i>
                        </div>
                        <div class="flex flex-col gap-1 pb-4 px-5">
                            <span class="text-2xl font-semibold text-mono tabular-nums truncate">
                                R$ {{ number_format($summary->average_ticket, 2, ',', '.') }}
                            </span>
                            <span class="text-sm font-normal text-secondary-foreground">{{ $summary->average_label }}</span>
                        </div>
                    </div>

                </div>

                {{-- GASTOS POR CATEGORIA --}}
                @if($categoryBreakdown->isNotEmpty())
                    <div class="kt-card">
                        <div class="kt-card-header">
                            <h3 class="kt-card-title">Gastos por Categoria</h3>
                        </div>
                        <div class="kt-card-content pb-5">
                            <div class="grid lg:grid-cols-2 gap-4 items-center">
                                <div id="reportCategoryChart" class="max-w-[220px] mx-auto lg:max-w-none" style="height: 220px;"></div>
                                <div class="grid gap-2">
                                    @php $catTotal = $categoryBreakdown->sum('total') ?: 1; @endphp
                                    @foreach($categoryBreakdown as $cat)
                                        @php $catPct = ($cat->total / $catTotal) * 100; @endphp
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-0.5 sm:gap-2">
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <span class="size-2.5 rounded-full shrink-0" style="background-color: {{ $cat->category_color }}"></span>
                                                <span class="text-sm text-foreground truncate">{{ $cat->category_name }}</span>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0 ps-5 sm:ps-0">
                                                <span class="text-xs text-secondary-foreground tabular-nums">{{ number_format($catPct, 0) }}%</span>
                                                <span class="text-sm font-semibold text-foreground tabular-nums">R$ {{ number_format($cat->total, 2, ',', '.') }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- EVOLUÇÃO MENSAL E POR EMISSOR --}}
                <div class="grid gap-5 lg:gap-7.5 {{ count($byIssuer) > 0 ? 'lg:grid-cols-2' : '' }}">
                    <div class="kt-card">
                        <div class="kt-card-header">
                            <h3 class="kt-card-title">Evolução mensal</h3>
                            <span class="text-xs text-secondary-foreground">12 meses até {{ \Carbon\Carbon::parse($filters['end_date'])->format('m/Y') }}</span>
                        </div>
                        <div class="kt-card-content pb-4">
                            @if(collect($monthly)->sum('total') > 0)
                                <div id="reportMonthlyChart" style="height: 240px;"></div>
                            @else
                                <p class="text-sm text-secondary-foreground text-center py-10">Sem gastos nos últimos 12 meses com estes filtros.</p>
                            @endif
                        </div>
                    </div>

                    @if(count($byIssuer) > 0)
                        @php $issuerTop = collect($byIssuer)->max('total') ?: 1; @endphp
                        <div class="kt-card">
                            <div class="kt-card-header">
                                <h3 class="kt-card-title">Onde você gasta</h3>
                            </div>
                            <div class="kt-card-content px-5 pb-4 flex flex-col">
                                @foreach($byIssuer as $row)
                                    <div class="py-2.5 border-b border-border last:border-b-0">
                                        <div class="flex items-start justify-between gap-3">
                                            @if($row['issuer_id'])
                                                <a href="{{ route('issuers.detail', ['id' => $row['issuer_id']]) }}"
                                                   class="text-sm font-medium text-foreground hover:text-primary leading-snug line-clamp-1 min-w-0" title="{{ $row['name'] }}">{{ $row['name'] }}</a>
                                            @else
                                                <span class="text-sm font-medium text-foreground leading-snug line-clamp-1 min-w-0">{{ $row['name'] }}</span>
                                            @endif
                                            <span class="text-sm font-semibold font-mono text-foreground tabular-nums shrink-0">R$ {{ number_format($row['total'], 2, ',', '.') }}</span>
                                        </div>
                                        <div class="kt-progress h-1 mt-1.5">
                                            <div class="kt-progress-indicator" style="width: {{ min($row['total'] / $issuerTop * 100, 100) }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                {{-- AVISO: categoria alterada na tabela --}}
                <div id="reportStaleNotice" class="hidden items-center justify-between gap-3 rounded-lg border border-yellow-500/30 bg-yellow-500/10 px-4 py-3 text-sm text-yellow-700">
                    <span>Categoria atualizada. Os totais e o gráfico só refletem a mudança depois de atualizar.</span>
                    <a href="{{ route('reports.index', $activeFilters) }}" class="kt-btn kt-btn-outline kt-btn-sm shrink-0">Atualizar</a>
                </div>

                {{-- ITENS DETALHADOS --}}
                <div class="kt-card kt-card-grid">
                    <div class="kt-card-header">
                        <h3 class="kt-card-title">Itens Detalhados</h3>
                        <div class="kt-card-toolbar">
                            <span class="kt-badge kt-badge-primary kt-badge-outline kt-badge-sm">
                                {{ number_format($items->total()) }} {{ $items->total() === 1 ? 'item' : 'itens' }}
                            </span>
                        </div>
                    </div>
                    @if($items->isEmpty())
                        <div class="kt-card-content p-5">
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <i class="ki-filled ki-document text-4xl text-secondary-foreground/30 mb-3"></i>
                                <p class="text-sm font-medium text-foreground">Nenhum item encontrado</p>
                                <p class="text-xs text-secondary-foreground mt-1">Tente ajustar os filtros.</p>
                            </div>
                        </div>
                    @else
                        {{-- DESKTOP (lg+): tabela --}}
                        <div class="kt-card-table hidden lg:block">
                            <div class="kt-scrollable-x-auto">
                                <table class="kt-table kt-table-border table-auto">
                                    <thead>
                                        <tr>
                                            <th class="min-w-[100px]">Data</th>
                                            <th class="min-w-[160px]">Emissor</th>
                                            <th class="min-w-[200px]">Produto</th>
                                            <th class="min-w-[130px]">Categoria</th>
                                            <th class="min-w-[70px] text-end">Qtd</th>
                                            <th class="min-w-[100px] text-end">Preço Unit.</th>
                                            <th class="min-w-[110px] text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($items as $item)
                                            <tr class="transition-colors hover:bg-accent/60">
                                                <td class="text-sm text-secondary-foreground">
                                                    {{ \Carbon\Carbon::parse($item->issued_at)->format('d/m/Y') }}
                                                </td>
                                                <td class="text-sm truncate">{{ $item->issuer_name }}</td>
                                                <td class="text-sm font-medium text-foreground">
                                                    <div class="flex items-center gap-1.5 min-w-0">
                                                        <span class="truncate item-alias-name" data-item-description="{{ $item->raw_description }}">{{ $item->description }}</span>
                                                        <button type="button"
                                                                data-action="edit-product-alias"
                                                                data-kt-modal-toggle="#productAliasModal"
                                                                data-description="{{ $item->raw_description }}"
                                                                data-canonical-name="{{ $item->canonical_name }}"
                                                                class="shrink-0 text-muted-foreground hover:text-primary transition-colors"
                                                                title="Unificar nome do produto">
                                                            <i class="ki-filled ki-pencil text-xs"></i>
                                                        </button>
                                                        <button type="button"
                                                                data-action="favorite-product"
                                                                data-description="{{ $item->description }}"
                                                                data-unit="{{ $item->unit }}"
                                                                class="shrink-0 text-muted-foreground hover:text-destructive transition-colors"
                                                                title="Avisar quando o preço cair">
                                                            <i class="ki-filled ki-heart text-xs"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                                <td class="item-category-cell">
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="category-dot size-2 rounded-full shrink-0" style="background-color: {{ $item->category_color }}"></span>
                                                        <select data-action="assign-category" data-item-id="{{ $item->item_id }}"
                                                                data-kt-select="true" data-kt-select-dropdown-strategy="fixed" data-kt-select-placeholder="Sem categoria"
                                                                class="text-xs bg-accent border border-border rounded-md px-2 py-1 cursor-pointer w-full focus:outline-none focus:ring-1 focus:ring-primary">
                                                            <option value="" data-color="#94a3b8" {{ ! $item->category_id ? 'selected' : '' }}>Sem categoria</option>
                                                            @foreach($categories as $cat)
                                                                <option value="{{ $cat->id }}" data-color="{{ $cat->color }}" {{ $item->category_id == $cat->id ? 'selected' : '' }}>
                                                                    {{ $cat->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        @if(auth()->user()->isPro())
                                                            <button type="button" data-action="suggest-category-ai" data-item-id="{{ $item->item_id }}"
                                                                    class="kt-btn kt-btn-ghost kt-btn-xs shrink-0" title="Sugerir categoria com IA">
                                                                <i class="ki-filled ki-artificial-intelligence"></i>
                                                            </button>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="text-end font-mono text-sm">
                                                    {{ rtrim(rtrim(number_format($item->quantity, 4, ',', '.'), '0'), ',') }}
                                                </td>
                                                <td class="text-end font-mono text-sm">
                                                    R$ {{ number_format($item->unit_price, 2, ',', '.') }}
                                                </td>
                                                <td class="text-end font-mono font-semibold text-sm">
                                                    R$ {{ number_format($item->total_price, 2, ',', '.') }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- MOBILE (< lg): cards --}}
                        <div class="kt-card-content lg:hidden grid gap-3 p-5">
                            @foreach($items as $item)
                                <div class="rounded-xl border border-border p-4 flex flex-col gap-2">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <p class="text-sm font-medium text-foreground truncate item-alias-name" data-item-description="{{ $item->raw_description }}">{{ $item->description }}</p>
                                            <button type="button"
                                                    data-action="edit-product-alias"
                                                    data-kt-modal-toggle="#productAliasModal"
                                                    data-description="{{ $item->raw_description }}"
                                                    data-canonical-name="{{ $item->canonical_name }}"
                                                    class="shrink-0 text-muted-foreground hover:text-primary transition-colors"
                                                    title="Unificar nome do produto">
                                                <i class="ki-filled ki-pencil text-xs"></i>
                                            </button>
                                            <button type="button"
                                                    data-action="favorite-product"
                                                    data-description="{{ $item->description }}"
                                                    data-unit="{{ $item->unit }}"
                                                    class="shrink-0 text-muted-foreground hover:text-destructive transition-colors"
                                                    title="Avisar quando o preço cair">
                                                <i class="ki-filled ki-heart text-xs"></i>
                                            </button>
                                        </div>
                                        <span class="text-sm font-mono font-semibold text-foreground shrink-0">
                                            R$ {{ number_format($item->total_price, 2, ',', '.') }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1.5 item-category-cell">
                                        <span class="category-dot size-2 rounded-full shrink-0" style="background-color: {{ $item->category_color }}"></span>
                                        <select data-action="assign-category" data-item-id="{{ $item->item_id }}"
                                                data-kt-select="true" data-kt-select-dropdown-strategy="fixed" data-kt-select-placeholder="Sem categoria"
                                                class="text-xs bg-accent border border-border rounded-md px-2 py-1 cursor-pointer w-full focus:outline-none focus:ring-1 focus:ring-primary">
                                            <option value="" data-color="#94a3b8" {{ ! $item->category_id ? 'selected' : '' }}>Sem categoria</option>
                                            @foreach($categories as $cat)
                                                <option value="{{ $cat->id }}" data-color="{{ $cat->color }}" {{ $item->category_id == $cat->id ? 'selected' : '' }}>
                                                    {{ $cat->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if(auth()->user()->isPro())
                                            <button type="button" data-action="suggest-category-ai" data-item-id="{{ $item->item_id }}"
                                                    class="kt-btn kt-btn-ghost kt-btn-xs shrink-0" title="Sugerir categoria com IA">
                                                <i class="ki-filled ki-artificial-intelligence"></i>
                                            </button>
                                        @endif
                                    </div>
                                    <div class="flex items-center justify-between gap-2 pt-2 border-t border-border/60 text-xs text-secondary-foreground">
                                        <span class="truncate">{{ $item->issuer_name }}</span>
                                        <span class="shrink-0">{{ \Carbon\Carbon::parse($item->issued_at)->format('d/m/Y') }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2 text-xs text-secondary-foreground">
                                        <span>Qtd: {{ rtrim(rtrim(number_format($item->quantity, 4, ',', '.'), '0'), ',') }}</span>
                                        <span>Unit.: R$ {{ number_format($item->unit_price, 2, ',', '.') }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($items->hasPages())
                        <div class="kt-card-footer justify-center md:justify-between flex-col md:flex-row gap-3 text-secondary-foreground text-sm font-medium">
                            <span class="order-2 md:order-1">Exibindo {{ $items->firstItem() }}–{{ $items->lastItem() }} de {{ number_format($items->total()) }} itens</span>
                            <div class="flex items-center gap-2 order-1 md:order-2">
                                {{ $items->links('vendor.pagination.metronic') }}
                            </div>
                        </div>
                    @endif
                </div>

            @endisset

        </div>
    </div>

    @include('product-alias._alias-modal')
    @if($isPro)
        @include('report._email-modal')
    @endif

@endsection

@push('scripts')
<script>
    window.pageConfig = Object.assign(window.pageConfig || {}, {
        emailUrl: '{{ route("reports.email") }}',
        scheduleUrl: '{{ route("reports.schedule.save") }}',
        reportMonthly: @json($monthly),
        reportFilters: @json($activeFilters),
        assignCategoryUrl: '{{ route("categories.assign-item") }}',
        suggestItemCategoryUrl: '{{ $isPro ? route("categories.suggest-item-category") : "" }}',
        categoryBreakdown: @json($categoryBreakdown ?? []),
        productAliasStoreUrl: '{{ route("product-aliases.store") }}',
        productAliasAiSuggestUrl: '{{ route("product-aliases.ai-suggest-name") }}',
    });
</script>
@endpush
