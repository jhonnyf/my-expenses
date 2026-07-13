@extends('layout.main')
@section('page-module', 'dashboard')

@section('content')

    {{-- PAGE HEADER --}}
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Dashboard</h1>
                <div class="flex items-center gap-2 text-sm font-normal text-secondary-foreground">
                    {{ now()->translatedFormat('l, d \d\e F \d\e Y') }}
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <a class="kt-btn kt-btn-primary" href="{{ route('my-purchases.upload.form') }}">
                    <i class="ki-filled ki-file-up"></i>
                    Importar NF-e
                </a>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed">
        <div class="grid gap-5 lg:gap-7.5">

            {{-- SEÇÃO 1 — 4 STAT CARDS --}}
            <style>
                .channel-stats-bg {
                    background-image: url('{{ asset('assets/media/images/2600x1600/bg-3.png') }}');
                }
                .dark .channel-stats-bg {
                    background-image: url('{{ asset('assets/media/images/2600x1600/bg-3-dark.png') }}');
                }
            </style>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 lg:gap-7.5">

                <div class="kt-card flex-col justify-between gap-6 bg-cover bg-[right_top_-1.7rem] bg-no-repeat channel-stats-bg">
                    <div class="flex items-center justify-center size-10 mt-4 ms-5 rounded-xl bg-primary/10">
                        <i class="ki-filled ki-dollar text-primary text-xl"></i>
                    </div>
                    <div class="flex flex-col gap-1 pb-4 px-5">
                        <span class="text-xl lg:text-2xl font-semibold text-mono tabular-nums truncate">
                            R$ {{ number_format($totalExpenses, 2, ',', '.') }}
                        </span>
                        <span class="text-sm font-normal text-secondary-foreground">Total Gasto</span>
                    </div>
                </div>

                <div class="kt-card flex-col justify-between gap-6 bg-cover bg-[right_top_-1.7rem] bg-no-repeat channel-stats-bg">
                    <div class="flex items-center justify-center size-10 mt-4 ms-5 rounded-xl bg-yellow-500/10">
                        <i class="ki-filled ki-chart text-yellow-600 text-xl"></i>
                    </div>
                    <div class="flex flex-col gap-1 pb-4 px-5">
                        <span class="text-xl lg:text-2xl font-semibold text-mono tabular-nums truncate">
                            R$ {{ number_format($totalTaxes, 2, ',', '.') }}
                        </span>
                        <span class="text-sm font-normal text-secondary-foreground">Impostos</span>
                    </div>
                </div>

                <div class="kt-card flex-col justify-between gap-6 bg-cover bg-[right_top_-1.7rem] bg-no-repeat channel-stats-bg">
                    <div class="flex items-center justify-center size-10 mt-4 ms-5 rounded-xl bg-violet-500/10">
                        <i class="ki-filled ki-purchase text-violet-600 text-xl"></i>
                    </div>
                    <div class="flex flex-col gap-1 pb-4 px-5">
                        <span class="text-xl lg:text-2xl font-semibold text-mono tabular-nums">
                            {{ number_format($totalPurchases) }}
                        </span>
                        <span class="text-sm font-normal text-secondary-foreground">NF-e importadas</span>
                    </div>
                </div>

                <div class="kt-card flex-col justify-between gap-6 bg-cover bg-[right_top_-1.7rem] bg-no-repeat channel-stats-bg">
                    <div class="flex items-center justify-center size-10 mt-4 ms-5 rounded-xl bg-green-500/10">
                        <i class="ki-filled ki-basket text-green-600 text-xl"></i>
                    </div>
                    <div class="flex flex-col gap-1 pb-4 px-5">
                        <span class="text-xl lg:text-2xl font-semibold text-mono tabular-nums truncate">
                            R$ {{ number_format($averageTicket, 2, ',', '.') }}
                        </span>
                        <span class="text-sm font-normal text-secondary-foreground">Ticket Médio</span>
                    </div>
                </div>

            </div>

            {{-- SEÇÃO 2 — MÊS ATUAL + ÚLTIMA COMPRA | EVOLUÇÃO DE GASTOS --}}
            <div class="grid lg:grid-cols-3 gap-5 lg:gap-7.5">

                {{-- Mês atual + última compra --}}
                <div class="lg:col-span-1 flex flex-col gap-5 lg:gap-7.5">

                    <div class="kt-card">
                        <div class="kt-card-header">
                            <h3 class="kt-card-title">Mês Atual</h3>
                            @if($monthVariation !== null)
                                <div class="kt-card-toolbar">
                                    @if($monthVariation > 0)
                                        <span class="kt-badge kt-badge-outline kt-badge-destructive kt-badge-sm">
                                            <i class="ki-filled ki-arrow-up text-2xs"></i>
                                            +{{ number_format($monthVariation, 1) }}%
                                        </span>
                                    @elseif($monthVariation < 0)
                                        <span class="kt-badge kt-badge-outline kt-badge-success kt-badge-sm">
                                            <i class="ki-filled ki-arrow-down text-2xs"></i>
                                            {{ number_format($monthVariation, 1) }}%
                                        </span>
                                    @else
                                        <span class="kt-badge kt-badge-outline kt-badge-secondary kt-badge-sm">Estável</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div class="kt-card-content p-5 lg:p-7.5 lg:pt-4 flex flex-col gap-4">
                            <div class="flex flex-col gap-0.5">
                                <span class="text-sm font-normal text-secondary-foreground capitalize">
                                    {{ now()->translatedFormat('F Y') }}
                                </span>
                                <span class="text-3xl font-semibold text-mono tabular-nums">
                                    R$ {{ number_format($currentMonthExpenses, 2, ',', '.') }}
                                </span>
                            </div>
                            <div class="border-b border-input"></div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm text-secondary-foreground capitalize">
                                    {{ now()->subMonth()->translatedFormat('F') }}
                                </span>
                                <span class="text-sm font-semibold text-mono tabular-nums">
                                    R$ {{ number_format($lastMonthExpenses, 2, ',', '.') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    @if($lastPurchase)
                        <div class="kt-card">
                            <div class="kt-card-header">
                                <h3 class="kt-card-title">Última Compra</h3>
                            </div>
                            <div class="kt-card-content p-5 lg:p-7.5 lg:pt-4 flex flex-col gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center size-10 rounded-xl bg-primary/10 shrink-0">
                                        <i class="ki-filled ki-shop text-primary text-lg"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-foreground truncate">
                                            {{ $lastPurchase->issuer->name ?? '—' }}
                                        </p>
                                        <p class="text-xs text-secondary-foreground">
                                            {{ \Carbon\Carbon::parse($lastPurchase->issued_at)->translatedFormat('d \d\e F') }}
                                        </p>
                                    </div>
                                    <span class="ms-auto text-base font-bold text-foreground tabular-nums shrink-0">
                                        R$ {{ number_format($lastPurchase->total_amount, 2, ',', '.') }}
                                    </span>
                                </div>
                                <div class="kt-card-footer px-0 pb-0 pt-2 justify-center border-t border-input">
                                    <a class="kt-link kt-link-underlined kt-link-dashed text-xs"
                                       href="{{ route('my-purchases.detail', $lastPurchase->id) }}">
                                        Ver detalhes
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                </div>

                {{-- Evolução de Gastos --}}
                <div class="lg:col-span-2">
                    <div class="kt-card">
                        <div class="kt-card-header">
                            <h3 class="kt-card-title">Evolução de Gastos</h3>
                            <div class="kt-card-toolbar">
                                <span class="kt-badge kt-badge-secondary kt-badge-outline kt-badge-sm">Últimos 12 meses</span>
                            </div>
                        </div>
                        <div class="kt-card-content pb-4">
                            @if($monthlyExpenses->isNotEmpty())
                                <div id="monthlyExpensesChart" style="height: 280px;"></div>
                            @else
                                <div class="flex flex-col items-center justify-center py-16 text-center">
                                    <i class="ki-filled ki-chart-line text-4xl text-secondary-foreground/30 mb-3"></i>
                                    <p class="text-sm text-secondary-foreground">Sem dados de gastos ainda.</p>
                                    <a href="{{ route('my-purchases.upload.form') }}" class="kt-link kt-link-underlined kt-link-dashed text-xs mt-2">
                                        Importar NF-e
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

            </div>

            {{-- SEÇÃO 3 — FORMAS DE PAGAMENTO | GASTOS POR CATEGORIA --}}
            <div class="grid lg:grid-cols-2 gap-5 lg:gap-7.5 items-stretch">

                {{-- Formas de Pagamento --}}
                <div class="kt-card h-full">
                    <div class="kt-card-header">
                        <h3 class="kt-card-title">Formas de Pagamento</h3>
                    </div>
                    <div class="kt-card-content pb-5">
                        @if($paymentDistribution->isNotEmpty())
                            <div class="grid lg:grid-cols-2 gap-4 items-center">
                                <div id="paymentChart" style="height: 200px;"></div>
                                <div class="grid gap-2">
                                    @php $payTotal = $paymentDistribution->sum('total') ?: 1; @endphp
                                    @foreach($paymentDistribution->take(5) as $pay)
                                        @php $pct = ($pay->total / $payTotal) * 100; @endphp
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <i class="{{ $paymentIcons[$pay->method] ?? 'ki-filled ki-wallet' }} text-sm text-muted-foreground shrink-0"></i>
                                                <span class="text-sm text-foreground truncate">{{ $paymentLabels[$pay->method] ?? $pay->method }}</span>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                <span class="text-xs text-secondary-foreground tabular-nums">{{ number_format($pct, 0) }}%</span>
                                                <span class="text-sm font-semibold text-foreground tabular-nums">R$ {{ number_format($pay->total, 2, ',', '.') }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <i class="ki-filled ki-wallet text-4xl text-secondary-foreground/30 mb-3"></i>
                                <p class="text-sm text-secondary-foreground">Sem dados de pagamento.</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Gastos por Categoria --}}
                <div class="kt-card h-full">
                    <div class="kt-card-header">
                        <h3 class="kt-card-title">Gastos por Categoria</h3>
                        <div class="kt-card-toolbar">
                            <a href="{{ route('categories.index') }}" class="kt-btn kt-btn-sm kt-btn-ghost kt-btn-icon" title="Gerenciar categorias">
                                <i class="ki-filled ki-setting-2 text-sm"></i>
                            </a>
                        </div>
                    </div>
                    <div class="kt-card-content pb-5">
                        @if($spendingByCategory->isNotEmpty())
                            <div class="grid lg:grid-cols-2 gap-4 items-center">
                                <div id="categoryChart" style="height: 200px;"></div>
                                <div class="grid gap-2">
                                    @php $catTotal = $spendingByCategory->sum('total') ?: 1; @endphp
                                    @foreach($spendingByCategory->take(5) as $cat)
                                        @php $catPct = ($cat->total / $catTotal) * 100; @endphp
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <span class="size-2.5 rounded-full shrink-0" style="background-color: {{ $cat->category_color }}"></span>
                                                <span class="text-sm text-foreground truncate">{{ $cat->category_name }}</span>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                <span class="text-xs text-secondary-foreground tabular-nums">{{ number_format($catPct, 0) }}%</span>
                                                <span class="text-sm font-semibold text-foreground tabular-nums">R$ {{ number_format($cat->total, 2, ',', '.') }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <i class="ki-filled ki-category text-4xl text-secondary-foreground/30 mb-3"></i>
                                <p class="text-sm text-secondary-foreground">Sem dados de categoria.</p>
                            </div>
                        @endif
                    </div>
                </div>

            </div>

            {{-- SEÇÃO 4 — ORÇAMENTO (condicional) --}}
            @if($budgets->isNotEmpty())
                <div class="kt-card">
                    <div class="kt-card-header">
                        <h3 class="kt-card-title">Orçamento do Mês</h3>
                        <div class="kt-card-toolbar">
                            <a href="{{ route('budgets.index') }}" class="kt-btn kt-btn-secondary kt-btn-sm">
                                <i class="ki-filled ki-setting-2 text-sm"></i>
                                Gerenciar
                            </a>
                        </div>
                    </div>
                    <div class="kt-card-content pb-5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($budgets->sortByDesc('percentage')->take(6) as $budget)
                                @php
                                    $pct    = min($budget->percentage, 100);
                                    $over   = $budget->percentage >= 100;
                                    $warn   = $budget->percentage >= 75 && !$over;
                                    $status = $over ? 'destructive' : ($warn ? 'warning' : 'success');
                                    $label  = $over ? 'Estourado' : ($warn ? 'Atenção' : 'OK');
                                    $textStatus  = $over ? 'text-destructive' : ($warn ? 'text-yellow-600' : 'text-green-600');
                                    $accentColor = $over ? '#ef4444' : ($warn ? '#eab308' : '#22c55e');
                                @endphp
                                <div class="rounded-xl border border-border p-4 space-y-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-foreground truncate">{{ $budget->category->name ?? 'Geral' }}</p>
                                            <p class="text-xs text-secondary-foreground mt-0.5 tabular-nums">
                                                R$ {{ number_format($budget->spent, 2, ',', '.') }} / R$ {{ number_format($budget->amount, 2, ',', '.') }}
                                            </p>
                                        </div>
                                        <span class="kt-badge kt-badge-{{ $status }} kt-badge-outline kt-badge-sm shrink-0">{{ $label }}</span>
                                    </div>
                                    <div class="kt-progress h-2">
                                        <div class="kt-progress-indicator" style="width: {{ $pct }}%; background-color: {{ $accentColor }}"></div>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-secondary-foreground tabular-nums">
                                            Restante: R$ {{ number_format(max($budget->remaining, 0), 2, ',', '.') }}
                                        </span>
                                        <span class="text-xs font-bold tabular-nums {{ $textStatus }}">
                                            {{ number_format($budget->percentage, 0) }}%
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- SEÇÃO 5 — TOP EMISSORES + TOP PRODUTOS --}}
            <div class="grid lg:grid-cols-2 gap-5 lg:gap-7.5 items-stretch">

                {{-- Top Emissores --}}
                <div class="kt-card h-full">
                    <div class="kt-card-header">
                        <h3 class="kt-card-title">Onde Você Mais Gasta</h3>
                        <div class="kt-card-toolbar">
                            <a href="{{ route('issuers.index') }}" class="kt-link text-xs">Ver todos</a>
                        </div>
                    </div>
                    <div class="kt-card-content pb-2">
                        @if($topIssuers->isNotEmpty())
                            @foreach($topIssuers as $i => $issuer)
                                <div class="flex items-center justify-between gap-3 px-2 py-3 rounded-lg hover:bg-accent/40 transition-colors {{ !$loop->last ? 'border-b border-border/40' : '' }}">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span class="flex items-center justify-center size-8 rounded-lg shrink-0 font-bold text-xs {{ $i === 0 ? 'bg-primary/10 text-primary' : 'bg-secondary/60 text-secondary-foreground' }}">
                                            {{ $i + 1 }}º
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-foreground truncate">{{ $issuer->name }}</p>
                                            <p class="text-xs text-secondary-foreground">
                                                {{ $issuer->count }} {{ $issuer->count == 1 ? 'compra' : 'compras' }}
                                            </p>
                                        </div>
                                    </div>
                                    <span class="text-sm font-semibold text-foreground shrink-0 tabular-nums">
                                        R$ {{ number_format($issuer->total, 2, ',', '.') }}
                                    </span>
                                </div>
                            @endforeach
                        @else
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <i class="ki-filled ki-shop text-4xl text-secondary-foreground/30 mb-3"></i>
                                <p class="text-sm text-secondary-foreground">Sem dados de emissores.</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Top Produtos --}}
                <div class="kt-card h-full">
                    <div class="kt-card-header">
                        <h3 class="kt-card-title">Produtos Mais Comprados</h3>
                        <div class="kt-card-toolbar">
                            <a href="{{ route('price-history.index') }}" class="kt-link text-xs">Ver histórico</a>
                        </div>
                    </div>
                    <div class="kt-card-content pb-2">
                        @if($topProducts->isNotEmpty())
                            @foreach($topProducts->take(5) as $i => $product)
                                <div class="flex items-center justify-between gap-3 px-2 py-3 rounded-lg hover:bg-accent/40 transition-colors {{ !$loop->last ? 'border-b border-border/40' : '' }}">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span class="flex items-center justify-center size-8 rounded-lg shrink-0 font-bold text-xs {{ $i === 0 ? 'bg-violet-500/10 text-violet-600' : 'bg-secondary/60 text-secondary-foreground' }}">
                                            {{ $i + 1 }}º
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-foreground truncate">{{ $product->description }}</p>
                                            <p class="text-xs text-secondary-foreground tabular-nums">
                                                Preço médio: R$ {{ number_format($product->avg_price, 2, ',', '.') }}
                                            </p>
                                        </div>
                                    </div>
                                    <span class="kt-badge kt-badge-primary kt-badge-outline kt-badge-sm shrink-0 tabular-nums">
                                        {{ $product->frequency }}×
                                    </span>
                                </div>
                            @endforeach
                        @else
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <i class="ki-filled ki-basket text-4xl text-secondary-foreground/30 mb-3"></i>
                                <p class="text-sm text-secondary-foreground">Sem dados de produtos.</p>
                            </div>
                        @endif
                    </div>
                </div>

            </div>

        </div>
    </div>

@endsection

@push('scripts')
<script>
    window.pageConfig = Object.assign(window.pageConfig || {}, {
        monthlyExpenses:     @json($monthlyExpenses),
        spendingByCategory:  @json($spendingByCategory),
        paymentDistribution: @json($paymentDistribution),
        paymentLabels:       @json($paymentLabels),
    });
</script>
@endpush
