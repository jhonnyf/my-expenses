@extends('layout.main')

@section('content')
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Compras Recorrentes</h1>
                <p class="text-sm text-secondary-foreground">Produtos comprados com frequência</p>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed space-y-5 pb-10">

        {{-- Cards de resumo --}}
        <div class="grid grid-cols-2 gap-5">
            <div class="kt-card">
                <div class="kt-card-content py-4 px-5">
                    <p class="text-xs text-secondary-foreground">Produtos Recorrentes</p>
                    <p class="text-2xl font-bold text-foreground mt-1">{{ $recurring->count() }}</p>
                </div>
            </div>
            <div class="kt-card">
                <div class="kt-card-content py-4 px-5">
                    <p class="text-xs text-secondary-foreground">Gasto Mensal Estimado</p>
                    <p class="text-2xl font-bold text-primary mt-1">
                        R$ {{ number_format($recurring->sum(fn($i) => $i->avg_price * $i->purchases_per_month), 2, ',', '.') }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Tabela de produtos recorrentes --}}
        <div class="kt-card kt-card-grid">
            <div class="kt-card-header">
                <h3 class="kt-card-title">
                    <i class="ki-filled ki-arrows-loop text-primary me-1"></i> Produtos
                </h3>
                <span class="text-xs text-secondary-foreground">Comprados 3 ou mais vezes</span>
            </div>
            <div class="kt-card-content">
                <div class="kt-scrollable-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr>
                                <th class="min-w-[220px]">Produto</th>
                                <th class="min-w-[80px] text-center">Frequência</th>
                                <th class="min-w-[110px] text-right">Preço Médio</th>
                                <th class="min-w-[140px] text-right">Faixa de Preço</th>
                                <th class="min-w-[100px]">Última Compra</th>
                                <th class="min-w-[110px]">Intervalo</th>
                                <th class="min-w-[150px]">Melhor Emissor</th>
                                <th class="w-[60px]"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recurring as $item)
                                @php
                                    $best = $bestIssuers[$item->description] ?? null;
                                @endphp
                                <tr>
                                    <td class="font-medium text-foreground">{{ $item->description }}</td>
                                    <td class="text-center">
                                        <span class="kt-badge kt-badge-primary kt-badge-sm">{{ $item->purchase_count }}x</span>
                                    </td>
                                    <td class="text-right font-mono text-sm">R$ {{ number_format($item->avg_price, 2, ',', '.') }}</td>
                                    <td class="text-right text-sm">
                                        <span class="text-green-600 font-mono">R$ {{ number_format($item->min_price, 2, ',', '.') }}</span>
                                        <span class="text-secondary-foreground mx-0.5">—</span>
                                        <span class="text-red-500 font-mono">R$ {{ number_format($item->max_price, 2, ',', '.') }}</span>
                                    </td>
                                    <td class="text-sm text-secondary-foreground">
                                        {{ $item->last_purchased_at ? \Carbon\Carbon::parse($item->last_purchased_at)->format('d/m/Y') : '—' }}
                                    </td>
                                    <td class="text-sm text-secondary-foreground">
                                        a cada ~{{ $item->avg_interval_days }} dias
                                    </td>
                                    <td class="text-sm text-foreground">
                                        {{ $best->issuer_name ?? '—' }}
                                    </td>
                                    <td>
                                        @if($best && $shoppingLists->isNotEmpty())
                                            <div class="relative">
                                                <button onclick="toggleDropdown(this)"
                                                        class="kt-btn kt-btn-xs kt-btn-outline kt-btn-icon size-7 rounded-md"
                                                        title="Adicionar à lista">
                                                    <i class="ki-filled ki-plus text-xs"></i>
                                                </button>
                                                <div class="hidden absolute end-0 top-full mt-1 w-48 bg-background border border-border rounded-lg shadow-lg z-10">
                                                    @foreach($shoppingLists as $list)
                                                        <button onclick="addToList({{ $list->id }}, '{{ addslashes($item->description) }}', {{ $best->avg_price }}, {{ $best->issuer_id }}, '{{ addslashes($best->unit ?? '') }}', this)"
                                                                class="block w-full text-left px-3 py-2 text-sm hover:bg-accent/30 transition-colors truncate">
                                                            {{ $list->name }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-secondary-foreground py-6">
                                        Nenhum produto recorrente encontrado. Importe mais notas fiscais.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>window.pageConfig = { addToListUrl: '{{ route("recurring-purchases.add-to-list") }}' };</script>
    @section('page-module', 'recurring-purchase')
@endsection
