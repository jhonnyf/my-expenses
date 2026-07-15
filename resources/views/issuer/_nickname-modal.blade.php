<div class="kt-modal" data-kt-modal="true" id="nicknameModal">
    <div class="kt-modal-content max-w-[420px] top-[15%]">
        <div class="kt-modal-header">
            <h3 class="kt-modal-title">Apelido do emissor</h3>
            <button class="kt-modal-close" data-kt-modal-dismiss="#nicknameModal" aria-label="Fechar">
                <i class="ki-filled ki-cross text-base"></i>
            </button>
        </div>
        <div class="kt-modal-body flex flex-col gap-3">
            <p class="text-sm text-secondary-foreground">
                Nome oficial: <span id="nicknameModalOriginalName" class="font-medium text-foreground"></span>
            </p>
            <label class="kt-input">
                <input type="text" id="nicknameModalInput" placeholder="Ex.: Padaria da esquina" maxlength="100" autocomplete="off" />
            </label>
            <p class="text-xs text-secondary-foreground">Deixe em branco para exibir o nome oficial.</p>
            <p id="nicknameModalError" class="text-xs text-destructive hidden"></p>
        </div>
        <div class="kt-modal-footer">
            <button class="kt-btn kt-btn-secondary" data-kt-modal-dismiss="#nicknameModal">Cancelar</button>
            <button class="kt-btn kt-btn-primary" id="nicknameModalSave">Salvar</button>
        </div>
    </div>
</div>
