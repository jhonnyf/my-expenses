@extends('layout.main')
@section('page-module', 'report')

@section('content')

    {{-- PAGE HEADER --}}
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Relatórios</h1>
                <div class="flex items-center gap-2 text-sm font-normal text-secondary-foreground">
                    Gere relatórios de gastos filtrados
                </div>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed">
        <div class="grid gap-5 lg:gap-7.5">

            {{-- FILTROS --}}
            <div class="kt-card">
                <div class="kt-card-header flex-wrap gap-2">
                    <h3 class="kt-card-title">Filtros</h3>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button type="button" data-action="quick-range" data-range="this-month" class="kt-btn kt-btn-outline kt-btn-sm">Este mês</button>
                        <button type="button" data-action="quick-range" data-range="last-month" class="kt-btn kt-btn-outline kt-btn-sm">Mês passado</button>
                        <button type="button" data-action="quick-range" data-range="last-3-months" class="kt-btn kt-btn-outline kt-btn-sm">Últimos 3 meses</button>
                        <button type="button" data-action="quick-range" data-range="this-year" class="kt-btn kt-btn-outline kt-btn-sm">Este ano</button>
                    </div>
                </div>
                <div class="kt-card-content pb-5">
                    <form id="reportForm" method="POST" action="{{ route('reports.generate') }}">
                        @csrf
                        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="text-xs font-medium text-secondary-foreground mb-1.5 block">De</label>
                                <input type="date" name="start_date" id="reportStartDate"
                                       class="kt-input w-full"
                                       value="{{ $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d') }}" />
                            </div>
                            <div>
                                <label class="text-xs font-medium text-secondary-foreground mb-1.5 block">Até</label>
                                <input type="date" name="end_date" id="reportEndDate"
                                       class="kt-input w-full"
                                       value="{{ $filters['end_date'] ?? now()->format('Y-m-d') }}" />
                            </div>
                            <div>
                                <label class="text-xs font-medium text-secondary-foreground mb-1.5 block">Emissor</label>
                                <select name="issuer_id" class="kt-input w-full">
                                    <option value="">Todos</option>
                                    @foreach($issuers as $issuer)
                                        <option value="{{ $issuer->id }}"
                                            {{ isset($filters['issuer_id']) && $filters['issuer_id'] == $issuer->id ? 'selected' : '' }}>
                                            {{ $issuer->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-secondary-foreground mb-1.5 block">Categoria</label>
                                <select name="category_id" class="kt-input w-full">
                                    <option value="">Todas</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}"
                                            {{ isset($filters['category_id']) && $filters['category_id'] == $cat->id ? 'selected' : '' }}>
                                            {{ $cat->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-5">
                            <button type="submit" class="kt-btn kt-btn-primary">
                                <i class="ki-filled ki-eye"></i>
                                Visualizar
                            </button>
                            <button type="button" data-action="submit-report" data-url="{{ route('reports.pdf') }}" class="kt-btn kt-btn-outline">
                                <i class="ki-filled ki-document"></i>
                                Exportar PDF
                            </button>
                            <button type="button" data-action="submit-report" data-url="{{ route('reports.csv') }}" class="kt-btn kt-btn-outline">
                                <i class="ki-filled ki-file-down"></i>
                                Exportar CSV
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            @isset($items)

                {{-- MINI STAT CARDS --}}
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
                            <span class="text-2xl font-semibold text-mono tabular-nums truncate">
                                R$ {{ number_format($summary->total_amount ?? 0, 2, ',', '.') }}
                            </span>
                            <span class="text-sm font-normal text-secondary-foreground">Total Gasto</span>
                        </div>
                    </div>

                    <div class="kt-card flex-col justify-between gap-6 bg-cover bg-[right_top_-1.7rem] bg-no-repeat channel-stats-bg">
                        <div class="flex items-center justify-center size-10 mt-4 ms-5 rounded-xl bg-violet-500/10">
                            <i class="ki-filled ki-basket text-violet-600 text-xl"></i>
                        </div>
                        <div class="flex flex-col gap-1 pb-4 px-5">
                            <span class="text-2xl font-semibold text-mono tabular-nums">
                                {{ $summary->total_items ?? 0 }}
                            </span>
                            <span class="text-sm font-normal text-secondary-foreground">Total de Itens</span>
                        </div>
                    </div>

                    <div class="kt-card flex-col justify-between gap-6 bg-cover bg-[right_top_-1.7rem] bg-no-repeat channel-stats-bg">
                        <div class="flex items-center justify-center size-10 mt-4 ms-5 rounded-xl bg-green-500/10">
                            <i class="ki-filled ki-document text-green-600 text-xl"></i>
                        </div>
                        <div class="flex flex-col gap-1 pb-4 px-5">
                            <span class="text-2xl font-semibold text-mono tabular-nums">
                                {{ $summary->total_invoices ?? 0 }}
                            </span>
                            <span class="text-sm font-normal text-secondary-foreground">Total de Notas</span>
                        </div>
                    </div>

                    <div class="kt-card flex-col justify-between gap-6 bg-cover bg-[right_top_-1.7rem] bg-no-repeat channel-stats-bg">
                        <div class="flex items-center justify-center size-10 mt-4 ms-5 rounded-xl bg-yellow-500/10">
                            <i class="ki-filled ki-chart text-yellow-600 text-xl"></i>
                        </div>
                        <div class="flex flex-col gap-1 pb-4 px-5">
                            <span class="text-2xl font-semibold text-mono tabular-nums truncate">
                                R$ {{ number_format($summary->total_invoices ? ($summary->total_amount / $summary->total_invoices) : 0, 2, ',', '.') }}
                            </span>
                            <span class="text-sm font-normal text-secondary-foreground">Ticket Médio</span>
                        </div>
                    </div>

                </div>

                {{-- GASTOS POR CATEGORIA --}}
                @if($categoryBreakdown->isNotEmpty())
                    <div class="kt-card">
                        <div class="kt-card-header">
                            <h3 class="kt-card-title">Gastos por Categoria</h3>
                        </div>
                        <div class="kt-card-content pb-5">
                            <div class="grid lg:grid-cols-2 gap-4 items-center">
                                <div id="reportCategoryChart" style="height: 220px;"></div>
                                <div class="grid gap-2">
                                    @php $catTotal = $categoryBreakdown->sum('total') ?: 1; @endphp
                                    @foreach($categoryBreakdown as $cat)
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
                        </div>
                    </div>
                @endif

                {{-- ITENS DETALHADOS --}}
                <div class="kt-card kt-card-grid">
                    <div class="kt-card-header">
                        <h3 class="kt-card-title">Itens Detalhados</h3>
                        <div class="kt-card-toolbar">
                            <span class="kt-badge kt-badge-primary kt-badge-outline kt-badge-sm">
                                {{ $items->count() }} {{ $items->count() === 1 ? 'item' : 'itens' }}
                            </span>
                        </div>
                    </div>
                    <div class="kt-card-table">
                        <div class="kt-scrollable-x-auto">
                            <table class="kt-table kt-table-border table-fixed">
                                <thead>
                                    <tr>
                                        <th class="min-w-[100px]">Data</th>
                                        <th class="min-w-[160px]">Emissor</th>
                                        <th class="min-w-[200px]">Produto</th>
                                        <th class="min-w-[130px]">Categoria</th>
                                        <th class="min-w-[70px] text-end">Qtd</th>
                                        <th class="min-w-[100px] text-end">Preço Unit.</th>
                                        <th class="min-w-[110px] text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($items as $item)
                                        <tr class="transition-colors hover:bg-accent/40">
                                            <td class="text-sm text-secondary-foreground">
                                                {{ \Carbon\Carbon::parse($item->issued_at)->format('d/m/Y') }}
                                            </td>
                                            <td class="text-sm truncate">{{ $item->issuer_name }}</td>
                                            <td class="text-sm font-medium text-foreground truncate">{{ $item->description }}</td>
                                            <td>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="size-2 rounded-full shrink-0" style="background-color: {{ $item->category_color }}"></span>
                                                    <span class="text-xs text-foreground truncate">{{ $item->category_name }}</span>
                                                </div>
                                            </td>
                                            <td class="text-end font-mono text-sm">
                                                {{ rtrim(rtrim(number_format($item->quantity, 4, ',', '.'), '0'), ',') }}
                                            </td>
                                            <td class="text-end font-mono text-sm">
                                                R$ {{ number_format($item->unit_price, 2, ',', '.') }}
                                            </td>
                                            <td class="text-end font-mono font-semibold text-sm">
                                                R$ {{ number_format($item->total_price, 2, ',', '.') }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7">
                                                <div class="flex flex-col items-center justify-center py-12 text-center">
                                                    <i class="ki-filled ki-document text-4xl text-secondary-foreground/30 mb-3"></i>
                                                    <p class="text-sm font-medium text-foreground">Nenhum item encontrado</p>
                                                    <p class="text-xs text-secondary-foreground mt-1">Tente ajustar os filtros e gerar novamente.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            @endisset

        </div>
    </div>

@endsection

@push('scripts')
<script>
    window.pageConfig = Object.assign(window.pageConfig || {}, {
        generateUrl: '{{ route("reports.generate") }}',
        categoryBreakdown: @json($categoryBreakdown ?? []),
    });
</script>
@endpush
