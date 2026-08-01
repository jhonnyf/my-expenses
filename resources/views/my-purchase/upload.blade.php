@extends('layout.main')
@section('page-module', 'upload')

@section('content')

    {{-- PAGE HEADER --}}
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Importar NF-e</h1>
                <div class="flex items-center gap-2 text-sm font-normal text-secondary-foreground">
                    Importe sua nota via QR Code
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <a href="{{ route('my-purchases.index') }}" class="kt-btn kt-btn-outline">
                    <i class="ki-filled ki-arrow-left"></i> Voltar
                </a>
            </div>
        </div>
    </div>

    {{-- CONTENT --}}
    <div class="kt-container-fixed">

        <div class="kt-card max-w-xl mx-auto">

            {{-- PANEL: QR Code --}}
            <div id="panel-qrcode" class="kt-card-content p-6 pt-5">
                <button type="button"
                        id="qrScannerTriggerBtn"
                        class="kt-btn kt-btn-outline w-full mb-4"
                        data-kt-modal-toggle="#qrScannerModal">
                    <i class="ki-filled ki-scan-barcode"></i> Ler QR Code com a câmera
                </button>

                <div class="flex items-center gap-2 mb-4">
                    <span class="border-t border-border w-full"></span>
                    <span class="text-xs text-muted-foreground font-medium uppercase">Ou</span>
                    <span class="border-t border-border w-full"></span>
                </div>

                <form action="{{ route('my-purchases.import-qrcode') }}" method="POST" class="space-y-5">
                    @csrf
                    <div class="kt-form-item">
                        <label class="kt-form-label" for="qrcode_url">URL do QR Code</label>
                        <div class="kt-form-control">
                            <label class="kt-input w-full">
                                <i class="ki-filled ki-scan-barcode"></i>
                                <input type="url"
                                       id="qrcode_url"
                                       name="qrcode_url"
                                       placeholder="https://www.nfce.fazenda.sp.gov.br/...?p=..."
                                       value="{{ old('qrcode_url') }}" />
                            </label>
                        </div>
                        @error('qrcode_url')
                            <div class="kt-form-message text-destructive">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="rounded-xl bg-accent/50 p-4 text-xs text-secondary-foreground">
                        <i class="ki-filled ki-information-2 text-primary me-1.5"></i>
                        Cole a URL que aparece no QR Code impresso no cupom fiscal. Os dados da nota serão
                        buscados diretamente no portal da SEFAZ.
                    </div>
                    <button type="submit" class="kt-btn kt-btn-primary w-full">
                        <i class="ki-filled ki-scan-barcode"></i> Importar via QR Code
                    </button>
                </form>
            </div>

        </div>

    </div>

    @include('my-purchase._qr-scanner-modal')

@endsection
