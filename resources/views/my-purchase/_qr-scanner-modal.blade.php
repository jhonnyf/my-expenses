<div class="kt-modal" data-kt-modal="true" id="qrScannerModal">
    <div class="kt-modal-content max-w-[420px] top-[15%]">
        <div class="kt-modal-header">
            <h3 class="kt-modal-title">Ler QR Code</h3>
            <button class="kt-modal-close" data-kt-modal-dismiss="#qrScannerModal" aria-label="Fechar">
                <i class="ki-filled ki-cross text-base"></i>
            </button>
        </div>
        <div class="kt-modal-body flex flex-col gap-3">
            <div class="relative rounded-lg overflow-hidden bg-black aspect-square">
                <video id="qrScannerVideo" class="w-full h-full object-cover" muted playsinline></video>
            </div>
            <p id="qrScannerStatus" class="text-sm text-secondary-foreground text-center">
                Aponte a câmera para o QR Code do cupom fiscal.
            </p>
            <p id="qrScannerError" class="text-xs text-destructive text-center hidden"></p>

            {{-- Só aparece quando o dispositivo expõe controle manual de foco (ex.: Chrome/Android) --}}
            <div id="qrScannerFocusControl" class="hidden flex items-center gap-2">
                <i class="ki-filled ki-eye text-secondary-foreground text-sm shrink-0"></i>
                <input
                    type="range"
                    id="qrScannerFocusRange"
                    class="w-full h-1.5 rounded-full bg-secondary accent-primary cursor-pointer"
                    aria-label="Ajustar foco da câmera"
                >
            </div>
        </div>
        <div class="kt-modal-footer">
            <button class="kt-btn kt-btn-secondary" data-kt-modal-dismiss="#qrScannerModal">Cancelar</button>
        </div>
    </div>
</div>
