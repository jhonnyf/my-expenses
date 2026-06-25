@extends('layout.main')

@php
    $paymentLabels = [
        'dinheiro'        => 'Dinheiro',
        'cheque'          => 'Cheque',
        'cartao_credito'  => 'Cartão de Crédito',
        'cartao_debito'   => 'Cartão de Débito',
        'credito_loja'    => 'Crédito Loja',
        'vale_alimentacao'=> 'Vale Alimentação',
        'vale_refeicao'   => 'Vale Refeição',
        'vale_presente'   => 'Vale Presente',
        'vale_combustivel'=> 'Vale Combustível',
        'boleto'          => 'Boleto',
        'sem_pagamento'   => 'Sem Pagamento',
        'outros'          => 'Outros',
    ];

    $paymentIcons = [
        'dinheiro'        => 'ki-filled ki-dollar',
        'cartao_credito'  => 'ki-filled ki-credit-cart',
        'cartao_debito'   => 'ki-filled ki-credit-cart',
        'vale_alimentacao'=> 'ki-filled ki-basket',
        'vale_refeicao'   => 'ki-filled ki-coffee',
        'pix'             => 'ki-filled ki-send',
        'boleto'          => 'ki-filled ki-document',
    ];
@endphp

@section('content')
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Dashboard</h1>
                <p class="text-sm text-secondary-foreground">Visão geral dos seus gastos</p>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed space-y-5 pb-10">

        {{-- Cards de resumo --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="kt-card">
                <div class="kt-card-content py-5 px-5">
                    <div class="flex items-center justify-between">
                        <div class="min-w-0">
                            <p class="text-xs text-secondary-foreground mb-2">Total de Gastos</p>
                            <p class="text-xl sm:text-2xl font-bold text-foreground truncate">R$ {{ number_format($totalExpenses, 2, ',', '.') }}</p>
                        </div>
                        <div class="flex items-center justify-center size-10 rounded-lg bg-primary/10 shrink-0">
                            <i class="ki-filled ki-dollar text-primary text-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="kt-card">
                <div class="kt-card-content py-5 px-5">
                    <div class="flex items-center justify-between">
                        <div class="min-w-0">
                            <p class="text-xs text-secondary-foreground mb-2">Total de Impostos</p>
                            <p class="text-xl sm:text-2xl font-bold text-foreground truncate">R$ {{ number_format($totalTaxes, 2, ',', '.') }}</p>
                        </div>
                        <div class="flex items-center justify-center size-10 rounded-lg bg-warning/10 shrink-0">
                            <i class="ki-filled ki-chart text-warning text-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="kt-card">
                <div class="kt-card-content py-5 px-5">
                    <div class="flex items-center justify-between">
                        <div class="min-w-0">
                            <p class="text-xs text-secondary-foreground mb-2">Total de Compras</p>
                            <p class="text-xl sm:text-2xl font-bold text-foreground">{{ $totalPurchases }}</p>
                        </div>
                        <div class="flex items-center justify-center size-10 rounded-lg bg-info/10 shrink-0">
                            <i class="ki-filled ki-purchase text-info text-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="kt-card">
                <div class="kt-card-content py-5 px-5">
                    <div class="flex items-center justify-between">
                        <div class="min-w-0">
                            <p class="text-xs text-secondary-foreground mb-2">Ticket Médio</p>
                            <p class="text-xl sm:text-2xl font-bold text-primary truncate">R$ {{ number_format($averageTicket, 2, ',', '.') }}</p>
                        </div>
                        <div class="flex items-center justify-center size-10 rounded-lg bg-success/10 shrink-0">
                            <i class="ki-filled ki-basket text-success text-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Mês atual vs anterior + Última compra --}}
        <div class="grid lg:grid-cols-3 gap-5">

            {{-- Mês atual --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-calendar text-primary me-1"></i> Mês Atual
                    </h3>
                    @if($monthVariation !== null)
                        <div class="kt-card-toolbar">
                            @if($monthVariation > 0)
                                <span class="kt-badge kt-badge-destructive kt-badge-outline kt-badge-sm">
                                    <i class="ki-filled ki-arrow-up text-2xs me-0.5"></i> +{{ number_format($monthVariation, 1) }}%
                                </span>
                            @elseif($monthVariation < 0)
                                <span class="kt-badge kt-badge-success kt-badge-outline kt-badge-sm">
                                    <i class="ki-filled ki-arrow-down text-2xs me-0.5"></i> {{ number_format($monthVariation, 1) }}%
                                </span>
                            @else
                                <span class="kt-badge kt-badge-secondary kt-badge-outline kt-badge-sm">Igual</span>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="kt-card-content pb-5">
                    <div class="space-y-4">
                        <div>
                            <p class="text-xs text-secondary-foreground">Gastos em {{ now()->translatedFormat('F') }}</p>
                            <p class="text-2xl font-bold text-foreground mt-1">R$ {{ number_format($currentMonthExpenses, 2, ',', '.') }}</p>
                        </div>
                        <div class="border-t border-border pt-3">
                            <p class="text-xs text-secondary-foreground">Mês anterior ({{ now()->subMonth()->translatedFormat('F') }})</p>
                            <p class="text-lg font-semibold text-foreground mt-1">R$ {{ number_format($lastMonthExpenses, 2, ',', '.') }}</p>
                        </div>
                        @if($monthVariation !== null)
                            <p class="text-xs text-secondary-foreground">
                                @if($monthVariation > 0)
                                    Você gastou <strong class="text-destructive">{{ number_format(abs($monthVariation), 1) }}% a mais</strong> que o mês anterior
                                @elseif($monthVariation < 0)
                                    Você gastou <strong class="text-success">{{ number_format(abs($monthVariation), 1) }}% a menos</strong> que o mês anterior
                                @else
                                    Gastos iguais ao mês anterior
                                @endif
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Última compra --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-purchase text-primary me-1"></i> Última Compra
                    </h3>
                </div>
                <div class="kt-card-content pb-5">
                    @if($lastPurchase)
                        <div class="space-y-3">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center size-10 rounded-lg bg-primary/10 shrink-0">
                                    <i class="ki-filled ki-shop text-primary"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-semibold text-foreground truncate">{{ $lastPurchase->issuer->name ?? '—' }}</p>
                                    <p class="text-xs text-secondary-foreground">{{ $lastPurchase->issued_at->format('d/m/Y H:i') }}</p>
                                </div>
                            </div>
                            <div class="border-t border-border pt-3">
                                <p class="text-xs text-secondary-foreground">Valor total</p>
                                <p class="text-2xl font-bold text-primary mt-1">R$ {{ number_format($lastPurchase->total_amount, 2, ',', '.') }}</p>
                            </div>
                            <a href="{{ route('my-purchases.detail', ['invoice' => $lastPurchase->id]) }}" class="kt-btn kt-btn-sm kt-btn-light w-full mt-2">
                                <i class="ki-filled ki-eye text-sm me-1"></i> Ver detalhes
                            </a>
                        </div>
                    @else
                        <p class="text-sm text-secondary-foreground py-4">Nenhuma compra registrada.</p>
                    @endif
                </div>
            </div>

            {{-- Distribuição por pagamento --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-wallet text-primary me-1"></i> Formas de Pagamento
                    </h3>
                </div>
                <div class="kt-card-content pb-5">
                    @if($paymentDistribution->isNotEmpty())
                        @php $paymentTotal = $paymentDistribution->sum('total'); @endphp
                        <div class="space-y-3">
                            @foreach($paymentDistribution as $payment)
                                @php $pct = $paymentTotal > 0 ? ($payment->total / $paymentTotal) * 100 : 0; @endphp
                                <div>
                                    <div class="flex justify-between items-center mb-1.5">
                                        <div class="flex items-center gap-2">
                                            <i class="{{ $paymentIcons[$payment->method] ?? 'ki-filled ki-wallet' }} text-sm text-secondary-foreground"></i>
                                            <span class="text-sm text-foreground">{{ $paymentLabels[$payment->method] ?? $payment->method }}</span>
                                        </div>
                                        <span class="text-xs font-semibold text-foreground">{{ number_format($pct, 0) }}%</span>
                                    </div>
                                    <div class="kt-progress">
                                        <div class="kt-progress-indicator" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <p class="text-xs text-secondary-foreground mt-1">R$ {{ number_format($payment->total, 2, ',', '.') }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-secondary-foreground py-4">Sem dados de pagamento.</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Orçamento --}}
        @if($budgets->isNotEmpty())
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-chart text-primary me-1"></i> Orçamento do Mês
                    </h3>
                    <div class="kt-card-toolbar">
                        <a href="{{ route('budgets.index') }}" class="kt-btn kt-btn-sm kt-btn-light">
                            <i class="ki-filled ki-setting-2 text-sm me-1"></i> Gerenciar
                        </a>
                    </div>
                </div>
                <div class="kt-card-content pb-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($budgets->sortByDesc('percentage')->take(6) as $budget)
                            @php
                                $pct = min($budget->percentage, 100);
                                $status = $budget->percentage < 75 ? 'success' : ($budget->percentage < 100 ? 'warning' : 'destructive');
                                $statusLabel = $budget->percentage < 75 ? 'Dentro do limite' : ($budget->percentage < 100 ? 'Atenção' : 'Estourado');
                            @endphp
                            <div class="border border-border rounded-lg p-4">
                                <div class="flex justify-between items-start gap-2 mb-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-foreground truncate">{{ $budget->category->name ?? 'Geral' }}</p>
                                        <p class="text-xs text-secondary-foreground mt-0.5">
                                            R$ {{ number_format($budget->spent, 2, ',', '.') }} de R$ {{ number_format($budget->amount, 2, ',', '.') }}
                                        </p>
                                    </div>
                                    <span class="kt-badge kt-badge-{{ $status }} kt-badge-outline kt-badge-sm shrink-0">{{ $statusLabel }}</span>
                                </div>
                                <div class="kt-progress">
                                    <div class="kt-progress-indicator kt-progress-{{ $status }}" style="width: {{ $pct }}%"></div>
                                </div>
                                <p class="text-xs text-secondary-foreground mt-1.5 text-right">{{ number_format($budget->percentage, 0) }}%</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Gráfico de gastos mensais (ApexCharts) --}}
        <div class="kt-card">
            <div class="kt-card-header">
                <h3 class="kt-card-title">
                    <i class="ki-filled ki-chart-line text-primary me-1"></i> Gastos Mensais
                </h3>
                <div class="kt-card-toolbar">
                    <span class="kt-badge kt-badge-secondary kt-badge-outline kt-badge-sm">Últimos 12 meses</span>
                </div>
            </div>
            <div class="kt-card-content pb-5">
                @if($monthlyExpenses->isNotEmpty())
                    <div id="monthlyExpensesChart" class="h-56 sm:h-72"></div>
                @else
                    <p class="text-sm text-secondary-foreground py-4">Sem dados para exibir.</p>
                @endif
            </div>
        </div>

        {{-- Gastos por Categoria --}}
        <div class="grid lg:grid-cols-2 gap-5">
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-category text-primary me-1"></i> Gastos por Categoria
                    </h3>
                    <div class="kt-card-toolbar">
                        <a href="{{ route('categories.index') }}" class="kt-btn kt-btn-sm kt-btn-light">
                            <i class="ki-filled ki-setting-2 text-sm me-1"></i> Gerenciar
                        </a>
                    </div>
                </div>
                <div class="kt-card-content pb-5">
                    @if($spendingByCategory->isNotEmpty())
                        <div id="categoryChart" class="h-56 sm:h-72 mb-4"></div>
                        @php $catTotal = $spendingByCategory->sum('total') ?: 1; @endphp
                        <div class="space-y-2">
                            @foreach($spendingByCategory->take(6) as $cat)
                                @php $catPct = ($cat->total / $catTotal) * 100; @endphp
                                <div class="flex items-center justify-between gap-2 py-1">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="size-2.5 rounded-full shrink-0" style="background-color: {{ $cat->category_color }}"></span>
                                        <span class="text-sm text-foreground truncate">{{ $cat->category_name }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                                        <span class="text-xs text-secondary-foreground">{{ number_format($catPct, 0) }}%</span>
                                        <span class="text-xs sm:text-sm font-semibold font-mono text-foreground text-right">R$ {{ number_format($cat->total, 2, ',', '.') }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-secondary-foreground py-4">Sem dados de categoria.</p>
                    @endif
                </div>
            </div>

            {{-- Distribuição por pagamento (donut chart) --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-wallet text-primary me-1"></i> Distribuição de Pagamentos
                    </h3>
                </div>
                <div class="kt-card-content pb-5">
                    @if($paymentDistribution->isNotEmpty())
                        <div id="paymentChart" class="h-56 sm:h-72 mb-4"></div>
                        @php $paymentTotal = $paymentDistribution->sum('total'); @endphp
                        <div class="space-y-2">
                            @foreach($paymentDistribution as $payment)
                                @php $pct = $paymentTotal > 0 ? ($payment->total / $paymentTotal) * 100 : 0; @endphp
                                <div class="flex items-center justify-between gap-2 py-1">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <i class="{{ $paymentIcons[$payment->method] ?? 'ki-filled ki-wallet' }} text-sm text-secondary-foreground shrink-0"></i>
                                        <span class="text-sm text-foreground truncate">{{ $paymentLabels[$payment->method] ?? $payment->method }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                                        <span class="text-xs text-secondary-foreground">{{ number_format($pct, 0) }}%</span>
                                        <span class="text-xs sm:text-sm font-semibold font-mono text-foreground text-right">R$ {{ number_format($payment->total, 2, ',', '.') }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-secondary-foreground py-4">Sem dados de pagamento.</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Top emissores + Produtos mais comprados --}}
        <div class="grid lg:grid-cols-2 gap-5">

            {{-- Top 5 emissores --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-shop text-primary me-1"></i> Onde Você Mais Gasta
                    </h3>
                </div>
                <div class="kt-card-content pb-4">
                    @if($topIssuers->isNotEmpty())
                        <div class="divide-y divide-border">
                            @foreach($topIssuers as $i => $issuer)
                                <div class="flex items-center justify-between gap-2 py-3 px-1">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span class="flex items-center justify-center size-8 rounded-lg bg-primary/10 text-primary text-xs font-bold shrink-0">
                                            {{ $i + 1 }}º
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-foreground truncate">{{ $issuer->name }}</p>
                                            <p class="text-xs text-secondary-foreground">{{ $issuer->count }} {{ $issuer->count == 1 ? 'compra' : 'compras' }}</p>
                                        </div>
                                    </div>
                                    <span class="font-semibold font-mono text-xs sm:text-sm shrink-0">R$ {{ number_format($issuer->total, 2, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-secondary-foreground py-4">Sem dados.</p>
                    @endif
                </div>
            </div>

            {{-- Produtos mais comprados --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-basket text-primary me-1"></i> Produtos Mais Comprados
                    </h3>
                </div>
                <div class="kt-card-content pb-4">
                    @if($topProducts->isNotEmpty())
                        <div class="divide-y divide-border">
                            @foreach($topProducts as $i => $product)
                                <div class="flex items-center justify-between gap-2 py-3 px-1">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span class="flex items-center justify-center size-8 rounded-lg bg-accent text-foreground text-xs font-bold shrink-0">
                                            {{ $i + 1 }}º
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-foreground truncate">{{ $product->description }}</p>
                                            <p class="text-xs text-secondary-foreground">Preço médio: R$ {{ number_format($product->avg_price, 2, ',', '.') }}</p>
                                        </div>
                                    </div>
                                    <span class="kt-badge kt-badge-primary kt-badge-outline kt-badge-sm shrink-0">{{ $product->frequency }}x</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-secondary-foreground py-4">Sem dados.</p>
                    @endif
                </div>
            </div>

        </div>

    </div>

    <script>
        window.pageConfig = {
            monthlyExpenses: @json($monthlyExpenses),
            spendingByCategory: @json($spendingByCategory),
            paymentDistribution: @json($paymentDistribution),
            paymentLabels: @json($paymentLabels),
        };
    </script>
    @section('page-module', 'dashboard')
@endsection
