@extends('layout.main')
@section('page-module', 'recurring-purchase')

@section('content')

    @php
        $monthlyTotal = $recurring->sum(fn($i) => $i->avg_price * $i->purchases_per_month);
        $potentialSavings = $recurring->sum(fn($i) => max($i->avg_price - $i->min_price, 0) * $i->purchases_per_month);
    @endphp

    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Compras Recorrentes</h1>
                <p class="text-sm font-normal text-secondary-foreground">Produtos comprados com frequência</p>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed">
        <div class="grid gap-5 lg:gap-7.5">

            <style>
                .channel-stats-bg {
                    background-image: url('{{ asset('assets/media/images/2600x1600/bg-3.png') }}');
                }
                .dark .channel-stats-bg {
                    background-image: url('{{ asset('assets/media/images/2600x1600/bg-3-dark.png') }}');
                }
            </style>

            <div class="grid grid-cols-2 lg:grid-cols-3 gap-5">

                <div class="kt-card flex-col justify-between gap-6 bg-cover bg-[right_top_-1.7rem] bg-no-repeat channel-stats-bg">
                    <div class="flex items-center justify-center size-10 mt-4 ms-5 rounded-xl bg-primary/10">
                        <i class="ki-filled ki-arrows-loop text-primary text-xl"></i>
                    </div>
                    <div class="flex flex-col gap-1 pb-4 px-5">
                        <span class="text-2xl font-semibold text-mono tabular-nums">{{ $recurring->count() }}</span>
                        <span class="text-sm font-normal text-secondary-foreground">Produtos Recorrentes</span>
                    </div>
                </div>

                <div class="kt-card flex-col justify-between gap-6 bg-cover bg-[right_top_-1.7rem] bg-no-repeat channel-stats-bg">
                    <div class="flex items-center justify-center size-10 mt-4 ms-5 rounded-xl bg-green-500/10">
                        <i class="ki-filled ki-dollar text-green-600 text-xl"></i>
                    </div>
                    <div class="flex flex-col gap-1 pb-4 px-5">
                        <span class="text-2xl font-semibold text-mono tabular-nums truncate">
                            R$ {{ number_format($monthlyTotal, 2, ',', '.') }}
                        </span>
                        <span class="text-sm font-normal text-secondary-foreground">Gasto Mensal Estimado</span>
                    </div>
                </div>

                <div class="kt-card flex-col justify-between gap-6 bg-cover bg-[right_top_-1.7rem] bg-no-repeat channel-stats-bg">
                    <div class="flex items-center justify-center size-10 mt-4 ms-5 rounded-xl bg-violet-500/10">
                        <i class="ki-filled ki-medal-star text-violet-600 text-xl"></i>
                    </div>
                    <div class="flex flex-col gap-1 pb-4 px-5">
                        <span class="text-2xl font-semibold text-mono tabular-nums truncate">
                            R$ {{ number_format($potentialSavings, 2, ',', '.') }}
                        </span>
                        <span class="text-sm font-normal text-secondary-foreground">Economia Potencial/Mês</span>
                    </div>
                </div>

            </div>

            <div class="kt-card kt-card-grid">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">Produtos Recorrentes</h3>
                    <div class="kt-card-toolbar">
                        <span class="kt-badge kt-badge-secondary kt-badge-outline kt-badge-sm">Comprados 3+ vezes</span>
                    </div>
                </div>

                @if($recurring->isNotEmpty())
                    <div class="kt-card-table">
                        <div class="kt-scrollable-x-auto">
                            <table class="kt-table kt-table-border table-fixed">
                                <thead>
                                    <tr>
                                        <th class="min-w-[220px]">Produto</th>
                                        <th class="w-[80px] text-center">Freq</th>
                                        <th class="min-w-[110px] text-end">Preço Médio</th>
                                        <th class="min-w-[150px]">Faixa</th>
                                        <th class="min-w-[110px]">Última Compra</th>
                                        <th class="min-w-[110px]">Intervalo</th>
                                        <th class="min-w-[150px]">Melhor Emissor</th>
                                        <th class="w-[60px]"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recurring as $item)
                                        @php
                                            $best = $bestIssuers[$item->description] ?? null;
                                            $range = max($item->max_price - $item->min_price, 0.01);
                                            $avgPos = min(max((($item->avg_price - $item->min_price) / $range) * 100, 0), 100);
                                            $daysSinceLast = $item->last_purchased_at ? \Carbon\Carbon::parse($item->last_purchased_at)->diffInDays(now()) : null;
                                            $isDue = $daysSinceLast !== null && $daysSinceLast >= $item->avg_interval_days;
                                        @endphp
                                        <tr class="transition-colors hover:bg-accent/40">
                                            <td class="font-medium text-foreground truncate py-2.5">{{ $item->description }}</td>
                                            <td class="text-center py-2.5">
                                                <span class="kt-badge kt-badge-primary kt-badge-sm">{{ $item->purchase_count }}×</span>
                                            </td>
                                            <td class="text-end font-mono text-sm tabular-nums py-2.5">R$ {{ number_format($item->avg_price, 2, ',', '.') }}</td>
                                            <td class="text-sm py-2.5">
                                                <div class="flex items-center justify-between text-xs mb-1">
                                                    <span class="text-green-600 font-mono tabular-nums">R$ {{ number_format($item->min_price, 2, ',', '.') }}</span>
                                                    <span class="text-destructive font-mono tabular-nums">R$ {{ number_format($item->max_price, 2, ',', '.') }}</span>
                                                </div>
                                                <div class="relative h-1.5 rounded-full bg-gradient-to-r from-green-500 to-red-500">
                                                    <span class="absolute top-1/2 -translate-y-1/2 size-2.5 rounded-full bg-mono ring-2 ring-background"
                                                          style="left: calc({{ $avgPos }}% - 5px)"
                                                          title="Preço médio: R$ {{ number_format($item->avg_price, 2, ',', '.') }}"></span>
                                                </div>
                                            </td>
                                            <td class="text-sm text-secondary-foreground py-2.5">
                                                <p>{{ $item->last_purchased_at ? \Carbon\Carbon::parse($item->last_purchased_at)->format('d/m/Y') : '—' }}</p>
                                                @if($isDue)
                                                    <span class="kt-badge kt-badge-warning kt-badge-outline kt-badge-sm mt-1">
                                                        <i class="ki-filled ki-time text-2xs"></i> Hora de comprar
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-sm text-secondary-foreground py-2.5">
                                                ~{{ $item->avg_interval_days }} dias
                                                <p class="text-xs">{{ $item->purchases_per_month }}×/mês</p>
                                            </td>
                                            <td class="text-sm text-foreground truncate py-2.5">
                                                {{ $best->issuer_name ?? '—' }}
                                            </td>
                                            <td>
                                                @if($shoppingLists->isNotEmpty() && $best)
                                                    <div class="kt-menu" data-kt-menu="true">
                                                        <div class="kt-menu-item" data-kt-menu-item-toggle="dropdown" data-kt-menu-item-trigger="click"
                                                             data-kt-menu-item-placement="bottom-end" data-kt-menu-item-offset="0, 5px">
                                                            <button class="kt-menu-toggle kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm" title="Adicionar à lista">
                                                                <i class="ki-filled ki-plus text-base"></i>
                                                            </button>
                                                            <div class="kt-menu-dropdown kt-menu-default w-48" data-kt-menu-dismiss="true">
                                                                @foreach($shoppingLists as $list)
                                                                    <div class="kt-menu-item">
                                                                        <button class="kt-menu-link w-full text-left"
                                                                                data-action="add-to-list"
                                                                                data-list-id="{{ $list->id }}"
                                                                                data-description="{{ $item->description }}"
                                                                                data-unit-price="{{ $best->avg_price ?? 0 }}"
                                                                                data-issuer-id="{{ $best->issuer_id ?? '' }}"
                                                                                data-unit="{{ $best->unit ?? '' }}">
                                                                            <span class="kt-menu-title truncate">{{ $list->name }}</span>
                                                                        </button>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="kt-card-content">
                        <div class="flex flex-col items-center justify-center py-12 text-center">
                            <i class="ki-filled ki-arrows-loop text-5xl text-secondary-foreground/30 mb-4"></i>
                            <p class="text-sm font-medium text-foreground mb-1">Nenhum produto recorrente.</p>
                            <p class="text-sm text-secondary-foreground">Importe mais NF-e para identificar padrões de compra.</p>
                            <a href="{{ route('my-purchases.upload.form') }}" class="kt-btn kt-btn-primary kt-btn-sm mt-4">
                                <i class="ki-filled ki-file-up"></i> Importar NF-e
                            </a>
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
        addToListUrl: '{{ route("recurring-purchases.add-to-list") }}',
    });
</script>
@endpush
