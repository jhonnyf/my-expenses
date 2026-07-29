<div class="kt-modal" data-kt-modal="true" id="productAliasModal">
    <div class="kt-modal-content max-w-[420px] top-[15%]">
        <div class="kt-modal-header">
            <h3 class="kt-modal-title">Nome do produto</h3>
            <button class="kt-modal-close" data-kt-modal-dismiss="#productAliasModal" aria-label="Fechar">
                <i class="ki-filled ki-cross text-base"></i>
            </button>
        </div>
        <div class="kt-modal-body flex flex-col gap-3">
            <p class="text-sm text-secondary-foreground">
                Descrição original: <span id="productAliasModalOriginalName" class="font-medium text-foreground"></span>
            </p>
            <div class="flex items-center gap-2">
                <label class="kt-input flex-1">
                    <input type="text" id="productAliasModalInput" placeholder="Ex.: Coca-Cola 350ml" maxlength="150" autocomplete="off" />
                </label>
                <button type="button" id="productAliasModalAiSuggest" class="kt-btn kt-btn-sm kt-btn-outline shrink-0" title="Sugerir nome automaticamente">
                    <i class="ki-filled ki-artificial-intelligence"></i> Sugerir
                </button>
            </div>
            <p class="text-xs text-secondary-foreground">Nome usado para exibir e agrupar as compras deste produto.</p>
            <p id="productAliasModalError" class="text-xs text-destructive hidden"></p>
        </div>
        <div class="kt-modal-footer">
            <button class="kt-btn kt-btn-secondary" data-kt-modal-dismiss="#productAliasModal">Cancelar</button>
            <button class="kt-btn kt-btn-primary" id="productAliasModalSave">Salvar</button>
        </div>
    </div>
</div>
