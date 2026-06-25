@extends('layout.main')

@section('content')
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div>
                <h1 class="text-xl font-medium leading-none text-mono">Importar NFC-e</h1>
                <p class="text-sm text-secondary-foreground mt-1">Importe sua nota fiscal via arquivo XML ou QR Code.</p>
            </div>
            <a href="{{ route('my-purchases.index') }}" class="kt-btn kt-btn-outline">
                <i class="ki-filled ki-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <div class="kt-container-fixed">
        <div class="kt-card max-w-lg">
            {{-- Abas --}}
            <div class="flex border-b border-border">
                <button onclick="switchTab('xml')" id="tab-xml"
                        class="flex-1 py-3 px-4 text-sm font-medium text-center border-b-2 border-primary text-primary transition-colors">
                    <i class="ki-filled ki-file-up me-1"></i> Arquivo XML
                </button>
                <button onclick="switchTab('qrcode')" id="tab-qrcode"
                        class="flex-1 py-3 px-4 text-sm font-medium text-center border-b-2 border-transparent text-secondary-foreground hover:text-foreground transition-colors">
                    <i class="ki-filled ki-scan-barcode me-1"></i> QR Code
                </button>
            </div>

            {{-- Formulário XML --}}
            <div class="kt-card-content pt-6" id="panel-xml">
                <form action="{{ route('my-purchases.upload') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                    @csrf
                    <div>
                        <label for="xml" class="block text-sm font-medium mb-2">Arquivo XML da NFC-e</label>
                        <input type="file" id="xml" name="xml" accept=".xml,text/xml" class="kt-input w-full" required>
                        @error('xml')
                            <p class="mt-2 text-sm text-destructive">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="kt-btn kt-btn-primary w-full">
                        <i class="ki-filled ki-cloud-add"></i> Importar XML
                    </button>
                </form>
            </div>

            {{-- Formulário QR Code --}}
            <div class="kt-card-content pt-6" id="panel-qrcode" style="display:none;">
                <form action="{{ route('my-purchases.import-qrcode') }}" method="POST" class="space-y-5">
                    @csrf
                    <div>
                        <label for="qrcode_url" class="block text-sm font-medium mb-2">URL do QR Code da NFC-e</label>
                        <input type="url" id="qrcode_url" name="qrcode_url" class="kt-input w-full"
                               placeholder="https://www.nfce.fazenda.sp.gov.br/...?p=... ou https://www.sefaz.rs.gov.br/...?chNFe=..."
                               value="{{ old('qrcode_url') }}" required>
                        @error('qrcode_url')
                            <p class="mt-2 text-sm text-destructive">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="bg-accent/50 rounded-lg p-4">
                        <p class="text-xs text-secondary-foreground">
                            <i class="ki-filled ki-information-2 text-primary me-1"></i>
                            Cole a URL que aparece no QR Code impresso no cupom fiscal. Os dados da nota serão
                            buscados diretamente no portal da SEFAZ.
                        </p>
                    </div>
                    <button type="submit" class="kt-btn kt-btn-primary w-full">
                        <i class="ki-filled ki-scan-barcode"></i> Importar via QR Code
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>window.pageConfig = { initialTab: '{{ $errors->has("qrcode_url") ? "qrcode" : "xml" }}' };</script>
    @section('page-module', 'upload')
@endsection
