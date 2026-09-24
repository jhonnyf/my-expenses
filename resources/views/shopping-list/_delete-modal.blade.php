<div class="kt-modal" data-kt-modal="true" id="deleteListModal">
    <div class="kt-modal-content max-w-[420px] top-[15%]">
        <div class="kt-modal-header">
            <h3 class="kt-modal-title">Excluir lista</h3>
            <button type="button" class="kt-modal-close" data-kt-modal-dismiss="#deleteListModal" aria-label="Fechar">
                <i class="ki-filled ki-cross text-base"></i>
            </button>
        </div>
        <div class="kt-modal-body flex flex-col gap-3">
            <p class="text-sm text-foreground">Excluir a lista <strong id="deleteListName"></strong>?</p>
            <p class="text-xs text-secondary-foreground">Todos os itens da lista são removidos. Esta ação não pode ser desfeita.</p>
            <p id="deleteListError" class="text-xs text-destructive hidden"></p>
        </div>
        <div class="kt-modal-footer">
            <button type="button" class="kt-btn kt-btn-secondary" data-kt-modal-dismiss="#deleteListModal">Cancelar</button>
            <button type="button" class="kt-btn kt-btn-destructive" data-action="confirm-delete-list">Excluir lista</button>
        </div>
    </div>
</div>
