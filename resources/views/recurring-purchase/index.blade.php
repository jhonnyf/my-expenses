@extends('layout.main')
@section('page-module', 'recurring-purchase')

@section('content')

    @php
        $statusLabels = [
            'active' => 'Ativos',
            'late' => 'Atrasados',
            'due' => 'Na hora',
            'soon' => 'Chegando',
            'ok' => 'Em dia',
            'inactive' => 'Parados',
            'all' => 'Todos',
        ];
        $sortLabels = [
            'due' => 'Mais urgentes',
            'frequency' => 'Mais frequentes',
            'spend' => 'Maior gasto',
            'saving' => 'Maior economia',
            'name' => 'Nome (A–Z)',
        ];
        $badge = [
            'late' => ['kt-badge-destructive', 'Atrasado'],
            'due' => ['kt-badge-warning', 'Na hora de comprar'],
            'soon' => ['kt-badge-info', 'Chega logo'],
            'ok' => ['kt-badge-secondary', 'Em dia'],
            'inactive' => ['kt-badge-secondary', 'Parado'],
        ];
        $brl = fn ($value) => 'R$ '.number_format($value, 2, ',', '.');
    @endphp

    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Compras Recorrentes</h1>
                <p class="text-sm font-normal text-secondary-foreground">Produtos que você compra com frequência e quando repor</p>
            </div>
            @if($summary['due_count'] > 0)
                <button type="button" id="replenishment-btn" class="kt-btn kt-btn-mono" data-url="{{ route('recurring-purchases.replenishment-list') }}">
                    <i class="ki-filled ki-basket"></i> Criar lista com o que está na hora ({{ $summary['due_count'] }})
                </button>
            @endif
        </div>
    </div>

    <div class="kt-container-fixed">
        <div class="grid gap-5 lg:gap-7.5">

            <div id="pageFlash" role="status" class="hidden items-center gap-3 rounded-lg border border-green-500/30 bg-green-500/10 px-4 py-3">
                <i class="ki-filled ki-check-circle text-lg shrink-0"></i>
                <span class="text-sm font-medium" data-flash-text></span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                @foreach([
                    ['ki-arrows-loop', 'text-primary', 'bg-primary/10', $summary['products'], 'Produtos recorrentes'],
                    ['ki-time', 'text-yellow-600', 'bg-yellow-500/10', $summary['due_count'], 'Na hora ou atrasados'],
                    ['ki-dollar', 'text-green-600', 'bg-green-500/10', $brl($summary['monthly_total']), 'Gasto mensal estimado'],
                    ['ki-medal-star', 'text-violet-600', 'bg-violet-500/10', $brl($summary['potential_saving']), 'Economia potencial/mês'],
                ] as [$icon, $color, $bg, $value, $label])
                    <div class="kt-card">
                        <div class="kt-card-content flex flex-col gap-4 p-5">
                            <div class="flex items-center justify-center size-10 rounded-xl {{ $bg }}">
                                <i class="ki-filled {{ $icon }} {{ $color }} text-xl"></i>
                            </div>
                            <div class="flex flex-col gap-1">
                                <span class="text-2xl font-semibold text-mono tabular-nums truncate">{{ $value }}</span>
                                <span class="text-sm text-secondary-foreground">{{ $label }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="kt-card">
                <div class="kt-card-header flex-wrap gap-3">
                    <h3 class="kt-card-title">{{ $filters['dismissed'] ? 'Produtos ocultados' : 'Produtos recorrentes' }}</h3>
                    <form method="GET" class="flex flex-wrap items-center gap-2">
                        @if($filters['dismissed'])<input type="hidden" name="dismissed" value="1">@endif
                        <input type="text" name="q" value="{{ $filters['q'] }}" maxlength="100" placeholder="Buscar produto" class="kt-input w-48">
                        <select name="status" class="kt-select w-36" onchange="this.form.submit()">
                            @foreach($statusLabels as $value => $label)
                                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <select name="sort" class="kt-select w-44" onchange="this.form.submit()">
                            @foreach($sortLabels as $value => $label)
                                <option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="kt-btn kt-btn-mono"><i class="ki-filled ki-magnifier"></i> Buscar</button>
                    </form>
                </div>

                <div class="kt-card-content p-0">
                    @forelse($recurring as $item)
                        @php
                            [$badgeClass, $badgeLabel] = $badge[$item->status];
                            $source = $item->best_issuer ?? $item->last_issuer;
                            $range = max($item->max_price - $item->min_price, 0.01);
                            $avgPos = min(max((($item->avg_price - $item->min_price) / $range) * 100, 0), 100);
                            $timing = $item->days_until_due > 0
                                ? 'próxima em ~'.$item->days_until_due.' '.($item->days_until_due === 1 ? 'dia' : 'dias')
                                : ($item->days_until_due === 0 ? 'previsto para hoje' : 'atrasado '.abs($item->days_until_due).' '.(abs($item->days_until_due) === 1 ? 'dia' : 'dias'));
                        @endphp
                        <div class="flex flex-col lg:flex-row lg:items-center gap-4 p-5 border-b border-border last:border-b-0" data-recurring-row>
                            <div class="flex flex-col gap-1 lg:w-1/4 min-w-0">
                                <p class="text-sm font-medium text-foreground truncate" title="{{ $item->description }}">
                                    {{ $item->description }}@if($item->unit !== '') <span class="text-secondary-foreground">({{ $item->unit }})</span>@endif
                                </p>
                                @unless($item->dismissed)
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="kt-badge kt-badge-sm kt-badge-light {{ $badgeClass }}">{{ $badgeLabel }}</span>
                                        @if($item->status !== 'inactive')<span class="text-xs text-secondary-foreground">{{ $timing }}</span>@endif
                                    </div>
                                @endunless
                                <span class="text-xs text-secondary-foreground">
                                    ~{{ $item->interval_days }} dias · {{ number_format($item->purchases_per_month, 1, ',', '.') }}×/mês · {{ $item->purchase_count }} compras · última {{ \Carbon\Carbon::parse($item->last_purchased_at)->format('d/m/Y') }}
                                </span>
                            </div>

                            <div class="lg:w-1/4">
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span class="text-green-600 font-mono tabular-nums">{{ $brl($item->min_price) }}</span>
                                    <span class="text-secondary-foreground">média {{ $brl($item->avg_price) }}</span>
                                    <span class="text-destructive font-mono tabular-nums">{{ $brl($item->max_price) }}</span>
                                </div>
                                <div class="relative h-1.5 rounded-full bg-gradient-to-r from-green-500 to-red-500">
                                    <span class="absolute top-1/2 -translate-y-1/2 size-2.5 rounded-full bg-mono ring-2 ring-background" style="left: calc({{ $avgPos }}% - 5px)"></span>
                                </div>
                            </div>

                            <div class="flex flex-col gap-0.5 lg:w-1/4 min-w-0 text-sm">
                                @if($item->best_issuer)
                                    <span class="text-xs text-secondary-foreground">Melhor preço atual</span>
                                    <span class="text-foreground truncate">{{ $item->best_issuer->issuer_name }} · <span class="font-mono tabular-nums">{{ $brl($item->best_issuer->price) }}</span></span>
                                    @if($item->best_issuer->is_stale)
                                        <span class="text-xs text-secondary-foreground">preço de {{ \Carbon\Carbon::parse($item->best_issuer->issued_at)->format('d/m/Y') }}, pode ter mudado</span>
                                    @elseif($item->estimated_saving_per_month > 0)
                                        <span class="text-xs text-green-600">economiza ~{{ $brl($item->estimated_saving_per_month) }}/mês</span>
                                    @endif
                                @else
                                    <span class="text-xs text-secondary-foreground">Sem mercado registrado</span>
                                @endif
                            </div>

                            <div class="flex items-center gap-2 lg:ms-auto">
                                @if($item->dismissed)
                                    <button type="button" class="kt-btn kt-btn-outline kt-btn-sm" data-action="restore" data-description="{{ $item->description }}">Voltar para a lista</button>
                                @else
                                    <div class="kt-menu" data-kt-menu="true">
                                        <div class="kt-menu-item" data-kt-menu-item-toggle="dropdown" data-kt-menu-item-trigger="click"
                                             data-kt-menu-item-placement="bottom-end" data-kt-menu-item-offset="0, 5px">
                                            <button type="button" class="kt-menu-toggle kt-btn kt-btn-outline kt-btn-sm">
                                                <i class="ki-filled ki-plus"></i> Lista
                                            </button>
                                            <div class="kt-menu-dropdown kt-menu-default w-56" data-kt-menu-dismiss="true">
                                                @foreach([...$shoppingLists->all(), null] as $list)
                                                    <div class="kt-menu-item">
                                                        <button type="button" class="kt-menu-link w-full text-left" data-action="add-to-list"
                                                                data-list-id="{{ $list?->id }}"
                                                                data-description="{{ $item->description }}"
                                                                data-unit="{{ $item->unit }}"
                                                                data-quantity="{{ $item->suggested_quantity }}"
                                                                data-unit-price="{{ $source?->price ?? $item->last_price }}"
                                                                data-issuer-id="{{ $source?->issuer_id }}">
                                                            <span class="kt-menu-title truncate">{{ $list ? $list->name : 'Nova lista' }}</span>
                                                        </button>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm" title="Não é uma compra recorrente" data-action="dismiss" data-description="{{ $item->description }}">
                                        <i class="ki-filled ki-eye-slash"></i>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center py-12 text-center">
                            <i class="ki-filled ki-arrows-loop text-5xl text-secondary-foreground/30 mb-4"></i>
                            <p class="text-sm font-medium text-foreground mb-1">Nenhum produto encontrado.</p>
                            <p class="text-sm text-secondary-foreground">Um produto vira recorrente depois de comprado em 3 dias diferentes nos últimos 24 meses.</p>
                            <a href="{{ route('my-purchases.upload.form') }}" class="kt-btn kt-btn-primary kt-btn-sm mt-4">
                                <i class="ki-filled ki-file-up"></i> Importar NFC-e
                            </a>
                        </div>
                    @endforelse
                </div>

                <div class="kt-card-footer justify-between text-xs text-secondary-foreground">
                    <span>
                        @if($summary['inactive_count'] > 0 && ! $filters['dismissed'])
                            {{ $summary['inactive_count'] }} parado(s) — filtre por "Parados" para ver.
                        @endif
                    </span>
                    @if($filters['dismissed'])
                        <a href="{{ route('recurring-purchases.index') }}" class="kt-link">Voltar aos produtos</a>
                    @elseif($summary['dismissed_count'] > 0)
                        <a href="{{ route('recurring-purchases.index', ['dismissed' => 1, 'status' => 'all']) }}" class="kt-link">Ocultados ({{ $summary['dismissed_count'] }})</a>
                    @endif
                </div>
            </div>

        </div>
    </div>

@endsection

@push('scripts')
<script>
    window.pageConfig = Object.assign(window.pageConfig || {}, {
        addToListUrl: '{{ route("recurring-purchases.add-to-list") }}',
        dismissUrl: '{{ route("recurring-purchases.dismiss") }}',
        restoreUrl: '{{ route("recurring-purchases.restore") }}',
        shoppingListUrl: '{{ route('shopping-list.index') }}',
    });
</script>
@endpush
