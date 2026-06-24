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
    {{-- Cabeçalho --}}
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div>
                <h1 class="text-xl font-medium leading-none text-mono">
                    NFC-e Nº {{ $invoice->number }} / Série {{ $invoice->series }}
                </h1>
                <p class="text-sm text-secondary-foreground mt-1 font-mono">
                    Chave: {{ wordwrap($invoice->access_key, 11, ' ', true) }}
                </p>
            </div>
            <div class="flex items-center gap-2.5">
                @if($invoice->environment === 'staging')
                    <span class="kt-badge kt-badge-warning">Homologação</span>
                @else
                    <span class="kt-badge kt-badge-success">Produção</span>
                @endif
                <a href="{{ route('my-purchases.index') }}" class="kt-btn kt-btn-outline">
                    <i class="ki-filled ki-arrow-left"></i> Voltar
                </a>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed space-y-5 pb-10">

        {{-- Resumo + Emitente --}}
        <div class="grid lg:grid-cols-2 gap-5">

            {{-- Emitente --}}
            <div class="kt-card">
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
                        <div>
                            <p class="text-xs text-secondary-foreground">Razão Social</p>
                            <p class="font-semibold text-foreground">{{ $invoice->issuer->name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-secondary-foreground">CNPJ</p>
                            <p class="font-medium">{{ $invoice->issuer->cnpj ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-secondary-foreground">Endereço</p>
                            <p class="font-medium">
                                @if($invoice->issuer)
                                    {{ $invoice->issuer->street }}, {{ $invoice->issuer->street_number }}
                                    — {{ $invoice->issuer->neighborhood }},
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
                </div>
            </div>

            {{-- Totais --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-dollar text-primary me-1"></i> Totais
                    </h3>
                    <span class="text-xs text-secondary-foreground">
                        Emitida em {{ $invoice->issued_at->format('d/m/Y H:i') }}
                    </span>
                </div>
                <div class="kt-card-content pb-5">
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-secondary-foreground">Valor dos produtos</span>
                            <span class="font-medium">R$ {{ number_format($invoice->total_products, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-secondary-foreground">Base de cálculo ICMS</span>
                            <span class="font-medium">R$ {{ number_format($invoice->total_icms_base, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-secondary-foreground">ICMS</span>
                            <span class="font-medium">R$ {{ number_format($invoice->total_icms, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-secondary-foreground">Tributos aprox.</span>
                            <span class="font-medium">R$ {{ number_format($invoice->total_taxes, 2, ',', '.') }}</span>
                        </div>
                        <div class="border-t border-border pt-3 flex justify-between">
                            <span class="font-semibold text-foreground">Total da nota</span>
                            <span class="font-bold text-lg text-primary">R$ {{ number_format($invoice->total_amount, 2, ',', '.') }}</span>
                        </div>
                    </div>

                    @if($invoice->payments->isNotEmpty())
                        <div class="mt-5 pt-4 border-t border-border space-y-2">
                            <p class="text-xs font-semibold text-secondary-foreground uppercase tracking-wide">Pagamentos</p>
                            @foreach($invoice->payments as $payment)
                                <div class="flex justify-between text-sm">
                                    <span class="text-secondary-foreground">{{ $paymentLabels[$payment->method] ?? $payment->method }}</span>
                                    <span class="font-medium">R$ {{ number_format($payment->amount, 2, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Itens --}}
        <div class="kt-card kt-card-grid">
            <div class="kt-card-header">
                <h3 class="kt-card-title">
                    <i class="ki-filled ki-basket text-primary me-1"></i> Itens da nota
                </h3>
                <span class="text-xs text-secondary-foreground">{{ $invoice->items->count() }} {{ $invoice->items->count() === 1 ? 'item' : 'itens' }}</span>
            </div>
            <div class="kt-card-content">
                <div class="kt-scrollable-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr>
                                <th class="w-10 text-center">#</th>
                                <th class="min-w-[260px]">Descrição</th>
                                <th class="min-w-[120px]">Categoria</th>
                                <th class="min-w-[100px]">Código</th>
                                <th class="min-w-[80px]">NCM</th>
                                <th class="min-w-[70px]">CFOP</th>
                                <th class="min-w-[90px] text-right">Qtd</th>
                                <th class="min-w-[60px] text-center">Un.</th>
                                <th class="min-w-[110px] text-right">Vl. Unit.</th>
                                <th class="min-w-[110px] text-right">Vl. Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invoice->items as $item)
                                <tr>
                                    <td class="text-center text-secondary-foreground">{{ $item->item_number }}</td>
                                    <td class="font-medium text-foreground">{{ $item->description }}</td>
                                    <td>
                                        <select onchange="assignCategory({{ $item->id }}, this.value)"
                                                class="text-xs bg-accent border border-border rounded px-2 py-1 cursor-pointer">
                                            <option value="">—</option>
                                            @foreach($categories as $cat)
                                                <option value="{{ $cat->id }}" {{ $item->category_id == $cat->id ? 'selected' : '' }}>
                                                    {{ $cat->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="text-secondary-foreground font-mono text-xs">{{ $item->code }}</td>
                                    <td class="text-secondary-foreground font-mono text-xs">{{ $item->ncm ?: '—' }}</td>
                                    <td class="text-secondary-foreground font-mono text-xs">{{ $item->cfop ?: '—' }}</td>
                                    <td class="text-right font-mono text-sm">{{ rtrim(rtrim(number_format($item->quantity, 4, ',', '.'), '0'), ',') }}</td>
                                    <td class="text-center text-secondary-foreground">{{ $item->unit }}</td>
                                    <td class="text-right font-mono text-sm">R$ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                                    <td class="text-right font-semibold font-mono text-sm">R$ {{ number_format($item->total_price, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-secondary-foreground py-6">Nenhum item encontrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script>window.pageConfig = { assignCategoryUrl: '{{ route("categories.assign-item") }}', issuerBaseUrl: '{{ url("issuers") }}' };</script>
    @section('page-module', 'issuer-favorite,invoice-detail')
@endsection
