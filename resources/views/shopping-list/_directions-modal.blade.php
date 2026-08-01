<div class="kt-modal" data-kt-modal="true" id="directionsModal">
    <div class="kt-modal-content max-w-[480px] lg:max-w-[720px] top-[12%] lg:top-[8%]">
        <div class="kt-modal-header">
            <h3 class="kt-modal-title" id="directionsModalTitle">Como chegar</h3>
            <button class="kt-modal-close" data-kt-modal-dismiss="#directionsModal" aria-label="Fechar">
                <i class="ki-filled ki-cross text-base"></i>
            </button>
        </div>
        <div class="kt-modal-body flex flex-col gap-3">
            <p id="directionsModalAddress" class="text-sm text-secondary-foreground"></p>
            <div id="directionsModalMapWrapper" class="overflow-hidden rounded-lg border border-border h-[260px] lg:h-[480px]">
                <iframe id="directionsModalMap" class="w-full h-full" style="border:0; display:block;"
                        allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                        title="Localização"></iframe>
            </div>
            <p id="directionsModalEmpty" class="text-sm text-secondary-foreground text-center py-4 hidden">
                Endereço não informado para este estabelecimento.
            </p>
        </div>
        <div class="kt-modal-footer">
            <button class="kt-btn kt-btn-secondary" data-kt-modal-dismiss="#directionsModal">Fechar</button>
            <div class="flex items-center gap-2 flex-wrap justify-end">
                <a id="directionsModalWazeLink" href="#" target="_blank" rel="noopener" class="kt-btn kt-btn-outline lg:hidden">
                    <i class="ki-filled ki-map"></i> Waze
                </a>
                <a id="directionsModalOpenLink" href="#" target="_blank" rel="noopener" class="kt-btn kt-btn-primary">
                    <i class="ki-filled ki-route"></i> Google Maps
                </a>
            </div>
        </div>
    </div>
</div>
