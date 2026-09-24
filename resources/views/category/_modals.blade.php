{{-- Exclusão: o texto (nome, itens afetados, orçamento) é preenchido pelo JS ao abrir. --}}
<div class="kt-modal" data-kt-modal="true" id="deleteCategoryModal">
    <div class="kt-modal-content max-w-[440px] top-[15%]">
        <div class="kt-modal-header">
            <h3 class="kt-modal-title">Excluir categoria</h3>
            <button type="button" class="kt-modal-close" data-kt-modal-dismiss="#deleteCategoryModal" aria-label="Fechar">
                <i class="ki-filled ki-cross text-base"></i>
            </button>
        </div>
        <div class="kt-modal-body flex flex-col gap-3">
            <p class="text-sm text-foreground">Excluir a categoria <strong id="deleteCategoryName"></strong>?</p>
            <ul class="text-xs text-secondary-foreground list-disc ps-4 grid gap-1">
                <li id="deleteCategoryItems"></li>
                <li>As regras aprendidas para esta categoria também são removidas.</li>
                <li id="deleteCategoryBudget" class="hidden">O orçamento desta categoria também é excluído.</li>
            </ul>
            <p class="text-xs text-secondary-foreground">Para manter o histórico, use <strong>Mesclar</strong> em vez de excluir.</p>
            <p id="deleteCategoryError" class="text-xs text-destructive hidden"></p>
        </div>
        <div class="kt-modal-footer">
            <button type="button" class="kt-btn kt-btn-secondary" data-kt-modal-dismiss="#deleteCategoryModal">Cancelar</button>
            <button type="button" class="kt-btn kt-btn-destructive" data-action="confirm-delete-category">Excluir categoria</button>
        </div>
    </div>
</div>

{{-- Mesclar: as opções de destino vêm dos cards da página (JS). --}}
<div class="kt-modal" data-kt-modal="true" id="mergeCategoryModal">
    <div class="kt-modal-content max-w-[440px] top-[15%]">
        <div class="kt-modal-header">
            <h3 class="kt-modal-title">Mesclar categoria</h3>
            <button type="button" class="kt-modal-close" data-kt-modal-dismiss="#mergeCategoryModal" aria-label="Fechar">
                <i class="ki-filled ki-cross text-base"></i>
            </button>
        </div>
        <div class="kt-modal-body flex flex-col gap-3">
            <p class="text-sm text-foreground">
                Mover tudo de <strong id="mergeCategoryName"></strong> para:
            </p>
            <select id="mergeCategoryTarget" class="kt-select w-full" aria-label="Categoria de destino"></select>
            <p class="text-xs text-secondary-foreground">
                Os itens, as regras aprendidas e as palavras-chave passam para a categoria escolhida, e a categoria de origem é excluída.
                O orçamento vai junto se o destino ainda não tiver um.
            </p>
            <p id="mergeCategoryError" class="text-xs text-destructive hidden"></p>
        </div>
        <div class="kt-modal-footer">
            <button type="button" class="kt-btn kt-btn-secondary" data-kt-modal-dismiss="#mergeCategoryModal">Cancelar</button>
            <button type="button" class="kt-btn kt-btn-primary" data-action="confirm-merge-category">Mesclar</button>
        </div>
    </div>
</div>

<div class="kt-modal" data-kt-modal="true" id="revertAutoModal">
    <div class="kt-modal-content max-w-[440px] top-[15%]">
        <div class="kt-modal-header">
            <h3 class="kt-modal-title">Desfazer categorização automática</h3>
            <button type="button" class="kt-modal-close" data-kt-modal-dismiss="#revertAutoModal" aria-label="Fechar">
                <i class="ki-filled ki-cross text-base"></i>
            </button>
        </div>
        <div class="kt-modal-body flex flex-col gap-3">
            <p class="text-sm text-foreground">
                <strong id="revertAutoCount"></strong> itens categorizados por palavras-chave, regras aprendidas ou IA voltam a ficar sem categoria.
            </p>
            <p class="text-xs text-secondary-foreground">
                As categorias que você escolheu manualmente não mudam. As regras aprendidas continuam valendo:
                uma nova auto-categorização aplicaria as mesmas escolhas.
            </p>
            <p id="revertAutoError" class="text-xs text-destructive hidden"></p>
        </div>
        <div class="kt-modal-footer">
            <button type="button" class="kt-btn kt-btn-secondary" data-kt-modal-dismiss="#revertAutoModal">Cancelar</button>
            <button type="button" class="kt-btn kt-btn-destructive" data-action="confirm-revert-auto">Desfazer</button>
        </div>
    </div>
</div>
