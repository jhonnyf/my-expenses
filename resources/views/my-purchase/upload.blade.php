@extends('layout.main')
@section('page-module', 'upload')

@section('content')

    {{-- PAGE HEADER --}}
    <div class="kt-container-fixed">
        <div class="flex flex-wrap items-center lg:items-end justify-between gap-5 pb-7.5">
            <div class="flex flex-col justify-center gap-2">
                <h1 class="text-xl font-medium leading-none text-mono">Importar NF-e</h1>
                <div class="flex items-center gap-2 text-sm font-normal text-secondary-foreground">
                    Importe sua nota via QR Code, arquivo XML ou chave de acesso
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

            {{-- TAB NAV --}}
            <div class="flex items-center gap-0 border-b border-border px-5" data-kt-tabs="true">
                <button class="kt-tab-toggle active border-b-2 border-b-transparent kt-tab-active:border-b-primary pb-3 pt-4 px-3 text-sm font-medium text-secondary-foreground kt-tab-active:text-foreground transition-colors"
                        data-kt-tab-toggle="#panel-qrcode">
                    <i class="ki-filled ki-scan-barcode me-1.5"></i> QR Code
                </button>
                <button class="kt-tab-toggle border-b-2 border-b-transparent kt-tab-active:border-b-primary pb-3 pt-4 px-3 text-sm font-medium text-secondary-foreground kt-tab-active:text-foreground transition-colors"
                        data-kt-tab-toggle="#panel-xml">
                    <i class="ki-filled ki-file-up me-1.5"></i> Arquivo XML
                </button>
                <button class="kt-tab-toggle border-b-2 border-b-transparent kt-tab-active:border-b-primary pb-3 pt-4 px-3 text-sm font-medium text-secondary-foreground kt-tab-active:text-foreground transition-colors"
                        data-kt-tab-toggle="#panel-access_key">
                    <i class="ki-filled ki-key me-1.5"></i> Chave de Acesso
                </button>
            </div>

            {{-- PANEL: QR Code --}}
            <div id="panel-qrcode" class="kt-card-content p-6 pt-5">
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

            {{-- PANEL: XML --}}
            <div id="panel-xml" class="hidden kt-card-content p-6 pt-5">
                <form action="{{ route('my-purchases.upload') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                    @csrf
                    <div class="kt-form-item">
                        <label class="kt-form-label" for="xml">Arquivo XML da NF-e</label>
                        <div class="kt-form-control">
                            <input type="file"
                                   id="xml"
                                   name="xml"
                                   accept=".xml,text/xml"
                                   class="kt-input w-full" />
                        </div>
                        @error('xml')
                            <div class="kt-form-message text-destructive">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="rounded-xl bg-accent/50 p-4 text-xs text-secondary-foreground">
                        <i class="ki-filled ki-information-2 text-primary me-1.5"></i>
                        Selecione o arquivo XML da NF-e salvo no seu dispositivo. O arquivo deve ser um XML
                        válido no padrão SEFAZ (extensão .xml).
                    </div>
                    <button type="submit" class="kt-btn kt-btn-primary w-full">
                        <i class="ki-filled ki-cloud-add"></i> Importar XML
                    </button>
                </form>
            </div>

            {{-- PANEL: Chave de Acesso --}}
            <div id="panel-access_key" class="hidden kt-card-content p-6 pt-5">
                <form action="{{ route('my-purchases.import-by-key') }}" method="POST" class="space-y-5">
                    @csrf
                    <div class="kt-form-item">
                        <label class="kt-form-label" for="access_key">Chave de Acesso (44 dígitos)</label>
                        <div class="kt-form-control">
                            <label class="kt-input w-full">
                                <i class="ki-filled ki-key"></i>
                                <input type="text"
                                       id="access_key"
                                       name="access_key"
                                       class="font-mono tabular-nums"
                                       placeholder="0000 0000 0000 0000 0000 0000 0000 0000 0000 0000 0000"
                                       maxlength="55"
                                       value="{{ old('access_key') }}" />
                            </label>
                        </div>
                        @error('access_key')
                            <div class="kt-form-message text-destructive">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="rounded-xl bg-accent/50 p-4 text-xs text-secondary-foreground">
                        <i class="ki-filled ki-information-2 text-primary me-1.5"></i>
                        Informe a chave de acesso de 44 dígitos impressa no cupom fiscal. Os dados serão
                        baixados diretamente da SEFAZ via certificado digital.
                    </div>
                    <button type="submit" class="kt-btn kt-btn-primary w-full">
                        <i class="ki-filled ki-key"></i> Importar via Chave de Acesso
                    </button>
                </form>
            </div>

        </div>

    </div>

@endsection

@push('scripts')
<script>
    window.pageConfig = Object.assign(window.pageConfig || {}, {
        initialTab: '{{ $errors->has("access_key") ? "access_key" : ($errors->has("xml") ? "xml" : "qrcode") }}',
    });

    document.addEventListener('DOMContentLoaded', () => {
        const tab = window.pageConfig.initialTab;
        if (tab !== 'qrcode') {
            document.querySelector(`[data-kt-tab-toggle="#panel-${tab}"]`)?.click();
        }
    });
</script>
@endpush
