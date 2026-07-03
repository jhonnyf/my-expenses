@extends('layout.main')
@section('page-module', 'issuer-favorite')

@section('content')

    {{-- PAGE HEADER --}}
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Emissores</h1>
                <div class="flex items-center gap-2 text-sm font-normal text-secondary-foreground">
                    {{ $records->total() }} {{ $records->total() == 1 ? 'emissor encontrado' : 'emissores encontrados' }}
                </div>
            </div>
        </div>
    </div>

    <div class="kt-container-fixed">
        <div class="grid gap-5 lg:gap-7.5">

            <div class="kt-card kt-card-grid min-w-full">

                <div class="kt-card-header flex-wrap gap-2">
                    <h3 class="kt-card-title">Lista de Emissores</h3>
                    <div class="flex items-center gap-3">
                        <label class="kt-input max-w-56">
                            <i class="ki-filled ki-magnifier"></i>
                            <input type="text" id="issuerSearchInput" placeholder="Buscar emissor..." autocomplete="off" />
                        </label>
                    </div>
                </div>

                <div class="kt-card-table">
                    <div class="kt-scrollable-x-auto">
                        <table class="kt-table kt-table-border table-fixed" id="issuersTable">
                            <thead>
                                <tr>
                                    <th class="w-[50px]"></th>
                                    <th class="min-w-[260px]">Emissor</th>
                                    <th class="min-w-[150px]">CNPJ</th>
                                    <th class="min-w-[180px]">Localização</th>
                                    <th class="w-[80px] text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($records->items() as $item)
                                    <tr class="issuer-row">
                                        <td class="text-center">
                                            <button
                                                data-favorite-id="{{ $item->id }}"
                                                title="{{ $favoriteIds->contains($item->id) ? 'Remover dos favoritos' : 'Adicionar aos favoritos' }}"
                                                class="kt-btn kt-btn-ghost kt-btn-icon kt-btn-sm favorite-btn {{ $favoriteIds->contains($item->id) ? 'text-yellow-500' : 'text-muted-foreground hover:text-yellow-500' }}">
                                                <i class="ki-filled ki-star text-base"></i>
                                            </button>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <div class="flex items-center justify-center size-9 rounded-lg shrink-0 font-semibold text-sm uppercase
                                                    {{ $favoriteIds->contains($item->id) ? 'bg-yellow-500/10 text-yellow-600' : 'bg-primary/10 text-primary' }}">
                                                    {{ strtoupper(substr($item->name, 0, 2)) }}
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="text-sm font-semibold text-foreground truncate issuer-name">{{ $item->name }}</p>
                                                    @if($item->neighborhood)
                                                        <p class="text-xs text-secondary-foreground truncate">{{ $item->neighborhood }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-sm text-foreground font-mono">
                                                {{ preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $item->cnpj) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($item->city || $item->state)
                                                <div class="flex items-center gap-1.5">
                                                    <i class="ki-filled ki-geolocation text-sm text-muted-foreground shrink-0"></i>
                                                    <span class="text-sm text-foreground truncate">
                                                        {{ implode(' — ', array_filter([$item->city, $item->state])) }}
                                                    </span>
                                                </div>
                                            @else
                                                <span class="text-sm text-secondary-foreground">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('issuers.detail', ['id' => $item->id]) }}" class="kt-btn kt-btn-sm kt-btn-ghost kt-btn-icon" title="Ver detalhes">
                                                <i class="ki-filled ki-eye text-base"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5">
                                            <div class="flex flex-col items-center justify-center py-16 text-center">
                                                <i class="ki-filled ki-shop text-5xl text-secondary-foreground/30 mb-4"></i>
                                                <p class="text-sm font-medium text-foreground mb-1">Nenhum emissor encontrado</p>
                                                <p class="text-xs text-secondary-foreground">Importe uma NF-e para registrar emissores.</p>
                                                <a href="{{ route('my-purchases.upload.form') }}" class="kt-btn kt-btn-primary kt-btn-sm mt-4">
                                                    <i class="ki-filled ki-file-up"></i>
                                                    Importar NF-e
                                                </a>
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
                            Exibindo {{ $records->firstItem() }}–{{ $records->lastItem() }} de {{ $records->total() }} emissores
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
            issuerBaseUrl: '{{ url("issuers") }}',
        });

        document.getElementById('issuerSearchInput')?.addEventListener('input', function () {
            const term = this.value.toLowerCase();
            document.querySelectorAll('#issuersTable tbody .issuer-row').forEach(row => {
                const name = row.querySelector('.issuer-name')?.textContent.toLowerCase() ?? '';
                row.style.display = name.includes(term) ? '' : 'none';
            });
        });
    </script>
@endpush
