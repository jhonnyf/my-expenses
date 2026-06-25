@extends('layout.main')

@section('content')
    {{-- Cabeçalho --}}
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div>
                <h1 class="text-xl font-medium leading-none text-mono">
                    {{ $record->name }}
                </h1>
                <p class="text-sm text-secondary-foreground mt-1 font-mono">
                    CNPJ: {{ $record->cnpj }}
                </p>
            </div>
            <div class="flex items-center gap-2.5">
                <button data-favorite-id="{{ $record->id }}" id="btnFavorite"
                        class="kt-btn kt-btn-outline {{ $isFavorite ? 'text-yellow-500 border-yellow-500' : '' }}">
                    <i class="ki-filled ki-star"></i>
                    <span>{{ $isFavorite ? 'Favoritado' : 'Favoritar' }}</span>
                </button>
                <a href="{{ route('issuers.index') }}" class="kt-btn kt-btn-outline">
                    <i class="ki-filled ki-arrow-left"></i> Voltar
                </a>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed space-y-5 pb-10">

        {{-- Informações do Emitente --}}
        <div class="grid lg:grid-cols-2 gap-5">

            {{-- Dados Gerais --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-shop text-primary me-1"></i> Dados do Emitente
                    </h3>
                </div>
                <div class="kt-card-content pb-5">
                    <div class="space-y-3">
                        <div>
                            <p class="text-xs text-secondary-foreground">Razão Social</p>
                            <p class="font-semibold text-foreground">{{ $record->name }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-secondary-foreground">CNPJ</p>
                            <p class="font-medium font-mono">{{ $record->cnpj }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Endereço --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title">
                        <i class="ki-filled ki-geolocation text-primary me-1"></i> Endereço
                    </h3>
                </div>
                <div class="kt-card-content pb-5">
                    <div class="space-y-3">
                        <div>
                            <p class="text-xs text-secondary-foreground">Logradouro</p>
                            <p class="font-medium text-foreground">
                                {{ $record->street ?? '—' }}@if($record->street_number), {{ $record->street_number }}@endif
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-secondary-foreground">Bairro</p>
                            <p class="font-medium text-foreground">{{ $record->neighborhood ?? '—' }}</p>
                        </div>
                        <div class="flex gap-8">
                            <div>
                                <p class="text-xs text-secondary-foreground">Cidade</p>
                                <p class="font-medium text-foreground">{{ $record->city ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-secondary-foreground">Estado</p>
                                <p class="font-medium text-foreground">{{ $record->state ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-secondary-foreground">CEP</p>
                                <p class="font-medium font-mono text-foreground">{{ $record->zip_code ?? '—' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Estatísticas --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="kt-card">
                <div class="kt-card-content py-4 px-5">
                    <p class="text-xs text-secondary-foreground">Total de Notas</p>
                    <p class="text-2xl font-bold text-foreground mt-1">{{ $stats->total_count }}</p>
                </div>
            </div>
            <div class="kt-card">
                <div class="kt-card-content py-4 px-5">
                    <p class="text-xs text-secondary-foreground">Valor Total</p>
                    <p class="text-2xl font-bold text-primary mt-1">R$ {{ number_format($stats->total_sum, 2, ',', '.') }}</p>
                </div>
            </div>
            <div class="kt-card">
                <div class="kt-card-content py-4 px-5">
                    <p class="text-xs text-secondary-foreground">Primeira Compra</p>
                    <p class="text-lg font-semibold text-foreground mt-1">
                        {{ $stats->first_at ? \Carbon\Carbon::parse($stats->first_at)->format('d/m/Y') : '—' }}
                    </p>
                </div>
            </div>
            <div class="kt-card">
                <div class="kt-card-content py-4 px-5">
                    <p class="text-xs text-secondary-foreground">Última Compra</p>
                    <p class="text-lg font-semibold text-foreground mt-1">
                        {{ $stats->last_at ? \Carbon\Carbon::parse($stats->last_at)->format('d/m/Y') : '—' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Notas Fiscais --}}
        <div class="kt-card kt-card-grid">
            <div class="kt-card-header">
                <h3 class="kt-card-title">
                    <i class="ki-filled ki-document text-primary me-1"></i> Notas Fiscais
                </h3>
                <span class="text-xs text-secondary-foreground">{{ $stats->total_count }} {{ $stats->total_count === 1 ? 'nota' : 'notas' }}</span>
            </div>
            <div class="kt-card-content">
                <div class="kt-scrollable-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr>
                                <th class="min-w-[80px]">Número</th>
                                <th class="min-w-[120px]">Data</th>
                                <th class="min-w-[120px] text-right">Valor</th>
                                <th class="min-w-[80px] text-center">Itens</th>
                                <th class="w-[60px]"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($record->invoices as $invoice)
                                <tr>
                                    <td class="font-mono text-sm">{{ $invoice->number }}/{{ $invoice->series }}</td>
                                    <td class="text-secondary-foreground text-sm">
                                        {{ $invoice->issued_at?->format('d/m/Y H:i') ?? '—' }}
                                    </td>
                                    <td class="text-right font-semibold font-mono text-sm">R$ {{ number_format($invoice->total_amount, 2, ',', '.') }}</td>
                                    <td class="text-center text-secondary-foreground">{{ $invoice->items_count }}</td>
                                    <td>
                                        <a href="{{ route('my-purchases.detail', ['invoice' => $invoice->id]) }}" class="kt-btn kt-btn-sm kt-btn-outline">
                                            <i class="ki-filled ki-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-secondary-foreground py-6">Nenhuma nota fiscal encontrada.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script>window.pageConfig = { issuerBaseUrl: '{{ url("issuers") }}' };</script>
    @section('page-module', 'issuer-favorite')
@endsection