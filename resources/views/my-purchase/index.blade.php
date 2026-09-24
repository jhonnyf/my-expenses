@extends('layout.main')
@section('page-module', 'my-purchases')

@section('content')

    {{-- PAGE HEADER --}}
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Minhas Compras</h1>
                <div class="flex items-center gap-2 text-sm font-normal text-secondary-foreground">
                    {{ $totalCount }} {{ $totalCount == 1 ? 'nota importada' : 'notas importadas' }}
                    @if($unconfirmedCount > 0)
                        <span class="kt-badge kt-badge-warning kt-badge-outline kt-badge-sm" title="Notas pendentes ou não confirmadas não entram nos totais.">
                            + {{ $unconfirmedCount }} aguardando confirmação
                        </span>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <a href="{{ route('my-purchases.upload.form') }}" class="kt-btn kt-btn-primary">
                    <i class="ki-filled ki-file-up"></i> Importar NFC-e
                </a>
            </div>
        </div>
    </div>

    {{-- CONTENT --}}
    <div class="kt-container-fixed">
        <div class="grid gap-5 lg:gap-7.5">

            @if(session('success'))
                <div class="flex items-center gap-3 rounded-lg border border-green-500/30 bg-green-500/10 px-4 py-3">
                    <i class="ki-filled ki-check-circle text-green-600 text-lg shrink-0"></i>
                    <span class="text-sm text-green-600 font-medium">{{ session('success') }}</span>
                </div>
            @endif

            @include('partials._period-filter', [
                'periodFilterAction' => route('my-purchases.index'),
                'filters' => $filters,
                'extraFields' => array_filter($listFilters, fn ($value) => $value !== null && $value !== '' && $value !== 'recent'),
            ])

            {{-- STAT CARDS --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 lg:gap-7.5">

                <div class="kt-card flex-row items-center gap-4 p-5">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-primary/10 shrink-0">
                        <i class="ki-filled ki-dollar text-primary text-xl"></i>
                    </div>
                    <div class="flex flex-col gap-0.5 min-w-0">
                        <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums truncate">
                            R$ {{ number_format($totalAmount, 2, ',', '.') }}
                        </span>
                        <span class="text-xs font-normal text-secondary-foreground">
                            Total gasto
                            @if($deltaPct !== null && $deltaPct != 0)
                                <span class="tabular-nums {{ $deltaPct > 0 ? 'text-destructive' : 'text-green-600' }}"
                                      title="Comparado ao período anterior, de mesma duração">
                                    · {{ $deltaPct > 0 ? '▲' : '▼' }} {{ number_format(abs($deltaPct), 1, ',', '.') }}%
                                </span>
                            @endif
                        </span>
                    </div>
                </div>

                <div class="kt-card flex-row items-center gap-4 p-5">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-info/10 shrink-0">
                        <i class="ki-filled ki-calendar text-info text-xl"></i>
                    </div>
                    <div class="flex flex-col gap-0.5 min-w-0">
                        <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums truncate">
                            R$ {{ number_format($dailyAverage, 2, ',', '.') }}
                        </span>
                        <span class="text-xs font-normal text-secondary-foreground">Gasto médio diário</span>
                    </div>
                </div>

                <div class="kt-card flex-row items-center gap-4 p-5">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-success/10 shrink-0">
                        <i class="ki-filled ki-basket text-success text-xl"></i>
                    </div>
                    <div class="flex flex-col gap-0.5 min-w-0">
                        <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums truncate">
                            R$ {{ number_format($averageTicket, 2, ',', '.') }}
                        </span>
                        <span class="text-xs font-normal text-secondary-foreground">Ticket médio</span>
                    </div>
                </div>

                <div class="kt-card flex-row items-center gap-4 p-5">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-warning/10 shrink-0">
                        <i class="ki-filled ki-document text-warning text-xl"></i>
                    </div>
                    <div class="flex flex-col gap-0.5 min-w-0">
                        <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums">
                            {{ number_format($totalCount) }}
                        </span>
                        <span class="text-xs font-normal text-secondary-foreground">NFC-e importadas</span>
                    </div>
                </div>

            </div>

            <div class="kt-card kt-card-grid min-w-full">

                <div class="kt-card-header flex-wrap gap-3 py-3 lg:py-0">
                    <h3 class="kt-card-title">Lista de compras</h3>
                </div>

                <form id="myPurchasesFilterForm" method="GET" action="{{ route('my-purchases.index') }}"
                      class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 px-5 py-3 border-b border-border">
                    <input type="hidden" name="start_date" value="{{ $filters['start_date'] }}" />
                    <input type="hidden" name="end_date" value="{{ $filters['end_date'] }}" />

                    {{-- Filtros --}}
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2 w-full sm:w-auto">
                            <label class="kt-input w-full sm:w-72">
                                <i class="ki-filled ki-magnifier"></i>
                                <input type="search" name="search" id="myPurchasesSearchInput" value="{{ $search }}"
                                       placeholder="Emissor, apelido, CNPJ ou nº da nota..." autocomplete="off" />
                            </label>
                            <button type="submit" class="kt-btn kt-btn-mono shrink-0">Buscar</button>
                        </div>
                        @if(count($issuerOptions) > 1)
                            <select name="issuer_id" class="kt-select w-full sm:w-52" aria-label="Filtrar por emissor">
                                <option value="">Todos os emissores</option>
                                @foreach($issuerOptions as $option)
                                    <option value="{{ $option['id'] }}" @selected(($listFilters['issuer_id'] ?? null) === $option['id'])>{{ $option['name'] }}</option>
                                @endforeach
                            </select>
                        @endif
                        <select name="status" class="kt-select w-full sm:w-44" aria-label="Filtrar por situação">
                            <option value="">Todas as situações</option>
                            @foreach(\App\Enums\InvoiceStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected(($listFilters['status'] ?? null) === $status->value)>{{ $status->shortLabel() }}</option>
                            @endforeach
                        </select>
                        @if($hasListFilters || ($listFilters['sort'] ?? 'recent') !== 'recent')
                            <a href="{{ route('my-purchases.index', ['start_date' => $filters['start_date'], 'end_date' => $filters['end_date']]) }}"
                               class="kt-btn kt-btn-ghost kt-btn-sm shrink-0">
                                <i class="ki-filled ki-cross"></i>
                                Limpar
                            </a>
                        @endif
                    </div>

                    {{-- Ordenação --}}
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <label for="myPurchasesSortSelect" class="text-sm text-secondary-foreground shrink-0">Ordenar por</label>
                        <select name="sort" id="myPurchasesSortSelect" class="kt-select w-full sm:w-52">
                            <option value="recent" @selected(($listFilters['sort'] ?? 'recent') === 'recent')>Mais recentes</option>
                            <option value="oldest" @selected(($listFilters['sort'] ?? '') === 'oldest')>Mais antigas</option>
                            <option value="highest" @selected(($listFilters['sort'] ?? '') === 'highest')>Maior valor</option>
                            <option value="lowest" @selected(($listFilters['sort'] ?? '') === 'lowest')>Menor valor</option>
                        </select>
                    </div>
                </form>

                @if($records->isEmpty())
                    <div class="kt-card-content p-5">
                        <div class="flex flex-col items-center justify-center py-16 text-center">
                            @if($hasListFilters)
                                <i class="ki-filled ki-magnifier text-5xl text-secondary-foreground/30 mb-4"></i>
                                <p class="text-sm font-medium text-foreground mb-1">Nenhuma compra encontrada</p>
                                <p class="text-xs text-secondary-foreground">Nenhuma nota corresponde aos filtros aplicados.</p>
                                <a href="{{ route('my-purchases.index', ['start_date' => $filters['start_date'], 'end_date' => $filters['end_date']]) }}" class="kt-btn kt-btn-secondary kt-btn-sm mt-4">
                                    Limpar filtros
                                </a>
                            @else
                                <i class="ki-filled ki-document text-5xl text-secondary-foreground/30 mb-4"></i>
                                <p class="text-sm font-medium text-foreground mb-1">Nenhuma compra encontrada</p>
                                <p class="text-xs text-secondary-foreground">Importe sua primeira NFC-e para começar.</p>
                                <a href="{{ route('my-purchases.upload.form') }}" class="kt-btn kt-btn-primary kt-btn-sm mt-4">
                                    <i class="ki-filled ki-file-up"></i> Importar NFC-e
                                </a>
                            @endif
                        </div>
                    </div>
                @else
                    {{-- DESKTOP (lg+): tabela --}}
                    <div class="kt-card-table hidden lg:block">
                        <div class="kt-scrollable-x-auto">
                            <table class="kt-table kt-table-border table-auto">
                                <thead>
                                    <tr>
                                        <th class="min-w-[260px]">Emissor</th>
                                        <th class="min-w-[140px]">Data</th>
                                        <th class="w-[80px] text-center">Itens</th>
                                        <th class="min-w-[130px] text-end">Valor</th>
                                        <th class="w-[60px] text-end">Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($records->items() as $item)
                                        <tr class="transition-colors duration-150 hover:bg-accent/60">
                                            <td class="py-2.5">
                                                <div class="flex items-center gap-3">
                                                    <div class="flex items-center justify-center size-9 rounded-lg bg-primary/10 text-primary font-semibold text-xs shrink-0 uppercase">
                                                        {{ mb_strtoupper(mb_substr($item->issuer->display_name ?? '??', 0, 2)) }}
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="text-sm font-semibold text-foreground truncate">{{ $item->issuer->display_name ?? '—' }}</p>
                                                        <p class="text-xs text-secondary-foreground font-mono truncate">
                                                            Nº {{ $item->number }} / Série {{ $item->series }}
                                                        </p>
                                                        @if($item->status->hint())
                                                            <span class="kt-badge kt-badge-warning kt-badge-sm mt-1" data-kt-tooltip="true" data-kt-tooltip-placement="top">
                                                                {{ $item->status->label() }}
                                                                <span data-kt-tooltip-content="true" class="kt-tooltip">{{ $item->status->hint() }}</span>
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-2.5">
                                                <p class="text-sm text-foreground">{{ $item->issued_at->format('d/m/Y') }}</p>
                                                <p class="text-xs text-secondary-foreground">{{ $item->issued_at->format('H:i') }}</p>
                                            </td>
                                            <td class="text-center py-2.5">
                                                @if($item->items_count > 0)
                                                    <span class="kt-badge kt-badge-secondary kt-badge-outline kt-badge-sm tabular-nums">{{ $item->items_count }}</span>
                                                @else
                                                    <span class="text-sm text-secondary-foreground">—</span>
                                                @endif
                                            </td>
                                            <td class="text-end py-2.5">
                                                <span class="text-sm font-semibold font-mono tabular-nums text-foreground">
                                                    R$ {{ number_format($item->total_amount, 2, ',', '.') }}
                                                </span>
                                            </td>
                                            <td class="text-end py-2.5">
                                                <a href="{{ route('my-purchases.detail', $item->id) }}"
                                                   class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm transition-transform duration-200 hover:scale-110"
                                                   title="Ver detalhes">
                                                    <i class="ki-filled ki-eye text-base"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- MOBILE (< lg): cards --}}
                    <div class="kt-card-content lg:hidden grid gap-3 p-5">
                        @foreach ($records->items() as $item)
                            <div class="rounded-xl border border-border p-4 flex flex-col gap-3 transition-colors duration-150">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center size-9 rounded-lg bg-primary/10 text-primary font-semibold text-xs shrink-0 uppercase">
                                        {{ mb_strtoupper(mb_substr($item->issuer->display_name ?? '??', 0, 2)) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-foreground truncate">{{ $item->issuer->display_name ?? '—' }}</p>
                                        <p class="text-xs text-secondary-foreground font-mono truncate">
                                            Nº {{ $item->number }} / Série {{ $item->series }}
                                        </p>
                                        @if($item->status->hint())
                                            <span class="kt-badge kt-badge-warning kt-badge-sm mt-1" data-kt-tooltip="true" data-kt-tooltip-placement="top">
                                                {{ $item->status->label() }}
                                                <span data-kt-tooltip-content="true" class="kt-tooltip">{{ $item->status->hint() }}</span>
                                            </span>
                                        @endif
                                    </div>
                                    <a href="{{ route('my-purchases.detail', $item->id) }}"
                                       class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm shrink-0"
                                       title="Ver detalhes">
                                        <i class="ki-filled ki-eye text-base"></i>
                                    </a>
                                </div>
                                <div class="flex items-center justify-between gap-2 pt-2 border-t border-border/60">
                                    <div class="flex flex-col gap-0.5">
                                        <span class="text-xs text-secondary-foreground">Data</span>
                                        <span class="text-sm text-foreground">
                                            {{ $item->issued_at->format('d/m/Y') }} {{ $item->issued_at->format('H:i') }}
                                        </span>
                                    </div>
                                    <div class="flex flex-col gap-0.5 items-center">
                                        <span class="text-xs text-secondary-foreground">Itens</span>
                                        <span class="text-sm text-foreground tabular-nums">{{ $item->items_count > 0 ? $item->items_count : '—' }}</span>
                                    </div>
                                    <div class="flex flex-col gap-0.5 items-end">
                                        <span class="text-xs text-secondary-foreground">Valor</span>
                                        <span class="text-sm font-semibold font-mono tabular-nums text-foreground">
                                            R$ {{ number_format($item->total_amount, 2, ',', '.') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($records->hasPages())
                    <div class="kt-card-footer justify-center md:justify-between flex-col md:flex-row gap-3 text-secondary-foreground text-sm font-medium">
                        <span class="order-2 md:order-1">
                            Exibindo {{ $records->firstItem() }}–{{ $records->lastItem() }} de {{ $records->total() }} compras
                        </span>
                        <div class="flex items-center gap-2 order-1 md:order-2">
                            {{ $records->links('vendor.pagination.metronic') }}
                        </div>
                    </div>
                @endif

            </div>

        </div>
    </div>

@endsection
