@extends('layout.main')
@section('page-module', 'issuer-favorite,issuer-detail,issuer-nickname')

@section('content')

    {{-- PAGE HEADER --}}
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Detalhe do Emissor</h1>
                <div class="flex items-center gap-2 text-sm font-normal text-secondary-foreground">
                    <a href="{{ route('issuers.index') }}" class="hover:text-primary transition-colors">Emissores</a>
                    <i class="ki-filled ki-right text-xs"></i>
                    <span class="truncate max-w-[200px]" id="issuerBreadcrumbName">{{ $nickname ?: $record->name }}</span>
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <button data-favorite-id="{{ $record->id }}" id="btnFavorite"
                        class="kt-btn transition-all duration-200 {{ $isFavorite
                            ? 'bg-yellow-500 hover:bg-yellow-600 text-white border-yellow-500 shadow-md shadow-yellow-500/30'
                            : 'kt-btn-outline hover:border-yellow-500 hover:text-yellow-500' }}">
                    <i id="btnFavoriteIcon" class="ki-filled ki-star transition-transform duration-200 {{ $isFavorite ? 'scale-125' : '' }}"></i>
                    <span id="btnFavoriteLabel">{{ $isFavorite ? 'Favoritado' : 'Favoritar' }}</span>
                </button>
                <a href="{{ route('issuers.index') }}" class="kt-btn kt-btn-outline">
                    <i class="ki-filled ki-arrow-left"></i>
                    Voltar
                </a>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed">
        <div class="grid lg:grid-cols-3 gap-5 lg:gap-7.5 items-start">

            {{-- COLUNA ESQUERDA — Perfil + Endereço --}}
            <div class="flex flex-col gap-5 lg:gap-7.5">

                {{-- Card Perfil --}}
                <div class="kt-card">
                    <div class="kt-card-content p-5 lg:p-7.5 flex flex-col items-center text-center gap-4">

                        {{-- Avatar --}}
                        <div id="profileAvatar"
                             class="flex items-center justify-center size-20 rounded-2xl font-bold text-2xl uppercase shrink-0 transition-all duration-300
                             {{ $isFavorite ? 'bg-yellow-500/15 text-yellow-600 ring-2 ring-yellow-400 shadow-lg shadow-yellow-500/20' : 'bg-primary/10 text-primary ring-2 ring-primary/30' }}">
                            <span id="profileAvatarInitials">{{ strtoupper(substr($nickname ?: $record->name, 0, 2)) }}</span>
                        </div>

                        {{-- Nome + CNPJ --}}
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center justify-center gap-1.5">
                                <h2 class="text-base font-semibold text-foreground leading-snug" id="issuerDisplayName">{{ $nickname ?: $record->name }}</h2>
                                <button
                                    data-action="edit-nickname"
                                    data-kt-modal-toggle="#nicknameModal"
                                    data-issuer-id="{{ $record->id }}"
                                    data-issuer-name="{{ $record->name }}"
                                    data-issuer-nickname="{{ $nickname }}"
                                    class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm shrink-0" title="Editar apelido">
                                    <i class="ki-filled ki-pencil text-sm text-muted-foreground"></i>
                                </button>
                            </div>
                            <p class="text-xs text-secondary-foreground {{ $nickname ? '' : 'hidden' }}" id="issuerOriginalNameWrap">
                                Nome oficial: <span id="issuerOriginalName">{{ $record->name }}</span>
                            </p>
                            <p class="text-xs font-mono text-secondary-foreground">
                                {{ preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $record->cnpj) }}
                            </p>
                        </div>

                        {{-- Localização --}}
                        @if($record->city || $record->state)
                            <div class="flex items-center justify-center gap-1.5">
                                <i class="ki-filled ki-geolocation text-sm text-muted-foreground"></i>
                                <span class="text-sm text-secondary-foreground">
                                    {{ implode(', ', array_filter([$record->city, $record->state])) }}
                                </span>
                            </div>
                        @endif

                        {{-- Badge favorito --}}
                        <span id="favoriteBadge" class="kt-badge kt-badge-warning kt-badge-outline kt-badge-sm transition-all duration-200 {{ $isFavorite ? '' : 'hidden' }}">
                            <i class="ki-filled ki-star text-2xs"></i> Favorito
                        </span>

                    </div>

                    <div class="border-t border-border mx-5"></div>

                    {{-- Stats --}}
                    <div class="kt-card-content px-5 py-4 grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs text-secondary-foreground">Total de Notas</span>
                            <span class="text-xl font-semibold text-mono tabular-nums">{{ number_format($stats->total_count) }}</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs text-secondary-foreground">Valor Total</span>
                            <span class="text-xl font-semibold text-primary tabular-nums truncate">R$ {{ number_format($stats->total_sum, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs text-secondary-foreground">Primeira Compra</span>
                            <span class="text-sm font-semibold text-foreground tabular-nums">
                                {{ $stats->first_at ? \Carbon\Carbon::parse($stats->first_at)->format('d/m/Y') : '—' }}
                            </span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs text-secondary-foreground">Última Compra</span>
                            <span class="text-sm font-semibold text-foreground tabular-nums">
                                {{ $stats->last_at ? \Carbon\Carbon::parse($stats->last_at)->format('d/m/Y') : '—' }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Card Endereço --}}
                <div class="kt-card">
                    <div class="kt-card-header">
                        <h3 class="kt-card-title">Endereço</h3>
                    </div>
                    <div class="kt-card-content pb-5 px-5 lg:px-7.5 grid gap-3">

                        @if($record->street)
                            <div class="flex items-start gap-2.5">
                                <i class="ki-filled ki-home text-sm text-muted-foreground mt-0.5 shrink-0"></i>
                                <div class="min-w-0">
                                    <p class="text-xs text-secondary-foreground">Logradouro</p>
                                    <p class="text-sm font-medium text-foreground">
                                        {{ $record->street }}@if($record->street_number), {{ $record->street_number }}@endif
                                    </p>
                                </div>
                            </div>
                        @endif

                        @if($record->neighborhood)
                            <div class="flex items-start gap-2.5">
                                <i class="ki-filled ki-map text-sm text-muted-foreground mt-0.5 shrink-0"></i>
                                <div class="min-w-0">
                                    <p class="text-xs text-secondary-foreground">Bairro</p>
                                    <p class="text-sm font-medium text-foreground">{{ $record->neighborhood }}</p>
                                </div>
                            </div>
                        @endif

                        <div class="flex items-start gap-2.5">
                            <i class="ki-filled ki-geolocation text-sm text-muted-foreground mt-0.5 shrink-0"></i>
                            <div class="min-w-0">
                                <p class="text-xs text-secondary-foreground">Cidade / Estado</p>
                                <p class="text-sm font-medium text-foreground">
                                    {{ implode(' — ', array_filter([$record->city ?? null, $record->state ?? null])) ?: '—' }}
                                </p>
                            </div>
                        </div>

                        @if($record->zip_code)
                            <div class="flex items-start gap-2.5">
                                <i class="ki-filled ki-pin text-sm text-muted-foreground mt-0.5 shrink-0"></i>
                                <div class="min-w-0">
                                    <p class="text-xs text-secondary-foreground">CEP</p>
                                    <p class="text-sm font-medium font-mono text-foreground">
                                        {{ preg_replace('/^(\d{5})(\d{3})$/', '$1-$2', $record->zip_code) }}
                                    </p>
                                </div>
                            </div>
                        @endif

                        @if(!$record->street && !$record->neighborhood && !$record->city && !$record->zip_code)
                            <p class="text-sm text-secondary-foreground text-center py-4">Endereço não informado.</p>
                        @endif

                    </div>

                    {{-- Google Maps --}}
                    @php
                        $mapParts = array_filter([
                            $record->street ? trim($record->street . ($record->street_number ? ', ' . $record->street_number : '')) : null,
                            $record->neighborhood,
                            $record->city,
                            $record->state,
                            $record->zip_code,
                            'Brasil',
                        ]);
                    @endphp
                    @if(count($mapParts) > 1)
                        <div class="overflow-hidden rounded-b-xl border-t border-border">
                            <iframe
                                src="https://maps.google.com/maps?q={{ urlencode(implode(', ', $mapParts)) }}&output=embed&z=16&hl=pt-BR"
                                width="100%"
                                height="220"
                                style="border:0; display:block;"
                                allowfullscreen
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                title="Localização de {{ $record->name }}">
                            </iframe>
                        </div>
                    @endif

                </div>

            </div>

            {{-- COLUNA DIREITA — Notas Fiscais --}}
            <div class="lg:col-span-2">
                <div class="kt-card kt-card-grid">
                    <div class="kt-card-header flex-wrap gap-3 py-3 lg:py-0">
                        <h3 class="kt-card-title">Notas Fiscais</h3>
                        <div class="flex items-center gap-3 w-full lg:w-auto">
                            <span class="kt-badge kt-badge-secondary kt-badge-outline kt-badge-sm shrink-0">
                                {{ $stats->total_count }} {{ $stats->total_count == 1 ? 'nota' : 'notas' }}
                            </span>
                            <label class="kt-input w-full lg:max-w-48">
                                <i class="ki-filled ki-magnifier"></i>
                                <input type="text" id="invoiceSearchInput" placeholder="Buscar nota..." autocomplete="off" />
                            </label>
                        </div>
                    </div>
                    @if($record->invoices->isEmpty())
                        <div class="kt-card-content p-5">
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <i class="ki-filled ki-document text-4xl text-secondary-foreground/30 mb-3"></i>
                                <p class="text-sm text-secondary-foreground">Nenhuma nota fiscal encontrada.</p>
                            </div>
                        </div>
                    @else
                        {{-- DESKTOP (lg+): tabela --}}
                        <div class="kt-card-table hidden lg:block">
                            <div class="kt-scrollable-x-auto">
                                <table class="kt-table kt-table-border table-auto" id="invoicesTable">
                                    <thead>
                                        <tr>
                                            <th class="w-[100px]">Número</th>
                                            <th class="min-w-[130px]">Data</th>
                                            <th class="min-w-[130px] text-end">Valor</th>
                                            <th class="w-[80px] text-center">Itens</th>
                                            <th class="w-[60px]"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($record->invoices as $invoice)
                                            <tr class="invoice-row transition-colors hover:bg-accent/40">
                                                <td>
                                                    <span class="text-sm font-mono text-foreground">
                                                        {{ $invoice->number }}<span class="text-secondary-foreground">/{{ $invoice->series }}</span>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="flex flex-col gap-0.5">
                                                        <span class="text-sm text-foreground">
                                                            {{ $invoice->issued_at?->format('d/m/Y') ?? '—' }}
                                                        </span>
                                                        @if($invoice->issued_at)
                                                            <span class="text-xs text-secondary-foreground">
                                                                {{ $invoice->issued_at->format('H:i') }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="text-end">
                                                    <span class="text-sm font-semibold text-foreground tabular-nums font-mono">
                                                        R$ {{ number_format($invoice->total_amount, 2, ',', '.') }}
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="kt-badge kt-badge-secondary kt-badge-outline kt-badge-sm tabular-nums">
                                                        {{ $invoice->items_count }}
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <a href="{{ route('my-purchases.detail', ['invoice' => $invoice->id]) }}"
                                                       class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm"
                                                       title="Ver nota">
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
                            @foreach($record->invoices as $invoice)
                                <div class="invoice-row rounded-xl border border-border p-4 flex flex-col gap-2 transition-colors hover:bg-accent/40">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-sm font-mono text-foreground">
                                            {{ $invoice->number }}<span class="text-secondary-foreground">/{{ $invoice->series }}</span>
                                        </span>
                                        <a href="{{ route('my-purchases.detail', ['invoice' => $invoice->id]) }}"
                                           class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm"
                                           title="Ver nota">
                                            <i class="ki-filled ki-eye text-base"></i>
                                        </a>
                                    </div>
                                    <div class="flex items-center justify-between gap-2 pt-2 border-t border-border/60">
                                        <div class="flex flex-col gap-0.5">
                                            <span class="text-xs text-secondary-foreground">Data</span>
                                            <span class="text-sm text-foreground">
                                                {{ $invoice->issued_at?->format('d/m/Y H:i') ?? '—' }}
                                            </span>
                                        </div>
                                        <div class="flex flex-col gap-0.5 items-center">
                                            <span class="text-xs text-secondary-foreground">Itens</span>
                                            <span class="kt-badge kt-badge-secondary kt-badge-outline kt-badge-sm tabular-nums">
                                                {{ $invoice->items_count }}
                                            </span>
                                        </div>
                                        <div class="flex flex-col gap-0.5 items-end">
                                            <span class="text-xs text-secondary-foreground">Valor</span>
                                            <span class="text-sm font-semibold text-foreground tabular-nums font-mono">
                                                R$ {{ number_format($invoice->total_amount, 2, ',', '.') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    @include('issuer._nickname-modal')

@endsection

@push('scripts')
<script>
    window.pageConfig = Object.assign(window.pageConfig || {}, {
        issuerBaseUrl: '{{ url("issuers") }}',
    });
</script>
@endpush
