@extends('layout.main')
@section('page-module', 'category-detail')

@section('content')

    @php
        $color = $category->color ?? '#94A3B8';
        $reportUrl = route('reports.index', ['category_id' => $category->id, 'start_date' => $filters['start_date'], 'end_date' => $filters['end_date']]);
        $topProductTotal = collect($detail['top_products'])->max('total') ?: 1;
        $topIssuerTotal = collect($detail['top_issuers'])->max('total') ?: 1;
    @endphp

    {{-- PAGE HEADER --}}
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2 min-w-0">
                <h1 class="flex items-center gap-2.5 text-xl font-medium leading-tight text-mono">
                    <span class="size-3 rounded-full shrink-0" style="background-color: {{ $color }}"></span>
                    <span class="min-w-0 break-words">{{ $category->name }}</span>
                    @if(! $category->user_id)
                        <span class="kt-badge kt-badge-secondary kt-badge-sm">Sistema</span>
                    @endif
                </h1>
                <div class="flex items-center gap-2 text-sm font-normal text-secondary-foreground">
                    <a href="{{ route('categories.index', array_filter(['start_date' => $filters['start_date'], 'end_date' => $filters['end_date']])) }}" class="hover:text-primary transition-colors">Categorias</a>
                    <i class="ki-filled ki-right text-xs"></i>
                    <span class="truncate max-w-[200px]">{{ $category->name }}</span>
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <a href="{{ $reportUrl }}" class="kt-btn kt-btn-outline">
                    <i class="ki-filled ki-document"></i> Ver no relatório
                </a>
                <a href="{{ route('categories.index', array_filter(['start_date' => $filters['start_date'], 'end_date' => $filters['end_date']])) }}" class="kt-btn kt-btn-outline">
                    <i class="ki-filled ki-arrow-left"></i> Voltar
                </a>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed">
        <div class="grid gap-5 lg:gap-7.5">

            @include('partials._period-filter', ['periodFilterAction' => route('categories.show', $category), 'filters' => $filters])

            {{-- STAT CARDS --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 lg:gap-7.5">
                <div class="kt-card flex-row items-center gap-4 p-5">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-green-500/10 shrink-0">
                        <i class="ki-filled ki-dollar text-green-600 text-xl"></i>
                    </div>
                    <div class="flex flex-col gap-0.5 min-w-0">
                        <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums truncate">R$ {{ number_format($detail['total'], 2, ',', '.') }}</span>
                        <span class="text-xs font-normal text-secondary-foreground">
                            Total gasto
                            @if($detail['delta_pct'] !== null && $detail['delta_pct'] != 0)
                                <span class="tabular-nums {{ $detail['delta_pct'] > 0 ? 'text-destructive' : 'text-green-600' }}"
                                      title="Comparado ao período anterior, de mesma duração">
                                    · {{ $detail['delta_pct'] > 0 ? '▲' : '▼' }} {{ number_format(abs($detail['delta_pct']), 1, ',', '.') }}%
                                </span>
                            @endif
                        </span>
                    </div>
                </div>

                <div class="kt-card flex-row items-center gap-4 p-5">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-primary/10 shrink-0">
                        <i class="ki-filled ki-basket text-primary text-xl"></i>
                    </div>
                    <div class="flex flex-col gap-0.5 min-w-0">
                        <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums">{{ number_format($detail['items']) }}</span>
                        <span class="text-xs font-normal text-secondary-foreground">{{ $detail['items'] == 1 ? 'Item comprado' : 'Itens comprados' }}</span>
                    </div>
                </div>

                <div class="kt-card flex-row items-center gap-4 p-5">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-yellow-500/10 shrink-0">
                        <i class="ki-filled ki-wallet text-yellow-500 text-xl"></i>
                    </div>
                    <div class="flex flex-col gap-1 min-w-0 w-full">
                        @if($budget)
                            <span class="text-sm font-semibold text-mono tabular-nums truncate {{ $budget->percentage >= 100 ? 'text-destructive' : '' }}">
                                R$ {{ number_format($budget->spent, 2, ',', '.') }} / R$ {{ number_format($budget->amount, 2, ',', '.') }}
                            </span>
                            <div class="kt-progress h-1">
                                <div class="kt-progress-indicator {{ $budget->percentage >= 100 ? 'bg-destructive' : '' }}" style="width: {{ min($budget->percentage, 100) }}%"></div>
                            </div>
                            <a href="{{ route('budgets.index') }}" class="text-xs text-secondary-foreground hover:text-primary">Orçamento do mês</a>
                        @else
                            <span class="text-sm font-medium text-foreground">Sem orçamento</span>
                            <a href="{{ route('budgets.index') }}" class="text-xs font-medium text-primary hover:underline">Definir orçamento</a>
                        @endif
                    </div>
                </div>
            </div>

            {{-- GASTO POR MÊS --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">Gasto por mês</h3>
                    <span class="text-xs text-secondary-foreground">últimos 12 meses</span>
                </div>
                <div class="kt-card-content pb-4">
                    @if(collect($detail['monthly'])->sum('total') > 0)
                        <div id="categoryMonthlyChart" style="height: 240px;"></div>
                    @else
                        <p class="text-sm text-secondary-foreground text-center py-10">Nenhum gasto nesta categoria nos últimos 12 meses.</p>
                    @endif
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-5 lg:gap-7.5">

                {{-- PRODUTOS --}}
                <div class="kt-card">
                    <div class="kt-card-header">
                        <h3 class="kt-card-title">Produtos que mais pesam</h3>
                    </div>
                    <div class="kt-card-content px-5 pb-4 flex flex-col">
                        @forelse($detail['top_products'] as $product)
                            <div class="py-3 border-b border-border last:border-b-0">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-foreground leading-snug line-clamp-2" title="{{ $product['name'] }}">{{ $product['name'] }}</p>
                                        <p class="mt-0.5 text-xs text-secondary-foreground tabular-nums">{{ $product['purchases'] }}x</p>
                                    </div>
                                    <span class="text-sm font-semibold font-mono text-foreground tabular-nums shrink-0">R$ {{ number_format($product['total'], 2, ',', '.') }}</span>
                                </div>
                                <div class="kt-progress h-1 mt-2">
                                    <div class="kt-progress-indicator" style="width: {{ min($product['total'] / $topProductTotal * 100, 100) }}%; background-color: {{ $color }}"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-secondary-foreground text-center py-8">Sem compras no período.</p>
                        @endforelse
                    </div>
                </div>

                {{-- EMISSORES --}}
                <div class="kt-card">
                    <div class="kt-card-header">
                        <h3 class="kt-card-title">Onde você gasta</h3>
                    </div>
                    <div class="kt-card-content px-5 pb-4 flex flex-col">
                        @forelse($detail['top_issuers'] as $issuer)
                            <div class="py-3 border-b border-border last:border-b-0">
                                <div class="flex items-start justify-between gap-3">
                                    <a href="{{ route('issuers.detail', ['id' => $issuer['issuer_id']]) }}"
                                       class="text-sm font-medium text-foreground hover:text-primary leading-snug line-clamp-2 min-w-0" title="{{ $issuer['name'] }}">{{ $issuer['name'] }}</a>
                                    <span class="text-sm font-semibold font-mono text-foreground tabular-nums shrink-0">R$ {{ number_format($issuer['total'], 2, ',', '.') }}</span>
                                </div>
                                <div class="kt-progress h-1 mt-2">
                                    <div class="kt-progress-indicator" style="width: {{ min($issuer['total'] / $topIssuerTotal * 100, 100) }}%; background-color: {{ $color }}"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-secondary-foreground text-center py-8">Sem compras no período.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            @if($category->keywords)
                <div class="kt-card">
                    <div class="kt-card-header">
                        <h3 class="kt-card-title">Palavras-chave</h3>
                    </div>
                    <div class="kt-card-content pb-5 flex flex-wrap gap-1.5">
                        @foreach($category->keywords as $keyword)
                            <span class="text-xs bg-accent px-1.5 py-0.5 rounded">{{ $keyword }}</span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

@endsection

@push('scripts')
<script>
    window.pageConfig = Object.assign(window.pageConfig || {}, {
        categoryMonthly: @json($detail['monthly']),
    });
</script>
@endpush
