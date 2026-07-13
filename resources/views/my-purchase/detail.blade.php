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
        'pix'             => 'Pix',
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
    {{-- Header --}}
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex items-center gap-4">
                <div class="flex items-center justify-center size-12 rounded-xl bg-primary/10 shrink-0">
                    <i class="ki-filled ki-document text-primary text-xl"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-xl font-medium leading-none text-mono">
                            NFC-e Nº {{ $invoice->number }} / Série {{ $invoice->series }}
                        </h1>
                        @if($invoice->environment === 'staging')
                            <span class="kt-badge kt-badge-warning kt-badge-outline kt-badge-sm">Homologação</span>
                        @else
                            <span class="kt-badge kt-badge-success kt-badge-outline kt-badge-sm">Produção</span>
                        @endif
                    </div>
                    <p class="text-sm text-secondary-foreground mt-1.5">
                        <i class="ki-filled ki-calendar text-xs me-1"></i>
                        {{ $invoice->issued_at->format('d/m/Y') }} às {{ $invoice->issued_at->format('H:i') }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <a href="{{ route('my-purchases.upload.form') }}" class="kt-btn kt-btn-primary">
                    <i class="ki-filled ki-cloud-add"></i> Importar Nova NF
                </a>
                <a href="{{ route('my-purchases.index') }}" class="kt-btn kt-btn-outline">
                    <i class="ki-filled ki-arrow-left"></i> Voltar
                </a>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed space-y-5 pb-10">

        {{-- Cards de destaque: Total, Produtos, Impostos, Itens --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="kt-card">
                <div class="kt-card-content py-5 px-5">
                    <div class="flex items-center justify-between">
                        <div class="min-w-0">
                            <p class="text-xs text-secondary-foreground mb-2">Total da Nota</p>
                            <p class="text-xl sm:text-2xl font-bold text-primary truncate">R$ {{ number_format($invoice->total_amount, 2, ',', '.') }}</p>
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
                            <p class="text-xs text-secondary-foreground mb-2">Valor Produtos</p>
                            <p class="text-xl sm:text-2xl font-bold text-foreground truncate">R$ {{ number_format($invoice->total_products, 2, ',', '.') }}</p>
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
                            <p class="text-xs text-secondary-foreground mb-2">Tributos Aprox.</p>
                            <p class="text-xl sm:text-2xl font-bold text-foreground truncate">R$ {{ number_format($invoice->total_taxes, 2, ',', '.') }}</p>
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
                            <p class="text-xs text-secondary-foreground mb-2">Itens</p>
                            <p class="text-xl sm:text-2xl font-bold text-foreground">{{ $invoice->items->count() }}</p>
                        </div>
                        <div class="flex items-center justify-center size-10 rounded-lg bg-success/10 shrink-0">
                            <i class="ki-filled ki-basket text-success text-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Emitente + Detalhes fiscais + Pagamentos --}}
        <div class="grid lg:grid-cols-3 gap-5">

            {{-- Emitente --}}
            <div class="kt-card lg:col-span-2">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-shop text-primary me-1"></i> Emitente
                    </h3>
                    @if($invoice->issuer)
                        <button data-favorite-id="{{ $invoice->issuer_id }}" id="btnFavoriteIssuer"
                                class="text-lg transition-colors {{ $isIssuerFavorite ? 'text-yellow-500' : 'text-muted-foreground hover:text-yellow-500' }}"
                                title="{{ $isIssuerFavorite ? 'Remover dos favoritos' : 'Favoritar emitente' }}">
                            <i class="ki-filled ki-star"></i>
                        </button>
                    @endif
                </div>
                <div class="kt-card-content pb-5">
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-10 rounded-lg bg-primary/10 shrink-0">
                                <i class="ki-filled ki-shop text-primary"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-foreground truncate">{{ $invoice->issuer->name ?? '—' }}</p>
                                <p class="text-xs text-secondary-foreground font-mono">{{ $invoice->issuer->cnpj ?? '—' }}</p>
                            </div>
                        </div>

                        <div class="border-t border-border pt-3">
                            <div class="flex items-start gap-2">
                                <i class="ki-filled ki-geolocation text-secondary-foreground text-sm mt-0.5 shrink-0"></i>
                                <p class="text-sm text-foreground">
                                    @if($invoice->issuer)
                                        {{ $invoice->issuer->street }}, {{ $invoice->issuer->street_number }}
                                        — {{ $invoice->issuer->neighborhood }}<br>
                                        {{ $invoice->issuer->city }}/{{ $invoice->issuer->state }}
                                        @if($invoice->issuer->zip_code)
                                            — CEP {{ $invoice->issuer->zip_code }}
                                        @endif
                                    @else
                                        —
                                    @endif
                                </p>
                            </div>
                        </div>

                        @if($invoice->issuer && $invoice->issuer->street && $invoice->issuer->city)
                            @php
                                $mapAddress = implode(', ', array_filter([
                                    $invoice->issuer->street,
                                    $invoice->issuer->street_number,
                                    $invoice->issuer->neighborhood,
                                    $invoice->issuer->city,
                                    $invoice->issuer->state,
                                ]));
                            @endphp
                            <div class="mt-3 rounded-lg overflow-hidden border border-border">
                                <iframe
                                    width="100%"
                                    height="300"
                                    style="border:0;"
                                    loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade"
                                    src="https://maps.google.com/maps?q={{ urlencode($mapAddress) }}&t=&z=16&ie=UTF8&iwloc=&output=embed"
                                    allowfullscreen>
                                </iframe>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Detalhes Fiscais --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-document text-primary me-1"></i> Detalhes Fiscais
                    </h3>
                </div>
                <div class="kt-card-content pb-5">
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-secondary-foreground">Valor dos produtos</span>
                            <span class="font-medium font-mono text-sm">R$ {{ number_format($invoice->total_products, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-secondary-foreground">Base de cálculo ICMS</span>
                            <span class="font-medium font-mono text-sm">R$ {{ number_format($invoice->total_icms_base, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-secondary-foreground">ICMS</span>
                            <span class="font-medium font-mono text-sm">R$ {{ number_format($invoice->total_icms, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-secondary-foreground">Tributos aprox.</span>
                            <span class="font-medium font-mono text-sm">R$ {{ number_format($invoice->total_taxes, 2, ',', '.') }}</span>
                        </div>
                        <div class="border-t border-border pt-3 flex justify-between items-center">
                            <span class="font-semibold text-foreground">Total da nota</span>
                            <span class="font-bold text-lg text-primary font-mono">R$ {{ number_format($invoice->total_amount, 2, ',', '.') }}</span>
                        </div>
                    </div>

                    {{-- Chave de acesso --}}
                    <div class="mt-5 pt-4 border-t border-border">
                        <p class="text-xs text-secondary-foreground mb-1.5">Chave de Acesso</p>
                        <p class="text-xs font-mono text-foreground bg-accent rounded-lg px-3 py-2 break-all leading-relaxed select-all">
                            {{ wordwrap($invoice->access_key, 4, ' ', true) }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Pagamentos --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-wallet text-primary me-1"></i> Pagamentos
                    </h3>
                </div>
                <div class="kt-card-content pb-5">
                    @if($invoice->payments->isNotEmpty())
                        <div class="space-y-3">
                            @foreach($invoice->payments as $payment)
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center justify-center size-9 rounded-lg bg-accent shrink-0">
                                            <i class="{{ $paymentIcons[$payment->method] ?? 'ki-filled ki-wallet' }} text-secondary-foreground text-sm"></i>
                                        </div>
                                        <span class="text-sm text-foreground">{{ $paymentLabels[$payment->method] ?? $payment->method }}</span>
                                    </div>
                                    <span class="font-semibold font-mono text-sm">R$ {{ number_format($payment->amount, 2, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>

                        @if($invoice->payments->count() > 1)
                            <div class="border-t border-border mt-4 pt-3 flex justify-between items-center">
                                <span class="text-sm font-semibold text-foreground">Total pago</span>
                                <span class="font-bold text-primary font-mono">R$ {{ number_format($invoice->payments->sum('amount'), 2, ',', '.') }}</span>
                            </div>
                        @endif
                    @else
                        <div class="flex flex-col items-center justify-center py-8 text-secondary-foreground">
                            <i class="ki-filled ki-wallet text-2xl mb-2"></i>
                            <p class="text-sm">Sem dados de pagamento</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Itens --}}
        <div class="kt-card kt-card-grid">
            <div class="kt-card-header">
                <h3 class="kt-card-title">
                    <i class="ki-filled ki-basket text-primary me-1"></i> Itens da Nota
                </h3>
                <span class="kt-badge kt-badge-primary kt-badge-outline kt-badge-sm">
                    {{ $invoice->items->count() }} {{ $invoice->items->count() === 1 ? 'item' : 'itens' }}
                </span>
            </div>
            <div class="kt-card-content">
                <div class="kt-scrollable-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr>
                                <th class="w-10 text-center">#</th>
                                <th class="min-w-[280px]">Descrição</th>
                                <th class="min-w-[150px]">Categoria</th>
                                <th class="min-w-[90px] text-right">Qtd</th>
                                <th class="min-w-[60px] text-center">Un.</th>
                                <th class="min-w-[110px] text-right">Vl. Unit.</th>
                                <th class="min-w-[110px] text-right">Vl. Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $itemsTotal = $invoice->items->sum('total_price') ?: 1; @endphp
                            @forelse($invoice->items as $item)
                                @php $share = ($item->total_price / $itemsTotal) * 100; @endphp
                                <tr class="transition-colors duration-150 hover:bg-accent/40">
                                    <td class="text-center text-secondary-foreground">{{ $item->item_number }}</td>
                                    <td class="py-2.5">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <span class="font-medium text-foreground truncate">{{ $item->description }}</span>
                                            <button type="button"
                                                    class="shrink-0 text-muted-foreground hover:text-foreground transition-colors"
                                                    data-kt-tooltip="true" data-kt-tooltip-placement="top">
                                                <i class="ki-filled ki-information-2 text-sm"></i>
                                                <span data-kt-tooltip-content="true" class="kt-tooltip">
                                                    Código: {{ $item->code ?: '—' }} &middot;
                                                    NCM: {{ $item->ncm ?: '—' }} &middot;
                                                    CFOP: {{ $item->cfop ?: '—' }}
                                                </span>
                                            </button>
                                        </div>
                                        <div class="kt-progress h-1 mt-1.5 max-w-32">
                                            <div class="kt-progress-indicator" style="width: {{ min($share, 100) }}%"></div>
                                        </div>
                                    </td>
                                    <td class="py-2.5">
                                        <div class="flex items-center gap-1.5">
                                            <span class="category-dot size-2 rounded-full shrink-0" style="background-color: {{ $item->category->color ?? '#94a3b8' }}"></span>
                                            <select data-action="assign-category" data-item-id="{{ $item->id }}"
                                                    class="text-xs bg-accent border border-border rounded-md px-2 py-1 cursor-pointer w-full focus:outline-none focus:ring-1 focus:ring-primary">
                                                <option value="" data-color="#94a3b8">Sem categoria</option>
                                                @foreach($categories as $cat)
                                                    <option value="{{ $cat->id }}" data-color="{{ $cat->color }}" {{ $item->category_id == $cat->id ? 'selected' : '' }}>
                                                        {{ $cat->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </td>
                                    <td class="text-right font-mono text-sm py-2.5">{{ rtrim(rtrim(number_format($item->quantity, 4, ',', '.'), '0'), ',') }}</td>
                                    <td class="text-center text-secondary-foreground py-2.5">{{ $item->unit }}</td>
                                    <td class="text-right font-mono text-sm py-2.5">R$ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                                    <td class="text-right font-semibold font-mono text-sm py-2.5">R$ {{ number_format($item->total_price, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-secondary-foreground py-6">Nenhum item encontrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    @section('page-module', 'issuer-favorite,invoice-detail')
@endsection

@push('scripts')
<script>
    window.pageConfig = Object.assign(window.pageConfig || {}, {
        assignCategoryUrl: '{{ route("categories.assign-item") }}',
        issuerBaseUrl: '{{ url("issuers") }}',
    });
</script>
@endpush
