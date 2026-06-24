@extends('layout.main')

@section('content')
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Relatórios</h1>
                <p class="text-sm text-secondary-foreground">Gere relatórios de gastos filtrados por período, emissor e categoria</p>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed space-y-5 pb-10">

        {{-- Filtros --}}
        <div class="kt-card">
            <div class="kt-card-header">
                <h3 class="kt-card-title">
                    <i class="ki-filled ki-filter text-primary me-1"></i> Filtros
                </h3>
            </div>
            <div class="kt-card-content pb-5">
                <form id="reportForm" method="POST" action="{{ route('reports.generate') }}">
                    @csrf
                    <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="text-xs text-secondary-foreground">De</label>
                            <input type="date" name="start_date" class="kt-input mt-1 w-full"
                                   value="{{ $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d') }}" />
                        </div>
                        <div>
                            <label class="text-xs text-secondary-foreground">Até</label>
                            <input type="date" name="end_date" class="kt-input mt-1 w-full"
                                   value="{{ $filters['end_date'] ?? now()->format('Y-m-d') }}" />
                        </div>
                        <div>
                            <label class="text-xs text-secondary-foreground">Emissor</label>
                            <select name="issuer_id" class="kt-input mt-1 w-full">
                                <option value="">Todos</option>
                                @foreach($issuers as $issuer)
                                    <option value="{{ $issuer->id }}" {{ isset($filters['issuer_id']) && $filters['issuer_id'] == $issuer->id ? 'selected' : '' }}>
                                        {{ $issuer->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-secondary-foreground">Categoria</label>
                            <select name="category_id" class="kt-input mt-1 w-full">
                                <option value="">Todas</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ isset($filters['category_id']) && $filters['category_id'] == $cat->id ? 'selected' : '' }}>
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 mt-4">
                        <button type="submit" class="kt-btn kt-btn-primary">
                            <i class="ki-filled ki-eye"></i> Visualizar
                        </button>
                        <button type="button" onclick="submitTo('{{ route('reports.pdf') }}')" class="kt-btn kt-btn-outline">
                            <i class="ki-filled ki-document"></i> Exportar PDF
                        </button>
                        <button type="button" onclick="submitTo('{{ route('reports.csv') }}')" class="kt-btn kt-btn-outline">
                            <i class="ki-filled ki-file-down"></i> Exportar CSV
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @isset($items)
            {{-- Resumo --}}
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-5">
                <div class="kt-card">
                    <div class="kt-card-content py-4 px-5">
                        <p class="text-xs text-secondary-foreground">Total Gasto</p>
                        <p class="text-2xl font-bold text-primary mt-1">R$ {{ number_format($summary->total_amount ?? 0, 2, ',', '.') }}</p>
                    </div>
                </div>
                <div class="kt-card">
                    <div class="kt-card-content py-4 px-5">
                        <p class="text-xs text-secondary-foreground">Total de Itens</p>
                        <p class="text-2xl font-bold text-foreground mt-1">{{ $summary->total_items ?? 0 }}</p>
                    </div>
                </div>
                <div class="kt-card">
                    <div class="kt-card-content py-4 px-5">
                        <p class="text-xs text-secondary-foreground">Total de Notas</p>
                        <p class="text-2xl font-bold text-foreground mt-1">{{ $summary->total_invoices ?? 0 }}</p>
                    </div>
                </div>
            </div>

            {{-- Breakdown por categoria --}}
            @if($categoryBreakdown->isNotEmpty())
                <div class="kt-card">
                    <div class="kt-card-header">
                        <h3 class="kt-card-title">
                            <i class="ki-filled ki-category text-primary me-1"></i> Resumo por Categoria
                        </h3>
                    </div>
                    <div class="kt-card-content pb-5">
                        @php
                            $catTotal = $categoryBreakdown->sum('total') ?: 1;
                        @endphp
                        <div class="space-y-3">
                            @foreach($categoryBreakdown as $cat)
                                @php
                                    $catPct = ($cat->total / $catTotal) * 100;
                                @endphp
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <div class="flex items-center gap-2">
                                            <span class="size-2.5 rounded-full shrink-0" style="background-color: {{ $cat->category_color }}"></span>
                                            <span class="text-sm text-foreground">{{ $cat->category_name }}</span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="text-xs text-secondary-foreground">{{ number_format($catPct, 0) }}%</span>
                                            <span class="font-semibold font-mono text-sm">R$ {{ number_format($cat->total, 2, ',', '.') }}</span>
                                        </div>
                                    </div>
                                    <div class="w-full bg-accent rounded-full h-2">
                                        <div class="rounded-full h-2" style="width: {{ $catPct }}%; background-color: {{ $cat->category_color }}"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- Tabela detalhada --}}
            <div class="kt-card kt-card-grid">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-document text-primary me-1"></i> Itens Detalhados
                    </h3>
                    <span class="text-xs text-secondary-foreground">{{ $items->count() }} {{ $items->count() === 1 ? 'item' : 'itens' }}</span>
                </div>
                <div class="kt-card-content">
                    <div class="kt-scrollable-x-auto">
                        <table class="kt-table table-auto kt-table-border">
                            <thead>
                                <tr>
                                    <th class="min-w-[100px]">Data</th>
                                    <th class="min-w-[160px]">Emissor</th>
                                    <th class="min-w-[200px]">Produto</th>
                                    <th class="min-w-[120px]">Categoria</th>
                                    <th class="min-w-[70px] text-right">Qtd</th>
                                    <th class="min-w-[90px] text-right">Preço Unit.</th>
                                    <th class="min-w-[100px] text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($items as $item)
                                    <tr>
                                        <td class="text-sm text-secondary-foreground">{{ \Carbon\Carbon::parse($item->issued_at)->format('d/m/Y') }}</td>
                                        <td class="text-sm">{{ $item->issuer_name }}</td>
                                        <td class="text-sm font-medium text-foreground">{{ $item->description }}</td>
                                        <td>
                                            <span class="inline-flex items-center gap-1 text-xs">
                                                <span class="size-2 rounded-full" style="background-color: {{ $item->category_color }}"></span>
                                                {{ $item->category_name }}
                                            </span>
                                        </td>
                                        <td class="text-right font-mono text-sm">{{ rtrim(rtrim(number_format($item->quantity, 4, ',', '.'), '0'), ',') }}</td>
                                        <td class="text-right font-mono text-sm">R$ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                                        <td class="text-right font-semibold font-mono text-sm">R$ {{ number_format($item->total_price, 2, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-secondary-foreground py-6">Nenhum item encontrado para os filtros selecionados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endisset

    </div>

    <script>window.pageConfig = { generateUrl: '{{ route("reports.generate") }}' };</script>
    @push('scripts') @vite('resources/js/pages/report.js') @endpush
@endsection
