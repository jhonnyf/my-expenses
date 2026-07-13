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
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <a href="{{ route('my-purchases.upload.form') }}" class="kt-btn kt-btn-primary">
                    <i class="ki-filled ki-file-up"></i> Importar NF-e
                </a>
            </div>
        </div>
    </div>

    {{-- CONTENT --}}
    <div class="kt-container-fixed">
        <div class="grid gap-5 lg:gap-7.5">

            {{-- STAT CARDS --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 lg:gap-7.5">

                <div class="kt-card flex-row items-center gap-4 p-5">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-primary/10 shrink-0">
                        <i class="ki-filled ki-dollar text-primary text-xl"></i>
                    </div>
                    <div class="flex flex-col gap-0.5 min-w-0">
                        <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums truncate">
                            R$ {{ number_format($totalAmount, 2, ',', '.') }}
                        </span>
                        <span class="text-xs font-normal text-secondary-foreground">Total Gasto</span>
                    </div>
                </div>

                <div class="kt-card flex-row items-center gap-4 p-5">
                    <div class="flex items-center justify-center size-10 rounded-xl bg-info/10 shrink-0">
                        <i class="ki-filled ki-calendar text-info text-xl"></i>
                    </div>
                    <div class="flex flex-col gap-0.5 min-w-0">
                        <span class="text-lg lg:text-xl font-semibold text-mono tabular-nums truncate">
                            R$ {{ number_format($monthAmount, 2, ',', '.') }}
                        </span>
                        <span class="text-xs font-normal text-secondary-foreground capitalize">{{ now()->translatedFormat('F') }}</span>
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
                        <span class="text-xs font-normal text-secondary-foreground">Ticket Médio</span>
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
                        <span class="text-xs font-normal text-secondary-foreground">NF-e Importadas</span>
                    </div>
                </div>

            </div>

            <div class="kt-card kt-card-grid min-w-full">

                <div class="kt-card-header flex-wrap gap-2">
                    <h3 class="kt-card-title">Lista de Compras</h3>
                    <div class="flex items-center gap-3">
                        <form id="myPurchasesSearchForm" method="GET" action="{{ route('my-purchases.index') }}">
                            <label class="kt-input max-w-56">
                                <i class="ki-filled ki-magnifier"></i>
                                <input type="text" name="search" id="myPurchasesSearchInput" value="{{ $search }}"
                                       placeholder="Buscar por emissor..." autocomplete="off" />
                                @if($search !== '')
                                    <button type="button" id="myPurchasesSearchClear" class="text-muted-foreground hover:text-foreground transition-colors shrink-0">
                                        <i class="ki-filled ki-cross text-xs"></i>
                                    </button>
                                @endif
                            </label>
                        </form>
                    </div>
                </div>

                <div class="kt-card-table">
                    <div class="kt-scrollable-x-auto">
                        <table class="kt-table kt-table-border table-fixed">
                            <thead>
                                <tr>
                                    <th class="min-w-[260px]">Emissor</th>
                                    <th class="min-w-[140px]">Data</th>
                                    <th class="min-w-[130px] text-end">Valor</th>
                                    <th class="w-[60px] text-end">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($records->items() as $item)
                                    <tr class="transition-colors duration-150 hover:bg-accent/40">
                                        <td class="py-2.5">
                                            <div class="flex items-center gap-3">
                                                <div class="flex items-center justify-center size-9 rounded-lg bg-primary/10 text-primary font-semibold text-xs shrink-0 uppercase">
                                                    {{ strtoupper(substr($item->issuer->name ?? '??', 0, 2)) }}
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="text-sm font-semibold text-foreground truncate">{{ $item->issuer->name ?? '—' }}</p>
                                                    <p class="text-xs text-secondary-foreground font-mono truncate">
                                                        Nº {{ $item->number }} / Série {{ $item->series }}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-2.5">
                                            <p class="text-sm text-foreground">{{ $item->issued_at->format('d/m/Y') }}</p>
                                            <p class="text-xs text-secondary-foreground">{{ $item->issued_at->format('H:i') }}</p>
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
                                @empty
                                    <tr>
                                        <td colspan="4">
                                            <div class="flex flex-col items-center justify-center py-16 text-center">
                                                @if($search !== '')
                                                    <i class="ki-filled ki-magnifier text-5xl text-secondary-foreground/30 mb-4"></i>
                                                    <p class="text-sm font-medium text-foreground mb-1">Nenhuma compra encontrada</p>
                                                    <p class="text-xs text-secondary-foreground">Nenhum resultado para "{{ $search }}".</p>
                                                    <a href="{{ route('my-purchases.index') }}" class="kt-btn kt-btn-secondary kt-btn-sm mt-4">
                                                        Limpar busca
                                                    </a>
                                                @else
                                                    <i class="ki-filled ki-document text-5xl text-secondary-foreground/30 mb-4"></i>
                                                    <p class="text-sm font-medium text-foreground mb-1">Nenhuma compra encontrada</p>
                                                    <p class="text-xs text-secondary-foreground">Importe sua primeira NF-e para começar.</p>
                                                    <a href="{{ route('my-purchases.upload.form') }}" class="kt-btn kt-btn-primary kt-btn-sm mt-4">
                                                        <i class="ki-filled ki-file-up"></i> Importar NF-e
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($records->hasPages())
                    <div class="kt-card-footer justify-center md:justify-between flex-col md:flex-row gap-3 text-secondary-foreground text-sm font-medium">
                        <span class="order-2 md:order-1">
                            Exibindo {{ $records->firstItem() }}–{{ $records->lastItem() }} de {{ $records->total() }} compras
                        </span>
                        <div class="flex items-center gap-2 order-1 md:order-2">
                            {{ $records->links() }}
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
            myPurchasesIndexUrl: '{{ route('my-purchases.index') }}',
        });
    </script>
@endpush
