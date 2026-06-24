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
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="kt-card">
                <div class="kt-card-content py-4 px-5">
                    <p class="text-xs text-secondary-foreground">Total de Gastos</p>
                    <p class="text-2xl font-bold text-foreground mt-1">R$ {{ number_format($totalExpenses, 2, ',', '.') }}</p>
                </div>
            </div>
            <div class="kt-card">
                <div class="kt-card-content py-4 px-5">
                    <p class="text-xs text-secondary-foreground">Total de Impostos</p>
                    <p class="text-2xl font-bold text-foreground mt-1">R$ {{ number_format($totalTaxes, 2, ',', '.') }}</p>
                </div>
            </div>
            <div class="kt-card">
                <div class="kt-card-content py-4 px-5">
                    <p class="text-xs text-secondary-foreground">Total de Compras</p>
                    <p class="text-2xl font-bold text-foreground mt-1">{{ $totalPurchases }}</p>
                </div>
            </div>
            <div class="kt-card">
                <div class="kt-card-content py-4 px-5">
                    <p class="text-xs text-secondary-foreground">Ticket Médio</p>
                    <p class="text-2xl font-bold text-primary mt-1">R$ {{ number_format($averageTicket, 2, ',', '.') }}</p>
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
                            <div class="flex items-center gap-2">
                                @if($monthVariation > 0)
                                    <span class="kt-badge kt-badge-danger kt-badge-sm">
                                        <i class="ki-filled ki-arrow-up text-xs me-0.5"></i> +{{ number_format($monthVariation, 1) }}%
                                    </span>
                                    <span class="text-xs text-secondary-foreground">a mais que o mês anterior</span>
                                @elseif($monthVariation < 0)
                                    <span class="kt-badge kt-badge-success kt-badge-sm">
                                        <i class="ki-filled ki-arrow-down text-xs me-0.5"></i> {{ number_format($monthVariation, 1) }}%
                                    </span>
                                    <span class="text-xs text-secondary-foreground">a menos que o mês anterior</span>
                                @else
                                    <span class="kt-badge kt-badge-secondary kt-badge-sm">Igual</span>
                                @endif
                            </div>
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
                            <div>
                                <p class="text-xs text-secondary-foreground">Estabelecimento</p>
                                <p class="font-semibold text-foreground">{{ $lastPurchase->issuer->name ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-secondary-foreground">Data</p>
                                <p class="font-medium">{{ $lastPurchase->issued_at->format('d/m/Y H:i') }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-secondary-foreground">Valor</p>
                                <p class="text-xl font-bold text-primary">R$ {{ number_format($lastPurchase->total_amount, 2, ',', '.') }}</p>
                            </div>
                            <a href="{{ route('my-purchases.detail', ['invoice' => $lastPurchase->id]) }}" class="kt-btn kt-btn-sm kt-btn-outline w-full mt-2">
                                Ver detalhes
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
                        <div class="space-y-3">
                            @php
                                $paymentTotal = $paymentDistribution->sum('total');
                            @endphp
                            @foreach($paymentDistribution as $payment)
                                @php
                                    $pct = $paymentTotal > 0 ? ($payment->total / $paymentTotal) * 100 : 0;
                                @endphp
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-sm text-foreground">{{ $paymentLabels[$payment->method] ?? $payment->method }}</span>
                                        <span class="text-xs font-medium text-secondary-foreground">{{ number_format($pct, 0) }}%</span>
                                    </div>
                                    <div class="w-full bg-accent rounded-full h-2">
                                        <div class="bg-primary rounded-full h-2" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <p class="text-xs text-secondary-foreground mt-0.5">R$ {{ number_format($payment->total, 2, ',', '.') }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-secondary-foreground py-4">Sem dados de pagamento.</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Gráfico de gastos mensais --}}
        <div class="kt-card">
            <div class="kt-card-header">
                <h3 class="kt-card-title">
                    <i class="ki-filled ki-chart-line text-primary me-1"></i> Gastos Mensais
                </h3>
                <span class="text-xs text-secondary-foreground">Últimos 12 meses</span>
            </div>
            <div class="kt-card-content pb-5">
                @if($monthlyExpenses->isNotEmpty())
                    @php
                        $maxMonthly = $monthlyExpenses->max('total') ?: 1;
                    @endphp
                    <div class="flex items-end gap-2 h-48">
                        @foreach($monthlyExpenses as $month)
                            @php
                                $height = ($month->total / $maxMonthly) * 100;
                                $label = \Carbon\Carbon::createFromFormat('Y-m', $month->month)->translatedFormat('M/y');
                            @endphp
                            <div class="flex-1 flex flex-col items-center gap-1">
                                <span class="text-xs font-mono text-secondary-foreground">
                                    R$ {{ number_format($month->total, 0, ',', '.') }}
                                </span>
                                <div class="w-full bg-primary/80 rounded-t-md transition-all hover:bg-primary"
                                     style="height: {{ max($height, 2) }}%"
                                     title="R$ {{ number_format($month->total, 2, ',', '.') }}">
                                </div>
                                <span class="text-xs text-secondary-foreground">{{ $label }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-secondary-foreground py-4">Sem dados para exibir.</p>
                @endif
            </div>
        </div>

        {{-- Gastos por Categoria --}}
        <div class="kt-card">
            <div class="kt-card-header">
                <h3 class="kt-card-title">
                    <i class="ki-filled ki-category text-primary me-1"></i> Gastos por Categoria
                </h3>
                <a href="{{ route('categories.index') }}" class="text-xs text-primary hover:underline">Gerenciar</a>
            </div>
            <div class="kt-card-content pb-5">
                @if($spendingByCategory->isNotEmpty())
                    @php
                        $catTotal = $spendingByCategory->sum('total') ?: 1;
                    @endphp
                    <div class="space-y-3">
                        @foreach($spendingByCategory as $cat)
                            @php
                                $catPct = ($cat->total / $catTotal) * 100;
                            @endphp
                            <div>
                                <div class="flex justify-between items-center mb-1">
                                    <div class="flex items-center gap-2">
                                        <span class="size-2.5 rounded-full shrink-0" style="background-color: {{ $cat->category_color }}"></span>
                                        <span class="text-sm text-foreground">{{ $cat->category_name }}</span>
                                    </div>
                                    <span class="text-xs font-medium text-secondary-foreground">{{ number_format($catPct, 0) }}%</span>
                                </div>
                                <div class="w-full bg-accent rounded-full h-2">
                                    <div class="rounded-full h-2" style="width: {{ $catPct }}%; background-color: {{ $cat->category_color }}"></div>
                                </div>
                                <p class="text-xs text-secondary-foreground mt-0.5">R$ {{ number_format($cat->total, 2, ',', '.') }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-secondary-foreground py-4">Sem dados de categoria.</p>
                @endif
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
                                <div class="flex items-center justify-between py-3 px-1">
                                    <div class="flex items-center gap-3">
                                        <span class="flex items-center justify-center size-7 rounded-full bg-primary/10 text-primary text-xs font-bold">
                                            {{ $i + 1 }}
                                        </span>
                                        <div>
                                            <p class="text-sm font-medium text-foreground">{{ $issuer->name }}</p>
                                            <p class="text-xs text-secondary-foreground">{{ $issuer->count }} {{ $issuer->count == 1 ? 'compra' : 'compras' }}</p>
                                        </div>
                                    </div>
                                    <span class="font-semibold font-mono text-sm">R$ {{ number_format($issuer->total, 2, ',', '.') }}</span>
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
                                <div class="flex items-center justify-between py-3 px-1">
                                    <div class="flex items-center gap-3">
                                        <span class="flex items-center justify-center size-7 rounded-full bg-accent text-foreground text-xs font-bold">
                                            {{ $i + 1 }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-foreground truncate">{{ $product->description }}</p>
                                            <p class="text-xs text-secondary-foreground">Preço médio: R$ {{ number_format($product->avg_price, 2, ',', '.') }}</p>
                                        </div>
                                    </div>
                                    <span class="kt-badge kt-badge-primary kt-badge-sm shrink-0">{{ $product->frequency }}x</span>
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
@endsection
