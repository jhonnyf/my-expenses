<div class="kt-modal" data-kt-modal="true" id="addToListModal">
    <div class="kt-modal-content max-w-[440px] top-[15%]">
        <div class="kt-modal-header">
            <h3 class="kt-modal-title">Adicionar à lista de compras</h3>
            <button type="button" class="kt-modal-close" data-kt-modal-dismiss="#addToListModal" aria-label="Fechar">
                <i class="ki-filled ki-cross text-base"></i>
            </button>
        </div>
        <div class="kt-modal-body flex flex-col gap-3">
            <p class="text-sm text-foreground">
                <strong id="addToListProduct"></strong>
                <span class="block text-xs text-secondary-foreground mt-0.5" id="addToListDetail"></span>
            </p>
            <label class="text-xs text-secondary-foreground" for="addToListSelect">Lista</label>
            <select id="addToListSelect" class="kt-select w-full"></select>
            <p id="addToListError" class="text-xs text-destructive hidden"></p>
        </div>
        <div class="kt-modal-footer">
            <button type="button" class="kt-btn kt-btn-secondary" data-kt-modal-dismiss="#addToListModal">Cancelar</button>
            <button type="button" class="kt-btn kt-btn-primary" data-action="confirm-add-to-list">Adicionar</button>
        </div>
    </div>
</div>
