@extends('layout.main')
@section('page-module', 'dashboard')


@section('content')

    {{-- ===== PAGE HEADER ===== --}}
    <div class="kt-container-fixed mb-6 px-4 lg:px-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-foreground leading-tight">Dashboard</h1>
                <p class="text-sm text-secondary-foreground mt-0.5">
                    {{ now()->translatedFormat('l, d \d\e F \d\e Y') }}
                </p>
            </div>
            <a href="{{ route('my-purchases.upload.form') }}" class="kt-btn kt-btn-primary kt-btn-sm">
                <i class="ki-filled ki-file-up text-base"></i>
                Importar NF-e
            </a>
        </div>
    </div>

    <div class="kt-container-fixed space-y-5 pb-10 px-4 lg:px-6">

        {{-- ===== CARDS DE RESUMO ===== --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">

            <div class="kt-card">
                <div class="kt-card-content p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] sm:text-xs text-secondary-foreground font-medium uppercase tracking-wide mb-2">
                                Total Gasto
                            </p>
                            <p class="text-lg sm:text-2xl font-bold text-foreground leading-none truncate tabular-nums">
                                R$ {{ number_format($totalExpenses, 2, ',', '.') }}
                            </p>
                            <p class="text-[11px] sm:text-xs text-secondary-foreground mt-2">acumulado geral</p>
                        </div>
                        <div class="flex items-center justify-center size-9 sm:size-11 rounded-xl bg-primary/10 shrink-0">
                            <i class="ki-filled ki-dollar text-primary text-base sm:text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="kt-card">
                <div class="kt-card-content p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] sm:text-xs text-secondary-foreground font-medium uppercase tracking-wide mb-2">
                                Impostos
                            </p>
                            <p class="text-lg sm:text-2xl font-bold text-foreground leading-none truncate tabular-nums">
                                R$ {{ number_format($totalTaxes, 2, ',', '.') }}
                            </p>
                            <p class="text-[11px] sm:text-xs text-secondary-foreground mt-2">valor tributos</p>
                        </div>
                        <div class="flex items-center justify-center size-9 sm:size-11 rounded-xl bg-warning/10 shrink-0">
                            <i class="ki-filled ki-chart text-warning text-base sm:text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="kt-card">
                <div class="kt-card-content p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] sm:text-xs text-secondary-foreground font-medium uppercase tracking-wide mb-2">
                                Compras
                            </p>
                            <p class="text-lg sm:text-2xl font-bold text-foreground leading-none tabular-nums">
                                {{ $totalPurchases }}
                            </p>
                            <p class="text-[11px] sm:text-xs text-secondary-foreground mt-2">NF-e importadas</p>
                        </div>
                        <div class="flex items-center justify-center size-9 sm:size-11 rounded-xl bg-info/10 shrink-0">
                            <i class="ki-filled ki-purchase text-info text-base sm:text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="kt-card">
                <div class="kt-card-content p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] sm:text-xs text-secondary-foreground font-medium uppercase tracking-wide mb-2">
                                Ticket Médio
                            </p>
                            <p class="text-lg sm:text-2xl font-bold text-primary leading-none truncate tabular-nums">
                                R$ {{ number_format($averageTicket, 2, ',', '.') }}
                            </p>
                            <p class="text-[11px] sm:text-xs text-secondary-foreground mt-2">por compra</p>
                        </div>
                        <div class="flex items-center justify-center size-9 sm:size-11 rounded-xl bg-success/10 shrink-0">
                            <i class="ki-filled ki-basket text-success text-base sm:text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        {{-- ===== MÊS ATUAL + ÚLTIMA COMPRA + FORMAS DE PAGAMENTO ===== --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

            {{-- Mês atual vs anterior --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-calendar text-primary me-1.5 text-base"></i>
                        Mês Atual
                    </h3>
                    @if($monthVariation !== null)
                        <div class="kt-card-toolbar">
                            @if($monthVariation > 0)
                                <span class="kt-badge kt-badge-destructive kt-badge-outline kt-badge-sm">
                                    <i class="ki-filled ki-arrow-up text-2xs"></i>
                                    +{{ number_format($monthVariation, 1) }}%
                                </span>
                            @elseif($monthVariation < 0)
                                <span class="kt-badge kt-badge-success kt-badge-outline kt-badge-sm">
                                    <i class="ki-filled ki-arrow-down text-2xs"></i>
                                    {{ number_format($monthVariation, 1) }}%
                                </span>
                            @else
                                <span class="kt-badge kt-badge-secondary kt-badge-outline kt-badge-sm">Estável</span>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="kt-card-content pb-5 space-y-3">
                    <div class="rounded-xl bg-primary/5 border border-primary/10 p-4">
                        <p class="text-xs text-secondary-foreground mb-1.5 capitalize">
                            {{ now()->translatedFormat('F Y') }}
                        </p>
                        <p class="text-2xl font-bold text-foreground truncate tabular-nums">
                            R$ {{ number_format($currentMonthExpenses, 2, ',', '.') }}
                        </p>
                    </div>
                    <div class="flex items-center justify-between gap-3 px-1 py-2 rounded-lg hover:bg-accent/40 transition-colors">
                        <div class="min-w-0">
                            <p class="text-xs text-secondary-foreground capitalize">
                                {{ now()->subMonth()->translatedFormat('F') }}
                            </p>
                            <p class="text-sm font-semibold text-foreground truncate tabular-nums">
                                R$ {{ number_format($lastMonthExpenses, 2, ',', '.') }}
                            </p>
                        </div>
                        @if($monthVariation !== null)
                            <span class="text-xs font-medium shrink-0 {{ $monthVariation > 0 ? 'text-destructive' : ($monthVariation < 0 ? 'text-success' : 'text-secondary-foreground') }}">
                                @if($monthVariation > 0)
                                    +{{ number_format(abs($monthVariation), 1) }}% a mais
                                @elseif($monthVariation < 0)
                                    {{ number_format(abs($monthVariation), 1) }}% a menos
                                @else
                                    Sem variação
                                @endif
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Última compra --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-purchase text-primary me-1.5 text-base"></i>
                        Última Compra
                    </h3>
                </div>
                <div class="kt-card-content pb-5">
                    @if($lastPurchase)
                        <div class="space-y-3">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center size-11 rounded-xl bg-primary/10 shrink-0">
                                    <i class="ki-filled ki-shop text-primary text-xl"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-foreground truncate">
                                        {{ $lastPurchase->issuer->name ?? '—' }}
                                    </p>
                                    <p class="text-xs text-secondary-foreground mt-0.5">
                                        {{ $lastPurchase->issued_at->translatedFormat('d \d\e M') }}
                                        · {{ $lastPurchase->issued_at->format('H:i') }}
                                    </p>
                                </div>
                            </div>
                            <div class="rounded-xl bg-primary/5 border border-primary/10 p-4">
                                <p class="text-xs text-secondary-foreground mb-1">Valor total</p>
                                <p class="text-2xl font-bold text-primary truncate tabular-nums">
                                    R$ {{ number_format($lastPurchase->total_amount, 2, ',', '.') }}
                                </p>
                            </div>
                            <a href="{{ route('my-purchases.detail', ['invoice' => $lastPurchase->id]) }}"
                               class="kt-btn kt-btn-secondary kt-btn-sm w-full justify-center">
                                <i class="ki-filled ki-eye text-sm"></i>
                                Ver detalhes
                            </a>
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center py-8 text-center">
                            <div class="flex items-center justify-center size-12 rounded-xl bg-secondary/50 mb-3">
                                <i class="ki-filled ki-purchase text-secondary-foreground text-xl"></i>
                            </div>
                            <p class="text-sm text-secondary-foreground mb-3">Nenhuma compra registrada.</p>
                            <a href="{{ route('my-purchases.upload.form') }}" class="kt-btn kt-btn-primary kt-btn-sm">
                                Importar NF-e
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Formas de pagamento --}}
            <div class="kt-card sm:col-span-2 lg:col-span-1">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-wallet text-primary me-1.5 text-base"></i>
                        Formas de Pagamento
                    </h3>
                </div>
                <div class="kt-card-content pb-5">
                    @if($paymentDistribution->isNotEmpty())
                        @php $paymentTotal = $paymentDistribution->sum('total') ?: 1; @endphp
                        <div class="space-y-3.5">
                            @foreach($paymentDistribution as $payment)
                                @php $pct = ($payment->total / $paymentTotal) * 100; @endphp
                                <div>
                                    <div class="flex items-center justify-between gap-2 mb-1.5">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <i class="{{ $paymentIcons[$payment->method] ?? 'ki-filled ki-wallet' }} text-sm text-muted-foreground shrink-0"></i>
                                            <span class="text-sm text-foreground truncate">
                                                {{ $paymentLabels[$payment->method] ?? $payment->method }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <span class="text-xs text-secondary-foreground tabular-nums">
                                                {{ number_format($pct, 0) }}%
                                            </span>
                                            <span class="text-xs font-semibold text-foreground tabular-nums">
                                                R$ {{ number_format($payment->total, 2, ',', '.') }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="kt-progress h-1.5">
                                        <div class="kt-progress-indicator" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center py-8 text-center">
                            <i class="ki-filled ki-wallet text-4xl text-secondary-foreground/30 mb-2"></i>
                            <p class="text-sm text-secondary-foreground">Sem dados de pagamento.</p>
                        </div>
                    @endif
                </div>
            </div>

        </div>

        {{-- ===== ORÇAMENTO DO MÊS ===== --}}
        @if($budgets->isNotEmpty())
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-chart text-primary me-1.5 text-base"></i>
                        Orçamento do Mês
                    </h3>
                    <div class="kt-card-toolbar">
                        <a href="{{ route('budgets.index') }}" class="kt-btn kt-btn-secondary kt-btn-sm">
                            <i class="ki-filled ki-setting-2 text-sm"></i>
                            <span class="hidden sm:inline">Gerenciar</span>
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
                                $label  = $over ? 'Estourado' : ($warn ? 'Atenção' : 'No limite');
                            @endphp
                            <div class="rounded-xl border border-border p-4 space-y-3 hover:border-border/80 transition-colors">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-foreground truncate">
                                            {{ $budget->category->name ?? 'Geral' }}
                                        </p>
                                        <p class="text-xs text-secondary-foreground mt-0.5 tabular-nums">
                                            R$ {{ number_format($budget->spent, 2, ',', '.') }}
                                            <span class="text-secondary-foreground/50">
                                                / R$ {{ number_format($budget->amount, 2, ',', '.') }}
                                            </span>
                                        </p>
                                    </div>
                                    <span class="kt-badge kt-badge-{{ $status }} kt-badge-outline kt-badge-sm shrink-0">
                                        {{ $label }}
                                    </span>
                                </div>
                                <div class="kt-progress h-2">
                                    <div class="kt-progress-indicator kt-progress-{{ $status }}"
                                         style="width: {{ $pct }}%"></div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-secondary-foreground tabular-nums">
                                        Restante: R$ {{ number_format(max($budget->remaining, 0), 2, ',', '.') }}
                                    </span>
                                    <span class="text-xs font-bold tabular-nums {{ $over ? 'text-destructive' : ($warn ? 'text-warning' : 'text-success') }}">
                                        {{ number_format($budget->percentage, 0) }}%
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- ===== GRÁFICO EVOLUÇÃO MENSAL ===== --}}
        @if($monthlyExpenses->isNotEmpty())
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-chart-line text-primary me-1.5 text-base"></i>
                        Evolução de Gastos
                    </h3>
                    <div class="kt-card-toolbar">
                        <span class="kt-badge kt-badge-secondary kt-badge-outline kt-badge-sm">
                            Últimos 12 meses
                        </span>
                    </div>
                </div>
                <div class="kt-card-content pb-5">
                    <div id="monthlyExpensesChart" class="w-full" style="height: 260px;"></div>
                </div>
            </div>
        @endif

        {{-- ===== CATEGORIAS + DISTRIBUIÇÃO DE PAGAMENTOS ===== --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

            {{-- Gastos por categoria --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-category text-primary me-1.5 text-base"></i>
                        Gastos por Categoria
                    </h3>
                    <div class="kt-card-toolbar">
                        <a href="{{ route('categories.index') }}" class="kt-btn kt-btn-secondary kt-btn-sm">
                            <i class="ki-filled ki-setting-2 text-sm"></i>
                            <span class="hidden sm:inline">Gerenciar</span>
                        </a>
                    </div>
                </div>
                <div class="kt-card-content pb-5">
                    @if($spendingByCategory->isNotEmpty())
                        <div id="categoryChart" class="w-full" style="height: 200px;"></div>
                        @php $catTotal = $spendingByCategory->sum('total') ?: 1; @endphp
                        <div class="mt-3 space-y-0.5">
                            @foreach($spendingByCategory->take(6) as $cat)
                                @php $catPct = ($cat->total / $catTotal) * 100; @endphp
                                <div class="flex items-center justify-between gap-3 px-2 py-2 rounded-lg hover:bg-accent/40 transition-colors">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <span class="size-2.5 rounded-full shrink-0"
                                              style="background-color: {{ $cat->category_color }}"></span>
                                        <span class="text-sm text-foreground truncate">{{ $cat->category_name }}</span>
                                    </div>
                                    <div class="flex items-center gap-2.5 shrink-0">
                                        <span class="text-xs text-secondary-foreground tabular-nums">
                                            {{ number_format($catPct, 0) }}%
                                        </span>
                                        <span class="text-sm font-semibold text-foreground tabular-nums">
                                            R$ {{ number_format($cat->total, 2, ',', '.') }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center py-10 text-center">
                            <div class="flex items-center justify-center size-12 rounded-xl bg-secondary/50 mb-3">
                                <i class="ki-filled ki-category text-secondary-foreground text-xl"></i>
                            </div>
                            <p class="text-sm text-secondary-foreground">Sem dados de categoria.</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Distribuição de pagamentos --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-wallet text-primary me-1.5 text-base"></i>
                        Distribuição de Pagamentos
                    </h3>
                </div>
                <div class="kt-card-content pb-5">
                    @if($paymentDistribution->isNotEmpty())
                        <div id="paymentChart" class="w-full" style="height: 200px;"></div>
                        @php $paymentTotal = $paymentDistribution->sum('total') ?: 1; @endphp
                        <div class="mt-3 space-y-0.5">
                            @foreach($paymentDistribution as $payment)
                                @php $pct = ($payment->total / $paymentTotal) * 100; @endphp
                                <div class="flex items-center justify-between gap-3 px-2 py-2 rounded-lg hover:bg-accent/40 transition-colors">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <i class="{{ $paymentIcons[$payment->method] ?? 'ki-filled ki-wallet' }} text-sm text-muted-foreground shrink-0"></i>
                                        <span class="text-sm text-foreground truncate">
                                            {{ $paymentLabels[$payment->method] ?? $payment->method }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2.5 shrink-0">
                                        <span class="text-xs text-secondary-foreground tabular-nums">
                                            {{ number_format($pct, 0) }}%
                                        </span>
                                        <span class="text-sm font-semibold text-foreground tabular-nums">
                                            R$ {{ number_format($payment->total, 2, ',', '.') }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center py-10 text-center">
                            <div class="flex items-center justify-center size-12 rounded-xl bg-secondary/50 mb-3">
                                <i class="ki-filled ki-wallet text-secondary-foreground text-xl"></i>
                            </div>
                            <p class="text-sm text-secondary-foreground">Sem dados de pagamento.</p>
                        </div>
                    @endif
                </div>
            </div>

        </div>

        {{-- ===== TOP EMISSORES + PRODUTOS MAIS COMPRADOS ===== --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

            {{-- Onde você mais gasta --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-shop text-primary me-1.5 text-base"></i>
                        Onde Você Mais Gasta
                    </h3>
                </div>
                <div class="kt-card-content pb-2">
                    @if($topIssuers->isNotEmpty())
                        @foreach($topIssuers as $i => $issuer)
                            <div class="flex items-center justify-between gap-3 px-2 py-3 rounded-lg hover:bg-accent/40 transition-colors border-b border-border/40 last:border-0">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="flex items-center justify-center size-8 rounded-lg shrink-0 font-bold text-xs
                                        {{ $i === 0 ? 'bg-primary/10 text-primary' : 'bg-secondary/60 text-secondary-foreground' }}">
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
                        <div class="flex flex-col items-center justify-center py-10 text-center">
                            <div class="flex items-center justify-center size-12 rounded-xl bg-secondary/50 mb-3">
                                <i class="ki-filled ki-shop text-secondary-foreground text-xl"></i>
                            </div>
                            <p class="text-sm text-secondary-foreground">Sem dados de emissores.</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Produtos mais comprados --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-basket text-primary me-1.5 text-base"></i>
                        Produtos Mais Comprados
                    </h3>
                </div>
                <div class="kt-card-content pb-2">
                    @if($topProducts->isNotEmpty())
                        @foreach($topProducts as $i => $product)
                            <div class="flex items-center justify-between gap-3 px-2 py-3 rounded-lg hover:bg-accent/40 transition-colors border-b border-border/40 last:border-0">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="flex items-center justify-center size-8 rounded-lg shrink-0 font-bold text-xs
                                        {{ $i === 0 ? 'bg-info/10 text-info' : 'bg-secondary/60 text-secondary-foreground' }}">
                                        {{ $i + 1 }}º
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-foreground truncate">
                                            {{ $product->description }}
                                        </p>
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
                        <div class="flex flex-col items-center justify-center py-10 text-center">
                            <div class="flex items-center justify-center size-12 rounded-xl bg-secondary/50 mb-3">
                                <i class="ki-filled ki-basket text-secondary-foreground text-xl"></i>
                            </div>
                            <p class="text-sm text-secondary-foreground">Sem dados de produtos.</p>
                        </div>
                    @endif
                </div>
            </div>

        </div>

    </div>

    <script>
        window.pageConfig = Object.assign(window.pageConfig || {}, {
            monthlyExpenses:     @json($monthlyExpenses),
            spendingByCategory:  @json($spendingByCategory),
            paymentDistribution: @json($paymentDistribution),
            paymentLabels:       @json($paymentLabels),
        });
    </script>

@endsection
